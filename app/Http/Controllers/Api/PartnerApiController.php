<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Transaction;
use App\Services\Ali1688Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @OA\Info(
 *     title="Partzona Partner API",
 *     version="1.0.0",
 *     description="1688.com məhsul məlumatlarına çıxış üçün Partner API"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     description="Partner API token. Authorization: Bearer pz_live_xxxxx"
 * )
 *
 * @OA\Server(url="/api/v1", description="Partner API v1")
 *
 * @OA\Parameter(
 *     parameter="lang",
 *     name="lang",
 *     in="query",
 *     description="Cavab dilini təyin edir (az, ru, en). Həmçinin Accept-Language header ilə göndərilə bilər.",
 *     @OA\Schema(type="string", enum={"az","ru","en"}, default="en")
 * )
 */
class PartnerApiController extends Controller
{
    private Ali1688Service $ali1688;

    public function __construct(Ali1688Service $ali1688)
    {
        $this->ali1688 = $ali1688;
    }

    /**
     * @OA\Get(
     *     path="/categories/{categoryId}/products",
     *     summary="Kateqoriya üzrə məhsullar",
     *     description="Verilmiş kateqoriyadakı məhsulları səhifələr şəklində qaytarır. Yalnız icazəli kateqoriyalar üçün işləyir.",
     *     operationId="getCategoryProducts",
     *     tags={"Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/lang"),
     *     @OA\Parameter(
     *         name="categoryId",
     *         in="path",
     *         required=true,
     *         description="1688 kateqoriya ID-si",
     *         @OA\Schema(type="integer", example=71)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Səhifə nömrəsi (1-dən başlayır)",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Səhifədəki məhsul sayı (max 50)",
     *         @OA\Schema(type="integer", default=20, maximum=50)
     *     ),
     *     @OA\Parameter(
     *         name="language",
     *         in="query",
     *         description="Tərcümə dili",
     *         @OA\Schema(type="string", default="en", enum={"en", "ru", "ja", "ko"})
     *     ),
     *     @OA\Parameter(
     *         name="sort_field",
     *         in="query",
     *         description="Sıralama sahəsi",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="sort_type",
     *         in="query",
     *         description="Sıralama istiqaməti",
     *         @OA\Schema(type="string", enum={"asc", "desc"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Məhsul siyahısı",
     *         @OA\JsonContent(
     *             @OA\Property(property="result", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Token etibarsızdır"),
     *     @OA\Response(response=403, description="Kateqoriya və ya endpoint icazəsi yoxdur"),
     *     @OA\Response(response=429, description="Rate limit aşılıb")
     * )
     */
    public function categoryProducts(string $categoryId, Request $request): JsonResponse
    {
        // Kateqoriya icazəsi middleware-də yoxlanılır
        $result = $this->ali1688->pullOffer(
            config('services.ali1688.offer_pool_id'),
            (int) $request->input('page', 1),
            min((int) $request->input('per_page', 20), 50),
            $categoryId,
            null,
            $request->input('language'),
            $request->input('sort_field'),
            $request->input('sort_type')
        );

        return response()->json($result);
    }

    /**
     * @OA\Get(
     *     path="/categories/{categoryId}/products/total",
     *     summary="Kateqoriya üzrə məhsul sayı",
     *     description="Verilmiş kateqoriyadakı ümumi məhsul sayını qaytarır.",
     *     operationId="getCategoryProductTotal",
     *     tags={"Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/lang"),
     *     @OA\Parameter(
     *         name="categoryId",
     *         in="path",
     *         required=true,
     *         description="1688 kateqoriya ID-si",
     *         @OA\Schema(type="integer", example=71)
     *     ),
     *     @OA\Response(response=200, description="Məhsul sayı"),
     *     @OA\Response(response=401, description="Token etibarsızdır"),
     *     @OA\Response(response=403, description="Kateqoriya icazəsi yoxdur"),
     *     @OA\Response(response=429, description="Rate limit aşılıb")
     * )
     */
    public function categoryProductTotal(string $categoryId, Request $request): JsonResponse
    {
        // Kateqoriya icazəsi middleware-də yoxlanılır
        $result = $this->ali1688->getProductTotal(
            config('services.ali1688.offer_pool_id'),
            $categoryId
        );

        return response()->json($result);
    }

