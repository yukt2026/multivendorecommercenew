<?php

use App\Http\Controllers\Api\V1\StorefrontApiController;
use Illuminate\Support\Facades\Route;

Route::get('/config', [StorefrontApiController::class, 'config'])->name('config');
Route::get('/health', [\App\Http\Controllers\HealthCheckController::class, 'check'])->name('api.health');

// AI Semantic Search Query Parsing & Recommendations
Route::get('/search/parse', [\App\Http\Controllers\Storefront\SearchController::class, 'parseQueryApi'])->name('api.search.parse');
Route::get('/products/{product}/recommendations', function (\App\Models\Product $product, \App\Services\AI\Contracts\RecommendationServiceInterface $recService) {
    return response()->json([
        'similar' => $recService->getSimilarProducts($product),
        'frequently_bought_together' => $recService->getFrequentlyBoughtTogether($product),
    ]);
})->name('api.products.recommendations');
Route::get('/recommendations/personalized', function (\Illuminate\Http\Request $request, \App\Services\AI\Contracts\RecommendationServiceInterface $recService) {
    return response()->json([
        'personalized' => $recService->getPersonalizedRecommendations($request->user()),
    ]);
})->name('api.recommendations.personalized');

// Quick Commerce Hyperlocal Discovery & Rider Telemetry
Route::get('/quick/stores', [\App\Http\Controllers\Storefront\QuickCommerceController::class, 'nearbyStoresApi'])->name('api.quick.stores');
Route::post('/rider/location', [\App\Http\Controllers\Rider\RiderController::class, 'updateLocation'])->name('api.rider.location');

// OneStall Cargo Webhook Receiver
Route::post('/cargo/webhook', [\App\Http\Controllers\Api\CargoWebhookController::class, 'handle'])->name('cargo.webhook');

// Connect My Shop — Public Integration Endpoints & Webhooks
Route::prefix('integrations')->middleware(['throttle:60,1'])->group(function () {
    Route::middleware('integration.auth')->group(function () {
        Route::get('/products', [\App\Http\Controllers\Api\IntegrationController::class, 'listProducts'])->name('integrations.products.list');
        Route::post('/products', [\App\Http\Controllers\Api\IntegrationController::class, 'upsertProduct'])->name('integrations.products.upsert');
        Route::get('/inventory', [\App\Http\Controllers\Api\IntegrationController::class, 'getInventory'])->name('integrations.inventory.list');
        Route::post('/inventory/adjust', [\App\Http\Controllers\Api\IntegrationController::class, 'adjustInventory'])->name('integrations.inventory.adjust');
        Route::get('/orders', [\App\Http\Controllers\Api\IntegrationController::class, 'listOrders'])->name('integrations.orders.list');
        Route::post('/orders/{id}/status', [\App\Http\Controllers\Api\IntegrationController::class, 'updateOrderStatus'])->name('integrations.orders.status');
    });

    // Inward Webhooks
    Route::post('/woocommerce/webhook/{connection}', [\App\Http\Controllers\Api\IntegrationController::class, 'woocommerceWebhook'])->name('integrations.woocommerce.webhook');
    Route::post('/shopify/webhook/{connection}', [\App\Http\Controllers\Api\IntegrationController::class, 'shopifyWebhook'])->name('integrations.shopify.webhook');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (\Illuminate\Http\Request $request) {
        return $request->user();
    })->name('user');
});
