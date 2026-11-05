<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\CustomerAuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\OtpAuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Storefront\BrandController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CategoryController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\CustomerOrderController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\LocationController;
use App\Http\Controllers\Storefront\PaymentController;
use App\Http\Controllers\Storefront\ProductDetailController;
use App\Http\Controllers\Storefront\SearchController;
use App\Http\Controllers\Storefront\StoresPageController;
use App\Http\Controllers\Storefront\StoreController as PublicStoreController;
use App\Http\Controllers\Storefront\WishlistController;
use Illuminate\Support\Facades\Route;

// Production Deep Health-Check Endpoint
Route::get('/healthz', [\App\Http\Controllers\HealthCheckController::class, 'check'])->name('healthz');

// Storefront Home
Route::get('/', [HomeController::class, 'index'])->name('home');

// Quick Commerce Discovery (Blueprint Section 11)
Route::get('/quick', [\App\Http\Controllers\Storefront\QuickCommerceController::class, 'index'])->name('quick.index');
Route::get('/api/v1/quick/nearby', [\App\Http\Controllers\Storefront\QuickCommerceController::class, 'nearbyStoresApi'])->name('quick.nearby_api');
Route::get('/orders/{order}/live-tracking', [\App\Http\Controllers\Storefront\QuickCommerceController::class, 'liveTracking'])->name('orders.live_tracking');
Route::get('/orders/{order}/live-location', [\App\Http\Controllers\Storefront\QuickCommerceController::class, 'liveLocationApi'])->name('orders.live_location');

// Rider Mobile-Friendly Web Portal (Blueprint Section 11)
Route::prefix('rider')->name('rider.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Rider\RiderController::class, 'dashboard'])->name('dashboard');
    Route::get('/orders/{assignment}', [\App\Http\Controllers\Rider\RiderController::class, 'showAssignment'])->name('assignment');
    Route::post('/orders/{assignment}/accept', [\App\Http\Controllers\Rider\RiderController::class, 'accept'])->name('accept');
    Route::post('/orders/{assignment}/pickup', [\App\Http\Controllers\Rider\RiderController::class, 'pickup'])->name('pickup');
    Route::post('/orders/{assignment}/deliver', [\App\Http\Controllers\Rider\RiderController::class, 'deliver'])->name('deliver');
    Route::post('/location', [\App\Http\Controllers\Rider\RiderController::class, 'updateLocation'])->name('location_api');
    Route::post('/switch', [\App\Http\Controllers\Rider\RiderController::class, 'switchRider'])->name('switch');
});

// Discovery Pages (Search, Categories, Brands, Stores, Product Details, CMS)
Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::get('/c/{slug}', [CategoryController::class, 'show'])->name('category.show');
Route::get('/b/{slug}', [BrandController::class, 'show'])->name('brand.show');
Route::get('/stores', [StoresPageController::class, 'index'])->name('stores.index');
Route::get('/p/{slug}', [ProductDetailController::class, 'show'])->name('product.show');
Route::post('/p/{slug}/review', [ProductDetailController::class, 'submitReview'])->name('product.review.submit');
Route::post('/p/{slug}/question', [ProductDetailController::class, 'submitQuestion'])->name('product.question.submit');
Route::get('/page/{slug}', [\App\Http\Controllers\Storefront\CmsPageController::class, 'show'])->name('pages.show');

// Dynamic Sitemaps & Robots.txt (Blueprint Section 32)
Route::get('/sitemap.xml', [\App\Http\Controllers\Storefront\SitemapController::class, 'index'])->name('sitemap.index');
Route::get('/sitemap-products.xml', [\App\Http\Controllers\Storefront\SitemapController::class, 'products'])->name('sitemap.products');
Route::get('/sitemap-products-{chunk}.xml', [\App\Http\Controllers\Storefront\SitemapController::class, 'products'])->name('sitemap.products.chunk');
Route::get('/sitemap-categories.xml', [\App\Http\Controllers\Storefront\SitemapController::class, 'categories'])->name('sitemap.categories');
Route::get('/sitemap-brands.xml', [\App\Http\Controllers\Storefront\SitemapController::class, 'brands'])->name('sitemap.brands');
Route::get('/sitemap-stores.xml', [\App\Http\Controllers\Storefront\SitemapController::class, 'stores'])->name('sitemap.stores');
Route::get('/sitemap-pages.xml', [\App\Http\Controllers\Storefront\SitemapController::class, 'pages'])->name('sitemap.pages');
Route::get('/robots.txt', [\App\Http\Controllers\Storefront\SitemapController::class, 'robots'])->name('robots');

