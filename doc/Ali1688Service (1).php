<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 1688 API Service - Sərhədlərarası VIP Müştərilər üçün
 *
 * İmza alqoritmi: HMAC-SHA1(apiPath + sorted(key+value), appSecret)
 */
class Ali1688Service
{
    private ?string $appKey;
    private ?string $appSecret;
    private ?string $accessToken;
    private string $baseUrl = 'https://gw.open.1688.com/openapi';
    private string $tokenFilePath;

    public function __construct()
    {
        $this->appKey = config('services.ali1688.app_key');
        $this->appSecret = config('services.ali1688.app_secret');
        $this->tokenFilePath = storage_path('app/ali1688_tokens.json');

        // Token-i fayldan və ya config-dən yüklə
        $this->loadAccessToken();
    }

    /**
     * Access token-i yüklə (fayldan və ya config-dən)
     */
    private function loadAccessToken(): void
    {
        // Əvvəlcə saxlanmış faylı yoxla
        if (file_exists($this->tokenFilePath)) {
            $tokens = json_decode(file_get_contents($this->tokenFilePath), true);

            if (isset($tokens['access_token'])) {
                // Token-in vaxtını yoxla, lazım gələrsə yenilə
                if (isset($tokens['expires_at'])) {
                    $expiresAt = \Carbon\Carbon::parse($tokens['expires_at']);

                    // 1 saat qalmış yenilə
                    if ($expiresAt->subHour()->isPast() && !empty($tokens['refresh_token'])) {
                        $this->autoRefreshToken($tokens['refresh_token']);
                        return;
                    }
                }

                $this->accessToken = $tokens['access_token'];
                return;
            }
        }

        // Faylda yoxdursa, config-dən al
        $this->accessToken = config('services.ali1688.access_token', '');
    }

    /**
     * Token-i avtomatik yenilə
     */
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

