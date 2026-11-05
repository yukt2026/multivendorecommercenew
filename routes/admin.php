<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CmsPageController;
use App\Http\Controllers\Admin\CommissionRuleController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ProductAttributeController;
use App\Http\Controllers\Admin\ProductModerationController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SellerController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SettlementController;
use App\Http\Controllers\Admin\ShippingSettingController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\StoreController;
use App\Http\Controllers\Admin\SupportTicketController;
use App\Http\Controllers\Admin\UiKitController;
use App\Http\Controllers\Auth\AdminAuthController;
use Illuminate\Support\Facades\Route;

// Dedicated Admin Login (Separate portal screen)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login'])->name('login.submit');
});

// Protected Administration Console
Route::middleware(['auth', 'role:super_admin|admin|support_admin'])->group(function () {
    Route::match(['get', 'post'], '/logout', [AdminAuthController::class, 'logout'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
    Route::get('/dashboard/chart-data', [DashboardController::class, 'chartData'])->name('dashboard.chart_data');
    Route::get('/dashboard/export', [DashboardController::class, 'exportSummary'])->name('dashboard.export');
    Route::get('/ui-kit', [UiKitController::class, 'index'])->name('uikit');

    // Sellers Management & KYC Governance
    Route::prefix('sellers')->name('sellers.')->group(function () {
        Route::get('/', [SellerController::class, 'index'])->name('index');
        Route::get('/export', [SellerController::class, 'export'])->name('export');
        Route::post('/bulk-action', [SellerController::class, 'bulkAction'])->name('bulk_action');
        Route::get('/{seller}', [SellerController::class, 'show'])->name('show');
        Route::post('/{seller}/approve', [SellerController::class, 'approve'])->name('approve');
        Route::post('/{seller}/reject', [SellerController::class, 'reject'])->name('reject');
        Route::post('/{seller}/suspend', [SellerController::class, 'suspend'])->name('suspend');
        Route::post('/{seller}/reactivate', [SellerController::class, 'reactivate'])->name('reactivate');
        Route::post('/{seller}/commission', [SellerController::class, 'updateCommission'])->name('commission.update');
        Route::post('/{seller}/documents/{document}/review', [SellerController::class, 'reviewDocument'])->name('documents.review');
        Route::post('/{seller}/bank-accounts/{bankAccount}/verify', [SellerController::class, 'verifyBankAccount'])->name('bank.verify');
    });

    // Catalogue, Categories, Brands & Moderation
    Route::prefix('catalogue')->name('catalogue.')->group(function () {
        // Categories
        Route::prefix('categories')->name('categories.')->group(function () {
            Route::get('/', [CategoryController::class, 'index'])->name('index');
            Route::post('/', [CategoryController::class, 'store'])->name('store');
            Route::post('/reorder', [CategoryController::class, 'reorder'])->name('reorder');
            Route::put('/{category}', [CategoryController::class, 'update'])->name('update');
            Route::delete('/{category}', [CategoryController::class, 'destroy'])->name('destroy');
        });

        // Brands
        Route::prefix('brands')->name('brands.')->group(function () {
            Route::get('/', [BrandController::class, 'index'])->name('index');
            Route::post('/', [BrandController::class, 'store'])->name('store');
            Route::put('/{brand}', [BrandController::class, 'update'])->name('update');
            Route::delete('/{brand}', [BrandController::class, 'destroy'])->name('destroy');
            Route::post('/{brand}/toggle-verify', [BrandController::class, 'toggleVerify'])->name('toggle_verify');
        });

        // Attributes & Values
        Route::prefix('attributes')->name('attributes.')->group(function () {
            Route::get('/', [ProductAttributeController::class, 'index'])->name('index');
            Route::post('/', [ProductAttributeController::class, 'store'])->name('store');
            Route::put('/{attribute}', [ProductAttributeController::class, 'update'])->name('update');
            Route::delete('/{attribute}', [ProductAttributeController::class, 'destroy'])->name('destroy');
            Route::post('/{attribute}/values', [ProductAttributeController::class, 'storeValue'])->name('values.store');
            Route::delete('/{attribute}/values/{value}', [ProductAttributeController::class, 'destroyValue'])->name('values.destroy');
        });

        // Product Moderation Queue
        Route::prefix('moderation')->name('moderation.')->group(function () {
            Route::get('/', [ProductModerationController::class, 'index'])->name('index');
            Route::post('/bulk-approve', [ProductModerationController::class, 'bulkApprove'])->name('bulk_approve');
            Route::get('/{product}', [ProductModerationController::class, 'show'])->name('show');
            Route::get('/{product}/edit', [ProductModerationController::class, 'edit'])->name('edit');
            Route::put('/{product}', [ProductModerationController::class, 'update'])->name('update');
            Route::post('/{product}/approve', [ProductModerationController::class, 'approve'])->name('approve');
            Route::post('/{product}/reject', [ProductModerationController::class, 'reject'])->name('reject');
        });
    });

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingController::class, 'index'])->name('index');
        Route::post('/', [SettingController::class, 'update'])->name('update');
    });

    // Global Inventory Overview & Sync Logs
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\InventoryController::class, 'index'])->name('index');
        Route::get('/sync-logs', [\App\Http\Controllers\Admin\InventoryController::class, 'syncLogs'])->name('sync_logs');
    });

    // Orders Central & Governance (Blueprint Section 21)
    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\OrderController::class, 'index'])->name('index');
        Route::get('/{order}', [\App\Http\Controllers\Admin\OrderController::class, 'show'])->name('show');
        Route::post('/{order}/override-status', [\App\Http\Controllers\Admin\OrderController::class, 'overrideStatus'])->name('override_status');
        Route::post('/{order}/add-note', [\App\Http\Controllers\Admin\OrderController::class, 'addNote'])->name('add_note');
        Route::get('/{order}/invoice', [\App\Http\Controllers\Admin\OrderController::class, 'invoice'])->name('invoice');
    });

    // Returns & RMA Governance (Blueprint Section 26)
    Route::prefix('returns')->name('returns.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\ReturnController::class, 'index'])->name('index');
        Route::get('/export', [\App\Http\Controllers\Admin\ReturnController::class, 'export'])->name('export');
        Route::get('/{return}', [\App\Http\Controllers\Admin\ReturnController::class, 'show'])->name('show');
        Route::post('/{return}/override', [\App\Http\Controllers\Admin\ReturnController::class, 'override'])->name('override');
        Route::post('/{return}/force-refund', [\App\Http\Controllers\Admin\ReturnController::class, 'forceRefund'])->name('force_refund');
    });

    // Commission Engine & Simulator (Blueprint Section 18)
    Route::prefix('commissions')->name('commissions.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\CommissionRuleController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\Admin\CommissionRuleController::class, 'store'])->name('store');
        Route::put('/{commission}', [\App\Http\Controllers\Admin\CommissionRuleController::class, 'update'])->name('update');
        Route::delete('/{commission}', [\App\Http\Controllers\Admin\CommissionRuleController::class, 'destroy'])->name('destroy');
        Route::post('/{commission}/toggle', [\App\Http\Controllers\Admin\CommissionRuleController::class, 'toggle'])->name('toggle');
        Route::post('/simulate', [\App\Http\Controllers\Admin\CommissionRuleController::class, 'simulate'])->name('simulate');
    });

    // Seller Settlements & Payouts (Blueprint Section 18)
    Route::prefix('settlements')->name('settlements.')->group(function () {
        Route::get('/', [SettlementController::class, 'index'])->name('index');
        Route::post('/generate', [SettlementController::class, 'generate'])->name('generate');
        Route::get('/{settlement}', [SettlementController::class, 'show'])->name('show');
        Route::post('/{settlement}/approve', [SettlementController::class, 'approve'])->name('approve');
        Route::post('/{settlement}/pay', [SettlementController::class, 'markPaid'])->name('pay');
        Route::post('/{settlement}/hold', [SettlementController::class, 'hold'])->name('hold');
        Route::post('/{settlement}/release', [SettlementController::class, 'release'])->name('release');
        Route::post('/{settlement}/adjustment', [SettlementController::class, 'addAdjustment'])->name('adjustment');
        Route::get('/{settlement}/pdf', [SettlementController::class, 'exportPdf'])->name('pdf');
        Route::get('/{settlement}/csv', [SettlementController::class, 'exportCsv'])->name('csv');
    });

    // Customer Relationship Management
    Route::prefix('customers')->name('customers.')->group(function () {
        Route::get('/', [CustomerController::class, 'index'])->name('index');
        Route::get('/export', [CustomerController::class, 'export'])->name('export');
        Route::get('/{customer}', [CustomerController::class, 'show'])->name('show');
        Route::post('/{customer}/toggle-status', [CustomerController::class, 'toggleStatus'])->name('toggle_status');
    });

    // Physical Dark Stores & Outlets
    Route::prefix('stores')->name('stores.')->group(function () {
        Route::get('/', [StoreController::class, 'index'])->name('index');
        Route::get('/export', [StoreController::class, 'export'])->name('export');
        Route::get('/map', [StoreController::class, 'map'])->name('map');
        Route::get('/{store}', [StoreController::class, 'show'])->name('show');
        Route::post('/{store}/approve', [StoreController::class, 'approve'])->name('approve');
        Route::post('/{store}/suspend', [StoreController::class, 'suspend'])->name('suspend');
    });

    // Payments & Refunds Governance
    Route::prefix('payments')->name('payments.')->group(function () {
        Route::get('/', [PaymentController::class, 'index'])->name('index');
        Route::get('/export', [PaymentController::class, 'export'])->name('export');
        Route::get('/refunds', [PaymentController::class, 'refunds'])->name('refunds');
        Route::get('/{payment}', [PaymentController::class, 'show'])->name('show');
        Route::post('/{payment}/refund', [PaymentController::class, 'refund'])->name('refund');
    });

    // Coupons & Promotional Discounts
    Route::prefix('coupons')->name('coupons.')->group(function () {
        Route::get('/', [CouponController::class, 'index'])->name('index');
        Route::get('/export', [CouponController::class, 'export'])->name('export');
        Route::post('/', [CouponController::class, 'store'])->name('store');
        Route::put('/{coupon}', [CouponController::class, 'update'])->name('update');
        Route::delete('/{coupon}', [CouponController::class, 'destroy'])->name('destroy');
        Route::post('/{coupon}/toggle', [CouponController::class, 'toggle'])->name('toggle');
    });

    // Featured Marketing Campaigns
    Route::prefix('campaigns')->name('campaigns.')->group(function () {
        Route::get('/', [CampaignController::class, 'index'])->name('index');
        Route::post('/', [CampaignController::class, 'store'])->name('store');
        Route::put('/{campaign}', [CampaignController::class, 'update'])->name('update');
        Route::delete('/{campaign}', [CampaignController::class, 'destroy'])->name('destroy');
        Route::post('/{campaign}/toggle', [CampaignController::class, 'toggle'])->name('toggle');
    });

    // Visual Merchandising & Banners
    Route::prefix('banners')->name('banners.')->group(function () {
        Route::get('/', [BannerController::class, 'index'])->name('index');
        Route::post('/', [BannerController::class, 'store'])->name('store');
        Route::put('/{banner}', [BannerController::class, 'update'])->name('update');
        Route::delete('/{banner}', [BannerController::class, 'destroy'])->name('destroy');
        Route::post('/{banner}/toggle', [BannerController::class, 'toggle'])->name('toggle');
    });

    // Static CMS Content Pages
    Route::prefix('cms')->name('cms.')->group(function () {
        Route::get('/', [CmsPageController::class, 'index'])->name('index');
        Route::get('/create', [CmsPageController::class, 'create'])->name('create');
        Route::post('/', [CmsPageController::class, 'store'])->name('store');
        Route::get('/{page}/edit', [CmsPageController::class, 'edit'])->name('edit');
        Route::put('/{page}', [CmsPageController::class, 'update'])->name('update');
        Route::delete('/{page}', [CmsPageController::class, 'destroy'])->name('destroy');
    });

    // Logistics & Shipping Rules
    Route::prefix('shipping')->name('shipping.')->group(function () {
        Route::get('/', [ShippingSettingController::class, 'index'])->name('index');
        Route::post('/', [ShippingSettingController::class, 'update'])->name('update');
        Route::get('/failed-queue', [ShippingSettingController::class, 'failedQueue'])->name('failed_queue');
        Route::post('/shipments/{shipment}/retry', [ShippingSettingController::class, 'retryShipment'])->name('retry_shipment');
        Route::post('/sync-all', [ShippingSettingController::class, 'syncAllTracking'])->name('sync_all');
    });

    // Analytics & Business Intelligence
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/export/{type}', [ReportController::class, 'export'])->name('export');
    });

    // Support Desk & Escalations
    Route::prefix('support')->name('support.')->group(function () {
        Route::get('/', [SupportTicketController::class, 'index'])->name('index');
        Route::get('/export', [SupportTicketController::class, 'export'])->name('export');
        Route::get('/{ticket}', [SupportTicketController::class, 'show'])->name('show');
        Route::post('/{ticket}/reply', [SupportTicketController::class, 'reply'])->name('reply');
        Route::post('/{ticket}/assign', [SupportTicketController::class, 'assign'])->name('assign');
        Route::post('/{ticket}/status', [SupportTicketController::class, 'status'])->name('status');
        Route::post('/{ticket}/ai-reply', [SupportTicketController::class, 'generateAiReply'])->name('ai_reply');
    });

    // Admin Staff & RBAC Permissions
    Route::prefix('staff')->name('staff.')->group(function () {
        Route::get('/', [StaffController::class, 'index'])->name('index');
        Route::get('/roles', [StaffController::class, 'roles'])->name('roles');
        Route::put('/roles/{role}', [StaffController::class, 'updateRolePermissions'])->name('roles.update');
        Route::post('/', [StaffController::class, 'store'])->name('store');
        Route::put('/{staff}', [StaffController::class, 'update'])->name('update');
        Route::delete('/{staff}', [StaffController::class, 'destroy'])->name('destroy');
    });

    // Quick Commerce Operations & Mission Control (Blueprint Section 11)
    Route::prefix('quick-commerce')->name('quick.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\QuickCommerceOpsController::class, 'index'])->name('index');
        Route::get('/api-feed', [\App\Http\Controllers\Admin\QuickCommerceOpsController::class, 'apiFeed'])->name('api_feed');
    });

    // Delivery Partners Fleet Management (Blueprint Section 11)
    Route::prefix('delivery-partners')->name('delivery_partners.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\DeliveryPartnerController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\Admin\DeliveryPartnerController::class, 'store'])->name('store');
        Route::post('/{deliveryPartner}/toggle', [\App\Http\Controllers\Admin\DeliveryPartnerController::class, 'toggleStatus'])->name('toggle');
    });

    // Security & Operational Audit Trail
    Route::prefix('audit-logs')->name('audit-logs.')->group(function () {
        Route::get('/', [AuditLogController::class, 'index'])->name('index');
        Route::get('/export', [AuditLogController::class, 'export'])->name('export');
    });

    // Notification Templates & Audit Logs (Blueprint Section 27)
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/templates', [\App\Http\Controllers\Admin\NotificationTemplateController::class, 'index'])->name('templates.index');
        Route::put('/templates/{template}', [\App\Http\Controllers\Admin\NotificationTemplateController::class, 'update'])->name('templates.update');
        Route::get('/logs', [\App\Http\Controllers\Admin\NotificationLogController::class, 'index'])->name('logs.index');
        Route::post('/logs/{log}/retry', [\App\Http\Controllers\Admin\NotificationLogController::class, 'retry'])->name('logs.retry');
    });

    // SEO 301/302 Redirect Manager (Blueprint Section 32)
    Route::prefix('redirects')->name('redirects.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\RedirectRuleController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\Admin\RedirectRuleController::class, 'store'])->name('store');
        Route::put('/{rule}', [\App\Http\Controllers\Admin\RedirectRuleController::class, 'update'])->name('update');
        Route::delete('/{rule}', [\App\Http\Controllers\Admin\RedirectRuleController::class, 'destroy'])->name('destroy');
        Route::post('/{rule}/toggle', [\App\Http\Controllers\Admin\RedirectRuleController::class, 'toggle'])->name('toggle');
        Route::post('/import-csv', [\App\Http\Controllers\Admin\RedirectRuleController::class, 'importCsv'])->name('import');
    });
});