// Full Cart Page & Actions (Dual AJAX / HTTP form fallback)
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
Route::post('/cart/update/{id}', [CartController::class, 'update'])->name('cart.update');
Route::post('/cart/remove/{id}', [CartController::class, 'remove'])->name('cart.remove');
Route::post('/cart/clear', [CartController::class, 'clear'])->name('cart.clear');
Route::post('/cart/save-for-later/{id}', [CartController::class, 'saveForLater'])->name('cart.save-for-later');
Route::post('/cart/move-to-cart/{id}', [CartController::class, 'moveToCart'])->name('cart.move-to-cart');
Route::post('/cart/coupon/apply', [CartController::class, 'applyCoupon'])->name('cart.coupon.apply');
Route::post('/cart/coupon/remove', [CartController::class, 'removeCoupon'])->name('cart.coupon.remove');
Route::post('/cart/accept-price-changes', [CartController::class, 'acceptPriceChanges'])->name('cart.accept-price-changes');

// Single-Page Checkout & Order Placement (Blueprint Sections 10 and 22)
Route::middleware('auth')->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout/address', [CheckoutController::class, 'addAddress'])->name('checkout.address.add');
    Route::post('/checkout/place-order', [CheckoutController::class, 'placeOrder'])->name('checkout.place-order');
    Route::get('/checkout/success/{order_number}', [CheckoutController::class, 'success'])->name('checkout.success');

    // Customer Order Management & Returns (Blueprint Sections 21 & 26)
    Route::get('/orders', [CustomerOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order_number}', [CustomerOrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order_number}/invoice', [CustomerOrderController::class, 'invoice'])->name('orders.invoice');
    Route::post('/orders/{order_number}/cancel', [CustomerOrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('/orders/{order_number}/reorder', [CustomerOrderController::class, 'reorder'])->name('orders.reorder');
    Route::get('/orders/{order_number}/track/{fo_number}', [CustomerOrderController::class, 'track'])->name('orders.track');
    Route::post('/orders/{order_number}/track/{fo_number}/ndr', [CustomerOrderController::class, 'resolveNdr'])->name('orders.ndr.resolve');
    Route::post('/orders/{order_number}/return/{fo_number}', [CustomerOrderController::class, 'submitReturn'])->name('orders.return');
    Route::get('/orders/{order_number}/return-item/{item_id}', [CustomerOrderController::class, 'returnForm'])->name('orders.return.form');
    Route::post('/orders/{order_number}/return-item/{item_id}', [CustomerOrderController::class, 'submitItemReturn'])->name('orders.return.item');
    Route::post('/orders/{order_number}/cancel-item/{item_id}', [CustomerOrderController::class, 'cancelItem'])->name('orders.cancel.item');
    Route::get('/orders/{order_number}/returns/{return_number}', [CustomerOrderController::class, 'trackReturn'])->name('orders.return.track');

    // In-App Notification Center & Channel Preferences (Blueprint Section 27)
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [\App\Http\Controllers\NotificationController::class, 'index'])->name('index');
        Route::post('/preferences', [\App\Http\Controllers\NotificationController::class, 'updatePreferences'])->name('preferences.update');
        Route::post('/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('read');
        Route::post('/mark-all-read', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('mark_all_read');
        Route::delete('/clear-all', [\App\Http\Controllers\NotificationController::class, 'clearAll'])->name('clear_all');
    });
    Route::get('/api/notifications/unread-count', [\App\Http\Controllers\NotificationController::class, 'unreadCountApi'])->name('notifications.unread_count');
});

// Payment Processing (Gateway Checkout, Callback, Retry & Webhooks)
Route::get('/payment/pay/{order_number}', [PaymentController::class, 'pay'])->name('payment.pay');
Route::match(['get', 'post'], '/payment/callback', [PaymentController::class, 'callback'])->name('payment.callback');
Route::get('/payment/retry/{order_number}', [PaymentController::class, 'retry'])->name('payment.retry');
Route::post('/webhook/payment/{gateway}', [PaymentController::class, 'webhook'])
    ->middleware('throttle:60,1')
    ->name('payment.webhook');