    /**
     * @OA\Get(
     *     path="/products/{offerId}",
     *     summary="Məhsul detalları",
     *     description="Konkret məhsulun tam məlumatlarını qaytarır: qiymət, şəkillər, variantlar.",
     *     operationId="getProductDetail",
     *     tags={"Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/lang"),
     *     @OA\Parameter(
     *         name="offerId",
     *         in="path",
     *         required=true,
     *         description="1688 məhsul ID-si",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="spec_id",
     *         in="query",
     *         description="Variant (SKU) ID-si",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="country",
     *         in="query",
     *         description="Ölkə kodu",
     *         @OA\Schema(type="string", default="AZ")
     *     ),
     *     @OA\Response(response=200, description="Məhsul detalları"),
     *     @OA\Response(response=401, description="Token etibarsızdır"),
     *     @OA\Response(response=429, description="Rate limit aşılıb")
     * )
     */
    public function productDetail(int $offerId, Request $request): JsonResponse
    {
        $result = $this->ali1688->getOfferDetail(
            $offerId,
            $request->input('spec_id'),
            $request->input('country', 'AZ')
        );

        // Freight məlumatını arxa tərəfdə çək və response-a əlavə et
        try {
            $freightResult = $this->ali1688->estimateFreight($offerId);

            if (isset($freightResult['result']['result'])) {
                $result['freight'] = $freightResult['result']['result'];
            }
        } catch (\Exception $e) {
            // Freight alınmasa əsas response-u pozmur
        }

        return response()->json($result);
    }

