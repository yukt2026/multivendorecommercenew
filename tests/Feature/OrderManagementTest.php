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
use App\Enums\ReturnStatus;
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
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\Store;
use App\Models\User;
use App\Notifications\OrderStatusUpdatedNotification;
use App\Services\Inventory\InventoryService;
use App\Services\Order\Exceptions\IllegalOrderTransitionException;
use App\Services\Order\OrderStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $customerUser;
    protected Customer $customer;
    protected CustomerAddress $address;
    protected User $sellerUser;
    protected Seller $seller;
    protected Store $store;
    protected InventoryLocation $location;
    protected User $adminUser;
    protected Product $product;
    protected ProductVariant $variant;
    protected OrderStateMachine $stateMachine;
    protected InventoryService $inventoryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stateMachine = app(OrderStateMachine::class);
        $this->inventoryService = app(InventoryService::class);

        // 1. Customer User
        $this->customerUser = User::factory()->create([
            'name' => 'John Customer',
            'email' => 'customer@onestall.local',
            'phone' => '9876543210',
            'status' => 'active',
        ]);
        $this->customer = Customer::create([
            'user_id' => $this->customerUser->id,
            'loyalty_points' => 0,
        ]);
        $this->address = CustomerAddress::create([
            'user_id' => $this->customerUser->id,
            'customer_id' => $this->customer->id,
            'name' => 'John Customer',
            'phone' => '9876543210',
            'line1' => 'Plot 45, Indiranagar 100ft Rd',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560038',
            'latitude' => 12.9716,
            'longitude' => 77.6412,
            'is_default' => true,
        ]);

        // 2. Seller User
        $this->sellerUser = User::factory()->create([
            'name' => 'Rajesh Merchant',
            'email' => 'seller@onestall.local',
            'status' => 'active',
        ]);
        $this->seller = Seller::create([
            'user_id' => $this->sellerUser->id,
            'business_name' => 'Indiranagar Express Supermarket',
            'legal_name' => 'Indiranagar Express Pvt Ltd',
            'slug' => 'indiranagar-express',
            'status' => SellerStatus::APPROVED,
        ]);
        $this->store = Store::create([
            'seller_id' => $this->seller->id,
            'name' => 'Indiranagar Dark Store #01',
            'slug' => 'indiranagar-dark-store-01',
            'status' => StoreStatus::ACTIVE,
            'accepts_quick_commerce' => true,
            'latitude' => 12.9720,
            'longitude' => 77.6415,
            'delivery_radius_km' => 8.0,
        ]);
        $this->location = InventoryLocation::create([
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'name' => 'Aisle A - Quick Pick',
            'code' => 'LOC-IND-01',
            'type' => InventoryLocationType::STORE,
            'is_active' => true,
        ]);

        // 3. Admin User
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->adminUser = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@onestall.local',
            'status' => 'active',
        ]);
        $this->adminUser->assignRole('super_admin');

        // 4. Category & Product
        $category = Category::create([
            'name' => 'Beverages',
            'slug' => 'beverages',
            'is_active' => true,
        ]);
        $this->product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $category->id,
            'title' => 'Cold Brew Coffee 250ml',
            'slug' => 'cold-brew-coffee-250ml',
            'type' => ProductType::SIMPLE,
            'status' => ProductStatus::ACTIVE,
        ]);
        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'name' => 'Standard Can',
            'sku' => 'BREW-250ML',
            'selling_price' => 120.00,
            'mrp' => 150.00,
            'is_active' => true,
        ]);

        $inv = Inventory::create([
            'variant_id' => $this->variant->id,
            'location_id' => $this->location->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
            'low_stock_threshold' => 10,
        ]);
    }

    /**
     * Create test order helper.
     */
    protected function createOrder(
        FulfilmentType $type = FulfilmentType::QUICK,
        string $foStatus = 'placed',
        OrderStatus $orderStatus = OrderStatus::PLACED
    ): array {
        $order = Order::create([
            'order_number' => 'ORD-' . strtoupper(uniqid()),
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
            'subtotal' => 240.00,
            'delivery_fee' => 30.00,
            'discount' => 0.00,
            'tax' => 12.00,
            'total' => 282.00,
            'status' => $orderStatus,
            'payment_status' => PaymentStatus::PAID,
            'payment_method' => PaymentMethod::UPI,
        ]);

        $fo = FulfilmentOrder::create([
            'order_id' => $order->id,
            'fulfilment_order_number' => 'FO-' . strtoupper(uniqid()),
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'inventory_location_id' => $this->location->id,
            'fulfilment_type' => $type,
            'subtotal' => 240.00,
            'delivery_fee' => 30.00,
            'commission_amount' => 24.00,
            'seller_payable' => 246.00,
            'status' => $foStatus,
        ]);

        OrderItem::create([
            'fulfilment_order_id' => $fo->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'title' => $this->product->title,
            'variant_name' => $this->variant->name,
            'sku' => $this->variant->sku,
            'qty' => 2,
            'price' => 120.00,
            'tax' => 6.00,
            'discount' => 0.00,
            'total' => 240.00,
        ]);

        $inv = Inventory::where('variant_id', $this->variant->id)->where('location_id', $this->location->id)->first();
        $this->inventoryService->reserve($inv, 2, $order->order_number);

        return [$order, $fo];
    }

    // =========================================================================
    // 1. CUSTOMER ORDER MANAGEMENT TESTS
    // =========================================================================

    public function test_customer_can_view_orders_list_with_status_filtering_and_search(): void
    {
        [$order1, $fo1] = $this->createOrder(FulfilmentType::QUICK, 'placed', OrderStatus::PLACED);
        [$order2, $fo2] = $this->createOrder(FulfilmentType::STANDARD, 'delivered', OrderStatus::DELIVERED);

        $response = $this->actingAs($this->customerUser)->get(route('orders.index'));

        $response->assertStatus(200);
        $response->assertViewIs('storefront.orders.index');
        $response->assertSee($order1->order_number);
        $response->assertSee($order2->order_number);

        // Filter by delivered
        $delivResponse = $this->actingAs($this->customerUser)->get(route('orders.index', ['status' => 'delivered']));
        $delivResponse->assertSee($order2->order_number);
        $delivResponse->assertDontSee($order1->order_number);

        // Search by order number
        $searchResponse = $this->actingAs($this->customerUser)->get(route('orders.index', ['search' => $order1->order_number]));
        $searchResponse->assertSee($order1->order_number);
        $searchResponse->assertDontSee($order2->order_number);
    }

    public function test_customer_can_view_order_details_with_per_fulfilment_order_timeline(): void
    {
        [$order, $fo] = $this->createOrder(FulfilmentType::QUICK, 'store_accepted', OrderStatus::PROCESSING);

        $response = $this->actingAs($this->customerUser)->get(route('orders.show', $order->order_number));

        $response->assertStatus(200);
        $response->assertViewIs('storefront.orders.show');
        $response->assertSee($order->order_number);
        $response->assertSee($fo->fulfilment_order_number);
        $response->assertSee('Store Accepted');
        $response->assertSee('Download Tax Invoice');
        $response->assertSee('Cancel Order');
    }

    public function test_customer_can_download_tax_invoice_pdf(): void
    {
        [$order, $fo] = $this->createOrder();

        $response = $this->actingAs($this->customerUser)->get(route('orders.invoice', $order->order_number));

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString("Tax-Invoice-{$order->order_number}.pdf", (string) $response->headers->get('Content-Disposition'));
    }

    public function test_customer_can_cancel_order_while_allowed_which_releases_inventory(): void
    {
        [$order, $fo] = $this->createOrder(FulfilmentType::QUICK, 'placed', OrderStatus::PLACED);

        $invBefore = Inventory::where('variant_id', $this->variant->id)->where('location_id', $this->location->id)->first();
        $this->assertEquals(2, $invBefore->quantity_reserved);

        $response = $this->actingAs($this->customerUser)->post(route('orders.cancel', $order->order_number), [
            'reason' => 'Changed my mind before dispatch',
        ]);

        $response->assertRedirect(route('orders.show', $order->order_number));
        $response->assertSessionHas('success');

        $order->refresh();
        $fo->refresh();

        $this->assertEquals(OrderStatus::CANCELLED, $order->status);
        $this->assertEquals('cancelled', $fo->status);

        // Reserved stock must have been released back!
        $invAfter = Inventory::where('variant_id', $this->variant->id)->where('location_id', $this->location->id)->first();
        $this->assertEquals(0, $invAfter->quantity_reserved);

        // Status history recorded
        $this->assertDatabaseHas('order_status_history', [
            'orderable_type' => Order::class,
            'orderable_id' => $order->id,
            'to_status' => OrderStatus::CANCELLED->value,
        ]);
    }

    public function test_customer_cannot_cancel_order_after_already_delivered(): void
    {
        [$order, $fo] = $this->createOrder(FulfilmentType::QUICK, 'delivered', OrderStatus::DELIVERED);

        $response = $this->actingAs($this->customerUser)->post(route('orders.cancel', $order->order_number), [
            'reason' => 'Cancel request',
        ]);

        $response->assertSessionHas('error');
        $order->refresh();
        $this->assertEquals(OrderStatus::DELIVERED, $order->status);
    }

    public function test_customer_can_reorder_items_into_cart(): void
    {
        [$order, $fo] = $this->createOrder();

        $response = $this->actingAs($this->customerUser)->post(route('orders.reorder', $order->order_number));

        $response->assertRedirect(route('cart.index'));
        $response->assertSessionHas('success');

        $cartResponse = $this->actingAs($this->customerUser)->get(route('cart.index'));
        $cartResponse->assertSee($this->product->title);
    }

    public function test_customer_can_track_shipment_and_view_milestones(): void
    {
        [$order, $fo] = $this->createOrder(FulfilmentType::QUICK, 'rider_assigned', OrderStatus::PROCESSING);

        $response = $this->actingAs($this->customerUser)->get(route('orders.track', [$order->order_number, $fo->fulfilment_order_number]));

        $response->assertStatus(200);
        $response->assertViewIs('storefront.orders.track');
        $response->assertSee($fo->fulfilment_order_number);
        $response->assertSee('Rider Assigned');
    }

    public function test_customer_can_submit_return_request_for_delivered_order(): void
    {
        [$order, $fo] = $this->createOrder(FulfilmentType::STANDARD, 'delivered', OrderStatus::DELIVERED);
        $item = $fo->items->first();

        $response = $this->actingAs($this->customerUser)->post(route('orders.return', [$order->order_number, $fo->fulfilment_order_number]), [
            'order_item_id' => $item->id,
            'reason' => 'Item arrived damaged or leaking',
        ]);

        $response->assertRedirect(route('orders.show', $order->order_number));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('returns', [
            'order_item_id' => $item->id,
            'reason' => 'Item arrived damaged or leaking',
            'status' => ReturnStatus::REQUESTED->value,
        ]);
    }

    // =========================================================================
    // 2. SELLER ORDER MANAGEMENT TESTS
    // =========================================================================

    public function test_seller_can_view_orders_with_tabs_all_quick_online_pickup(): void
    {
        [$orderQ, $foQ] = $this->createOrder(FulfilmentType::QUICK, 'placed');
        [$orderS, $foS] = $this->createOrder(FulfilmentType::STANDARD, 'placed');

        // All tab
        $response = $this->actingAs($this->sellerUser)->get(route('seller.orders.index'));
        $response->assertStatus(200);
        $response->assertViewIs('seller.orders.index');
        $response->assertSee($foQ->fulfilment_order_number);
        $response->assertSee($foS->fulfilment_order_number);

        // Quick tab
        $qResponse = $this->actingAs($this->sellerUser)->get(route('seller.orders.index', ['tab' => 'quick']));
        $qResponse->assertSee($foQ->fulfilment_order_number);
        $qResponse->assertDontSee($foS->fulfilment_order_number);

        // Online tab
        $sResponse = $this->actingAs($this->sellerUser)->get(route('seller.orders.index', ['tab' => 'online']));
        $sResponse->assertSee($foS->fulfilment_order_number);
        $sResponse->assertDontSee($foQ->fulfilment_order_number);
    }

    public function test_seller_cannot_view_or_transition_another_sellers_orders(): void
    {
        $otherSellerUser = User::factory()->create(['status' => 'active']);
        $otherSeller = Seller::create([
            'user_id' => $otherSellerUser->id,
            'business_name' => 'Competitor Mart',
            'legal_name' => 'Competitor Mart LLC',
            'slug' => 'competitor-mart',
            'status' => SellerStatus::APPROVED,
        ]);

        [$order, $fo] = $this->createOrder();

        // Attempting to view order belonging to $this->seller as $otherSellerUser must be forbidden
        $response = $this->actingAs($otherSellerUser)->get(route('seller.orders.show', $fo));
        $response->assertStatus(403);

        $transResponse = $this->actingAs($otherSellerUser)->post(route('seller.orders.transition', $fo), [
            'target_status' => 'store_accepted',
        ]);
        $transResponse->assertStatus(403);
    }

    public function test_seller_can_transition_quick_order_through_full_lifecycle(): void
    {
        [$order, $fo] = $this->createOrder(FulfilmentType::QUICK, 'placed', OrderStatus::PLACED);

        // 1. Accept Order: placed -> store_accepted
        $this->actingAs($this->sellerUser)
            ->post(route('seller.orders.transition', $fo), ['target_status' => 'store_accepted'])
            ->assertRedirect();
        $this->assertEquals('store_accepted', $fo->fresh()->status);
        $this->assertEquals(OrderStatus::PROCESSING, $order->fresh()->status);

        // 2. Mark Packing: store_accepted -> packing
        $this->actingAs($this->sellerUser)
            ->post(route('seller.orders.transition', $fo), ['target_status' => 'packing'])
            ->assertRedirect();
        $this->assertEquals('packing', $fo->fresh()->status);

        // 3. Mark Ready for Pickup: packing -> ready
        $this->actingAs($this->sellerUser)
            ->post(route('seller.orders.transition', $fo), ['target_status' => 'ready'])
            ->assertRedirect();
        $this->assertEquals('ready', $fo->fresh()->status);

        // 4. Assign Rider: ready -> rider_assigned
        $partner = DeliveryPartner::create(['name' => 'Shadowfax Express', 'phone' => '9988776655', 'is_active' => true]);
        $this->actingAs($this->sellerUser)
            ->post(route('seller.orders.assign_staff', $fo), ['delivery_partner_id' => $partner->id])
            ->assertRedirect();
        $this->assertEquals('rider_assigned', $fo->fresh()->status);

        // 5. Rider Picked Up: rider_assigned -> picked_up
        $this->actingAs($this->sellerUser)
            ->post(route('seller.orders.transition', $fo), ['target_status' => 'picked_up'])
            ->assertRedirect();
        $this->assertEquals('picked_up', $fo->fresh()->status);

        // 6. Out for Delivery: picked_up -> out_for_delivery
        $this->actingAs($this->sellerUser)
            ->post(route('seller.orders.transition', $fo), ['target_status' => 'out_for_delivery'])
            ->assertRedirect();
        $this->assertEquals('out_for_delivery', $fo->fresh()->status);
        $this->assertEquals(OrderStatus::OUT_FOR_DELIVERY, $order->fresh()->status);

        // 7. Delivered: out_for_delivery -> delivered
        $this->actingAs($this->sellerUser)
            ->post(route('seller.orders.transition', $fo), ['target_status' => 'delivered'])
            ->assertRedirect();
        $this->assertEquals('delivered', $fo->fresh()->status);
        $this->assertEquals(OrderStatus::DELIVERED, $order->fresh()->status);
        $this->assertNotNull($fo->fresh()->delivered_at);
    }

    public function test_seller_can_transition_standard_order_through_full_lifecycle(): void
    {
        [$order, $fo] = $this->createOrder(FulfilmentType::STANDARD, 'placed', OrderStatus::PLACED);

        // 1. Confirmed
        $this->actingAs($this->sellerUser)
            ->post(route('seller.orders.transition', $fo), ['target_status' => 'confirmed'])
            ->assertRedirect();
        $this->assertEquals('confirmed', $fo->fresh()->status);

        // 2. Packed
        $this->actingAs($this->sellerUser)
            ->post(route('seller.orders.transition', $fo), ['target_status' => 'packed'])
            ->assertRedirect();
        $this->assertEquals('packed', $fo->fresh()->status);

        // 3. Ready to Ship
        $this->actingAs($this->sellerUser)
            ->post(route('seller.orders.transition', $fo), ['target_status' => 'ready_to_ship'])
            ->assertRedirect();
        $this->assertEquals('ready_to_ship', $fo->fresh()->status);

        // 4. In Transit (picked_up -> in_transit)
        $this->actingAs($this->sellerUser)
            ->post(route('seller.orders.transition', $fo), ['target_status' => 'picked_up'])
            ->assertRedirect();
        $this->actingAs($this->sellerUser)
            ->post(route('seller.orders.transition', $fo), ['target_status' => 'in_transit'])
            ->assertRedirect();
        $this->assertEquals('in_transit', $fo->fresh()->status);

        // 5. Out for Delivery
        $this->actingAs($this->sellerUser)
            ->post(route('seller.orders.transition', $fo), ['target_status' => 'out_for_delivery'])
            ->assertRedirect();
        $this->assertEquals('out_for_delivery', $fo->fresh()->status);

        // 6. Delivered
        $this->actingAs($this->sellerUser)
            ->post(route('seller.orders.transition', $fo), ['target_status' => 'delivered'])
            ->assertRedirect();
        $this->assertEquals('delivered', $fo->fresh()->status);
        $this->assertEquals(OrderStatus::DELIVERED, $order->fresh()->status);
    }

    public function test_seller_reject_quick_order_triggers_auto_reroute_hook(): void
    {
        [$order, $fo] = $this->createOrder(FulfilmentType::QUICK, 'placed', OrderStatus::PLACED);

        // Create an alternative eligible nearby store for reroute
        $altSeller = Seller::create([
            'user_id' => User::factory()->create(['status' => 'active'])->id,
            'business_name' => 'Koramangala Backup Store',
            'legal_name' => 'Koramangala Backup Pvt Ltd',
            'slug' => 'koramangala-backup',
            'status' => SellerStatus::APPROVED,
        ]);
        $altStore = Store::create([
            'seller_id' => $altSeller->id,
            'name' => 'Koramangala Express Store #02',
            'slug' => 'koramangala-express-02',
            'status' => StoreStatus::ACTIVE,
            'accepts_quick_commerce' => true,
            'latitude' => 12.9352,
            'longitude' => 77.6245,
            'delivery_radius_km' => 7.0,
        ]);

        $response = $this->actingAs($this->sellerUser)
            ->post(route('seller.orders.transition', $fo), [
                'target_status' => 'rejected',
                'note' => 'Store closing early for inventory audit',
            ]);

        $response->assertRedirect();

        $fo->refresh();
        // The auto-reroute service transferred the package to the backup store and re-queued as placed
        $this->assertEquals($altStore->id, $fo->store_id);
        $this->assertEquals('placed', $fo->status);

        $this->assertDatabaseHas('order_status_history', [
            'orderable_type' => FulfilmentOrder::class,
            'orderable_id' => $fo->id,
            'to_status' => 'placed',
        ]);
    }

    public function test_seller_can_download_packing_slip_invoice_and_shipping_label_pdfs(): void
    {
        [$order, $fo] = $this->createOrder();

        $slip = $this->actingAs($this->sellerUser)->get(route('seller.orders.packing_slip', $fo));
        $slip->assertStatus(200);
        $this->assertEquals('application/pdf', $slip->headers->get('Content-Type'));

        $inv = $this->actingAs($this->sellerUser)->get(route('seller.orders.invoice', $fo));
        $inv->assertStatus(200);
        $this->assertEquals('application/pdf', $inv->headers->get('Content-Type'));

        $label = $this->actingAs($this->sellerUser)->get(route('seller.orders.shipping_label', $fo));
        $label->assertStatus(200);
        $this->assertEquals('application/pdf', $label->headers->get('Content-Type'));
    }

    public function test_seller_pending_orders_count_api_for_topbar_badge_and_sound(): void
    {
        [$order, $fo] = $this->createOrder(FulfilmentType::QUICK, 'placed');

        $response = $this->actingAs($this->sellerUser)->getJson(route('seller.orders.pending_count'));

        $response->assertStatus(200);
        $response->assertJson([
            'pending_count' => 1,
            'has_fresh' => true,
        ]);
    }

    // =========================================================================
    // 3. ADMIN ORDER MANAGEMENT TESTS
    // =========================================================================

    public function test_admin_can_view_global_orders_list_with_metrics_and_filters(): void
    {
        [$order, $fo] = $this->createOrder();

        $response = $this->actingAs($this->adminUser)->get(route('admin.orders.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.orders.index');
        $response->assertSee($order->order_number);
        $response->assertSee('Total Orders');
        $response->assertSee('Gross Merchandise Value');
    }

    public function test_admin_can_view_master_order_console_with_complete_audit_trail(): void
    {
        [$order, $fo] = $this->createOrder();

        $response = $this->actingAs($this->adminUser)->get(route('admin.orders.show', $order));

        $response->assertStatus(200);
        $response->assertViewIs('admin.orders.show');
        $response->assertSee($order->order_number);
        $response->assertSee($fo->fulfilment_order_number);
        $response->assertSee('Gateway Audit');
        $response->assertSee('Override Status');
    }

    public function test_admin_can_override_order_status_with_mandatory_reason(): void
    {
        [$order, $fo] = $this->createOrder(FulfilmentType::QUICK, 'placed', OrderStatus::PLACED);

        $response = $this->actingAs($this->adminUser)->post(route('admin.orders.override_status', $order), [
            'status' => OrderStatus::DELIVERED->value,
            'reason' => 'Customer confirmed manual delivery pickup directly at warehouse',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertEquals(OrderStatus::DELIVERED, $order->status);

        // Audit trail recorded with [ADMIN OVERRIDE]
        $this->assertDatabaseHas('order_status_history', [
            'orderable_type' => Order::class,
            'orderable_id' => $order->id,
            'to_status' => OrderStatus::DELIVERED->value,
            'note' => '[ADMIN OVERRIDE]: Customer confirmed manual delivery pickup directly at warehouse',
        ]);
    }

    public function test_admin_can_add_internal_notes_to_order(): void
    {
        [$order, $fo] = $this->createOrder();

        $response = $this->actingAs($this->adminUser)->post(route('admin.orders.add_note', $order), [
            'note' => 'Spoke to customer on phone. Address confirmed as corner plot opposite bank.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertStringContainsString('corner plot opposite bank', (string) $order->notes);

        $this->assertDatabaseHas('order_status_history', [
            'orderable_type' => Order::class,
            'orderable_id' => $order->id,
            'note' => '[INTERNAL NOTE]: Spoke to customer on phone. Address confirmed as corner plot opposite bank.',
        ]);
    }

    // =========================================================================
    // 4. ORDER STATE MACHINE & ILLEGAL TRANSITIONS TESTS
    // =========================================================================

    public function test_order_state_machine_throws_exception_on_illegal_quick_transition(): void
    {
        [$order, $fo] = $this->createOrder(FulfilmentType::QUICK, 'placed');

        // Cannot jump straight from 'placed' to 'delivered'
        $this->expectException(IllegalOrderTransitionException::class);
        $this->stateMachine->transitionFulfilmentOrder($fo, 'delivered');
    }

    public function test_order_state_machine_throws_exception_on_illegal_standard_transition(): void
    {
        [$order, $fo] = $this->createOrder(FulfilmentType::STANDARD, 'placed');

        // Cannot jump straight from 'placed' to 'in_transit'
        $this->expectException(IllegalOrderTransitionException::class);
        $this->stateMachine->transitionFulfilmentOrder($fo, 'in_transit');
    }

    public function test_order_state_machine_throws_exception_when_transitioning_delivered_order_backwards(): void
    {
        [$order, $fo] = $this->createOrder(FulfilmentType::QUICK, 'delivered');

        // Cannot transition from 'delivered' back to 'store_accepted'
        $this->expectException(IllegalOrderTransitionException::class);
        $this->stateMachine->transitionFulfilmentOrder($fo, 'store_accepted');
    }

    public function test_transition_fires_queued_notifications(): void
    {
        Notification::fake();

        [$order, $fo] = $this->createOrder(FulfilmentType::STANDARD, 'placed', OrderStatus::PLACED);

        $this->stateMachine->transitionOrder($order, OrderStatus::PROCESSING, 'Package picking started');

        Notification::assertSentTo(
            $this->customerUser,
            OrderStatusUpdatedNotification::class,
            function (OrderStatusUpdatedNotification $notification) use ($order) {
                return $notification->order->id === $order->id
                    && $notification->newStatus === OrderStatus::PROCESSING->value;
            }
        );
    }
}