// Full Wishlist Page & Actions
Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
Route::get('/wishlist/share/{token}', [WishlistController::class, 'share'])->name('wishlist.share');
Route::post('/wishlist/toggle', [WishlistController::class, 'toggle'])->name('wishlist.toggle');
Route::post('/wishlist/move-to-cart/{id}', [WishlistController::class, 'moveToCart'])->name('wishlist.move-to-cart');
Route::post('/wishlist/remove/{id}', [WishlistController::class, 'remove'])->name('wishlist.remove');

// Storefront Location, Cart, Search & Wishlist Interactive Endpoints
Route::prefix('api/storefront')->name('storefront.')->group(function () {
    // Location
    Route::get('/location/current', [LocationController::class, 'current'])->name('location.current');
    Route::post('/location/set', [LocationController::class, 'set'])->name('location.set');
    Route::get('/location/search', [LocationController::class, 'search'])->name('location.search');

    // Cart
    Route::get('/cart/summary', [CartController::class, 'summary'])->name('cart.summary');
    Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
    Route::post('/cart/update/{id}', [CartController::class, 'update'])->name('cart.update');
    Route::post('/cart/remove/{id}', [CartController::class, 'remove'])->name('cart.remove');
    Route::post('/cart/clear', [CartController::class, 'clear'])->name('cart.clear');
    Route::post('/cart/save-for-later/{id}', [CartController::class, 'saveForLater'])->name('cart.save-for-later');
    Route::post('/cart/move-to-cart/{id}', [CartController::class, 'moveToCart'])->name('cart.move-to-cart');
    Route::post('/cart/coupon/apply', [CartController::class, 'applyCoupon'])->name('cart.coupon.apply');
    Route::post('/cart/coupon/remove', [CartController::class, 'removeCoupon'])->name('cart.coupon.remove');
    Route::post('/cart/accept-price-changes', [CartController::class, 'acceptPriceChanges'])->name('cart.accept-price-changes');

    // Search suggestions
    Route::get('/search/suggestions', [SearchController::class, 'suggestions'])->name('search.suggestions');

    // Wishlist
    Route::post('/wishlist/toggle', [WishlistController::class, 'toggle'])->name('wishlist.toggle');
    Route::get('/wishlist/status', [WishlistController::class, 'status'])->name('wishlist.status');
    Route::post('/wishlist/move-to-cart/{id}', [WishlistController::class, 'moveToCart'])->name('wishlist.move-to-cart');
    Route::post('/wishlist/remove/{id}', [WishlistController::class, 'remove'])->name('wishlist.remove');

    // Pincode fulfillment check
    Route::post('/pincode/check', [ProductDetailController::class, 'checkPincode'])->name('pincode.check');
});

// Public Seller Storefront (SEO Friendly / Blueprint Section 7)
Route::get('/store/{slug}', [PublicStoreController::class, 'show'])->name('store.show');
Route::post('/store/{slug}/toggle-follow', [PublicStoreController::class, 'toggleFollow'])->name('store.follow');

// Guest Authentication Routes
Route::middleware('guest')->group(function () {
    // Customer Login & Registration
    Route::get('/login', [CustomerAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [CustomerAuthController::class, 'login'])->name('login.submit');
    Route::get('/register', [CustomerAuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [CustomerAuthController::class, 'register'])->name('register.submit');

    // Phone OTP Login
    Route::prefix('login/otp')->name('login.otp')->group(function () {
        Route::get('/', [OtpAuthController::class, 'showOtpForm']);
        Route::post('/send', [OtpAuthController::class, 'sendOtp'])->name('.send');
        Route::post('/verify', [OtpAuthController::class, 'verifyOtp'])->name('.verify');
    });

    // Password Reset
    Route::get('/forgot-password', [PasswordResetController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.store');
});

// Authenticated Actions
Route::middleware('auth')->group(function () {
    Route::match(['get', 'post'], '/logout', [CustomerAuthController::class, 'logout'])->name('logout');

    // Email Verification
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])->middleware('throttle:6,1')->name('verification.send');
});