    /**
     * @OA\Get(
     *     path="/categories",
     *     summary="İcazəli kateqoriyalar",
     *     description="Partnerin icazəli kateqoriyalarının siyahısını qaytarır.",
     *     operationId="getCategories",
     *     tags={"Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/lang"),
     *     @OA\Response(
     *         response=200,
     *         description="Kateqoriya siyahısı",
     *         @OA\JsonContent(type="array", @OA\Items(
     *             @OA\Property(property="category_id", type="integer"),
     *             @OA\Property(property="translated_name", type="string"),
     *             @OA\Property(property="chinese_name", type="string"),
     *             @OA\Property(property="parent_category_id", type="integer"),
     *             @OA\Property(property="leaf", type="boolean")
     *         ))
     *     ),
     *     @OA\Response(response=401, description="Token etibarsızdır")
     * )
     */
    public function categories(Request $request): JsonResponse
    {
        $partner = $request->input('_partner');

        $categories = $partner->allowedCategories()
            ->select('categories_1688.category_id', 'categories_1688.chinese_name', 'categories_1688.translated_name', 'categories_1688.parent_category_id', 'categories_1688.leaf')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/account/info",
     *     summary="Hesab məlumatları",
     *     description="Partnerin hesab məlumatlarını, limitlərini və icazələrini qaytarır.",
     *     operationId="getAccountInfo",
     *     tags={"Account"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/lang"),
     *     @OA\Response(response=200, description="Hesab məlumatları"),
     *     @OA\Response(response=401, description="Token etibarsızdır")
     * )
     */
    public function accountInfo(Request $request): JsonResponse
    {
        $partner = $request->input('_partner');

        return response()->json([
            'success' => true,
            'data' => [
                'company_name' => $partner->company_name,
                'plan' => $partner->plan?->display_name,
                'status' => $partner->status,
                'limits' => [
                    'rpm' => $partner->rpm_limit,
                    'daily' => $partner->daily_limit,
                    'monthly' => $partner->monthly_limit,
                    'max_concurrent' => $partner->max_concurrent,
                ],
                'allowed_categories_count' => $partner->allowedCategories()->count(),
                'allowed_endpoints' => $partner->allowedEndpoints()->pluck('endpoint'),
            ],
        ]);
    }

    // ==================== ORDER ENDPOINTS ====================

    /**
     * @OA\Post(
     *     path="/orders",
     *     summary="Sifariş yarat",
     *     description="1688-dən sifariş yaradır. Balans yoxlanılır, uğurlu olsa məbləğ balansdan düşür.",
     *     operationId="createOrder",
     *     tags={"Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/lang"),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"flow","address","items"},
     *             @OA\Property(property="flow", type="string", enum={"bigcfenxiao","bigcpifa"}, example="bigcpifa", description="bigcfenxiao=B2C, bigcpifa=B2B"),
     *             @OA\Property(property="address", type="object",
     *                 required={"fullName","mobile","provinceText","cityText","address"},
     *                 @OA\Property(property="fullName", type="string", example="Test User"),
     *                 @OA\Property(property="mobile", type="string", example="13800138000"),
     *                 @OA\Property(property="phone", type="string", example="13800138000"),
     *                 @OA\Property(property="postCode", type="string", example="string"),
     *                 @OA\Property(property="provinceText", type="string", example="浙江省"),
     *                 @OA\Property(property="cityText", type="string", example="杭州市"),
     *                 @OA\Property(property="areaText", type="string", example="滨江区"),
     *                 @OA\Property(property="address", type="string", example="网商路699号"),
     *                 @OA\Property(property="districtText", type="string", example="string")
     *             ),
     *             @OA\Property(property="items", type="array",
     *                 @OA\Items(
     *                     required={"offerId","quantity"},
     *                     @OA\Property(property="offerId", type="integer", example=1000000749350),
     *                     @OA\Property(property="quantity", type="integer", example=1),
     *                     @OA\Property(property="specId", type="string", example="2ea1ac0451fb8db8492ca6205199b47a")
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", nullable=true, example="string", description="Satıcıya mesaj")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Sifariş yaradıldı və ödəniş hazırlandı",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="order_id", type="string"),
     *             @OA\Property(property="out_order_id", type="string"),
     *             @OA\Property(property="total_amount", type="number", description="Məhsul cəmi (CNY)"),
     *             @OA\Property(property="post_fee", type="number", description="Shipping (CNY)"),
     *             @OA\Property(property="amount_usd", type="number", description="Balansdan düşülən məbləğ (USD)"),
     *             @OA\Property(property="exchange_rate", type="number", description="İstifadə olunan CNY→USD məzənnəsi"),
     *             @OA\Property(property="payment", type="object", description="Protocol Pay hazırlıq cavabı")
     *         )
     *     ),
     *     @OA\Response(response=400, description="1688 API xətası"),
     *     @OA\Response(response=403, description="Balans kifayət etmir"),
     *     @OA\Response(response=422, description="Validasiya xətası"),
     *     @OA\Response(response=429, description="Rate limit aşılıb")
     * )
     */
    public function createOrder(Request $request): JsonResponse
    {
        $request->validate([
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
            'items.*.specId' => 'nullable|string',
            'message' => 'nullable|string',
        ]);

        $partner = $request->input('_partner');

        // Sandbox mode — mock cavab qaytar
        if ($request->input('_is_sandbox')) {
            return $this->sandboxCreateOrder($request);
        }

        // CNY → USD məzənnəsini al
        $cnyToUsd = Currency::getRate('CNY', 'USD');
        if (!$cnyToUsd) {
            return response()->json([
                'status' => 500,
                'message' => 'Valyuta məzənnəsi tapılmadı. Əvvəlcə currency:update əmrini işlədin.',
            ], 500);
        }

        $outOrderId = 'PZ-' . $partner->id . '-' . Str::upper(Str::random(8));

        try {
            $result = $this->ali1688->createOrder(
                $request->input('flow'),
                $outOrderId,
                $request->input('address'),
                $request->input('items'),
                'y',
                $request->input('message')
            );

            // 1688 uğurlu cavab verdisə
            if (isset($result['result']) && ($result['result']['success'] ?? false)) {
                $orderId = (string) ($result['result']['orderId'] ?? '');
                $totalSuccessAmount = $result['result']['totalSuccessAmount'] ?? 0;
                $postFee = $result['result']['postFee'] ?? 0;
                $totalCny = (float) ($totalSuccessAmount / 100); // fen -> yuan (shipping daxil)
                $shippingFeeCny = (float) ($postFee / 100);
                $productCny = $totalCny - $shippingFeeCny; // yalnız məhsul

                // CNY → USD çevir (totalCny artıq shipping daxildir)
                $amountUsd = round($totalCny * $cnyToUsd, 2);

                // Balans yoxla
                if (!$partner->canPlaceOrder($amountUsd)) {
                    // Balans çatmır — sifarişi 1688-dən ləğv et
                    $this->ali1688->cancelOrder((int) $orderId, 'Insufficient balance');

                    return response()->json([
                        'status' => 403,
                        'message' => __('api.insufficient_balance'),
                        'required' => $amountUsd,
                        'available' => $partner->availableBalance(),
                    ], 403);
                }

                // Ödəniş hazırla (Protocol Pay) — totalSuccessAmount artıq fen-dədir
                $payAmountFen = (int) $totalSuccessAmount;
                $paymentResult = $this->ali1688->preparePayment((int) $orderId, $payAmountFen);

                // Payment uğursuz → 1688-dən ləğv et, DB-yə yazmadan qaytar
                if (empty($paymentResult['success'])) {
                    $this->ali1688->cancelOrder((int) $orderId, 'Payment failed');

                    $code = $paymentResult['code'] ?? 'UNKNOWN';
                    $message = __("api.payment_error_{$code}") !== "api.payment_error_{$code}"
                        ? __("api.payment_error_{$code}")
                        : __('api.payment_failed');

                    return response()->json([
                        'status' => 400,
                        'message' => $message,
                        'error_code' => $code,
                        'raw_1688' => $paymentResult,
                    ], 400);
                }

                // Payment uğurlu → DB-yə yaz + balansdan düş
                DB::transaction(function () use ($partner, $productCny, $shippingFeeCny, $amountUsd, $orderId, $outOrderId, $request) {
                    Order::create([
                        'partner_id' => $partner->id,
                        'order_id' => $orderId,
                        'out_order_id' => $outOrderId,
                        'status' => 'waitsellersend',
                        'products' => null,
                        'total_amount' => $productCny,
                        'post_fee' => $shippingFeeCny,
                        'flow' => $request->input('flow'),
                        'address' => $request->input('address'),
                        'message' => $request->input('message'),
                    ]);

                    $partner->chargeForOrder($amountUsd);

                    Transaction::create([
                        'partner_id' => $partner->id,
                        'amount' => -$amountUsd,
                        'type' => 'charge',
                        'description' => __('api.order_charge', ['id' => $outOrderId]),
                        'reference_type' => 'order',
                        'reference_id' => $orderId,
                        'balance_after' => $partner->availableBalance(),
                    ]);
                });

                return response()->json([
                    'success' => true,
                    'order_id' => $orderId,
                    'out_order_id' => $outOrderId,
                    'total_amount' => $productCny,
                    'post_fee' => $shippingFeeCny,
                    'total_cny' => $totalCny,
                    'amount_usd' => $amountUsd,
                    'exchange_rate' => $cnyToUsd,
                ]);
            }

            // 1688 xəta qaytardı
            return response()->json([
                'status' => 400,
                'message' => $result['error_message'] ?? $result['errorMessage'] ?? __('api.order_failed'),
                'raw' => $result,
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => __('api.order_error', ['error' => $e->getMessage()]),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/orders",
     *     summary="Sifariş siyahısı",
     *     description="Partnerin öz sifarişlərini DB-dən qaytarır. Statuslar hər 5 dəqiqə 1688 ilə sinxronlaşır.",
     *     operationId="getOrders",
     *     tags={"Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/lang"),
     *     @OA\Parameter(name="status", in="query", description="Sifariş statusu: waitbuyerpay, waitsellersend, waitbuyerreceive, confirm_goods, success, cancel, terminated", @OA\Schema(type="string", enum={"waitbuyerpay","waitsellersend","waitbuyerreceive","confirm_goods","success","cancel","terminated"})),
     *     @OA\Parameter(name="search", in="query", description="Order ID və ya PZ ID ilə axtarış", @OA\Schema(type="string")),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20, maximum=50)),
     *     @OA\Response(response=200, description="Sifariş siyahısı (paginated)",
     *         @OA\JsonContent(
     *             @OA\Property(property="current_page", type="integer"),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="order_id", type="string"),
     *                 @OA\Property(property="out_order_id", type="string"),
     *                 @OA\Property(property="status", type="string"),
     *                 @OA\Property(property="total_amount", type="string"),
     *                 @OA\Property(property="post_fee", type="string"),
     *                 @OA\Property(property="products", type="array", nullable=true, @OA\Items(type="object")),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )),
     *             @OA\Property(property="last_page", type="integer"),
     *             @OA\Property(property="per_page", type="integer"),
     *             @OA\Property(property="total", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Token etibarsızdır"),
     *     @OA\Response(response=429, description="Rate limit aşılıb")
     * )
     */
    public function orders(Request $request): JsonResponse
    {
        $partner = $request->input('_partner');
        $query = Order::where('partner_id', $partner->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('order_id', 'like', "%{$s}%")
                  ->orWhere('out_order_id', 'like', "%{$s}%");
            });
        }

        $page = max(1, (int) $request->input('page', 1));
        $perPage = min((int) $request->input('per_page', 20), 50);

        $orders = $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json($orders);
    }

