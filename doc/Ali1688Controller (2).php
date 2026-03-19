<?php

namespace App\Http\Controllers;

use App\Services\Ali1688Service;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class Ali1688Controller extends Controller
{
    private Ali1688Service $ali1688;

    public function __construct(Ali1688Service $ali1688)
    {
        $this->ali1688 = $ali1688;
    }

    // ==================== MƏHSUL ENDPOINTLƏRİ ====================

    /**
     * Məhsul siyahısını çək
     * GET /api/1688/products
     */
    public function products(Request $request): JsonResponse
    {
        $result = $this->ali1688->pullOffer(
            $request->input('pool_id', config('services.ali1688.offer_pool_id')),
            $request->input('page', 1),
            $request->input('per_page', 20),
            $request->input('cat_id'),
            $request->input('item_id'),
            $request->input('language'),
            $request->input('task_id'),
            $request->input('sort_field'),
            $request->input('sort_type')
        );

        return response()->json($result);
    }

    /**
     * Məhsul sayını al
     * GET /api/1688/products/total
     */
    public function productTotal(Request $request): JsonResponse
    {
        $result = $this->ali1688->getProductTotal(
            $request->input('pool_id', config('services.ali1688.offer_pool_id')),
            $request->input('cat_id')
        );

        return response()->json($result);
    }

    /**
     * Məhsul detalları
     * GET /api/1688/products/{offerId}
     */
    public function productDetail(int $offerId, Request $request): JsonResponse
    {
        $result = $this->ali1688->getOfferDetail(
            $offerId,
            $request->input('spec_id'),
            $request->input('country', 'AZ')
        );

        return response()->json($result);
    }

    /**
     * Çatdırılma qiyməti təxmini
     * POST /api/1688/products/{offerId}/freight
     */
    public function estimateFreight(int $offerId, Request $request): JsonResponse
    {
        $request->validate([
            'destination' => 'required|string',
            'quantity' => 'integer|min:1',
        ]);

        $result = $this->ali1688->estimateFreight(
            $offerId,
            $request->input('destination'),
            $request->input('quantity', 1),
            $request->input('spec_id')
        );

        return response()->json($result);
    }

    // ==================== KATEQORİYA ENDPOINTLƏRİ ====================

    /**
     * Kateqoriya siyahısı (tərcüməli)
     * GET /api/1688/categories
     */
    public function categories(Request $request): JsonResponse
    {
        $result = $this->ali1688->getCategoryList(
            $request->input('language', 'en'),
            $request->input('category_id', '0'),
            $request->input('parent_id', '0')
        );

        return response()->json($result);
    }

    /**
     * Kateqoriya üzrə məhsullar
     * GET /api/1688/categories/{categoryId}/products
     */
    public function categoryProducts(string $categoryId, Request $request): JsonResponse
    {
        $result = $this->ali1688->pullOffer(
            $request->input('pool_id', config('services.ali1688.offer_pool_id')),
            $request->input('page', 1),
            $request->input('per_page', 20),
            $categoryId,
            $request->input('item_id'),
            $request->input('language'),
            $request->input('task_id'),
            $request->input('sort_field'),
            $request->input('sort_type')
        );

        return response()->json($result);
    }

    /**
     * Kateqoriya üzrə məhsul sayı
     * GET /api/1688/categories/{categoryId}/products/total
     */
    public function categoryProductTotal(string $categoryId, Request $request): JsonResponse
    {
        $result = $this->ali1688->getProductTotal(
            $request->input('pool_id', config('services.ali1688.offer_pool_id')),
            $categoryId
        );

        return response()->json($result);
    }

    // ==================== SİFARİŞ ENDPOINTLƏRİ ====================

    /**
     * Sifariş yarat
     * POST /api/1688/orders
     */
    public function createOrder(Request $request): JsonResponse
    {
        $request->validate([
            'out_order_id' => 'required|string',
            'flow' => 'required|in:bigcfenxiao,bigcpifa',
            'address' => 'required|array',
            'address.fullName' => 'required|string',
            'address.mobile' => 'required|string',
            'address.provinceText' => 'required|string',
            'address.cityText' => 'required|string',
            'address.address' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.offerId' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $result = $this->ali1688->createOrder(
            $request->input('flow'),
            $request->input('out_order_id'),
            $request->input('address'),
            $request->input('items'),
            $request->input('dropshipping', 'y'),
            $request->input('message')
        );

        return response()->json($result);
    }

    /**
     * Sifariş siyahısı
     * GET /api/1688/orders
     */
    public function orders(Request $request): JsonResponse
    {
        $result = $this->ali1688->getOrderList(
            $request->input('status'),
            $request->input('page', 1),
            $request->input('per_page', 20),
            $request->input('start_time'),
            $request->input('end_time')
        );

        return response()->json($result);
    }

    /**
     * Sifariş detalları
     * GET /api/1688/orders/{orderId}
     */
    public function orderDetail(int $orderId): JsonResponse
    {
        $result = $this->ali1688->getOrderDetail($orderId);
        return response()->json($result);
    }

    /**
     * Sifarişi ləğv et
     * DELETE /api/1688/orders/{orderId}
     */
    public function cancelOrder(int $orderId, Request $request): JsonResponse
    {
        $result = $this->ali1688->cancelOrder(
            $orderId,
            $request->input('reason')
        );

        return response()->json($result);
    }

    /**
     * Malı qəbul et
     * POST /api/1688/orders/{orderId}/confirm
     */
    public function confirmReceipt(int $orderId): JsonResponse
    {
        $result = $this->ali1688->confirmReceipt($orderId);
        return response()->json($result);
    }

    // ==================== ÖDƏNİŞ ENDPOINTLƏRİ ====================

    /**
     * Ödəniş URL-i al
     * GET /api/1688/orders/{orderId}/payment-url
     */
    public function paymentUrl(int $orderId): JsonResponse
    {
        $result = $this->ali1688->getPaymentUrl($orderId);
        return response()->json($result);
    }

    /**
     * Ödəniş hazırla (Protocol Pay)
     * POST /api/1688/orders/prepare-payment
     */
    public function preparePayment(Request $request): JsonResponse
    {
        $request->validate([
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'integer',
        ]);

        $result = $this->ali1688->preparePayment($request->input('order_ids'));
        return response()->json($result);
    }

    // ==================== GERİ QAYTARMA ====================

    /**
     * Refund yarat
     * POST /api/1688/orders/{orderId}/refund
     */
    public function createRefund(int $orderId, Request $request): JsonResponse
    {
        $request->validate([
            'entry_ids' => 'required|array|min:1',
            'type' => 'in:refund,returnRefund',
        ]);

        $result = $this->ali1688->createRefund(
            $orderId,
            $request->input('entry_ids'),
            $request->input('type', 'refund'),
            $request->input('reason'),
            $request->input('amount')
        );

        return response()->json($result);
    }

    // ==================== LOGİSTİKA ====================

    /**
     * Waybill nömrəsinə görə xarici sifariş ID al
     * GET /api/1688/logistics/out-order-id
     */
    public function getOutOrderId(Request $request): JsonResponse
    {
        $request->validate(['waybill_number' => 'required|string']);

        $result = $this->ali1688->getOutOrderId($request->input('waybill_number'));
        return response()->json($result);
    }

    // ==================== ƏLAVƏ MƏHSUL API-ləri ====================

    /**
     * Kateqoriya atribut xəritələməsi
     * GET /api/1688/categories/{categoryId}/attributes
     */
    public function attributeMapping(string $categoryId): JsonResponse
    {
        $result = $this->ali1688->getAttributeMapping($categoryId);
        return response()->json($result);
    }

    /**
     * Məhsul satış nöqtələri
     * GET /api/1688/products/{offerId}/selling-points
     */
    public function sellingPoints(int $offerId): JsonResponse
    {
        $result = $this->ali1688->getSellingPoints($offerId);
        return response()->json($result);
    }

    /**
     * Məhsulları izlə / distribusiya siyahısına əlavə et
     * POST /api/1688/products/follow
     */
    public function followProducts(Request $request): JsonResponse
    {
        $request->validate(['offer_ids' => 'required|array|min:1']);

        $result = $this->ali1688->followProducts($request->input('offer_ids'));
        return response()->json($result);
    }

    // ==================== TOKEN / OAUTH API-ləri ====================

    /**
     * OAuth Authorization səhifəsinə yönləndir
     * GET /api/1688/auth/authorize
     */
    public function redirectToAuth(): \Illuminate\Http\RedirectResponse
    {
        $appKey = config('services.ali1688.app_key');
        $redirectUri = url('/api/1688/auth/callback');

        $authUrl = "https://auth.1688.com/oauth/authorize?" . http_build_query([
            'client_id' => $appKey,
            'redirect_uri' => $redirectUri,
            'site' => '1688',
            'state' => csrf_token(),
        ]);

        return redirect()->away($authUrl);
    }

    /**
     * OAuth Callback - 1688-dən code alıb token-ə çevirir
     * GET /api/1688/auth/callback
     */
    public function callback(Request $request)
    {
        $code = $request->input('code');

        if (!$code) {
            return response()->json([
                'success' => false,
                'error' => 'Authorization code tapılmadı',
            ], 400);
        }

        $redirectUri = url('/api/1688/auth/callback');
        $result = $this->ali1688->getTokenByCode($code, $redirectUri);

        if (isset($result['access_token'])) {
            // Token-ləri faylda saxla
            $expiresIn = $result['expires_in'] ?? null;
            $tokenData = [
                'access_token' => $result['access_token'],
                'refresh_token' => $result['refresh_token'] ?? null,
                'expires_in' => $expiresIn,
                'expires_at' => ($expiresIn && $expiresIn > 0)
                    ? now()->addSeconds($expiresIn)->toDateTimeString()
                    : null,
                'is_permanent' => ($expiresIn && $expiresIn < 0),
                'ali_id' => $result['aliId'] ?? null,
                'member_id' => $result['memberId'] ?? null,
                'resource_owner' => $result['resource_owner'] ?? null,
                'updated_at' => now()->toDateTimeString(),
            ];

            $this->saveTokens($tokenData);

            return response()->json([
                'success' => true,
                'message' => 'Token uğurla alındı və saxlanıldı!',
                'token_data' => $tokenData,
                'raw_response' => $result,
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $result['error'] ?? 'Token alınmadı',
            'details' => $result,
        ], 400);
    }

    /**
     * Mövcud token-i refresh et
     * POST /api/1688/auth/refresh
     */
    public function refreshToken(Request $request): JsonResponse
    {
        // Refresh token-i request-dən və ya saxlanmış fayldan al
        $refreshToken = $request->input('refresh_token');

        if (!$refreshToken) {
            $savedTokens = $this->getTokens();
            $refreshToken = $savedTokens['refresh_token'] ?? null;
        }

        if (!$refreshToken) {
            return response()->json([
                'success' => false,
                'error' => 'Refresh token tapılmadı. Əvvəlcə /api/1688/auth/authorize keçin.',
            ], 400);
        }

        $result = $this->ali1688->refreshToken($refreshToken);

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

            $this->saveTokens($tokenData);

            return response()->json([
                'success' => true,
                'message' => 'Token uğurla yeniləndi!',
                'expires_at' => $tokenData['expires_at'],
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $result['error'] ?? 'Token yenilənmədi',
            'details' => $result,
        ], 400);
    }

    /**
     * Mövcud token məlumatlarını göstər
     * GET /api/1688/auth/status
     */
    public function tokenStatus(): JsonResponse
    {
        $tokens = $this->getTokens();

        if (!$tokens || !isset($tokens['access_token'])) {
            return response()->json([
                'success' => false,
                'authorized' => false,
                'message' => 'Token yoxdur. /api/1688/auth/authorize keçin.',
            ]);
        }

        $expiresAt = isset($tokens['expires_at']) ? \Carbon\Carbon::parse($tokens['expires_at']) : null;
        $isExpired = $expiresAt ? $expiresAt->isPast() : null;

        return response()->json([
            'success' => true,
            'authorized' => true,
            'access_token' => substr($tokens['access_token'], 0, 20) . '...',
            'has_refresh_token' => !empty($tokens['refresh_token']),
            'expires_at' => $tokens['expires_at'] ?? null,
            'is_expired' => $isExpired,
            'updated_at' => $tokens['updated_at'] ?? null,
        ]);
    }

    /**
     * Token-ləri faylda saxla
     */
    private function saveTokens(array $data): void
    {
        $path = storage_path('app/ali1688_tokens.json');
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));

        // .env faylında ALI1688_ACCESS_TOKEN-i yenilə
        if (isset($data['access_token'])) {
            $envPath = base_path('.env');
            $envContent = file_get_contents($envPath);

            if (preg_match('/^ALI1688_ACCESS_TOKEN=.*/m', $envContent)) {
                $envContent = preg_replace(
                    '/^ALI1688_ACCESS_TOKEN=.*/m',
                    'ALI1688_ACCESS_TOKEN=' . $data['access_token'],
                    $envContent
                );
            } else {
                $envContent .= "\nALI1688_ACCESS_TOKEN=" . $data['access_token'];
            }

            file_put_contents($envPath, $envContent);
        }
    }

    /**
     * Saxlanmış token-ləri oxu
     */
    private function getTokens(): ?array
    {
        $path = storage_path('app/ali1688_tokens.json');

        if (!file_exists($path)) {
            return null;
        }

        return json_decode(file_get_contents($path), true);
    }
}
