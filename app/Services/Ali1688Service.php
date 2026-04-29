<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Ali1688Service
{
    private ?string $appKey;
    private ?string $appSecret;
    private ?string $accessToken;
    private ?string $buyerId;
    private string $accountType;
    private string $baseUrl = 'https://gw.open.1688.com/openapi';
    private string $tokenFilePath;

    public function __construct()
    {
        $this->appKey = config('services.ali1688.app_key');
        $this->appSecret = config('services.ali1688.app_secret');
        $this->buyerId = config('services.ali1688.buyer_id');
        $this->accountType = config('services.ali1688.account_type', 'buyer');
        $this->tokenFilePath = storage_path('app/ali1688_tokens.json');
        $this->loadAccessToken();
    }

    private function loadAccessToken(): void
    {
        if (file_exists($this->tokenFilePath)) {
            $tokens = json_decode(file_get_contents($this->tokenFilePath), true);

            if (isset($tokens['access_token'])) {
                if (isset($tokens['expires_at'])) {
                    $expiresAt = \Carbon\Carbon::parse($tokens['expires_at']);
                    if ($expiresAt->subHour()->isPast() && !empty($tokens['refresh_token'])) {
                        $this->autoRefreshToken($tokens['refresh_token']);
                        return;
                    }
                }
                $this->accessToken = $tokens['access_token'];
                return;
            }
        }

        $this->accessToken = config('services.ali1688.access_token', '');
    }

    private function autoRefreshToken(string $refreshToken): void
    {
        $result = $this->refreshToken($refreshToken);

        if (isset($result['access_token'])) {
            $tokenData = [
                'access_token' => $result['access_token'],
                'refresh_token' => $result['refresh_token'] ?? $refreshToken,
                'expires_in' => $result['expires_in'] ?? null,
                'expires_at' => isset($result['expires_in'])
                    ? now()->addSeconds($result['expires_in'])->toDateTimeString()
                    : null,
                'updated_at' => now()->toDateTimeString(),
            ];

            file_put_contents($this->tokenFilePath, json_encode($tokenData, JSON_PRETTY_PRINT));
            $this->accessToken = $result['access_token'];
            Log::info('1688 Token avtomatik yenilendi');
        } else {
            Log::error('1688 Token avtomatik yenilene bilmedi', $result);
            $this->accessToken = config('services.ali1688.access_token', '');
        }
    }

    private function signRequest(string $apiPath, array $params): string
    {
        $paramPairs = [];
        foreach ($params as $key => $val) {
            if ($val !== null && $val !== '') {
                $paramPairs[] = $key . $val;
            }
        }
        sort($paramPairs);
        $signStr = $apiPath . implode('', $paramPairs);
        $signature = hash_hmac('sha1', $signStr, $this->appSecret, true);
        return strtoupper(bin2hex($signature));
    }

    private int $maxRetries = 5;
    private int $baseDelay = 0;

    private function callApi(string $namespace, string $apiName, string $version, array $params = []): array
    {
        $apiPath = "param2/{$version}/{$namespace}/{$apiName}/{$this->appKey}";
        $url = "{$this->baseUrl}/{$apiPath}";

        $allParams = $params;
        $allParams['access_token'] = $this->accessToken;
        $allParams['appKey'] = $this->appKey;

        $signature = $this->signRequest($apiPath, $allParams);
        $allParams['_aop_signature'] = $signature;

        usleep($this->baseDelay);

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $response = Http::timeout(30)->asForm()->post($url, $allParams);
                $result = $response->json() ?? ['error' => 'Empty response'];

                if (isset($result['error_code']) && $result['error_code'] === 'gw.QosAppFrequencyLimit') {
                    $waitSeconds = min(10 * pow(2, $attempt - 1), 120);
                    Log::warning("1688 Rate limit (attempt {$attempt}/{$this->maxRetries}), {$waitSeconds}s gozlenilir... [{$apiName}]");
                    sleep($waitSeconds);
                    continue;
                }

                return $result;
            } catch (\Exception $e) {
                Log::error('1688 API Error: ' . $e->getMessage());
                if ($attempt === $this->maxRetries) {
                    return ['error' => $e->getMessage()];
                }
                sleep(5);
            }
        }

        return ['error_code' => 'gw.QosAppFrequencyLimit', 'error_message' => 'Rate limit after max retries'];
    }

    /**
     * Kateqoriya siyahisi (tercumeli)
     */
    public function getCategoryList(
        string $language = 'en',
        string $categoryId = '0',
        string $parentCateId = '0',
        ?string $outMemberId = null
    ): array {
        $params = [
            'language' => $language,
            'categoryId' => $categoryId,
            'parentCateId' => $parentCateId,
            'outMemberId' => $outMemberId ?? $this->appKey,
        ];

        return $this->callApi(
            'com.alibaba.fenxiao.crossborder',
            'category.translation.getById',
            '1',
            $params
        );
    }

    /**
     * Mehsul siyahisi (offer pool)
     */
    public function pullOffer(
        string $offerPoolId,
        int $pageNo = 1,
        int $pageSize = 20,
        ?string $catId = null,
        ?string $itemId = null,
        ?string $language = null,
        ?string $sortField = null,
        ?string $sortType = null
    ): array {
        $queryParam = [
            'offerPoolId' => $offerPoolId,
            'pageNo' => $pageNo,
            'pageSize' => $pageSize,
        ];
        if ($catId) $queryParam['cateId'] = $catId;
        if ($itemId) $queryParam['itemId'] = $itemId;
        if ($language) $queryParam['language'] = $language;
        if ($sortField) $queryParam['sortField'] = $sortField;
        if ($sortType) $queryParam['sortType'] = $sortType;

        return $this->callApi(
            'com.alibaba.fenxiao.crossborder',
            'pool.product.pull',
            '1',
            ['offerPoolQueryParam' => json_encode($queryParam, JSON_UNESCAPED_UNICODE)]
        );
    }

    /**
     * Mehsul detallari
     */
    public function getOfferDetail(int $offerId, ?string $specId = null, ?string $country = null): array
    {
        $queryParam = ['offerId' => $offerId];
        if ($specId) $queryParam['specId'] = $specId;
        if ($country) $queryParam['country'] = $country;

        return $this->callApi(
            'com.alibaba.fenxiao.crossborder',
            'product.search.queryProductDetail',
            '1',
            ['offerDetailParam' => json_encode($queryParam, JSON_UNESCAPED_UNICODE)]
        );
    }

    /**
     * Mehsul sayi
     */
    public function getProductTotal(string $palletId, ?string $categoryId = null): array
    {
        $params = ['palletId' => $palletId];
        if ($categoryId) $params['categoryId'] = $categoryId;

        return $this->callApi(
            'com.alibaba.fenxiao.crossborder',
            'pool.product.total',
            '1',
            $params
        );
    }

    /**
     * Açar söz ilə məhsul axtarışı.
     * keyword Çincə olmalıdır.
     */
    public function searchByKeyword(
        string $chineseKeyword,
        int $page = 1,
        int $pageSize = 20,
        array $options = []
    ): array {
        $offerQueryParam = [
            'keyword' => $chineseKeyword,
            'beginPage' => max(1, $page),
            'pageSize' => min(50, max(1, $pageSize)),
            'keywordTranslate' => $options['keywordTranslate'] ?? true,
            'country' => $options['country'] ?? 'en',
        ];

        foreach (['priceStart', 'priceEnd', 'categoryId', 'categoryIdList', 'sort', 'filter', 'snId', 'outMemberId'] as $key) {
            if (!empty($options[$key])) {
                $offerQueryParam[$key] = $options[$key];
            }
        }

        return $this->callApi(
            'com.alibaba.fenxiao.crossborder',
            'product.search.keywordQuery',
            '1',
            ['offerQueryParam' => json_encode($offerQueryParam, JSON_UNESCAPED_UNICODE)]
        );
    }

    /**
     * Şəkil ilə məhsul axtarışı (URL və ya base64).
     * 1688 imageQuery yalnız imageId qəbul edir — əvvəlcə uploadImage ilə imageId alırıq.
     */
    public function searchByImage(
        string $imageUrlOrBase64,
        int $page = 1,
        int $pageSize = 20,
        array $options = []
    ): array {
        if (preg_match('~^https?://~i', $imageUrlOrBase64)) {
            try {
                $response = Http::timeout(30)
                    ->withOptions(['verify' => false])
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                    ->get($imageUrlOrBase64);

                if (!$response->successful()) {
                    return ['result' => ['success' => 'false', 'code' => 'image_fetch_failed', 'message' => "Unable to fetch image URL (HTTP {$response->status()})"]];
                }
                $bin = $response->body();
                if ($bin === '' || $bin === null) {
                    return ['result' => ['success' => 'false', 'code' => 'image_fetch_failed', 'message' => 'Image URL returned empty body']];
                }
            } catch (\Exception $e) {
                Log::error('1688 image fetch error: ' . $e->getMessage());
                return ['result' => ['success' => 'false', 'code' => 'image_fetch_failed', 'message' => 'Unable to fetch image URL: ' . $e->getMessage()]];
            }
            $b64 = base64_encode($bin);
        } else {
            $b64 = preg_replace('~^data:image/[a-zA-Z]+;base64,~', '', $imageUrlOrBase64);
        }

        $uploadResponse = $this->callApi(
            'com.alibaba.fenxiao.crossborder',
            'product.image.upload',
            '1',
            ['uploadImageParam' => json_encode(['imageBase64' => $b64], JSON_UNESCAPED_UNICODE)]
        );
        $imageId = $uploadResponse['result']['result'] ?? null;
        if (!is_string($imageId) || $imageId === '') {
            return [
                'result' => [
                    'success' => 'false',
                    'code' => 'image_upload_failed',
                    'message' => 'Image upload to 1688 failed',
                    'upload_response' => $uploadResponse,
                ],
            ];
        }

        return $this->searchByImageId($imageId, $page, $pageSize, $options);
    }

    /**
     * Əvvəlcədən alınmış imageId ilə axtarış (camera flow / pagination).
     */
    public function searchByImageId(
        string $imageId,
        int $page = 1,
        int $pageSize = 20,
        array $options = []
    ): array {
        $offerQueryParam = [
            'imageId' => $imageId,
            'beginPage' => max(1, $page),
            'pageSize' => min(50, max(1, $pageSize)),
            'country' => $options['country'] ?? 'en',
        ];

        foreach (['categoryId', 'priceStart', 'priceEnd', 'sort', 'filter'] as $key) {
            if (!empty($options[$key])) {
                $offerQueryParam[$key] = $options[$key];
            }
        }

        return $this->callApi(
            'com.alibaba.fenxiao.crossborder',
            'product.search.imageQuery',
            '1',
            ['offerQueryParam' => json_encode($offerQueryParam, JSON_UNESCAPED_UNICODE)]
        );
    }

    /**
     * 1688 imageQuery üçün şəkil yükləmə — base64 → imageId.
     */
    public function uploadImage(string $imageBase64): ?string
    {
        $response = $this->callApi(
            'com.alibaba.fenxiao.crossborder',
            'product.image.upload',
            '1',
            ['uploadImageParam' => json_encode(['imageBase64' => $imageBase64], JSON_UNESCAPED_UNICODE)]
        );
        $imageId = $response['result']['result'] ?? null;
        return is_string($imageId) && $imageId !== '' ? $imageId : null;
    }

    /**
     * Refresh token
     */
    // ==================== SİFARİŞ API-ləri ====================

    public function createOrder(
        string $flow,
        string $outOrderId,
        array $addressParam,
        array $cargoParamList,
        string $dropshipping = 'y',
        ?string $message = null
    ): array {
        $params = [
            'flow' => $flow,
            'outOrderId' => $outOrderId,
            'dropshipping' => $dropshipping,
            'addressParam' => json_encode($addressParam, JSON_UNESCAPED_UNICODE),
            'cargoParamList' => json_encode($cargoParamList, JSON_UNESCAPED_UNICODE),
        ];
        if ($message) $params['message'] = $message;

        return $this->callApi('com.alibaba.trade', 'alibaba.trade.createCrossOrder', '1', $params);
    }

    public function getOrderList(
        ?string $orderStatus = null,
        int $pageNo = 1,
        int $pageSize = 20,
        ?string $createStartTime = null,
        ?string $createEndTime = null
    ): array {
        $queryParam = ['pageNo' => $pageNo, 'pageSize' => $pageSize];
        if ($orderStatus) $queryParam['orderStatus'] = $orderStatus;
        if ($createStartTime) $queryParam['createStartTime'] = $createStartTime;
        if ($createEndTime) $queryParam['createEndTime'] = $createEndTime;

        return $this->callApi(
            'com.alibaba.trade',
            'alibaba.trade.getBuyerOrderList',
            '1',
            ['param' => json_encode($queryParam, JSON_UNESCAPED_UNICODE)]
        );
    }

    public function getOrderDetail(int $orderId): array
    {
        return $this->callApi(
            'com.alibaba.trade',
            'alibaba.trade.get.buyerView',
            '1',
            ['orderId' => (string) $orderId, 'webSite' => '1688']
        );
    }

    public function cancelOrder(int $orderId, ?string $reason = null): array
    {
        $params = ['tradeID' => (string) $orderId, 'webSite' => '1688'];
        if ($reason) $params['cancelReason'] = $reason;

        return $this->callApi('com.alibaba.trade', 'alibaba.trade.cancel', '1', $params);
    }

    public function confirmReceipt(int $orderId): array
    {
        return $this->callApi(
            'com.alibaba.trade',
            'trade.receivegoods.confirm',
            '1',
            ['orderId' => (string) $orderId]
        );
    }

    public function getPaymentUrl(int $orderId): array
    {
        return $this->callApi(
            'com.alibaba.trade',
            'alibaba.alipay.url.get',
            '1',
            ['orderIdList' => json_encode([$orderId])]
        );
    }

    /**
     * alibaba.trade.pay.protocolPay.preparePay — şifrəsiz ödəniş (Alipay protokolu)
     *
     * QEYD: payChannel = 'alipay' düzgündür.
     *       'kjpayV2' (跨境宝) AYRI müqavilə tələb edir.
     *
     * @param int      $orderId       1688 order ID
     * @param int|null $payAmountFen  Ödəniləcək məbləğ fen-lə (CNY × 100). null isə 1688 özü hesablayır.
     */
    public function preparePayment(int $orderId, ?int $payAmountFen = null): array
    {
        $param = [
            'orderId'     => (int) $orderId,
            'payChannel'  => 'alipay',
            'opRequestId' => 'pz_' . $orderId . '_' . time(),
            'buyerId'     => (int) $this->buyerId,
            'accountType' => $this->accountType,
        ];
        if ($payAmountFen !== null) {
            $param['payAmount'] = $payAmountFen;
        }

        return $this->callApi(
            'com.alibaba.trade',
            'alibaba.trade.pay.protocolPay.preparePay',
            '1',
            ['tradeWithholdPreparePayParam' => json_encode($param, JSON_UNESCAPED_UNICODE)]
        );
    }

    /**
     * alibaba.trade.createRefund — refund yarat
     *
     * goodsStatus enum:
     *   refundWaitSellerSend       — satıcı hələ göndərməyib
     *   refundWaitBuyerReceive     — alıcı qəbul gözləyir
     *   refundBuyerReceived        — alıcı qəbul edib
     *   aftersaleBuyerNotReceived  — satışdan sonra, alıcı qəbul etməyib
     *   aftersaleBuyerReceived     — satışdan sonra, alıcı qəbul edib
     *
     * @param int    $orderId           1688 order ID
     * @param int[]  $orderEntryIds     subItemID-lər (productItems[].subItemID)
     * @param int    $applyPaymentFen   Geri qaytarılan məhsul məbləği fen-lə
     * @param int    $applyCarriageFen  Geri qaytarılan çatdırılma məbləği fen-lə
     * @param int    $applyReasonId     Refund səbəb ID-si (default 20006 = 不想买了)
     * @param string $description       Refund açıqlaması
     * @param string $goodsStatus       Mal statusu (yuxarıdakı enum)
     */
    public function createRefund(
        int $orderId,
        array $orderEntryIds,
        int $applyPaymentFen,
        int $applyCarriageFen,
        int $applyReasonId = 20006,
        string $description = 'Refund request',
        string $goodsStatus = 'refundWaitSellerSend'
    ): array {
        return $this->callApi(
            'com.alibaba.trade',
            'alibaba.trade.createRefund',
            '1',
            [
                'orderId'        => $orderId,
                'orderEntryIds'  => json_encode($orderEntryIds),
                'disputeRequest' => 'refund',
                'applyPayment'   => $applyPaymentFen,
                'applyCarriage'  => $applyCarriageFen,
                'applyReasonId'  => $applyReasonId,
                'description'    => $description,
                'goodsStatus'    => $goodsStatus,
            ]
        );
    }

    /**
     * alibaba.trade.getRefundReasonList — refund səbəblər siyahısı
     */
    public function getRefundReasonList(
        int $orderId,
        array $orderEntryIds,
        string $goodsStatus = 'refundWaitSellerSend'
    ): array {
        return $this->callApi(
            'com.alibaba.trade',
            'alibaba.trade.getRefundReasonList',
            '1',
            [
                'orderId'       => $orderId,
                'orderEntryIds' => json_encode($orderEntryIds),
                'goodsStatus'   => $goodsStatus,
            ]
        );
    }

    public function estimateFreight(
        int $offerId,
        int $quantity = 1,
        ?string $toProvinceCode = '440000',
        ?string $toCityCode = '440100',
        ?string $toCountryCode = '440111'
    ): array {
        $param = [
            'offerId' => $offerId,
            'totalNum' => $quantity,
            'toProvinceCode' => $toProvinceCode,
            'toCityCode' => $toCityCode,
            'toCountryCode' => $toCountryCode,
        ];

        return $this->callApi(
            'com.alibaba.fenxiao.crossborder',
            'product.freight.estimate',
            '1',
            ['productFreightQueryParamsNew' => json_encode($param, JSON_UNESCAPED_UNICODE)]
        );
    }

    public function getOutOrderId(string $waybillNumber): array
    {
        return $this->callApi(
            'com.alibaba.fenxiao.crossborder',
            'logistics.order.getOutOrderId',
            '1',
            ['waybillNumber' => $waybillNumber]
        );
    }

    // ==================== TOKEN ====================

    public function refreshToken(string $refreshToken): array
    {
        $url = "https://gw.open.1688.com/openapi/http/1/system.oauth2/getToken/{$this->appKey}";

        $params = [
            'grant_type' => 'refresh_token',
            'client_id' => $this->appKey,
            'client_secret' => $this->appSecret,
            'refresh_token' => $refreshToken,
        ];

        try {
            $response = Http::timeout(30)->asForm()->post($url, $params);
            return $response->json() ?? ['error' => 'Empty response'];
        } catch (\Exception $e) {
            Log::error('1688 Refresh Token Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
}
