<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\FulfilmentType;
use App\Enums\InventoryLocationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\SellerStatus;
use App\Enums\StoreStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\DeliveryPartner;
use App\Models\FulfilmentOrder;
use App\Models\Inventory;
use App\Models\InventoryLocation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerQuickOrdersTest extends TestCase
{
    use RefreshDatabase;

    protected User $sellerUser;
    protected Seller $seller;
    protected Store $store;
    protected InventoryLocation $location;
    protected User $customerUser;
    protected Customer $customer;
    protected CustomerAddress $address;
    protected Product $product;
    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Customer
        $this->customerUser = User::factory()->create([
            'name' => 'Aditi Sharma',
            'email' => 'aditi@onestall.local',
            'phone' => '9876543210',
            'status' => 'active',
        ]);
        $this->customer = Customer::create([
            'user_id' => $this->customerUser->id,
        ]);
        $this->address = CustomerAddress::create([
            'user_id' => $this->customerUser->id,
            'customer_id' => $this->customer->id,
            'name' => 'Aditi Sharma',
            'phone' => '9876543210',
            'line1' => 'Flat 201, Sunshine Heights',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560038',
            'is_default' => true,
        ]);

        // 2. Seller & Store
        $this->sellerUser = User::factory()->create([
            'name' => 'Express Seller',
            'email' => 'seller@express.local',
            'status' => 'active',
        ]);
        $this->seller = Seller::create([
            'user_id' => $this->sellerUser->id,
            'business_name' => 'Express Dark Store',
            'legal_name' => 'Express Retail LLP',
            'slug' => 'express-dark-store',
            'status' => SellerStatus::APPROVED,
        ]);
        $this->store = Store::create([
            'seller_id' => $this->seller->id,
            'name' => 'Koramangala 15m Hub',
            'slug' => 'koramangala-15m-hub',
            'status' => StoreStatus::ACTIVE,
            'accepts_quick_commerce' => true,
            'quick_delivery_enabled' => true,
            'latitude' => 12.9352,
            'longitude' => 77.6245,
            'delivery_radius_km' => 6.0,
            'quick_capacity' => 20,
            'quick_timeout_seconds' => 90,
        ]);
        $this->location = InventoryLocation::create([
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'name' => 'Aisle 1 - Rapid Dispatch',
            'code' => 'LOC-KOR-01',
            'type' => InventoryLocationType::STORE,
            'is_active' => true,
        ]);

        // 3. Product & Inventory
        $category = Category::create([
            'name' => 'Dairy & Breakfast',
            'slug' => 'dairy-breakfast',
            'is_active' => true,
        ]);
        $this->product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $category->id,
            'title' => 'Organic Fresh Milk 500ml',
            'slug' => 'organic-fresh-milk-500ml',
            'type' => ProductType::SIMPLE,
            'status' => ProductStatus::ACTIVE,
            'is_quick_capable' => true,
        ]);
        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'name' => 'Pouch',
            'sku' => 'MILK-500ML',
            'selling_price' => 38.00,
            'mrp' => 40.00,
            'is_active' => true,
        ]);
        Inventory::create([
            'variant_id' => $this->variant->id,
            'location_id' => $this->location->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
            'low_stock_threshold' => 5,
        ]);
    }

    protected function createQuickOrder(string $status = 'placed'): array
    {
        $order = Order::create([
            'order_number' => 'ORD-Q-' . strtoupper(uniqid()),
            'customer_id' => $this->customer->id,
            'customer_address_id' => $this->address->id,
            'address_snapshot' => [
                'name' => $this->address->name,
                'phone' => $this->address->phone,
                'line1' => $this->address->line1,
                'city' => $this->address->city,
                'state' => $this->address->state,
                'pincode' => $this->address->pincode,
            ],
            'subtotal' => 76.00,
            'delivery_fee' => 15.00,
            'discount' => 0.00,
            'tax' => 0.00,
            'total' => 91.00,
            'status' => OrderStatus::PLACED,
            'payment_status' => PaymentStatus::PAID,
            'payment_method' => PaymentMethod::UPI,
        ]);

        $fo = FulfilmentOrder::create([
            'order_id' => $order->id,
            'fulfilment_order_number' => 'FO-Q-' . strtoupper(uniqid()),
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'inventory_location_id' => $this->location->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'subtotal' => 76.00,
            'delivery_fee' => 15.00,
            'commission_amount' => 7.60,
            'seller_payable' => 83.40,
            'status' => $status,
            'delivery_otp' => '4567',
        ]);

        OrderItem::create([
            'fulfilment_order_id' => $fo->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'title' => $this->product->title,
            'variant_name' => $this->variant->name,
            'sku' => $this->variant->sku,
            'price' => 38.00,
            'qty' => 2,
            'total' => 76.00,
        ]);

        return [$order, $fo];
    }

    public function test_seller_can_view_quick_live_console(): void
    {
        [$order, $fo] = $this->createQuickOrder('placed');

        $response = $this->actingAs($this->sellerUser)->get(route('seller.quick.live'));

        $response->assertStatus(200);
        $response->assertViewIs('seller.orders.quick-live');
        $response->assertSee('Quick 15m Dispatch Console');
        $response->assertSee($this->store->name);
        $response->assertSee($fo->fulfilment_order_number);
    }

    public function test_seller_pending_orders_api_returns_json_feed_with_countdowns(): void
    {
        [$order, $fo] = $this->createQuickOrder('placed');

        $response = $this->actingAs($this->sellerUser)->getJson(route('seller.quick.orders_api'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'count',
            'orders' => [
                '*' => [
                    'id',
                    'order_number',
                    'status',
                    'items_count',
                    'items',
                    'subtotal',
                    'delivery_fee',
                    'total',
                    'elapsed_seconds',
                    'remaining_seconds',
                    'customer_name',
                    'customer_address',
                ],
            ],
        ]);
        $response->assertJsonFragment([
            'order_number' => $fo->fulfilment_order_number,
            'status' => 'placed',
            'customer_name' => 'Aditi Sharma',
        ]);
    }

    public function test_seller_can_accept_quick_order(): void
    {
        [$order, $fo] = $this->createQuickOrder('placed');

        $response = $this->actingAs($this->sellerUser)
            ->post(route('seller.orders.accept', $fo));

        $response->assertRedirect();
        $this->assertEquals('store_accepted', $fo->fresh()->status);
        $this->assertEquals(OrderStatus::PROCESSING, $order->fresh()->status);
    }

    public function test_seller_can_pack_quick_order(): void
    {
        [$order, $fo] = $this->createQuickOrder('store_accepted');

        $response = $this->actingAs($this->sellerUser)
            ->post(route('seller.orders.pack', $fo));

        $response->assertRedirect();
        $this->assertEquals('packing', $fo->fresh()->status);
    }

    public function test_seller_can_mark_ready_for_pickup(): void
    {
        [$order, $fo] = $this->createQuickOrder('packing');

        $response = $this->actingAs($this->sellerUser)
            ->post(route('seller.orders.ready', $fo));

        $response->assertRedirect();
        $this->assertContains($fo->fresh()->status, ['ready', 'rider_assigned']);
    }

    public function test_seller_can_handover_order_to_rider(): void
    {
        [$order, $fo] = $this->createQuickOrder('ready');

        $rider = DeliveryPartner::create([
            'name' => 'Suresh Rider',
            'phone' => '9988776655',
            'vehicle' => 'Electric Scooter',
            'vehicle_number' => 'KA-01-EQ-9876',
            'is_active' => true,
            'status' => 'busy',
            'current_lat' => 12.9350,
            'current_lng' => 77.6240,
        ]);

        // Assign rider
        $this->actingAs($this->sellerUser)
            ->post(route('seller.orders.assign_rider', $fo), [
                'delivery_partner_id' => $rider->id,
            ])
            ->assertRedirect();

        $this->assertEquals('rider_assigned', $fo->fresh()->status);

        // Handover order to rider -> picked_up & out_for_delivery
        $this->actingAs($this->sellerUser)
            ->post(route('seller.orders.handover', $fo))
            ->assertRedirect();

        $this->assertEquals('out_for_delivery', $fo->fresh()->status);
        $this->assertEquals(OrderStatus::OUT_FOR_DELIVERY, $order->fresh()->status);
    }

    public function test_seller_can_reject_quick_order_triggering_reroute(): void
    {
        [$order, $fo] = $this->createQuickOrder('placed');

        $response = $this->actingAs($this->sellerUser)
            ->post(route('seller.orders.reject', $fo), [
                'reason' => 'Store at peak capacity',
            ]);

        $response->assertRedirect();
        $this->assertEquals('rejected', $fo->fresh()->status);
    }

    public function test_unauthenticated_user_cannot_access_quick_orders(): void
    {
        $response = $this->get(route('seller.quick.live'));
        $response->assertRedirect(route('seller.login'));
    }
}