    /**
     * @OA\Get(
     *     path="/orders/{orderId}",
     *     summary="Sifariş detalları",
     *     description="Konkret sifarişin tam məlumatlarını qaytarır: status, məhsullar, qiymətlər, tracking.",
     *     operationId="getOrderDetail",
     *     tags={"Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/lang"),
     *     @OA\Parameter(name="orderId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Sifariş detalları"),
     *     @OA\Response(response=401, description="Token etibarsızdır"),
     *     @OA\Response(response=429, description="Rate limit aşılıb")
     * )
     */
    public function orderDetail(int $orderId): JsonResponse
    {
        $result = $this->ali1688->getOrderDetail($orderId);

        // DB-dəki order-i yenilə
        if (isset($result['result'])) {
            $r = $result['result'];
            $status1688 = $r['baseInfo']['status'] ?? null;
            $productItems = $r['productItems'] ?? null;

            $order = Order::where('order_id', (string) $orderId)->first();
            if ($order) {
                $update = [];

                // Status yenilə
                if ($status1688) {
                    $validStatuses = ['waitbuyerpay', 'waitsellersend', 'waitbuyerreceive', 'confirm_goods', 'success', 'cancel', 'terminated'];
                    if (in_array($status1688, $validStatuses)) {
                        $update['status'] = $status1688;
                    }
                }

                // Products yenilə (null idisə və ya hər zaman)
                if ($productItems) {
                    $update['products'] = collect($productItems)->map(fn($item) => [
                        'productID' => $item['productID'] ?? null,
                        'name' => $item['name'] ?? null,
                        'price' => $item['price'] ?? 0,
                        'quantity' => $item['quantity'] ?? 0,
                        'itemAmount' => $item['itemAmount'] ?? 0,
                        'status' => $item['status'] ?? null,
                        'statusStr' => $item['statusStr'] ?? null,
                        'skuID' => $item['skuID'] ?? null,
                        'specId' => $item['specId'] ?? null,
                        'unit' => $item['unit'] ?? null,
                        'productImgUrl' => $item['productImgUrl'][1] ?? $item['productImgUrl'][0] ?? null,
                        'skuInfos' => $item['skuInfos'] ?? [],
                    ])->toArray();
                }

                // Total amount və post fee yenilə
                if (isset($r['baseInfo']['sumProductPayment'])) {
                    $update['total_amount'] = $r['baseInfo']['sumProductPayment'];
                }
                if (isset($r['baseInfo']['shippingFee'])) {
                    $update['post_fee'] = $r['baseInfo']['shippingFee'];
                }

                if (!empty($update)) {
                    $order->update($update);
                }
            }
        }

        return response()->json($result);
    }

