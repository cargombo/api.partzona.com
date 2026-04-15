<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * STANDALONE 1688 SİFARİŞ + ÖDƏNİŞ + REFUND COMMAND (TAM MÜSTƏQİL)
 * ================================================================
 * Bu fayl tamamilə müstəqildir:
 *   - Heç bir Service / Model / Config / .env asılılığı YOXDUR
 *   - Bütün credentials, warehouse ünvanı, token bu faylın içindədir
 *   - Yalnız raw PHP cURL istifadə edir
 *
 * BAŞQA LARAVEL LAYİHƏSİNƏ KÖÇÜRMƏK ÜÇÜN:
 *   1. Bu faylı yeni layihənin app/Console/Commands/ qovluğuna kopyala
 *   2. `php artisan list` ilə komandanın göründüyünü yoxla
 *
 * İSTİFADƏ:
 *
 *   # Order yarat + ödə (avtomatik ən ucuz SKU)
 *   php artisan 1688:standalone-pay 986690516422
 *
 *   # Konkret variant ilə
 *   php artisan 1688:standalone-pay 986690516422 --specId=adf1ced9891bf6499058d7243a666d42
 *
 *   # Yalnız order yarat, ödəmə
 *   php artisan 1688:standalone-pay 986690516422 --skip-pay
 *
 *   # Hazır order ödəmək
 *   php artisan 1688:standalone-pay 0 --order-id=3295113924686115298
 *
 *   # REFUND (geri qaytarma) — ödənilmiş orderi geri al
 *   php artisan 1688:standalone-pay 0 --refund --order-id=3295113924686115298
 *
 *   # REFUND xüsusi məbləğlə
 *   php artisan 1688:standalone-pay 0 --refund --order-id=ID --apply-payment=100 --apply-carriage=300
 *
 * QEYD: Bu credentials APP 2 (7418748 / tb090081666607) üçündür.
 *       Bu hesabda ALIPAY müqaviləsi imzalanıbdır (signedStatus: true).
 *       payChannel = "alipay" (kjpayV2 deyil — o ayrı müqavilə tələb edir).
 */
class Standalone1688OrderPay extends Command
{
    protected $signature = '1688:standalone-pay
                            {offerId=0 : 1688 product offerId}
                            {--specId= : SKU variant id (boş buraxsan avtomatik tapılacaq)}
                            {--qty=1 : quantity}
                            {--flow=fenxiao : order flow}
                            {--pay-channel=alipay : payment channel (alipay/kjpayV2)}
                            {--order-id= : mövcud 1688 orderId — ödəmək və ya refund üçün}
                            {--skip-pay : yalnız order yarat, ödəmə}
                            {--refund : refund rejimi — --order-id ilə ödənilmiş orderi geri qaytar}
                            {--reason-id=20006 : refund səbəb ID-si (default: 不想买了)}
                            {--reason-text=Test refund - artiq lazim deyil : refund açıqlaması}
                            {--apply-payment= : refund məhsul məbləği fen-lə (default: order detail-dən)}
                            {--apply-carriage= : refund çatdırılma məbləği fen-lə (default: order detail-dən)}
                            {--goods-status=refundWaitSellerSend : refundWaitSellerSend|refundWaitBuyerReceive|refundBuyerReceived}';

    protected $description = 'Standalone 1688 sifariş yarat, ödə və refund et (heç bir asılılıq yox, tam müstəqil)';

    // ════════════════════════════════════════════════════════════════
    //   SABİT KONFİQURASİYA — bütün məlumat burada
    //   Başqa hesaba köçürəndə yalnız bu hissəni dəyiş.
    // ════════════════════════════════════════════════════════════════

    /** 1688 OpenAPI base URL */
    private const BASE_URL = 'https://gw.open.1688.com/openapi';

    /** App credentials (APP 2 — tb090081666607 — ALIPAY müqaviləsi imzalanıbdır) */
    private const APP_KEY      = '7418748';
    private const APP_SECRET   = 'TvZsoUZtAL';
    private const ACCESS_TOKEN = '767cae7b-094f-40ee-925b-57d74f534c67';

    /** Buyer info — preparePay üçün lazımdır (1688 SDK class faylında required kimi qeyd olunub) */
    private const BUYER_ID     = 2220729119852; // ali_id
    private const ACCOUNT_TYPE = 'buyer';

