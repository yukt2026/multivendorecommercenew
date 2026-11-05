<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\SellerAuthController;
use App\Http\Controllers\Seller\DashboardController;
use App\Http\Controllers\Seller\OnboardingController;
use App\Http\Controllers\Seller\ProductController;
use App\Http\Controllers\Seller\StoreController;
use Illuminate\Support\Facades\Route;

// Seller Guest Authentication
Route::middleware('guest')->group(function () {
    Route::get('/login', [SellerAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [SellerAuthController::class, 'login'])->name('login.submit');
    Route::get('/register', [SellerAuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [SellerAuthController::class, 'register'])->name('register.submit');
});

// Authenticated Seller Operations
Route::middleware(['auth', 'seller.status'])->group(function () {
    Route::match(['get', 'post'], '/logout', [SellerAuthController::class, 'logout'])->name('logout');

    // Onboarding Wizard Routes
    Route::get('/onboarding', [OnboardingController::class, 'index'])->name('onboarding');
    Route::post('/onboarding/send-phone-otp', [OnboardingController::class, 'sendPhoneOtp'])->name('onboarding.send_otp');
    Route::post('/onboarding/verify-phone-otp', [OnboardingController::class, 'verifyPhoneOtp'])->name('onboarding.verify_otp');
    Route::post('/onboarding/verify-email', [OnboardingController::class, 'verifyEmail'])->name('onboarding.verify_email');
    Route::post('/onboarding/step/2', [OnboardingController::class, 'saveStep2'])->name('onboarding.step2');
    Route::post('/onboarding/step/3', [OnboardingController::class, 'saveStep3'])->name('onboarding.step3');
    Route::post('/onboarding/preview-bank', [OnboardingController::class, 'previewBankVerification'])->name('onboarding.preview_bank');
    Route::post('/onboarding/step/4', [OnboardingController::class, 'saveStep4'])->name('onboarding.step4');
    Route::post('/onboarding/step/5', [OnboardingController::class, 'saveStep5'])->name('onboarding.step5');
    Route::post('/onboarding/step/6', [OnboardingController::class, 'saveStep6'])->name('onboarding.step6');
    Route::post('/onboarding/submit', [OnboardingController::class, 'submit'])->name('onboarding.submit');
    Route::post('/onboarding/resubmit', [OnboardingController::class, 'resubmit'])->name('onboarding.resubmit');

    // Suspended seller notification screen
    Route::get('/suspended', [SellerAuthController::class, 'suspended'])->name('suspended');

    // Active Seller Portal & Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
    Route::get('/chart-data', [DashboardController::class, 'chartData'])->name('chart_data');
    Route::post('/switch-store', [DashboardController::class, 'switchStore'])->name('switch_store');
    Route::post('/store/toggle-status', [DashboardController::class, 'toggleStoreStatus'])->name('store.toggle_status');
    Route::post('/orders/{fulfilmentOrder}/accept', [\App\Http\Controllers\Seller\QuickOrderController::class, 'accept'])->name('orders.accept');
    Route::post('/orders/{fulfilmentOrder}/pack', [\App\Http\Controllers\Seller\QuickOrderController::class, 'pack'])->name('orders.pack');
    Route::post('/orders/{fulfilmentOrder}/ready', [\App\Http\Controllers\Seller\QuickOrderController::class, 'ready'])->name('orders.ready');
    Route::post('/orders/{fulfilmentOrder}/reject', [\App\Http\Controllers\Seller\QuickOrderController::class, 'reject'])->name('orders.reject');
    Route::post('/orders/{fulfilmentOrder}/assign-rider', [\App\Http\Controllers\Seller\QuickOrderController::class, 'assignRider'])->name('orders.assign_rider');
    Route::get('/quick-orders', [\App\Http\Controllers\Seller\QuickOrderController::class, 'liveConsole'])->name('quick.live');
    Route::get('/quick-orders/api/pending', [\App\Http\Controllers\Seller\QuickOrderController::class, 'pendingOrdersApi'])->name('quick.orders_api');
    Route::post('/inventory/{inventory}/quick-restock', [DashboardController::class, 'quickRestock'])->name('inventory.quick_restock');

    // Seller Order Management Console (Blueprint Section 21)
    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Seller\OrderController::class, 'index'])->name('index');
        Route::get('/{fulfilmentOrder}', [\App\Http\Controllers\Seller\OrderController::class, 'show'])->name('show');
        Route::post('/{fulfilmentOrder}/transition', [\App\Http\Controllers\Seller\OrderController::class, 'transition'])->name('transition');
        Route::post('/{fulfilmentOrder}/assign-staff', [\App\Http\Controllers\Seller\OrderController::class, 'assignStaff'])->name('assign_staff');
        Route::get('/{fulfilmentOrder}/packing-slip', [\App\Http\Controllers\Seller\OrderController::class, 'packingSlip'])->name('packing_slip');
        Route::get('/{fulfilmentOrder}/invoice', [\App\Http\Controllers\Seller\OrderController::class, 'invoice'])->name('invoice');
        Route::get('/{fulfilmentOrder}/shipping-label', [\App\Http\Controllers\Seller\OrderController::class, 'shippingLabel'])->name('shipping_label');
    });
    Route::get('/api/orders/pending-count', [\App\Http\Controllers\Seller\OrderController::class, 'pendingCount'])->name('orders.pending_count');

    // My Store Settings & Public Profile Management
    Route::get('/store', [StoreController::class, 'edit'])->name('store.edit');
    Route::put('/store', [StoreController::class, 'update'])->name('store.update');
    Route::match(['get', 'post'], '/store/check-slug', [StoreController::class, 'checkSlug'])->name('store.check_slug');
    Route::get('/store/qr-code', [StoreController::class, 'qrCode'])->name('store.qr_code');

    // Multi-Store Outlets Management & Store-Level Reports
    Route::prefix('stores')->name('stores.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Seller\StoreOutletController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Seller\StoreOutletController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Seller\StoreOutletController::class, 'store'])->name('store');
        Route::get('/reports', [\App\Http\Controllers\Seller\StoreOutletController::class, 'reports'])->name('reports');
    });

    // Staff & Permissions (Blueprint Section 20)
    Route::prefix('staff')->name('staff.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Seller\StoreStaffController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Seller\StoreStaffController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Seller\StoreStaffController::class, 'store'])->name('store');
        Route::put('/{staff}', [\App\Http\Controllers\Seller\StoreStaffController::class, 'update'])->name('update');
        Route::delete('/{staff}', [\App\Http\Controllers\Seller\StoreStaffController::class, 'destroy'])->name('destroy');
    });

    // Connect My Shop Wizard & Channels (/seller/connections)
    Route::prefix('connections')->name('connections.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Seller\ConnectionController::class, 'index'])->name('index');

        // CSV / Excel
        Route::get('/csv', [\App\Http\Controllers\Seller\ConnectionController::class, 'csvIndex'])->name('csv');
        Route::get('/csv/template', [\App\Http\Controllers\Seller\ConnectionController::class, 'csvTemplate'])->name('csv.template');
        Route::post('/csv/upload', [\App\Http\Controllers\Seller\ConnectionController::class, 'csvUpload'])->name('csv.upload');
        Route::post('/csv/{import}/process', [\App\Http\Controllers\Seller\ConnectionController::class, 'csvImport'])->name('csv.import');

        // WooCommerce
        Route::get('/woocommerce', [\App\Http\Controllers\Seller\ConnectionController::class, 'woocommerceIndex'])->name('woocommerce');
        Route::post('/woocommerce/connect', [\App\Http\Controllers\Seller\ConnectionController::class, 'woocommerceConnect'])->name('woocommerce.connect');
        Route::post('/woocommerce/test', [\App\Http\Controllers\Seller\ConnectionController::class, 'woocommerceTest'])->name('woocommerce.test');
        Route::post('/woocommerce/sync', [\App\Http\Controllers\Seller\ConnectionController::class, 'woocommerceSync'])->name('woocommerce.sync');

        // Shopify
        Route::get('/shopify', [\App\Http\Controllers\Seller\ConnectionController::class, 'shopifyIndex'])->name('shopify');
        Route::post('/shopify/connect', [\App\Http\Controllers\Seller\ConnectionController::class, 'shopifyConnect'])->name('shopify.connect');
        Route::post('/shopify/test', [\App\Http\Controllers\Seller\ConnectionController::class, 'shopifyTest'])->name('shopify.test');
        Route::post('/shopify/sync', [\App\Http\Controllers\Seller\ConnectionController::class, 'shopifySync'])->name('shopify.sync');

        // Custom Website API
        Route::get('/custom-api', [\App\Http\Controllers\Seller\ConnectionController::class, 'customApiIndex'])->name('custom_api');
        Route::post('/custom-api/generate', [\App\Http\Controllers\Seller\ConnectionController::class, 'customApiGenerate'])->name('custom_api.generate');
        Route::post('/custom-api/webhooks', [\App\Http\Controllers\Seller\ConnectionController::class, 'customApiWebhooks'])->name('custom_api.webhooks');

        // Sync logs retry
        Route::post('/sync-logs/{syncLog}/retry', [\App\Http\Controllers\Seller\ConnectionController::class, 'retrySync'])->name('sync_logs.retry');
    });

    // Products & Catalogue Management
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    Route::patch('/products/variants/{variant}/quick-update', [ProductController::class, 'quickUpdateVariant'])->name('products.quick_update_variant');
    Route::post('/products/bulk-action', [ProductController::class, 'bulkAction'])->name('products.bulk_action');
    Route::get('/products-export', [ProductController::class, 'export'])->name('products.export');
    Route::match(['get', 'post'], '/products/check-sku', [ProductController::class, 'checkSku'])->name('products.check_sku');

    // Inventory & Stock Balances (Blueprint Section 9)
    Route::get('/inventory', [\App\Http\Controllers\Seller\InventoryController::class, 'index'])->name('inventory.index');
    Route::put('/inventory/{inventory}', [\App\Http\Controllers\Seller\InventoryController::class, 'update'])->name('inventory.update');
    Route::get('/inventory/{inventory}/movements', [\App\Http\Controllers\Seller\InventoryController::class, 'movements'])->name('inventory.movements');
    Route::post('/inventory/transfer', [\App\Http\Controllers\Seller\InventoryController::class, 'transfer'])->name('inventory.transfer');
    Route::post('/inventory/import-csv', [\App\Http\Controllers\Seller\InventoryController::class, 'importCsv'])->name('inventory.import_csv');
    Route::get('/inventory-sample-csv', [\App\Http\Controllers\Seller\InventoryController::class, 'sampleCsv'])->name('inventory.sample_csv');

    // Financials, Earnings Ledger & Settlements (Blueprint Section 18)
    Route::get('/payments', [\App\Http\Controllers\Seller\PaymentController::class, 'payments'])->name('payments.index');
    Route::get('/settlements', [\App\Http\Controllers\Seller\PaymentController::class, 'settlements'])->name('settlements.index');
    Route::get('/settlements/{settlement}', [\App\Http\Controllers\Seller\PaymentController::class, 'showSettlement'])->name('settlements.show');
    Route::get('/settlements/{settlement}/pdf', [\App\Http\Controllers\Seller\PaymentController::class, 'downloadPdf'])->name('settlements.pdf');
    Route::get('/settlements/{settlement}/csv', [\App\Http\Controllers\Seller\PaymentController::class, 'downloadCsv'])->name('settlements.csv');

    // Returns & RMA Management (Blueprint Section 26)
    Route::prefix('returns')->name('returns.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Seller\ReturnController::class, 'index'])->name('index');
        Route::get('/export', [\App\Http\Controllers\Seller\ReturnController::class, 'export'])->name('export');
        Route::get('/{return}', [\App\Http\Controllers\Seller\ReturnController::class, 'show'])->name('show');
        Route::post('/{return}/approve', [\App\Http\Controllers\Seller\ReturnController::class, 'approve'])->name('approve');
        Route::post('/{return}/reject', [\App\Http\Controllers\Seller\ReturnController::class, 'reject'])->name('reject');
        Route::post('/{return}/schedule-pickup', [\App\Http\Controllers\Seller\ReturnController::class, 'schedulePickup'])->name('schedule_pickup');
        Route::post('/{return}/receive', [\App\Http\Controllers\Seller\ReturnController::class, 'receive'])->name('receive');
        Route::post('/{return}/qc', [\App\Http\Controllers\Seller\ReturnController::class, 'recordQc'])->name('record_qc');
        Route::post('/{return}/refund', [\App\Http\Controllers\Seller\ReturnController::class, 'refund'])->name('refund');
    });

    // Shipments & Cargo Logistics Management
    Route::prefix('shipments')->name('shipments.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Seller\ShipmentController::class, 'index'])->name('index');
        Route::get('/export', [\App\Http\Controllers\Seller\ShipmentController::class, 'export'])->name('export');
        Route::post('/bulk-labels', [\App\Http\Controllers\Seller\ShipmentController::class, 'bulkLabels'])->name('bulk_labels');
        Route::post('/bulk-create', [\App\Http\Controllers\Seller\ShipmentController::class, 'bulkCreate'])->name('bulk_create');
        Route::get('/{shipment}', [\App\Http\Controllers\Seller\ShipmentController::class, 'show'])->name('show');
        Route::get('/{shipment}/label', [\App\Http\Controllers\Seller\ShipmentController::class, 'downloadLabel'])->name('label');
        Route::post('/{shipment}/pickup', [\App\Http\Controllers\Seller\ShipmentController::class, 'requestPickup'])->name('request_pickup');
        Route::post('/{shipment}/sync-tracking', [\App\Http\Controllers\Seller\ShipmentController::class, 'syncTracking'])->name('sync_tracking');
        Route::post('/{shipment}/ndr-action', [\App\Http\Controllers\Seller\ShipmentController::class, 'resolveNdr'])->name('ndr_action');
    });

    // Seller AI Assistant & Inventory Forecasting (Blueprint AI & Automation)
    Route::prefix('ai')->name('ai.')->group(function () {
        Route::post('/generate-catalogue', [\App\Http\Controllers\Seller\SellerAiController::class, 'generateCatalogue'])->name('generate_catalogue');
        Route::post('/suggest-pricing/{variant}', [\App\Http\Controllers\Seller\SellerAiController::class, 'suggestPricing'])->name('suggest_pricing');
        Route::get('/demand-forecast', [\App\Http\Controllers\Seller\SellerAiController::class, 'demandForecast'])->name('demand_forecast');
        Route::get('/action-logs', [\App\Http\Controllers\Seller\SellerAiController::class, 'actionLogs'])->name('action_logs');
    });
});

// Signed route for private document downloads
Route::middleware(['auth', 'signed'])->get('/onboarding/documents/{document}', [OnboardingController::class, 'downloadDocument'])
    ->name('onboarding.document.view');
