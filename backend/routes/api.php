<?php

use App\Http\Controllers\Client\AuthController;
use App\Http\Controllers\Client\CartController;
use App\Http\Controllers\Client\CatalogController;
use App\Http\Controllers\Client\OrderController;
use App\Http\Controllers\Client\OrganizationController;
use Illuminate\Support\Facades\Route;

/*
 * Клиентский API мобильного приложения: /api/client/v1/...
 * Описание: docs/02-arhitektura/05-api.md. Закупочные данные не отдаются ни одним методом.
 */
Route::prefix('client/v1')->middleware('throttle:client-api')->group(function () {
    // Без входа
    Route::get('regions', [CatalogController::class, 'regions']);
    Route::get('categories', [CatalogController::class, 'categories']);
    Route::middleware('region')->group(function () {
        Route::get('categories/{category}/filters', [CatalogController::class, 'filters']);
        Route::get('products', [CatalogController::class, 'products']);
        Route::get('products/{product}', [CatalogController::class, 'product']);
        Route::get('pickup-points', [CatalogController::class, 'pickupPoints']);
    });
    Route::get('products/{product}/convert', [CatalogController::class, 'convert']);

    Route::post('auth/otp/request', [AuthController::class, 'requestCode'])->middleware('throttle:otp');
    Route::post('auth/otp/verify', [AuthController::class, 'verifyCode'])->middleware('throttle:10,1');

    // После входа
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::patch('me', [AuthController::class, 'updateMe']);
        Route::post('me/push-tokens', [AuthController::class, 'registerPushToken']);

        Route::get('organizations', [OrganizationController::class, 'index']);
        Route::get('organizations/lookup', [OrganizationController::class, 'lookup'])->middleware('throttle:30,1');
        Route::post('organizations', [OrganizationController::class, 'store']);

        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{order}', [OrderController::class, 'show']);
        Route::get('orders/{order}/offers', [OrderController::class, 'offers']);
        Route::post('orders/{order}/offers/{offer}/accept', [OrderController::class, 'accept']);
        Route::post('orders/{order}/offers/{offer}/request-changes', [OrderController::class, 'requestChanges']);
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);
        Route::post('orders/{order}/payment-notice', [OrderController::class, 'paymentNotice']);
        Route::post('orders/{order}/repeat', [OrderController::class, 'repeat']);

        // В контексте организации (заголовок X-Organization)
        Route::middleware('organization')->group(function () {
            Route::get('organization', [OrganizationController::class, 'show']);
            Route::patch('organization', [OrganizationController::class, 'update']);
            Route::get('organization/members', [OrganizationController::class, 'members']);
            Route::post('organization/members', [OrganizationController::class, 'invite']);
            Route::delete('organization/members/{memberId}', [OrganizationController::class, 'removeMember']);
            Route::get('addresses', [OrganizationController::class, 'addresses']);
            Route::post('addresses', [OrganizationController::class, 'storeAddress']);
            Route::patch('addresses/{addressId}', [OrganizationController::class, 'updateAddress']);
            Route::delete('addresses/{addressId}', [OrganizationController::class, 'deleteAddress']);

            // Корзина и оформление зависят и от региона (заголовок X-Region)
            Route::middleware('region')->group(function () {
                Route::get('cart', [CartController::class, 'show']);
                Route::post('cart/items', [CartController::class, 'add']);
                Route::patch('cart/items/{itemId}', [CartController::class, 'update']);
                Route::delete('cart/items/{itemId}', [CartController::class, 'remove']);
                Route::post('orders', [OrderController::class, 'store']);
            });
        });
    });
});
