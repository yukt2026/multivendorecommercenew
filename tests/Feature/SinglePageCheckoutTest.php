<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\FulfilmentType;
use App\Enums\InventoryLocationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\SellerStatus;
use App\Enums\StoreStatus;
use App\Events\OrderPlaced;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\CommissionEntry;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\FulfilmentOrder;
use App\Models\Inventory;
use App\Models\InventoryLocation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\Store;
use App\Models\StoreTiming;
use App\Models\User;
use App\Services\Fulfilment\FulfilmentService;
use App\Services\Storefront\CartService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class SinglePageCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Customer $customer;
    protected CustomerAddress $address;
    protected Seller $localSeller;
    protected Store $localStore;
    protected InventoryLocation $storeLocation;
    protected Seller $nationalSeller;
    protected InventoryLocation $warehouseLocation;
    protected Product $quickProduct;
    protected ProductVariant $quickVariant;
    protected Product $nationalProduct;
    protected ProductVariant $nationalVariant;
    protected FulfilmentService $fulfilmentService;
    protected CartService $cartService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fulfilmentService = app(FulfilmentService::class);
        $this->cartService = app(CartService::class);

        // Settings
        Setting::set('base_delivery_fee', 40.00);
        Setting::set('free_delivery_threshold', 499.00);
        Setting::set('max_cod_amount', 5000.00);
        Setting::set('cod_enabled', true);
        Setting::set('default_commission_pct', 10.00);

        // Customer & Address (Bengaluru Koramangala 12.9352, 77.6245)
        $this->user = User::factory()->create([
            'email' => 'shopper@onestall.in',
            'phone' => '9123456780',
            'status' => 'active',
        ]);
        $this->customer = Customer::create([
            'user_id' => $this->user->id,
        ]);
        $this->address = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'name' => 'Aditi Sharma',
            'phone' => '9123456780',
            'line1' => 'Flat 402, Green Glen Apartments',
            'line2' => '80ft Road, 4th Block Koramangala',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560034',
            'lat' => 12.9352,
            'lng' => 77.6245,
            'is_default' => true,
        ]);

        // Category
        $category = Category::create([
            'name' => 'Daily Essentials',
            'slug' => 'daily-essentials',
            'is_active' => true,
        ]);

        // 1. Local Hyperlocal Store (1.5 km away in Koramangala: 12.9340, 77.6101)
        $localUser = User::factory()->create(['email' => 'local_seller@onestall.in']);
        $this->localSeller = Seller::create([
            'user_id' => $localUser->id,
            'business_name' => 'Speedy Mart Hyperlocal',
            'legal_name' => 'Speedy Mart Retail LLP',
            'status' => SellerStatus::APPROVED,
        ]);
        $this->localStore = Store::create([
            'seller_id' => $this->localSeller->id,
            'name' => 'Speedy Mart 4th Block',
            'slug' => 'speedy-mart-4th-block',
            'status' => StoreStatus::ACTIVE,
            'is_active' => true,
            'accepts_quick_commerce' => true,
            'delivery_radius_km' => 8.0,
        ]);
        // Set store open 7 days a week (09:00 - 23:00)
        for ($day = 0; $day <= 6; $day++) {
            StoreTiming::create([
                'store_id' => $this->localStore->id,
                'day_of_week' => $day,
                'open_time' => '00:00:00',
                'close_time' => '23:59:59',
                'is_closed' => false,
            ]);
        }
        $this->storeLocation = InventoryLocation::create([
            'seller_id' => $this->localSeller->id,
            'store_id' => $this->localStore->id,
            'type' => InventoryLocationType::STORE,
            'name' => 'Speedy Mart Store Shelf',
            'lat' => 12.9340,
            'lng' => 77.6101,
            'is_active' => true,
        ]);

        // 2. National Warehouse (200km away in Chennai)
        $nationalUser = User::factory()->create(['email' => 'national_seller@onestall.in']);
        $this->nationalSeller = Seller::create([
            'user_id' => $nationalUser->id,
            'business_name' => 'Artisans of India',
            'legal_name' => 'Artisans of India Pvt Ltd',
            'status' => SellerStatus::APPROVED,
        ]);
        $this->warehouseLocation = InventoryLocation::create([
            'seller_id' => $this->nationalSeller->id,
            'type' => InventoryLocationType::WAREHOUSE,
            'name' => 'National Central Depot',
            'lat' => 13.0827,
            'lng' => 80.2707,
            'is_active' => true,
        ]);

        // Quick Product
        $this->quickProduct = Product::create([
            'seller_id' => $this->localSeller->id,
            'category_id' => $category->id,
            'title' => 'Organic Cow Milk 1L',
            'slug' => 'organic-cow-milk-1l',
            'type' => ProductType::SIMPLE,
            'status' => ProductStatus::ACTIVE,
            'is_quick_capable' => true,
            'is_cod_allowed' => true,
            'sku' => 'OMILK-1L',
        ]);
        $this->quickVariant = ProductVariant::create([
            'product_id' => $this->quickProduct->id,
            'name' => '1 Litre',
            'sku' => 'OMILK-1L-01',
            'selling_price' => 70.00,
            'mrp' => 80.00,
            'is_default' => true,
            'is_active' => true,
        ]);

        // National Product
        $this->nationalProduct = Product::create([
            'seller_id' => $this->nationalSeller->id,
            'category_id' => $category->id,
            'title' => 'Brass Pooja Diya 12-inch',
            'slug' => 'brass-pooja-diya-12-inch',
            'type' => ProductType::SIMPLE,
            'status' => ProductStatus::ACTIVE,
            'is_quick_capable' => false,
            'is_cod_allowed' => true,
            'sku' => 'BRASS-DIYA-12',
        ]);
        $this->nationalVariant = ProductVariant::create([
            'product_id' => $this->nationalProduct->id,
            'name' => 'Antique Gold',
            'sku' => 'BRASS-DIYA-12-GLD',
            'selling_price' => 850.00,
            'mrp' => 1100.00,
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    public function test_fulfilment_service_all_local_scenario(): void
    {
        // 10 units at local store
        Inventory::create([
            'variant_id' => $this->quickVariant->id,
            'location_id' => $this->storeLocation->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
            'low_stock_threshold' => 2,
        ]);

        $items = [
            ['variant_id' => $this->quickVariant->id, 'quantity' => 2],
        ];

        $plan = $this->fulfilmentService->createPlan($items, $this->address->toArray());

        $this->assertFalse($plan->hasUnfulfillable());
        $this->assertCount(1, $plan->groups);
        $this->assertTrue($plan->allQuick);

        $group = $plan->groups[0];
        $this->assertEquals(FulfilmentType::QUICK, $group->fulfilmentType);
        $this->assertEquals($this->localStore->id, $group->storeId);
        $this->assertStringContainsString('Quick Delivery', $group->deliveryBadge);
        $this->assertLessThanOrEqual(30, $group->estimatedMinutes);
        $this->assertCount(1, $group->items);
        $this->assertEquals(2, $group->items[0]['quantity']);
    }

    public function test_fulfilment_service_mixed_scenario_splits_into_two_packages(): void
    {
        // Local inventory for quick product
        Inventory::create([
            'variant_id' => $this->quickVariant->id,
            'location_id' => $this->storeLocation->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
            'low_stock_threshold' => 2,
        ]);

        // Warehouse inventory for national product
        Inventory::create([
            'variant_id' => $this->nationalVariant->id,
            'location_id' => $this->warehouseLocation->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
            'low_stock_threshold' => 1,
        ]);

        $items = [
            ['variant_id' => $this->quickVariant->id, 'quantity' => 2],
            ['variant_id' => $this->nationalVariant->id, 'quantity' => 1],
        ];

        $plan = $this->fulfilmentService->createPlan($items, $this->address->toArray());

        $this->assertFalse($plan->hasUnfulfillable());
        // Should produce 2 distinct fulfilment packages!
        $this->assertCount(2, $plan->groups);
        $this->assertFalse($plan->allQuick);

        $quickPkg = collect($plan->groups)->firstWhere('fulfilmentType', FulfilmentType::QUICK);
        $standardPkg = collect($plan->groups)->firstWhere('fulfilmentType', FulfilmentType::STANDARD);

        $this->assertNotNull($quickPkg);
        $this->assertNotNull($standardPkg);
        $this->assertEquals($this->localSeller->id, $quickPkg->sellerId);
        $this->assertEquals($this->nationalSeller->id, $standardPkg->sellerId);
    }

    public function test_fulfilment_service_nothing_available_scenario(): void
    {
        // Inventory exists but quantity is 0
        Inventory::create([
            'variant_id' => $this->quickVariant->id,
            'location_id' => $this->storeLocation->id,
            'quantity_on_hand' => 0,
            'quantity_reserved' => 0,
            'low_stock_threshold' => 2,
        ]);

        $items = [
            ['variant_id' => $this->quickVariant->id, 'quantity' => 1],
        ];

        $plan = $this->fulfilmentService->createPlan($items, $this->address->toArray());

        $this->assertTrue($plan->hasUnfulfillable());
        $this->assertCount(1, $plan->unfulfillableItems);
        $this->assertEquals($this->quickVariant->id, $plan->unfulfillableItems[0]['variant_id']);
    }

    public function test_fulfilment_service_store_closed_falls_back(): void
    {
        // Mark all store timings as closed
        StoreTiming::where('store_id', $this->localStore->id)->update(['is_closed' => true]);

        // Stock exists at local store AND at regional warehouse
        Inventory::create([
            'variant_id' => $this->quickVariant->id,
            'location_id' => $this->storeLocation->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
            'low_stock_threshold' => 2,
        ]);

        $regionalWarehouse = InventoryLocation::create([
            'seller_id' => $this->localSeller->id,
            'type' => InventoryLocationType::WAREHOUSE,
            'name' => 'Regional Hub',
            'is_active' => true,
        ]);

        Inventory::create([
            'variant_id' => $this->quickVariant->id,
            'location_id' => $regionalWarehouse->id,
            'quantity_on_hand' => 20,
            'quantity_reserved' => 0,
            'low_stock_threshold' => 5,
        ]);

        $items = [
            ['variant_id' => $this->quickVariant->id, 'quantity' => 2],
        ];

        $plan = $this->fulfilmentService->createPlan($items, $this->address->toArray());

        $this->assertFalse($plan->hasUnfulfillable());
        // Since local store is closed, it successfully routed to warehouse with standard delivery!
        $this->assertCount(1, $plan->groups);
        $this->assertEquals(FulfilmentType::STANDARD, $plan->groups[0]->fulfilmentType);
        $this->assertEquals($regionalWarehouse->id, $plan->groups[0]->inventoryLocationId);
    }

    public function test_fulfilment_service_partial_stock_across_locations(): void
    {
        // Local store has only 2 units
        Inventory::create([
            'variant_id' => $this->quickVariant->id,
            'location_id' => $this->storeLocation->id,
            'quantity_on_hand' => 2,
            'quantity_reserved' => 0,
            'low_stock_threshold' => 1,
        ]);

        // Warehouse has 10 units
        Inventory::create([
            'variant_id' => $this->quickVariant->id,
            'location_id' => $this->warehouseLocation->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
            'low_stock_threshold' => 1,
        ]);

        // Customer requests 5 units
        $items = [
            ['variant_id' => $this->quickVariant->id, 'quantity' => 5],
        ];

        $plan = $this->fulfilmentService->createPlan($items, $this->address->toArray());

        $this->assertFalse($plan->hasUnfulfillable());
        // 2 units fulfilled quick from store + 3 units fulfilled standard from warehouse = 2 packages
        $this->assertCount(2, $plan->groups);
        $totalAllocated = 0;
        foreach ($plan->groups as $g) {
            $totalAllocated += $g->items[0]['quantity'];
        }
        $this->assertEquals(5, $totalAllocated);
    }

    public function test_place_order_action_atomic_transaction_and_splitting(): void
    {
        Event::fake([OrderPlaced::class]);

        // Seed inventory
        $inv1 = Inventory::create([
            'variant_id' => $this->quickVariant->id,
            'location_id' => $this->storeLocation->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
            'low_stock_threshold' => 2,
        ]);

        $inv2 = Inventory::create([
            'variant_id' => $this->nationalVariant->id,
            'location_id' => $this->warehouseLocation->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
            'low_stock_threshold' => 2,
        ]);

        // Prepare customer cart
        $cart = $this->cartService->getActiveCart($this->user, 'test-cart-session-123');
        $this->cartService->addItem($cart, $this->quickVariant->id, 2); // 70 * 2 = 140
        $this->cartService->addItem($cart, $this->nationalVariant->id, 1); // 850 * 1 = 850
        // Total subtotal = 990 (qualifies for free delivery over 499)

        $idempotencyKey = 'order-idemp-' . Str::uuid()->toString();

        $action = app(\App\Actions\Order\PlaceOrderAction::class);
        $order = $action->execute(
            customer: $this->customer,
            address: $this->address,
            cart: $cart,
            paymentMethod: 'online',
            idempotencyKey: $idempotencyKey,
            notes: 'Leave at front door please'
        );

        // 1. Parent Order assertions
        $this->assertNotNull($order->id);
        $this->assertStringStartsWith('OS-', $order->order_number);
        $this->assertEquals(990.00, (float) $order->subtotal);
        $this->assertEquals(0.00, (float) $order->delivery_fee);
        $this->assertEquals(990.00, (float) $order->total);
        $this->assertEquals(OrderStatus::PLACED, $order->status);
        $this->assertEquals(PaymentMethod::UPI, $order->payment_method);
        $this->assertEquals($idempotencyKey, $order->idempotency_key);

        // 2. Order Splitting assertions: Exactly 2 Fulfilment Orders
        $this->assertCount(2, $order->fulfilmentOrders);

        $foQuick = $order->fulfilmentOrders()->where('fulfilment_type', FulfilmentType::QUICK)->first();
        $this->assertNotNull($foQuick);
        $this->assertEquals($this->localSeller->id, $foQuick->seller_id);
        $this->assertEquals($this->localStore->id, $foQuick->store_id);
        $this->assertEquals(140.00, (float) $foQuick->subtotal);

        $foStandard = $order->fulfilmentOrders()->where('fulfilment_type', FulfilmentType::STANDARD)->first();
        $this->assertNotNull($foStandard);
        $this->assertEquals($this->nationalSeller->id, $foStandard->seller_id);
        $this->assertEquals(850.00, (float) $foStandard->subtotal);

        // 3. Stock Reservation assertions
        $this->assertEquals(2, $inv1->fresh()->quantity_reserved);
        $this->assertEquals(1, $inv2->fresh()->quantity_reserved);

        // 4. Commission Entry assertions
        $this->assertDatabaseHas('commission_entries', ['fulfilment_order_id' => $foQuick->id]);
        $this->assertDatabaseHas('commission_entries', ['fulfilment_order_id' => $foStandard->id]);

        // 5. Status History assertions
        $this->assertDatabaseHas('order_status_history', [
            'orderable_type' => Order::class,
            'orderable_id' => $order->id,
            'to_status' => OrderStatus::PLACED->value,
        ]);
        $this->assertDatabaseHas('order_status_history', [
            'orderable_type' => FulfilmentOrder::class,
            'orderable_id' => $foQuick->id,
            'to_status' => OrderStatus::PLACED->value,
        ]);

        // 6. Active Cart cleared
        $this->assertCount(0, $cart->fresh()->activeItems);

        // 7. Event dispatched
        Event::assertDispatched(OrderPlaced::class);

        // 8. Idempotency test: duplicate call returns same order without creating new one
        $orderAgain = $action->execute(
            customer: $this->customer,
            address: $this->address,
            cart: $cart,
            paymentMethod: 'online',
            idempotencyKey: $idempotencyKey
        );
        $this->assertEquals($order->id, $orderAgain->id);
        $this->assertEquals(1, Order::where('idempotency_key', $idempotencyKey)->count());
    }

    public function test_checkout_screen_and_address_addition_flow(): void
    {
        $this->actingAs($this->user);

        // Seed inventory & cart
        Inventory::create([
            'variant_id' => $this->quickVariant->id,
            'location_id' => $this->storeLocation->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
            'low_stock_threshold' => 2,
        ]);

        $cart = $this->cartService->getActiveCart($this->user, 'test-session-cart');
        $this->cartService->addItem($cart, $this->quickVariant->id, 2);

        // 1. Visit /checkout
        $response = $this->get(route('checkout.index'));
        $response->assertStatus(200)
            ->assertSee('Secure Checkout')
            ->assertSee('Aditi Sharma')
            ->assertSee('Flat 402, Green Glen Apartments')
            ->assertSee('Delivery Packages')
            ->assertSee('Place Order Now');

        // 2. Add new address via POST /checkout/address
        $addressRes = $this->post(route('checkout.address.add'), [
            'name' => 'Aditi Office',
            'phone' => '9888877777',
            'type' => 'work',
            'line1' => 'Tower B, Tech Park',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560103',
            'lat' => 12.9279,
            'lng' => 77.6826,
            'is_default' => 0,
        ]);
        $addressRes->assertRedirect();

        $this->assertDatabaseHas('customer_addresses', [
            'customer_id' => $this->customer->id,
            'name' => 'Aditi Office',
            'pincode' => '560103',
        ]);
    }

    public function test_cod_eligibility_enforcement(): void
    {
        $this->actingAs($this->user);

        // Set high price product that exceeds 5000 COD limit
        $expensiveProduct = Product::create([
            'seller_id' => $this->nationalSeller->id,
            'category_id' => Category::first()->id,
            'title' => 'Silk Saree with Pure Zari',
            'slug' => 'silk-saree-pure-zari',
            'type' => ProductType::SIMPLE,
            'status' => ProductStatus::ACTIVE,
            'is_cod_allowed' => true,
            'sku' => 'SAREE-GOLD',
        ]);
        $expensiveVariant = ProductVariant::create([
            'product_id' => $expensiveProduct->id,
            'name' => 'Gold Woven',
            'sku' => 'SAREE-GOLD-01',
            'selling_price' => 7500.00,
            'mrp' => 9000.00,
            'is_default' => true,
            'is_active' => true,
        ]);

        Inventory::create([
            'variant_id' => $expensiveVariant->id,
            'location_id' => $this->warehouseLocation->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
            'low_stock_threshold' => 1,
        ]);

        $cart = $this->cartService->getActiveCart($this->user, 'test-expensive-cart');
        $this->cartService->addItem($cart, $expensiveVariant->id, 1); // 7500 > 5000 COD limit

        // Checkout page shows COD is disabled
        $checkoutPage = $this->get(route('checkout.index'));
        $checkoutPage->assertStatus(200)
            ->assertSee('Not Eligible')
            ->assertSee('exceeds the maximum COD limit');

        // Attempting to place order with COD fails validation
        $placeRes = $this->post(route('checkout.place-order'), [
            'address_id' => $this->address->id,
            'payment_method' => 'cod',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $placeRes->assertSessionHas('error');
        $this->assertDatabaseMissing('orders', ['customer_id' => $this->customer->id]);
    }

    public function test_checkout_place_order_and_success_screen_flow(): void
    {
        $this->actingAs($this->user);

        Inventory::create([
            'variant_id' => $this->quickVariant->id,
            'location_id' => $this->storeLocation->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
            'low_stock_threshold' => 2,
        ]);

        $cart = $this->cartService->getActiveCart($this->user, 'test-success-session');
        $this->cartService->addItem($cart, $this->quickVariant->id, 2);

        $idempotencyKey = 'idemp-web-' . Str::uuid()->toString();

        $response = $this->post(route('checkout.place-order'), [
            'address_id' => $this->address->id,
            'payment_method' => 'cod',
            'idempotency_key' => $idempotencyKey,
            'notes' => 'Call before arrival',
        ]);

        $order = Order::where('idempotency_key', $idempotencyKey)->first();
        $this->assertNotNull($order);

        $response->assertRedirect(route('checkout.success', $order->order_number));

        // Visit success confirmation page
        $successPage = $this->get(route('checkout.success', $order->order_number));
        $successPage->assertStatus(200)
            ->assertSee('Thank You For Your Order!')
            ->assertSee($order->order_number)
            ->assertSee('Order Confirmed')
            ->assertSee('Organic Cow Milk 1L')
            ->assertSee('Speedy Mart Hyperlocal');
    }
}
