<?php
Route::prefix('1688')->group(function () {

    // Məhsul API-ləri
    Route::get('/products', [Ali1688Controller::class, 'products']);
    Route::get('/products/total', [Ali1688Controller::class, 'productTotal']);
    Route::get('/products/{offerId}', [Ali1688Controller::class, 'productDetail']);
    Route::post('/products/{offerId}/freight', [Ali1688Controller::class, 'estimateFreight']);

    // Sifariş API-ləri
    Route::get('/orders', [Ali1688Controller::class, 'orders']);
    Route::post('/orders', [Ali1688Controller::class, 'createOrder']);
    Route::get('/orders/{orderId}', [Ali1688Controller::class, 'orderDetail']);
    Route::delete('/orders/{orderId}', [Ali1688Controller::class, 'cancelOrder']);
    Route::post('/orders/{orderId}/confirm', [Ali1688Controller::class, 'confirmReceipt']);

    // Ödəniş API-ləri
    Route::get('/orders/{orderId}/payment-url', [Ali1688Controller::class, 'paymentUrl']);
    Route::post('/orders/prepare-payment', [Ali1688Controller::class, 'preparePayment']);

    // Geri qaytarma
    Route::post('/orders/{orderId}/refund', [Ali1688Controller::class, 'createRefund']);

    // Logistika
    Route::get('/logistics/out-order-id', [Ali1688Controller::class, 'getOutOrderId']);

    // Kateqoriya API-ləri
    Route::get('/categories', [Ali1688Controller::class, 'categories']);
    Route::get('/categories/{categoryId}/products', [Ali1688Controller::class, 'categoryProducts']);
    Route::get('/categories/{categoryId}/products/total', [Ali1688Controller::class, 'categoryProductTotal']);
    Route::get('/categories/{categoryId}/attributes', [Ali1688Controller::class, 'attributeMapping']);
    Route::get('/products/{offerId}/selling-points', [Ali1688Controller::class, 'sellingPoints']);
    Route::post('/products/follow', [Ali1688Controller::class, 'followProducts']);

    // OAuth / Token API-ləri
    Route::get('/auth/authorize', [Ali1688Controller::class, 'redirectToAuth']);
    Route::get('/auth/callback', [Ali1688Controller::class, 'callback']);
    Route::get('/auth/status', [Ali1688Controller::class, 'tokenStatus']);
    Route::post('/auth/refresh', [Ali1688Controller::class, 'refreshToken']);
});