    /** Çatdırılma anbarı (Çin daxilində — fenxiao flow üçün) */
    private const WAREHOUSE = [
        'fullName'     => 'Partzona Warehouse',
        'mobile'       => '15626113080',
        'phone'        => '15626113080',
        'postCode'     => '510450',
        'provinceText' => '广东省',
        'cityText'     => '广州市',
        'areaText'     => '白云区',
        'address'      => '夏花二路961号恒河沙仓储814仓',
        'districtText' => '',
    ];

    // ════════════════════════════════════════════════════════════════

    public function handle(): int
    {
        $this->line('App Key: ' . self::APP_KEY);
        $this->line('Access Token: ' . substr(self::ACCESS_TOKEN, 0, 8) . '...');

        // ───────────────────────────────────────────────
        // REFUND rejimi — order-id-i geri qaytar
        // ───────────────────────────────────────────────
        if ($this->option('refund')) {
            return $this->handleRefund();
        }

        // ───────────────────────────────────────────────
        // 1. Order yarat və ya hazırını götür
        // ───────────────────────────────────────────────
        $orderId = $this->option('order-id');

        if (!$orderId) {
            $offerId = (int) $this->argument('offerId');
            if ($offerId <= 0) {
                $this->error('offerId tələb olunur (və ya --order-id keç)');
                return Command::FAILURE;
            }

            // Variant ID — verilibsə istifadə et, verilməyibsə avtomatik tap
            $specId = $this->option('specId');
            if (!$specId) {
                $this->newLine();
                $this->info('═══ ADDIM 0: Məhsul detalı çəkilir (specId avtomatik) ═══');
                $specId = $this->autoDetectSpecId($offerId);
            }

            $this->newLine();
            $this->info('═══ ADDIM 1: 1688-də sifariş yaradılır ═══');
            $orderId = $this->createOrder(
                $offerId,
                $specId,
                (int) $this->option('qty'),
                (string) $this->option('flow')
            );

            if (!$orderId) {
                return Command::FAILURE;
            }
        } else {
            $this->info("Hazır order istifadə olunur: {$orderId}");
        }

        // ───────────────────────────────────────────────
        // 2. Order detalını al (payAmount üçün)
        // ───────────────────────────────────────────────
        $this->newLine();
        $this->info('═══ ADDIM 2: Order detalı alınır ═══');
        $detail = $this->getOrderDetail((string) $orderId);
        $totalCny = $detail['result']['baseInfo']['totalAmount'] ?? null;
        $payAmountFen = $totalCny !== null ? (int) round(((float) $totalCny) * 100) : null;
        $this->line("totalAmount = {$totalCny} CNY  →  payAmount = " . ($payAmountFen ?? 'null') . ' 分');

        // ───────────────────────────────────────────────
        // 3. Ödəniş
        // ───────────────────────────────────────────────
        if ($this->option('skip-pay')) {
            $this->warn("\n--skip-pay aktivdir, ödəniş etmirik.");
            $this->info("Order ID: {$orderId}");
            return Command::SUCCESS;
        }

        $this->newLine();
        $this->info('═══ ADDIM 3: Şifrəsiz ödəniş (preparePay) ═══');
        $paid = $this->preparePay(
            (string) $orderId,
            (string) $this->option('pay-channel'),
            $payAmountFen
        );

        if (!$paid) {
            $this->newLine();
            $this->warn('preparePay uğursuz oldu. Manual ödəniş URL-i alınır...');
            $payUrl = $this->getAlipayUrl((string) $orderId);
            if ($payUrl) {
                $this->info("Manual ödəniş URL: {$payUrl}");
            }
            return Command::FAILURE;
        }

        $this->newLine();
        $this->info("✅ HƏR ŞEY UĞURLU. Order: {$orderId}");
        return Command::SUCCESS;
    }

    // ═══════════════════════════════════════════════════════
    //   REFUND HANDLER
    // ═══════════════════════════════════════════════════════

