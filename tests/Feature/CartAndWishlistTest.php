<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CouponType;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\SellerStatus;
use App\Enums\StoreStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\InventoryLocation;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\Storefront\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CartAndWishlistTest extends TestCase
{
    use RefreshDatabase;

    protected User $customerUser;
    protected Customer $customer;
    protected Seller $seller1;
    protected Seller $seller2;
    protected Store $store1;
    protected Product $product1;
    protected ProductVariant $variant1;
    protected Product $product2;
    protected ProductVariant $variant2;
    protected CartService $cartService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cartService = app(CartService::class);

        // Dynamic settings
        Setting::set('base_delivery_fee', 40.00);
        Setting::set('free_delivery_threshold', 499.00);
        Setting::set('default_gst_rate', 18.00);
        Setting::set('tax_inclusive', true);

        // Customer User
        $this->customerUser = User::factory()->create([
            'email' => 'customer_cart@onestall.in',
            'phone' => '9898989898',
            'status' => 'active',
        ]);
        $this->customer = Customer::create([
            'user_id' => $this->customerUser->id,
            'wishlist_share_token' => 'test-share-token-1234',
        ]);

        // Seller 1 (Quick commerce capable)
        $sellerUser1 = User::factory()->create(['email' => 'seller1_cart@onestall.in']);
        $this->seller1 = Seller::create([
            'user_id' => $sellerUser1->id,
            'business_name' => 'Fast Mart Superstore',
            'legal_name' => 'Fast Mart Superstore Private Limited',
            'status' => SellerStatus::APPROVED,
        ]);
        $this->store1 = Store::create([
            'seller_id' => $this->seller1->id,
            'name' => 'Fast Mart Koramangala',
            'slug' => 'fast-mart-koramangala',
            'status' => StoreStatus::ACTIVE,
            'is_active' => true,
            'accepts_quick_commerce' => true,
            'delivery_radius_km' => 8.0,
        ]);

        // Seller 2 (Standard national store)
        $sellerUser2 = User::factory()->create(['email' => 'seller2_cart@onestall.in']);
        $this->seller2 = Seller::create([
            'user_id' => $sellerUser2->id,
            'business_name' => 'Heritage Crafts India',
            'legal_name' => 'Heritage Crafts India LLP',
            'status' => SellerStatus::APPROVED,
        ]);

        // Category
        $category = \App\Models\Category::create([
            'name' => 'Groceries & Essentials',
            'slug' => 'groceries-essentials',
            'is_active' => true,
        ]);

        // Product 1 (Quick delivery)
        $this->product1 = Product::create([
            'seller_id' => $this->seller1->id,
            'category_id' => $category->id,
            'title' => 'Fresh Farm Whole Milk 1L',
            'slug' => 'fresh-farm-whole-milk-1l',
            'type' => ProductType::SIMPLE,
            'status' => ProductStatus::ACTIVE,
            'is_quick_capable' => true,
            'sku' => 'MILK-1L',
        ]);
        $this->variant1 = ProductVariant::create([
            'product_id' => $this->product1->id,
            'name' => '1 Litre Pouch',
            'sku' => 'MILK-1L-01',
            'selling_price' => 60.00,
            'mrp' => 70.00,
            'is_default' => true,
            'is_active' => true,
        ]);

        // Product 2 (Standard national delivery)
        $this->product2 = Product::create([
            'seller_id' => $this->seller2->id,
            'category_id' => $category->id,
            'title' => 'Handmade Kashmiri Pashmina Shawl',
            'slug' => 'kashmiri-pashmina-shawl',
            'type' => ProductType::SIMPLE,
            'status' => ProductStatus::ACTIVE,
            'is_quick_capable' => false,
            'sku' => 'SHAWL-01',
        ]);
        $this->variant2 = ProductVariant::create([
            'product_id' => $this->product2->id,
            'name' => 'Royal Crimson',
            'sku' => 'SHAWL-01-CRIMSON',
            'selling_price' => 1200.00,
            'mrp' => 1500.00,
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    public function test_guest_session_cart_and_items_lifecycle(): void
    {
        $sessionId = 'guest-session-' . Str::random(10);

        // 1. Get active cart for guest
        $cart = $this->cartService->getActiveCart(null, $sessionId);
        $this->assertNotNull($cart);
        $this->assertEquals($sessionId, $cart->session_id);
        $this->assertNull($cart->user_id);

        // 2. Add item
        $item = $this->cartService->addItem($cart, $this->variant1->id, 2);
        $this->assertEquals(2, $item->quantity);
        $this->assertEquals(60.00, (float) $item->price_snapshot);

        // 3. Update quantity
        $updated = $this->cartService->updateQuantity($cart, $item->id, 3);
        $this->assertEquals(3, $updated->quantity);

        // 4. Cart summary totals
        $summary = $this->cartService->getSummary($cart);
        $this->assertEquals(3, $summary['item_count']);
        $this->assertEquals(180.00, $summary['subtotal']); // 60 * 3
        $this->assertEquals(210.00, $summary['total_mrp']); // 70 * 3
        $this->assertEquals(30.00, $summary['product_discount']); // 210 - 180
        // Delivery fee applied because subtotal 180 < 499 threshold
        $this->assertEquals(40.00, $summary['delivery_fee']);
        $this->assertEquals(220.00, $summary['total_payable']); // 180 + 40

        // 5. Remove item
        $this->cartService->removeItem($cart, $item->id);
        $this->assertCount(0, $cart->fresh()->items);
    }

    public function test_guest_session_cart_merges_into_customer_cart_on_login(): void
    {
        $guestSessionId = 'guest-session-merge-' . Str::random(8);

        // Guest adds variant1 (qty 2) and variant2 (qty 1)
        $guestCart = $this->cartService->getActiveCart(null, $guestSessionId);
        $this->cartService->addItem($guestCart, $this->variant1->id, 2);
        $this->cartService->addItem($guestCart, $this->variant2->id, 1);
        $guestCart->coupon_code = 'WELCOME50';
        $guestCart->save();

        // Customer already has variant1 (qty 1) in their account cart
        $customerCart = $this->cartService->getActiveCart($this->customerUser, 'customer-session-123');
        $this->cartService->addItem($customerCart, $this->variant1->id, 1);

        // Perform merge
        $this->cartService->mergeSessionCart($guestSessionId, $this->customerUser);

        $customerCart->refresh();
        $this->assertFalse($guestCart->fresh()->is_active);

        // Variant 1 quantity should now be 1 + 2 = 3
        $mergedItem1 = $customerCart->items()->where('variant_id', $this->variant1->id)->first();
        $this->assertNotNull($mergedItem1);
        $this->assertEquals(3, $mergedItem1->quantity);

        // Variant 2 quantity should be 1
        $mergedItem2 = $customerCart->items()->where('variant_id', $this->variant2->id)->first();
        $this->assertNotNull($mergedItem2);
        $this->assertEquals(1, $mergedItem2->quantity);

        // Coupon code carried over
        $this->assertEquals('WELCOME50', $customerCart->coupon_code);
    }

    public function test_save_for_later_and_move_to_cart(): void
    {
        $sessionId = 'session-sfl-' . Str::random(8);
        $cart = $this->cartService->getActiveCart(null, $sessionId);

        $item1 = $this->cartService->addItem($cart, $this->variant1->id, 2);
        $item2 = $this->cartService->addItem($cart, $this->variant2->id, 1);

        // Move item1 to saved for later
        $this->cartService->moveToSavedForLater($cart, $item1->id);

        $summary = $this->cartService->getSummary($cart);
        $this->assertEquals(1, $summary['item_count']); // only variant2 is active
        $this->assertEquals(1, $summary['saved_count']); // variant1 is saved
        $this->assertEquals(1200.00, $summary['subtotal']); // variant2 price only

        // Move item1 back to cart
        $this->cartService->moveToCart($cart, $item1->id);

        $summaryAfter = $this->cartService->getSummary($cart);
        $this->assertEquals(3, $summaryAfter['item_count']); // 2 + 1
        $this->assertEquals(0, $summaryAfter['saved_count']);
        $this->assertEquals(1320.00, $summaryAfter['subtotal']); // 120 + 1200
    }

    public function test_price_change_detection(): void
    {
        $sessionId = 'session-price-' . Str::random(8);
        $cart = $this->cartService->getActiveCart(null, $sessionId);

        // Add item when price is 60.00 (snapshot = 60.00)
        $item = $this->cartService->addItem($cart, $this->variant1->id, 2);
        $this->assertEquals(60.00, (float) $item->price_snapshot);

        // Price increases to 75.00 on the variant
        $this->variant1->update(['selling_price' => 75.00]);

        $summary = $this->cartService->getSummary($cart);
        $this->assertTrue($summary['price_changes_detected']);
        $itemData = $summary['items'][0];
        $this->assertTrue($itemData['price_changed']);
        $this->assertEquals(15.00, $itemData['price_diff']);
        $this->assertEquals('increased', $itemData['price_diff_direction']);

        // Accept price changes
        $this->cartService->acceptPriceChanges($cart);

        $summaryAfter = $this->cartService->getSummary($cart);
        $this->assertFalse($summaryAfter['price_changes_detected']);
        $this->assertEquals(75.00, (float) $item->fresh()->price_snapshot);
    }

    public function test_stock_validation_against_inventory(): void
    {
        $location = InventoryLocation::create([
            'seller_id' => $this->seller1->id,
            'name' => 'Koramangala Dark Store',
            'code' => 'KOR-DS-01',
            'type' => \App\Enums\InventoryLocationType::STORE,
        ]);

        // Inventory with on-hand = 5, reserved = 3 -> available = 2
        Inventory::create([
            'variant_id' => $this->variant1->id,
            'location_id' => $location->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 3,
            'low_stock_threshold' => 2,
        ]);

        $sessionId = 'session-stock-' . Str::random(8);
        $cart = $this->cartService->getActiveCart(null, $sessionId);

        // Add 5 units (which exceeds available 2)
        $this->cartService->addItem($cart, $this->variant1->id, 5);

        $stockValidation = $this->cartService->validateStock($cart);
        $this->assertTrue($stockValidation['has_issues']);
        $this->assertCount(1, $stockValidation['issues']);
        $this->assertEquals('insufficient_stock', $stockValidation['issues'][0]['status']);
        $this->assertEquals(2, $stockValidation['issues'][0]['available_quantity']);

        $summary = $this->cartService->getSummary($cart);
        $this->assertTrue($summary['stock_issues_detected']);
    }

    public function test_items_grouped_by_seller_with_delivery_mode_and_eta(): void
    {
        $sessionId = 'session-grouped-' . Str::random(8);
        $cart = $this->cartService->getActiveCart(null, $sessionId);

        // Add Product 1 (from Seller 1 - Quick delivery eligible)
        $this->cartService->addItem($cart, $this->variant1->id, 2);

        // Add Product 2 (from Seller 2 - Standard delivery)
        $this->cartService->addItem($cart, $this->variant2->id, 1);

        $summary = $this->cartService->getSummary($cart);
        $grouped = $summary['grouped_by_seller'];

        $this->assertCount(2, $grouped);

        // Group 1: Fast Mart (quick delivery)
        $quickGroup = collect($grouped)->firstWhere('seller_id', $this->seller1->id);
        $this->assertNotNull($quickGroup);
        $this->assertEquals('quick', $quickGroup['delivery_type']);
        $this->assertStringContainsString('Quick Delivery', $quickGroup['delivery_badge']);
        $this->assertStringContainsString('15 - 30 mins', $quickGroup['estimated_eta']);
        $this->assertEquals(120.00, $quickGroup['subtotal']);

        // Group 2: Heritage Crafts (standard delivery)
        $standardGroup = collect($grouped)->firstWhere('seller_id', $this->seller2->id);
        $this->assertNotNull($standardGroup);
        $this->assertEquals('standard', $standardGroup['delivery_type']);
        $this->assertStringContainsString('Standard Delivery', $standardGroup['delivery_badge']);
        $this->assertStringContainsString('2 - 4 business days', $standardGroup['estimated_eta']);
        $this->assertEquals(1200.00, $standardGroup['subtotal']);
    }

    public function test_coupon_application_and_validation(): void
    {
        // 1. Percent coupon with max discount and min order
        $coupon = Coupon::create([
            'code' => 'DIWALI20',
            'type' => CouponType::PERCENT,
            'value' => 20.00,
            'max_discount' => 150.00,
            'min_order' => 500.00,
            'is_active' => true,
        ]);

        $sessionId = 'session-coupon-' . Str::random(8);
        $cart = $this->cartService->getActiveCart(null, $sessionId);

        // Add variant 1 (subtotal 120, below min order 500)
        $this->cartService->addItem($cart, $this->variant1->id, 2);

        // Fails min order check
        $failResult = $this->cartService->applyCoupon($cart, 'DIWALI20');
        $this->assertFalse($failResult['success']);
        $this->assertStringContainsString('Minimum order amount', $failResult['message']);

        // Add variant 2 (selling_price 1200, total subtotal = 1320 >= 500)
        $this->cartService->addItem($cart, $this->variant2->id, 1);

        // Applies successfully with max_discount cap (20% of 1320 = 264 -> capped at 150)
        $successResult = $this->cartService->applyCoupon($cart, 'DIWALI20');
        $this->assertTrue($successResult['success']);
        $this->assertEquals(150.00, $successResult['discount']);

        $summary = $this->cartService->getSummary($cart);
        $this->assertEquals('DIWALI20', $summary['coupon_code']);
        $this->assertEquals(150.00, $summary['coupon_discount']);
        // Subtotal (1320) - Coupon (150) + Delivery (0 because > 499) = 1170.00
        $this->assertEquals(1170.00, $summary['total_payable']);

        // Remove coupon
        $this->cartService->removeCoupon($cart);
        $this->assertNull($cart->fresh()->coupon_code);
    }

    public function test_seller_specific_coupon(): void
    {
        $sellerCoupon = Coupon::create([
            'code' => 'HERITAGE10',
            'type' => CouponType::PERCENT,
            'value' => 10.00,
            'seller_id' => $this->seller2->id, // Only for Heritage Crafts
            'min_order' => 500.00,
            'is_active' => true,
        ]);

        $sessionId = 'session-seller-coupon-' . Str::random(8);
        $cart = $this->cartService->getActiveCart(null, $sessionId);

        // Cart only contains Seller 1 products
        $this->cartService->addItem($cart, $this->variant1->id, 10); // 600 total

        $res = $this->cartService->applyCoupon($cart, 'HERITAGE10');
        $this->assertFalse($res['success']);
        $this->assertStringContainsString('Heritage Crafts India', $res['message']);

        // Add Seller 2 product (1200)
        $this->cartService->addItem($cart, $this->variant2->id, 1);

        $resSuccess = $this->cartService->applyCoupon($cart, 'HERITAGE10');
        $this->assertTrue($resSuccess['success']);
        // 10% of 1200 = 120 (does NOT apply to seller 1 item)
        $this->assertEquals(120.00, $resSuccess['discount']);
    }

    public function test_cart_full_page_and_ajax_endpoints(): void
    {
        $this->actingAs($this->customerUser);

        // Add items via HTTP POST /cart/add
        $response = $this->post(route('cart.add'), [
            'variant_id' => $this->variant1->id,
            'quantity' => 2,
        ]);
        $response->assertRedirect();

        // View full cart page /cart
        $cartPage = $this->get(route('cart.index'));
        $cartPage->assertStatus(200)
            ->assertSee('Shopping Cart')
            ->assertSee('Fresh Farm Whole Milk 1L')
            ->assertSee('Fast Mart Superstore');

        // Test JSON API endpoint /api/storefront/cart/summary
        $apiSummary = $this->getJson(route('storefront.cart.summary'));
        $apiSummary->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('cart.item_count', 2);
    }

    public function test_wishlist_toggle_move_to_cart_and_share_link(): void
    {
        // 1. Authenticated customer toggles wishlist
        $this->actingAs($this->customerUser);

        $responseToggle = $this->postJson(route('wishlist.toggle'), [
            'product_id' => $this->product1->id,
        ]);
        $responseToggle->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('is_wishlisted', true)
            ->assertJsonPath('wishlist_count', 1);

        $this->assertDatabaseHas('wishlists', [
            'customer_id' => $this->customer->id,
            'product_id' => $this->product1->id,
        ]);

        // 2. View wishlist page /wishlist
        $wishlistPage = $this->get(route('wishlist.index'));
        $wishlistPage->assertStatus(200)
            ->assertSee('My Wishlist')
            ->assertSee('Fresh Farm Whole Milk 1L')
            ->assertSee('Share Wishlist');

        // 3. Move from wishlist to cart
        $responseMove = $this->post(route('wishlist.move-to-cart', $this->product1->id));
        $responseMove->assertRedirect();

        // Product should be removed from wishlist
        $this->assertDatabaseMissing('wishlists', [
            'customer_id' => $this->customer->id,
            'product_id' => $this->product1->id,
        ]);

        // And present in customer's cart
        $customerCart = Cart::where('user_id', $this->customerUser->id)->first();
        $this->assertNotNull($customerCart);
        $this->assertCount(1, $customerCart->activeItems);

        // 4. Test public shared wishlist link /wishlist/share/{token}
        // Add product 2 to wishlist
        Wishlist::create([
            'customer_id' => $this->customer->id,
            'product_id' => $this->product2->id,
        ]);

        $sharePage = $this->get(route('wishlist.share', ['token' => $this->customer->wishlist_share_token]));
        $sharePage->assertStatus(200)
            ->assertSee('Wishlist shared by')
            ->assertSee('Handmade Kashmiri Pashmina Shawl');
    }
}