    /**
     * @OA\Delete(
     *     path="/orders/{orderId}",
     *     summary="Sifarişi ləğv et",
     *     description="Ödənilməmiş sifarişi ləğv edir.",
     *     operationId="cancelOrder",
     *     tags={"Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/lang"),
     *     @OA\Parameter(name="orderId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="reason", in="query", description="Ləğv səbəbi", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Sifariş ləğv edildi"),
     *     @OA\Response(response=401, description="Token etibarsızdır"),
     *     @OA\Response(response=429, description="Rate limit aşılıb")
     * )
     */
    public function cancelOrder(int $orderId, Request $request): JsonResponse
    {
        if ($request->input('_is_sandbox')) {
            return response()->json(['success' => true, 'sandbox' => true, 'message' => 'Order cancelled (sandbox)']);
        }

        $result = $this->ali1688->cancelOrder(
            $orderId,
            $request->input('reason')
        );

        if (isset($result['success'])){
            Order::where('order_id', (string) $orderId)->update(['status' => 'cancel']);
        }

        return response()->json($result);
    }

    /**
     * @OA\Post(
     *     path="/orders/{orderId}/confirm",
     *     summary="Malı qəbul et",
     *     description="Sifarişi 'alındı' olaraq işarələyir. Geri qaytarıla bilməz!",
     *     operationId="confirmReceipt",
     *     tags={"Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/lang"),
     *     @OA\Parameter(name="orderId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Mal qəbul edildi"),
     *     @OA\Response(response=401, description="Token etibarsızdır"),
     *     @OA\Response(response=429, description="Rate limit aşılıb")
     * )
     */
    public function confirmReceipt(int $orderId, Request $request): JsonResponse
    {
        if ($request->input('_is_sandbox')) {
            return response()->json(['success' => true, 'sandbox' => true, 'message' => 'Receipt confirmed (sandbox)']);
        }

        $result = $this->ali1688->confirmReceipt($orderId);

        if (isset($result['success'])) {
            Order::where('order_id', (string) $orderId)->update(['status' => 'success']);
        }

        return response()->json($result);
    }