            Log::info('1688 Token avtomatik yeniləndi');
        } else {
            Log::error('1688 Token avtomatik yenilənə bilmədi', $result);
            // Fallback: config-dən al
            $this->accessToken = config('services.ali1688.access_token', '');
        }
    }

    /**
     * İMZA YARATMA
     *
     * 1688 API-si HMAC-SHA1 imza alqoritmi istifadə edir.
     * İmza formulu: HMAC-SHA1(apiPath + sıralanmış(açar+dəyər), appSecret)
     *
     * @param string $apiPath - API yolu (məs: param2/1/com.alibaba.fenxiao.crossborder/pool.product.pull/5368519)
     * @param array $params - Sorğu parametrləri
     * @return string - Böyük hərflərlə HEX formatında imza
     */
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

    /**
     * API ÇAĞIRIŞI
     *
     * Bütün API sorğuları bu metod vasitəsilə göndərilir.
     * Avtomatik olaraq imza əlavə edir və cavabı JSON formatında qaytarır.
     *
     * @param string $namespace - API namespace (məs: com.alibaba.fenxiao.crossborder)
     * @param string $apiName - API adı (məs: pool.product.pull)
     * @param string $version - API versiyası (adətən "1")
     * @param array $params - Sorğu parametrləri
     * @return array - API cavabı
     */
    private int $maxRetries = 5;
    private int $baseDelay = 0; // delay yoxdur, network latency özü ~750ms

    private function callApi(string $namespace, string $apiName, string $version, array $params = []): array
    {
        $apiPath = "param2/{$version}/{$namespace}/{$apiName}/{$this->appKey}";
        $url = "{$this->baseUrl}/{$apiPath}";

        $allParams = $params;
        $allParams['access_token'] = $this->accessToken;
        $allParams['appKey'] = $this->appKey;

        $signature = $this->signRequest($apiPath, $allParams);
        $allParams['_aop_signature'] = $signature;

        // Hər çağırış arasında minimum delay
        usleep($this->baseDelay);

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $response = Http::timeout(30)->asForm()->post($url, $allParams);
                $result = $response->json() ?? ['error' => 'Empty response'];

                // Rate limit yoxla — exponential backoff ilə retry
                if (isset($result['error_code']) && $result['error_code'] === 'gw.QosAppFrequencyLimit') {
                    $waitSeconds = min(10 * pow(2, $attempt - 1), 120); // 10s, 20s, 40s, 80s, 120s
                    Log::warning("1688 Rate limit (attempt {$attempt}/{$this->maxRetries}), {$waitSeconds}s gözlənilir... [{$apiName}]");
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

    // ==================== MƏHSUL API-ləri ====================

    /**
     * MƏHSUL SİYAHISINI ÇƏK
     *
     * Məhsul hovuzundan məhsulları səhifələr şəklində çəkir.
     * Hər səhifədə maksimum 50 məhsul ola bilər.
     *
     * @param string $offerPoolId - Məhsul hovuzu ID-si (VIP müştəri panelindan alınır)
     * @param int $pageNo - Səhifə nömrəsi (1-dən başlayır)
     * @param int $pageSize - Səhifədəki məhsul sayı (max 50)
     * @param string|null $catId - Kateqoriya ID-si (filtrasiya üçün)
     * @param string|null $itemId - Konkret məhsul ID-si
     * @param string|null $language - Dil kodu (məs: "en", "ru")
     * @return array - Məhsul siyahısı
     */
    public function pullOffer(
        string $offerPoolId,
        int $pageNo = 1,
        int $pageSize = 20,
        ?string $catId = null,
        ?string $itemId = null,
        ?string $language = null,
        ?string $taskId = null,
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
        if ($taskId) $queryParam['taskId'] = $taskId;
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
     * MƏHSUL SAYINI AL
     *
     * Məhsul hovuzundakı (pallet) ümumi məhsul sayını qaytarır.
     * Səhifələmə hesablamaları üçün istifadə olunur.
     *
     * 1688 SDK-ya görə parametrlər: palletId (Long), categoryId (String)
     *
     * @param string $palletId - Pallet ID-si (offer pool ID ilə eynidir)
     * @param string|null $categoryId - Kateqoriya ID-si (filtrasiya üçün)
     * @return array - Total məhsul sayı
     */
    public function getProductTotal(string $palletId, ?string $categoryId = null): array
    {
        $params = [
            'palletId' => $palletId,
        ];
        if ($categoryId) $params['categoryId'] = $categoryId;

        return $this->callApi(
            'com.alibaba.fenxiao.crossborder',
            'pool.product.total',
            '1',
            $params
        );
    }

    /**
     * MƏHSUL DETALLARI
     *
     * Konkret məhsulun tam məlumatlarını qaytarır:
     * - Qiymətlər (quoteType-a görə hesablanır)
     * - Şəkillər
     * - Variantlar (SKU)
     * - Təsvir
     * - Çatdırılma məlumatları
     *
     * quoteType qiymət hesablama qaydası:
     * - 0: consignPrice istifadə et
     * - 1: channelPrice istifadə et
     * - 2: intervalPrices massivindən miqdarına uyğun qiyməti tap
     *
     * @param int $offerId - Məhsul ID-si
     * @param string|null $specId - Variant ID-si (SKU)
     * @param string|null $country - Ölkə kodu (məs: "AZ", "TR")
     * @return array - Məhsul detalları
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
     * ÇATDIRILMA QİYMƏTİ TƏXMİNİ
     *
     * Məhsulun müəyyən ünvana çatdırılma qiymətini hesablayır.
     * Diqqət: Yalnız Çin daxili ünvanlar üçün işləyir.
     *
     * @param int $offerId - Məhsul ID-si
     * @param string $destination - Təyinat ünvanı (məs: "浙江省 杭州市 滨江区")
     * @param int $quantity - Məhsul sayı
     * @param string|null $specId - Variant ID-si
     * @return array - Çatdırılma qiyməti
     */
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

    // ==================== SİFARİŞ API-ləri ====================

    /**
     * SİFARİŞ YARAT
     *
     * Yeni sifariş yaradır. Dropshipping sifarişləri üçün istifadə olunur.
     *
     * flow parametri:
     * - "bigcfenxiao": B2C (tək məhsul, birbaşa müştəriyə)
     * - "bigcpifa": B2B (topdan, distribütora)
     *
     * dropshipping="y" - SİFARİŞLƏRİN BİRLƏŞDİRİLMƏMƏSİ ÜÇÜN MÜTLƏQDİR!
     * Bu parametr olmadan eyni təchizatçıdan olan sifarişlər birləşdirilə bilər.
     *
     * @param string $flow - Sifariş tipi: "bigcfenxiao" (B2C) və ya "bigcpifa" (B2B)
     * @param string $outOrderId - Sizin sisteminizdəki sifariş ID-si (unikal olmalıdır)
     * @param array $addressParam - Çatdırılma ünvanı məlumatları
     * @param array $cargoParamList - Məhsul siyahısı
     * @param string $dropshipping - "y" = sifarişlər birləşdirilməsin
     * @param string|null $message - Satıcıya mesaj
     * @return array - Yaradılmış sifariş məlumatları (orderId daxil)
     *
     * addressParam nümunəsi:
     * [
     *     'fullName' => 'Müştəri Adı',
     *     'mobile' => '994501234567',
     *     'phone' => '994121234567',
     *     'postCode' => 'AZ1000',
     *     'cityText' => 'Bakı',
     *     'provinceText' => 'Azərbaycan',
     *     'areaText' => 'Nəsimi',
     *     'address' => 'Küçə ünvanı',
     *     'districtText' => ''
     * ]
     *
     * cargoParamList nümunəsi:
     * [
     *     [
     *         'offerId' => 123456789,
     *         'specId' => 'sku_variant_id',
     *         'quantity' => 2
     *     ]
     * ]
     */
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

        return $this->callApi(
            'com.alibaba.trade',
            'alibaba.trade.createCrossOrder',
            '1',
            $params
        );
    }

    /**
     * SİFARİŞ SİYAHISI
     *
     * Alıcı kimi verdiyiniz sifarişlərin siyahısını qaytarır.
     * Tarix filtrasiyası və status filtrasiyası dəstəklənir.
     *
     * orderStatus dəyərləri:
     * - waitbuyerpay: Ödəniş gözləyir
     * - waitsellersend: Göndərmə gözləyir
     * - waitbuyerreceive: Qəbul gözləyir
     * - success: Tamamlandı
     * - cancel: Ləğv edildi
     * - terminated: Dayandırıldı
     *
     * @param string|null $orderStatus - Sifariş statusu
     * @param int $pageNo - Səhifə nömrəsi
     * @param int $pageSize - Səhifədəki sifariş sayı
     * @param string|null $createStartTime - Başlanğıc tarixi (YYYYMMDDHHmmss)
     * @param string|null $createEndTime - Son tarix (YYYYMMDDHHmmss)
     * @return array - Sifariş siyahısı
     */
    public function getOrderList(
        ?string $orderStatus = null,
        int $pageNo = 1,
        int $pageSize = 20,
        ?string $createStartTime = null,
        ?string $createEndTime = null
    ): array {
        $queryParam = [
            'pageNo' => $pageNo,
            'pageSize' => $pageSize,
        ];
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

    /**
     * SİFARİŞ DETALLARI
     *
     * Konkret sifarişin tam məlumatlarını qaytarır:
     * - Sifariş statusu
     * - Məhsullar və qiymətlər
     * - Çatdırılma məlumatları
     * - Ödəniş məlumatları
     * - Tracking nömrəsi (göndərildikdən sonra)
     *
     * @param int $orderId - 1688 sifariş ID-si
     * @return array - Sifariş detalları
     */
    public function getOrderDetail(int $orderId): array
    {
        return $this->callApi(
            'com.alibaba.trade',
            'alibaba.trade.get.buyerView',
            '1',
            [
                'orderId' => (string) $orderId,
                'webSite' => '1688',
            ]
        );
    }

    // ==================== ÖDƏNİŞ API-ləri ====================

    /**
     * ŞİFRƏSİZ ÖDƏNİŞ HAZIRLAMA
     *
     * Protocol Pay (şifrəsiz ödəniş) üçün hazırlıq sorğusu.
     * Bu metod ödənişi birbaşa həyata keçirmir, yalnız hazırlayır.
     *
     * Qeyd: Bu funksionallıq əlavə müqavilə tələb edə bilər.
     *
     * @param array|int $orderIdList - Sifariş ID-si və ya ID-lər massivi
     * @return array - Ödəniş hazırlıq məlumatları
     */
    public function preparePayment($orderId): array
    {
        $param = [
            'orderId' => (int) $orderId,
        ];

        return $this->callApi(
            'com.alibaba.trade',
            'alibaba.trade.pay.protocolPay.preparePay',
            '1',
            ['tradeWithholdPreparePayParam' => json_encode($param, JSON_UNESCAPED_UNICODE)]
        );
    }

    /**
     * ÖDƏNİŞ URL-İ AL
     *
     * Alipay ödəniş səhifəsinin URL-ini qaytarır.
     * Müştərini bu URL-ə yönləndirərək ödəniş etdirə bilərsiniz.
     *
     * @param int $orderId - Sifariş ID-si
     * @return array - Ödəniş URL-i
     */
    public function getPaymentUrl(int $orderId): array
    {
        return $this->callApi(
            'com.alibaba.trade',
            'alibaba.alipay.url.get',
            '1',
            ['orderIdList' => json_encode([$orderId])]
        );
    }

    // ==================== SİFARİŞ İDARƏETMƏ ====================

    /**
     * MALI QƏBUL ET
     *
     * Sifarişi "alındı" olaraq işarələyir.
     * Bu əməliyyatdan sonra sifariş tamamlanmış sayılır.
     *
     * Diqqət: Bu əməliyyat geri qaytarıla bilməz!
     *
     * @param int $orderId - Sifariş ID-si
     * @return array - Əməliyyat nəticəsi
     */
    public function confirmReceipt(int $orderId): array
    {
        return $this->callApi(
            'com.alibaba.trade',
            'trade.receivegoods.confirm',
            '1',
            ['orderId' => (string) $orderId]
        );
    }

    /**
     * SİFARİŞİ LƏĞV ET
     *
     * Ödənilməmiş sifarişi ləğv edir.
     * Ödənilmiş sifarişlər üçün geri qaytarma (refund) istifadə edin.
     *
     * @param int $orderId - Sifariş ID-si
     * @param string|null $reason - Ləğv səbəbi
     * @return array - Əməliyyat nəticəsi
     */
    public function cancelOrder(int $orderId, ?string $reason = null): array
    {
        $params = ['orderId' => (string) $orderId];
        if ($reason) $params['cancelReason'] = $reason;

        return $this->callApi(
            'com.alibaba.trade',
            'alibaba.trade.cancel',
            '1',
            $params
        );
    }

    /**
     * GERİ QAYTARMA / REFUND YARAT
     *
     * Ödənilmiş sifariş üçün geri qaytarma müraciəti yaradır.
     *
     * refundType dəyərləri:
     * - "refund": Yalnız pul geri qaytarılsın (mal alınmayıb)
     * - "returnRefund": Mal geri göndərilsin və pul qaytarılsın
     *
     * @param int $orderId - Sifariş ID-si
     * @param array $orderEntryIds - Geri qaytarılacaq məhsul entry ID-ləri
     * @param string $refundType - Geri qaytarma tipi
     * @param string|null $applyReason - Müraciət səbəbi
     * @param float|null $applyAmount - Tələb olunan məbləğ
     * @return array - Müraciət nəticəsi
     */
    public function createRefund(
        int $orderId,
        array $orderEntryIds,
        string $refundType = 'refund',
        ?string $applyReason = null,
        ?float $applyAmount = null
    ): array {
        $refundParam = [
            'orderId' => $orderId,
            'orderEntryIds' => $orderEntryIds,
            'disputeRequest' => $refundType,
        ];
        if ($applyReason) $refundParam['applyReason'] = $applyReason;
        if ($applyAmount) $refundParam['applyPayment'] = $applyAmount;

        return $this->callApi(
            'com.alibaba.trade',
            'alibaba.trade.createRefund',
            '1',
            ['param' => json_encode($refundParam, JSON_UNESCAPED_UNICODE)]
        );
    }

    // ==================== TOKEN YENİLƏMƏ API-ləri ====================

    /**
     * REFRESH TOKEN - TOKENİ YENİLƏ
     *
     * Access token müddəti bitdikdə refresh token ilə yeni token alır.
     * Refresh token 1688 panelindən ilk dəfə token aldığınızda verilir.
     *
     * Token müddətləri:
     * - Access Token: təxminən 24 saat
     * - Refresh Token: 6 ay
     *
     * @param string $refreshToken - Refresh token (paneldən alınır)
     * @return array - Yeni access_token, refresh_token, expires_in
     *
     * Cavab nümunəsi:
     * {
     *     "access_token": "yeni-access-token",
     *     "refresh_token": "yeni-refresh-token",
     *     "expires_in": 86400,
     *     "memberId": "b2b-xxx"
     * }
     */
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

    /**
     * AUTHORIZATION CODE İLƏ TOKEN AL
     *
     * OAuth authorization code ilə ilk dəfə token almaq üçün.
     * Bu kod 1688 panelindən istifadəçi icazə verdikdən sonra alınır.
     *
     * @param string $code - Authorization code
     * @param string $redirectUri - Redirect URI (paneldə qeyd etdiyiniz)
     * @return array - access_token, refresh_token, expires_in
     */
    public function getTokenByCode(string $code, string $redirectUri): array
    {
        $url = "https://gw.open.1688.com/openapi/http/1/system.oauth2/getToken/{$this->appKey}";

        $params = [
            'grant_type' => 'authorization_code',
            'client_id' => $this->appKey,
            'client_secret' => $this->appSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ];

        try {
            $response = Http::timeout(30)->asForm()->post($url, $params);
            return $response->json() ?? ['error' => 'Empty response'];
        } catch (\Exception $e) {
            Log::error('1688 Get Token Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * TOKEN VƏZİYYƏTİNİ YOXLA VƏ YENİLƏ
     *
     * Cron job-da işlətmək üçün.
     * Token müddəti bitmədən avtomatik yeniləyir.
     *
     * @param string $refreshToken - Refresh token
     * @return array - Yeni token məlumatları və ya xəta
     */
    public function refreshAndSaveToken(string $refreshToken): array
    {
        $result = $this->refreshToken($refreshToken);

        if (isset($result['access_token'])) {
            // Yeni tokeni cari sessiyada istifadə et
            $this->accessToken = $result['access_token'];

            Log::info('1688 Token uğurla yeniləndi', [
                'expires_in' => $result['expires_in'] ?? 'unknown',
            ]);

            // TODO: Tokeni database-də və ya .env-də yeniləyin
            // Nümunə:
            // DB::table('settings')->updateOrInsert(
            //     ['key' => 'ali1688_access_token'],
            //     ['value' => $result['access_token'], 'updated_at' => now()]
            // );
        }

        return $result;
    }

    // ==================== LOGİSTİKA API-ləri ====================

    /**
     * XARİCİ SİFARİŞ ID AL
     *
     * Waybill (qaimə) nömrəsinə görə xarici sifariş ID-sini qaytarır.
     * Logistika izləmə üçün istifadə olunur.
     *
     * @param string $waybillNumber - Qaimə nömrəsi
     * @return array
     */
    public function getOutOrderId(string $waybillNumber): array
    {
        return $this->callApi(
            'com.alibaba.fenxiao.crossborder',
            'logistics.order.getOutOrderId',
            '1',
            ['waybillNumber' => $waybillNumber]
        );
    }

    // ==================== KATEQORİYA API-ləri ====================

    /**
     * KATEQORİYA SİYAHISI (TƏRCÜMƏLİ)
     *
     * Kateqoriya ağacını tərcümə ilə qaytarır.
     * categoryId=0 və parentCateId=0 göndərsəniz root kateqoriyaları alırsınız.
     * Hər kateqoriyanın children[] massivi var (alt kateqoriyalar).
     *
     * 1688 API: category.translation.getById
     *
     * @param string $language - Dil kodu (en, ru, ja və s.)
     * @param string $categoryId - Kateqoriya ID-si (0 = root)
     * @param string $parentCateId - Ana kateqoriya ID-si (0 = root)
     * @param string|null $outMemberId - İstifadəçi ID-si
     * @return array - Kateqoriya ağacı (categoryId, chineseName, translatedName, leaf, level, children[])
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

    // ==================== ƏLAVƏ MƏHSUL API-ləri ====================

    /**
     * ATRİBUT XƏRİTƏLƏMƏ
     *
     * Kateqoriyaya görə atribut xəritələmə məlumatlarını qaytarır.
     * Məhsul filtrasiyası və atribut uyğunlaşdırması üçün istifadə olunur.
     *
     * @param string $categoryId - Kateqoriya ID-si
     * @return array
     */
    public function getAttributeMapping(string $categoryId): array
    {
        return $this->callApi(
            'com.alibaba.fenxiao.crossborder',
            'product.category.getAttrById',
            '1',
            ['categoryId' => $categoryId]
        );
    }

    /**
     * MƏHSUL SATIŞ NÖQTƏLƏRİ
     *
     * Məhsulun distribusiya (fenxiao) satış məlumatlarını qaytarır.
     *
     * @param int $offerId - Məhsul ID-si
     * @return array
     */
    public function getSellingPoints(int $offerId): array
    {
        return $this->callApi(
            'com.alibaba.fenxiao.crossborder',
            'product.distribute.getDistributeInfo',
            '1',
            ['offerId' => (string) $offerId]
        );
    }

    /**
     * MƏHSULLARI İZLƏ
     *
     * Məhsulları distribusiya siyahısına əlavə edir (izləmə/follow).
     *
     * @param array $offerIds - Məhsul ID-ləri massivi
     * @return array
     */
    public function followProducts(array $offerIds): array
    {
        return $this->callApi(
            'com.alibaba.fenxiao.crossborder',
            'product.kjdistribute.addRelation',
            '1',
            ['offerIdList' => json_encode($offerIds)]
        );
    }

    // ==================== YARDIMÇI METODLAR ====================

    /**
     * BÜTÜN MƏHSULLARI ÇƏK
     *
     * Məhsul hovuzundakı bütün məhsulları avtomatik səhifələyərək çəkir.
     * Böyük hovuzlar üçün uzun müddət çəkə bilər.
     *
     * @param string $offerPoolId - Məhsul hovuzu ID-si
     * @param int $pageSize - Hər səhifədəki məhsul sayı
     * @return array - Bütün məhsullar
     */
    public function pullAllOffers(string $offerPoolId, int $pageSize = 50): array
    {
        $allOffers = [];
        $pageNo = 1;

        do {
            $result = $this->pullOffer($offerPoolId, $pageNo, $pageSize);

            if (!isset($result['result']) || !is_array($result['result'])) {
                break;
            }

            $offers = $result['result'];
            $allOffers = array_merge($allOffers, $offers);

            $pageNo++;
        } while (count($offers) === $pageSize);

        return $allOffers;
    }

    /**
     * QİYMƏT HESABLAMA
     *
     * quoteType-a görə düzgün qiyməti hesablayır.
     *
     * @param array $productData - Məhsul detalları
     * @param int $quantity - Miqdar
     * @return float|null - Hesablanmış qiymət
     */
    public function calculatePrice(array $productData, int $quantity = 1): ?float
    {
        $quoteType = $productData['quoteType'] ?? 0;

        switch ($quoteType) {
            case 0:
                return $productData['consignPrice'] ?? null;
            case 1:
                return $productData['channelPrice'] ?? null;
            case 2:
                $intervals = $productData['intervalPrices'] ?? [];
                foreach ($intervals as $interval) {
                    $start = $interval['startQuantity'] ?? 0;
                    $end = $interval['endQuantity'] ?? PHP_INT_MAX;
                    if ($quantity >= $start && $quantity <= $end) {
                        return $interval['price'] ?? null;
                    }
                }
                return null;
            default:
                return $productData['consignPrice'] ?? $productData['channelPrice'] ?? null;
        }
    }
}
