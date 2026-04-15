<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\PartnerController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\PartnerApiController;
use App\Http\Controllers\Api\ApiLogController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\SupportController;
use Illuminate\Support\Facades\Route;

// Admin auth
Route::post('/admin/login', [AuthController::class, 'adminLogin']);

// Partner auth
Route::post('/partner/login', [AuthController::class, 'partnerLogin']);

// Currencies (public)
Route::get('/currencies', [CurrencyController::class, 'index']);

// Admin protected routes
Route::middleware(['auth:sanctum', 'is.admin'])->group(function () {
    // Admin auth
    Route::get('/admin/me', [AuthController::class, 'adminMe']);
    Route::post('/admin/logout', [AuthController::class, 'logout']);

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // Reports
    Route::get('/reports', [ReportsController::class, 'index']);

    // Partner CRUD
    Route::get('/partners', [PartnerController::class, 'index']);
    Route::post('/partners', [PartnerController::class, 'store']);
    Route::get('/partners/{id}', [PartnerController::class, 'show']);
    Route::put('/partners/{id}', [PartnerController::class, 'update']);
    Route::delete('/partners/{id}', [PartnerController::class, 'destroy']);

    // Partner actions
    Route::put('/partners/{id}/password', [PartnerController::class, 'updatePassword']);
    Route::post('/partners/{id}/deposit', [PartnerController::class, 'addDeposit']);
    Route::put('/partners/{id}/outstanding', [PartnerController::class, 'updateOutstanding']);
    Route::get('/partners/{id}/can-order', [PartnerController::class, 'checkOrderAbility']);
    Route::put('/partners/{id}/permissions', [PartnerController::class, 'updatePermissions']);
    Route::post('/partners/{id}/reset-limits', [PartnerController::class, 'resetLimits']);
    Route::get('/partners/{id}/orders', [PartnerController::class, 'partnerOrders']);
    Route::get('/partners/{id}/refunds', [PartnerController::class, 'partnerRefunds']);

    // Plans CRUD
    Route::get('/plans', [PlanController::class, 'index']);
    Route::post('/plans', [PlanController::class, 'store']);
    Route::get('/plans/{id}', [PlanController::class, 'show']);
    Route::put('/plans/{id}', [PlanController::class, 'update']);
    Route::delete('/plans/{id}', [PlanController::class, 'destroy']);

    // Tokens
    Route::get('/tokens', [TokenController::class, 'index']);
    Route::post('/tokens', [TokenController::class, 'store']);
    Route::put('/tokens/{id}/revoke', [TokenController::class, 'revoke']);
    Route::post('/tokens/{id}/rotate', [TokenController::class, 'rotate']);
    Route::post('/tokens/batch-revoke', [TokenController::class, 'batchRevoke']);

    // Transactions
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/transactions/stats', [TransactionController::class, 'stats']);

    // API Logs
    Route::get('/api-logs', [ApiLogController::class, 'index']);

    // Orders (admin - all orders)
    Route::get('/orders', [PartnerController::class, 'allOrders']);

    // Categories
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/stats', [CategoryController::class, 'stats']);
    Route::post('/categories/sync', [CategoryController::class, 'sync']);
    Route::post('/categories/sync-product-counts', [CategoryController::class, 'syncProductCounts']);
    Route::get('/categories/{id}', [CategoryController::class, 'show']);
    Route::put('/categories/{id}/status', [CategoryController::class, 'updateStatus']);

    // Support Tickets
    Route::get('/support-tickets', [SupportController::class, 'index']);
    Route::get('/support-tickets/stats', [SupportController::class, 'stats']);
    Route::put('/support-tickets/{id}/reply', [SupportController::class, 'reply']);
    Route::post('/support-tickets/{id}/mark-read', [SupportController::class, 'markRead']);
});

// Partner portal protected routes
Route::middleware(['auth:sanctum', 'is.partner'])->group(function () {
    Route::get('/partner/me', [AuthController::class, 'partnerMe']);
    Route::post('/partner/logout', [AuthController::class, 'logout']);
    Route::get('/partner/tokens', [TokenController::class, 'myTokens']);
    Route::get('/partner/transactions', [TransactionController::class, 'myTransactions']);
    Route::get('/partner/api-logs', [ApiLogController::class, 'myLogs']);
    Route::get('/partner/orders', [PartnerController::class, 'myOrders']);
    Route::get('/partner/support-tickets', [SupportController::class, 'myTickets']);
    Route::get('/partner/support-tickets/unread', [SupportController::class, 'myUnreadCount']);
    Route::post('/partner/support-tickets', [SupportController::class, 'store']);
    Route::post('/partner/support-tickets/{id}/reply', [SupportController::class, 'partnerReply']);
    Route::post('/partner/support-tickets/{id}/mark-read', [SupportController::class, 'markReadPartner']);
});

// ==================== PARTNER API v1 ====================
// Partnerlərin öz tokenləri ilə istifadə edəcəyi API
Route::prefix('v1')->middleware('partner.api')->group(function () {
    // Categories
    Route::get('/categories', [PartnerApiController::class, 'categories']);
    Route::get('/categories/{categoryId}/products', [PartnerApiController::class, 'categoryProducts']);
    Route::get('/categories/{categoryId}/products/total', [PartnerApiController::class, 'categoryProductTotal']);

    // Products
    Route::get('/products/{offerId}', [PartnerApiController::class, 'productDetail']);

    // Orders
    Route::post('/orders', [PartnerApiController::class, 'createOrder']);
    Route::get('/orders', [PartnerApiController::class, 'orders']);
    Route::get('/orders/{orderId}', [PartnerApiController::class, 'orderDetail']);
    Route::delete('/orders/{orderId}', [PartnerApiController::class, 'cancelOrder']);
    Route::post('/orders/{orderId}/confirm', [PartnerApiController::class, 'confirmReceipt']);
    Route::post('/orders/{orderId}/refund', [PartnerApiController::class, 'createRefund']);

    // Account
    Route::get('/account/info', [PartnerApiController::class, 'accountInfo']);
});