    /**
     * Refund rejimini həyata keçirir.
     *
     * Addımlar:
     *   1. Order detalını çək (orderEntryIds, məbləğlər, status üçün)
     *   2. Refund səbəblər siyahısını al (verifikasiya üçün)
     *   3. createRefund çağır
     *   4. (Opsional) Refund statusunu qaytar
     */
    private function handleRefund(): int
    {
        $orderId = $this->option('order-id');
        if (!$orderId) {
            $this->error('Refund üçün --order-id tələb olunur');
            return Command::FAILURE;
        }

        $this->newLine();
        $this->info('═══ REFUND ADDIM 1: Order detalı alınır ═══');
        $detail = $this->getOrderDetail((string) $orderId);

        if (!isset($detail['result'])) {
            $this->error('Order detalı alına bilmədi');
            return Command::FAILURE;
        }

        $items = $detail['result']['productItems'] ?? [];
        if (empty($items)) {
            $this->error('Order-də productItems yoxdur');
            return Command::FAILURE;
        }

        // Bütün entry ID-ləri yığ
        $entryIds = [];
        $itemPriceTotal = 0;
        $shippingTotal = 0;
        foreach ($items as $it) {
            $entryIds[]    = (int) ($it['subItemID'] ?? 0);
            $itemPriceTotal += (int) round(((float)($it['price'] ?? 0)) * (int)($it['quantity'] ?? 1) * 100);
            $shippingTotal  += (int) round(((float)($it['sharePostage'] ?? 0)) * 100);
        }

        // Default məbləğlər (override etmək olar --apply-payment / --apply-carriage ilə)
        $applyPayment  = $this->option('apply-payment') !== null
            ? (int) $this->option('apply-payment')
            : $itemPriceTotal;
        $applyCarriage = $this->option('apply-carriage') !== null
            ? (int) $this->option('apply-carriage')
            : $shippingTotal;

        $goodsStatus = (string) $this->option('goods-status');
        $reasonId    = (int) $this->option('reason-id');
        $description = (string) $this->option('reason-text');

        $this->line("Entry IDs: " . implode(',', $entryIds));
        $this->line("applyPayment={$applyPayment} 分, applyCarriage={$applyCarriage} 分");
        $this->line("reasonId={$reasonId}, goodsStatus={$goodsStatus}");

        // ───────────────────────────────────────────────
        // Refund səbəblərini yoxla (validation üçün)
        // ───────────────────────────────────────────────
        $this->newLine();
        $this->info('═══ REFUND ADDIM 2: Səbəb siyahısı yoxlanılır ═══');
        $reasonsResult = $this->getRefundReasonList((string) $orderId, $entryIds, $goodsStatus);
        $reasons = $reasonsResult['result']['result']['reasons'] ?? [];
        $reasonValid = false;
        foreach ($reasons as $r) {
            if ((int)($r['id'] ?? 0) === $reasonId) {
                $reasonValid = true;
                $this->line("Səbəb tapıldı: {$r['name']} (id={$r['id']})");
                break;
            }
        }
        if (!$reasonValid) {
            $this->warn("Verilmiş reason-id ({$reasonId}) siyahıda yoxdur. Mövcud səbəblər:");
            foreach ($reasons as $r) {
                $this->line("  {$r['id']} — {$r['name']}");
            }
            $this->warn("Davam edirik (1688 yenə də qəbul edə bilər)");
        }

        // ───────────────────────────────────────────────
        // Refund yarat
        // ───────────────────────────────────────────────
        $this->newLine();
        $this->info('═══ REFUND ADDIM 3: createRefund ═══');
        $result = $this->createRefund(
            (int) $orderId,
            $entryIds,
            $applyPayment,
            $applyCarriage,
            $reasonId,
            $description,
            $goodsStatus
        );

        $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $refundId = $result['result']['result']['refundId'] ?? null;
        if (!$refundId) {
            $code = $result['error_code'] ?? $result['code'] ?? '?';
            $msg  = $result['error_message'] ?? $result['message'] ?? json_encode($result);
            $this->error("Refund XƏTA [{$code}]: {$msg}");
            return Command::FAILURE;
        }

        $this->newLine();
        $this->info("✅ REFUND UĞURLU. refundId: {$refundId}");
        $this->line("Order: {$orderId}");
        $this->line("Geri qaytarılan məbləğ: " . (($applyPayment + $applyCarriage) / 100) . " CNY");
        return Command::SUCCESS;
    }

    // ═══════════════════════════════════════════════════════
    //   1688 API METODLARI (hamısı bu fayldadır, sırf curl)
    // ═══════════════════════════════════════════════════════