    /**
     * @OA\Post(
     *     path="/orders/{orderId}/refund",
     *     summary="Refund yarat",
     *     description="Ödənilmiş sifariş üçün geri qaytarma müraciəti yaradır. Məbləğ verilməyibsə order detalından avtomatik hesablanır.",
     *     operationId="createRefund",
     *     tags={"Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/lang"),
     *     @OA\Parameter(name="orderId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"entry_ids"},
     *             @OA\Property(property="entry_ids", type="array", @OA\Items(type="integer"), description="Geri qaytarılacaq subItemID-lər"),
     *             @OA\Property(property="reason_id", type="integer", nullable=true, description="Refund səbəb ID-si (default 20006)"),
     *             @OA\Property(property="description", type="string", nullable=true),
     *             @OA\Property(property="goods_status", type="string", enum={"refundWaitSellerSend","refundWaitBuyerReceive","refundBuyerReceived","aftersaleBuyerNotReceived","aftersaleBuyerReceived"}, nullable=true),
     *             @OA\Property(property="apply_payment", type="integer", nullable=true, description="Məhsul məbləği fen-lə (CNY × 100). Verilməyibsə avtomatik hesablanır."),
     *             @OA\Property(property="apply_carriage", type="integer", nullable=true, description="Çatdırılma məbləği fen-lə. Verilməyibsə avtomatik hesablanır.")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Refund müraciəti yaradıldı"),
     *     @OA\Response(response=401, description="Token etibarsızdır"),
     *     @OA\Response(response=422, description="Validasiya xətası"),
     *     @OA\Response(response=429, description="Rate limit aşılıb")
     * )
     */
    public function createRefund(int $orderId, Request $request): JsonResponse
    {
        if ($request->input('_is_sandbox')) {
            return response()->json(['success' => true, 'sandbox' => true, 'message' => 'Refund created (sandbox)']);
        }

        $request->validate([
            'entry_ids' => 'required|array|min:1',
            'entry_ids.*' => 'integer',
            'reason_id' => 'nullable|integer',
            'description' => 'nullable|string',
            'goods_status' => 'nullable|string|in:refundWaitSellerSend,refundWaitBuyerReceive,refundBuyerReceived,aftersaleBuyerNotReceived,aftersaleBuyerReceived',
            'apply_payment' => 'nullable|integer|min:0',
            'apply_carriage' => 'nullable|integer|min:0',
        ]);

        $entryIds      = array_map('intval', $request->input('entry_ids'));
        $reasonId      = (int) $request->input('reason_id', 20006);
        $description   = (string) $request->input('description', __('api.refund_default_reason'));
        $goodsStatus   = (string) $request->input('goods_status', 'refundWaitSellerSend');
        $applyPayment  = $request->input('apply_payment');
        $applyCarriage = $request->input('apply_carriage');

        // Məbləğlər verilməyibsə → order detalından hesabla (yalnız seçilmiş entry-lər üçün)
        if ($applyPayment === null || $applyCarriage === null) {
            $detail = $this->ali1688->getOrderDetail($orderId);
            $items = $detail['result']['productItems'] ?? [];

            if (empty($items)) {
                return response()->json([
                    'status' => 400,
                    'message' => __('api.order_not_found'),
                    'raw' => $detail,
                ], 400);
            }

            $itemTotal = 0;
            $shippingTotal = 0;
            foreach ($items as $it) {
                if (!in_array((int) ($it['subItemID'] ?? 0), $entryIds, true)) {
                    continue;
                }
                $itemTotal     += (int) round(((float) ($it['price'] ?? 0)) * (int) ($it['quantity'] ?? 1) * 100);
                $shippingTotal += (int) round(((float) ($it['sharePostage'] ?? 0)) * 100);
            }

            if ($applyPayment === null)  $applyPayment  = $itemTotal;
            if ($applyCarriage === null) $applyCarriage = $shippingTotal;
        }

        $result = $this->ali1688->createRefund(
            $orderId,
            $entryIds,
            (int) $applyPayment,
            (int) $applyCarriage,
            $reasonId,
            $description,
            $goodsStatus
        );

        $refundId = $result['result']['result']['refundId'] ?? null;
        if ($refundId) {
            $partner = $request->user();
            $totalFen = (int) $applyPayment + (int) $applyCarriage;
            $totalCny = $totalFen / 100;
            $cnyToUsd = Currency::getRate('CNY', 'USD') ?? 0.145;
            $refundUsd = round($totalCny * $cnyToUsd, 2);

            DB::transaction(function () use ($partner, $refundUsd, $orderId, $refundId, $description) {
                Order::where('order_id', (string) $orderId)->update(['status' => 'terminated']);

                if ($partner && $refundUsd > 0) {
                    if ($partner->payment_model === 'deposit') {
                        $partner->deposit_balance += $refundUsd;
                    } else {
                        $partner->debit_used = max(0, $partner->debit_used - $refundUsd);
                    }
                    $partner->outstanding_balance = max(0, $partner->outstanding_balance - $refundUsd);
                    $partner->save();

                    Transaction::create([
                        'partner_id' => $partner->id,
                        'amount' => $refundUsd,
                        'type' => 'refund',
                        'description' => __('api.order_refund', ['id' => $orderId]) . ($description ? " — {$description}" : ''),
                        'reference_type' => 'order',
                        'reference_id' => (string) $orderId,
                        'balance_after' => $partner->availableBalance(),
                    ]);
                }
            });
        }

        return response()->json($result);
    }