    /**
     * product.search.queryProductDetail — məhsul detalı çək, ən ucuz mövcud SKU-nu seç
     * Variantsız məhsul üçün null qaytarır.
     */
    private function autoDetectSpecId(int $offerId): ?string
    {
        $result = $this->callApi(
            'com.alibaba.fenxiao.crossborder',
            'product.search.queryProductDetail',
            '1',
            ['offerDetailParam' => json_encode(['offerId' => $offerId], JSON_UNESCAPED_UNICODE)]
        );

        if (isset($result['error']) || !isset($result['result'])) {
            $this->warn('Məhsul detalı alına bilmədi — specId-siz davam edirik');
            $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return null;
        }

        $data = $result['result']['result'] ?? $result['result'] ?? [];
        $skuInfos = $data['productSkuInfos'] ?? [];

        if (empty($skuInfos)) {
            $this->line('Məhsulda SKU yoxdur — variantsız');
            return null;
        }

        // Stoku 0-dan böyük və ən ucuz SKU-nu tap
        $bestSku = null;
        $bestPrice = PHP_INT_MAX;
        foreach ($skuInfos as $sku) {
            $stock = (int) ($sku['amountOnSale'] ?? 0);
            if ($stock <= 0) continue;
            $price = (float) ($sku['price'] ?? $sku['consignPrice'] ?? 0);
            if ($price > 0 && $price < $bestPrice) {
                $bestPrice = $price;
                $bestSku   = $sku;
            }
        }

        if (!$bestSku) {
            // Heç biri stokda yoxdursa, ən ucuzunu götür
            foreach ($skuInfos as $sku) {
                $price = (float) ($sku['price'] ?? $sku['consignPrice'] ?? 0);
                if ($price > 0 && $price < $bestPrice) {
                    $bestPrice = $price;
                    $bestSku   = $sku;
                }
            }
        }

        if (!$bestSku || empty($bestSku['specId'])) {
            $this->warn('Uyğun SKU tapılmadı');
            return null;
        }

        $attrs = [];
        foreach ($bestSku['skuAttributes'] ?? [] as $a) {
            $attrs[] = ($a['attributeName'] ?? '') . '=' . ($a['value'] ?? '');
        }
        $this->info("✅ Avto SKU: specId={$bestSku['specId']}, price={$bestPrice}, " . implode(' / ', $attrs));

        return $bestSku['specId'];
    }

    /**
     * alibaba.trade.createCrossOrder — sifariş yarat
     */
    private function createOrder(int $offerId, ?string $specId, int $qty, string $flow): ?string
    {
        $cargo = ['offerId' => $offerId, 'quantity' => $qty];
        if ($specId) {
            $cargo['specId'] = $specId;
        }

        $bizParams = [
            'flow'           => $flow,
            'outOrderId'     => 'STANDALONE-' . time(),
            'dropshipping'   => 'y',
            'addressParam'   => json_encode(self::WAREHOUSE, JSON_UNESCAPED_UNICODE),
            'cargoParamList' => json_encode([$cargo], JSON_UNESCAPED_UNICODE),
        ];

        $this->line("Cargo: " . json_encode($cargo, JSON_UNESCAPED_UNICODE));

        $result = $this->callApi('com.alibaba.trade', 'alibaba.trade.createCrossOrder', '1', $bizParams);

        $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        if (!isset($result['result']['orderId'])) {
            $this->error('Sifariş yaradıla bilmədi.');
            return null;
        }

        $orderId = (string) $result['result']['orderId'];
        $this->info("✅ Order yaradıldı: {$orderId}");
        return $orderId;
    }

    /**
     * alibaba.trade.get.buyerView — order detalı
     */
    private function getOrderDetail(string $orderId): array
    {
        $result = $this->callApi('com.alibaba.trade', 'alibaba.trade.get.buyerView', '1', [
            'orderId' => $orderId,
            'webSite' => '1688',
        ]);

        if (isset($result['errorCode'])) {
            $this->warn("getOrderDetail: " . ($result['errorMessage'] ?? $result['errorCode']));
        }

        return $result;
    }

    /**
     * alibaba.trade.pay.protocolPay.preparePay — şifrəsiz ödəniş
     *
     * QEYD: payChannel = 'alipay' düzgündür.
     *       'kjpayV2' (跨境宝) AYRI müqavilə tələb edir.
     */
    private function preparePay(string $orderId, string $payChannel, ?int $payAmountFen): bool
    {
        $tradeParam = [
            'orderId'     => (int) $orderId,
            'payChannel'  => $payChannel,
            'opRequestId' => 'sa_' . $orderId . '_' . time(),
            'buyerId'     => self::BUYER_ID,
            'accountType' => self::ACCOUNT_TYPE,
        ];
        if ($payAmountFen !== null) {
            $tradeParam['payAmount'] = $payAmountFen;
        }

        $this->line("preparePay param: " . json_encode($tradeParam, JSON_UNESCAPED_UNICODE));

        $result = $this->callApi(
            'com.alibaba.trade',
            'alibaba.trade.pay.protocolPay.preparePay',
            '1',
            ['tradeWithholdPreparePayParam' => json_encode($tradeParam, JSON_UNESCAPED_UNICODE)]
        );

        $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        if (isset($result['success']) && $result['success'] === true) {
            $this->info("✅ preparePay uğurlu");
            return true;
        }

        $code = $result['code'] ?? $result['error_code'] ?? '?';
        $msg  = $result['message'] ?? $result['error_message'] ?? json_encode($result);
        $this->error("preparePay XƏTA [{$code}]: {$msg}");
        return false;
    }