    /**
     * Sandbox mode üçün mock order yaradır
     */
    private function sandboxCreateOrder(Request $request): JsonResponse
    {
        $items = $request->input('items', []);
        $totalCny = 0;
        $products = [];

        foreach ($items as $item) {
            $price = round(rand(500, 5000) / 100, 2); // random 5-50 yuan
            $qty = $item['quantity'] ?? 1;
            $itemAmount = round($price * $qty, 2);
            $totalCny += $itemAmount;

            $products[] = [
                'offerId' => $item['offerId'],
                'quantity' => $qty,
                'price' => $price,
                'itemAmount' => $itemAmount,
                'specId' => $item['specId'] ?? null,
            ];
        }

        $shippingFee = round(rand(300, 1500) / 100, 2); // random 3-15 yuan
        $totalWithShipping = round($totalCny + $shippingFee, 2);

        $cnyToUsd = Currency::getRate('CNY', 'USD') ?? 0.145;
        $amountUsd = round($totalWithShipping * $cnyToUsd, 2);

        return response()->json([
            'success' => true,
            'sandbox' => true,
            'order_id' => 'SB' . rand(1000000000000, 9999999999999),
            'out_order_id' => 'PZ-SANDBOX-' . Str::upper(Str::random(8)),
            'total_amount' => $totalCny,
            'post_fee' => $shippingFee,
            'total_cny' => $totalWithShipping,
            'amount_usd' => $amountUsd,
            'exchange_rate' => $cnyToUsd,
            'products' => $products,
        ]);
    }
}