    /**
     * alibaba.alipay.url.get — manual ödəniş səhifəsinin URL-i
     */
    private function getAlipayUrl(string $orderId): ?string
    {
        $result = $this->callApi('com.alibaba.trade', 'alibaba.alipay.url.get', '1', [
            'orderIdList' => json_encode([(int) $orderId]),
        ]);

        $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $result['payUrl'] ?? null;
    }

    /**
     * alibaba.trade.getRefundReasonList — refund səbəblər siyahısı
     */
    private function getRefundReasonList(string $orderId, array $entryIds, string $goodsStatus): array
    {
        return $this->callApi('com.alibaba.trade', 'alibaba.trade.getRefundReasonList', '1', [
            'orderId'       => (int) $orderId,
            'orderEntryIds' => json_encode($entryIds),
            'goodsStatus'   => $goodsStatus,
        ]);
    }

    /**
     * alibaba.trade.createRefund — refund yarat
     *
     * goodsStatus enum:
     *   refundWaitSellerSend       — satış zamanı, satıcı hələ göndərməyib
     *   refundWaitBuyerReceive     — satış zamanı, alıcı qəbul gözləyir
     *   refundBuyerReceived        — satış zamanı, alıcı qəbul edib
     *   aftersaleBuyerNotReceived  — satışdan sonra, alıcı qəbul etməyib
     *   aftersaleBuyerReceived     — satışdan sonra, alıcı qəbul edib
     */
    private function createRefund(
        int $orderId,
        array $orderEntryIds,
        int $applyPaymentFen,
        int $applyCarriageFen,
        int $applyReasonId,
        string $description,
        string $goodsStatus
    ): array {
        return $this->callApi('com.alibaba.trade', 'alibaba.trade.createRefund', '1', [
            'orderId'        => $orderId,
            'orderEntryIds'  => json_encode($orderEntryIds),
            'disputeRequest' => 'refund',
            'applyPayment'   => $applyPaymentFen,
            'applyCarriage'  => $applyCarriageFen,
            'applyReasonId'  => $applyReasonId,
            'description'    => $description,
            'goodsStatus'    => $goodsStatus,
        ]);
    }

    // ═══════════════════════════════════════════════════════
    //   ƏSAS HTTP + İMZA (sırf cURL, heç bir asılılıq yox)
    // ═══════════════════════════════════════════════════════

    /**
     * 1688 API çağırışı.
     * Bütün sorğular POST + application/x-www-form-urlencoded.
     */
    private function callApi(string $namespace, string $apiName, string $version, array $bizParams): array
    {
        $apiPath = "param2/{$version}/{$namespace}/{$apiName}/" . self::APP_KEY;
        $url     = self::BASE_URL . "/{$apiPath}";

        // Bütün parametrlər (biz + sistem)
        $allParams = $bizParams;
        $allParams['access_token'] = self::ACCESS_TOKEN;
        $allParams['appKey']       = self::APP_KEY;

        // İmzanı sonda əlavə et
        $allParams['_aop_signature'] = $this->signRequest($apiPath, $allParams);

        // Raw cURL
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($allParams),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return ['error' => 'cURL: ' . $curlErr];
        }
        if ($response === false || $response === '') {
            return ['error' => 'Empty response', 'http_code' => $httpCode];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return ['error' => 'Invalid JSON', 'raw' => $response, 'http_code' => $httpCode];
        }

        return $decoded;
    }

    /**
     * 1688 imza alqoritmi:
     *   signature = HMAC-SHA1( apiPath + sorted(key+value) , appSecret )
     *   → uppercase HEX
     */
    private function signRequest(string $apiPath, array $params): string
    {
        $pairs = [];
        foreach ($params as $key => $val) {
            // Boş və null parametrləri imzaya daxil etmə
            if ($val !== null && $val !== '') {
                $pairs[] = $key . $val;
            }
        }
        sort($pairs);
        $signStr = $apiPath . implode('', $pairs);
        return strtoupper(bin2hex(hash_hmac('sha1', $signStr, self::APP_SECRET, true)));
    }
}
