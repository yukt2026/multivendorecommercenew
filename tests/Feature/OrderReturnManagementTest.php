<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\FulfilmentType;
use App\Enums\InventoryLocationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\QCStatus;
use App\Enums\ReturnStatus;
use App\Enums\SellerStatus;
use App\Enums\StoreStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\FulfilmentOrder;
use App\Models\Inventory;
use App\Models\InventoryLocation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\Store;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Notifications\ReturnStatusUpdatedNotification;
use App\Services\Order\ReturnService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderReturnManagementTest extends TestCase
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
    protected Inventory $inventory;
    protected ReturnService $returnService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->returnService = app(ReturnService::class);
        Storage::fake('public');
        Notification::fake();

        // 1. Roles
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        // 2. Customer
        $this->customerUser = User::factory()->create([
            'name' => 'Aditi Sharma',
            'email' => 'aditi@example.com',
            'wallet_balance' => 0.00,
        ]);
        $this->customer = Customer::create([
            'user_id' => $this->customerUser->id,
            'loyalty_points' => 0,
        ]);
        $this->address = CustomerAddress::create([
            'user_id' => $this->customerUser->id,
            'customer_id' => $this->customer->id,
            'name' => 'Aditi Sharma',
            'phone' => '9876543210',
            'line1' => 'Flat 204, Green Heights',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560038',
            'is_default' => true,
        ]);

        // 3. Seller
        $this->sellerUser = User::factory()->create([
            'name' => 'Ramesh Merchant',
            'email' => 'ramesh@merchant.com',
        ]);
        $this->seller = Seller::create([
            'user_id' => $this->sellerUser->id,
            'company_name' => 'Apex Electronics Ltd',
            'business_name' => 'Apex Electronics',
            'legal_name' => 'Apex Retailers Pvt Ltd',
            'slug' => 'apex-electronics',
            'status' => SellerStatus::APPROVED,
        ]);
        $this->store = Store::create([
            'seller_id' => $this->seller->id,
            'name' => 'Apex Hub Bangalore',
            'slug' => 'apex-hub-bangalore',
            'status' => StoreStatus::ACTIVE,
            'delivery_radius_km' => 15.0,
        ]);
        $this->location = InventoryLocation::create([
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'name' => 'Main Warehouse Shelf A',
            'code' => 'APEX-WH-01',
            'type' => InventoryLocationType::WAREHOUSE,
            'is_active' => true,
        ]);

        // 4. Admin
        $this->adminUser = User::factory()->create([
            'name' => 'Operations Director',
            'email' => 'ops@onestall.local',
        ]);
        $this->adminUser->assignRole('super_admin');

        // 5. Product & Variant
        $category = Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
            'is_active' => true,
        ]);
        $this->product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $category->id,
            'title' => 'Wireless Noise Cancelling Headphones',
            'slug' => 'wireless-noise-cancelling-headphones',
            'is_active' => true,
            'is_returnable' => true,
            'return_window_days' => 7,
        ]);
        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'APEX-WNC-01',
            'name' => 'Matte Black',
            'selling_price' => 2999.00,
            'mrp' => 3999.00,
            'cost_price' => 1800.00,
            'is_active' => true,
        ]);

        // 6. Inventory
        $this->inventory = Inventory::create([
            'variant_id' => $this->variant->id,
            'location_id' => $this->location->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
            'reorder_threshold' => 2,
        ]);
    }

    /**
     * Helper to set up a delivered order with item.
     */
    protected function createDeliveredOrder(int $daysAgo = 2, bool $returnable = true): array
    {
        $this->product->update(['is_returnable' => $returnable]);

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
            'subtotal' => 2999.00,
            'tax' => 0.00,
            'delivery_fee' => 0.00,
            'discount' => 0.00,
            'total' => 2999.00,
            'status' => OrderStatus::DELIVERED,
            'payment_status' => PaymentStatus::PAID,
            'payment_method' => PaymentMethod::UPI,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $this->customerUser->id,
            'amount' => 2999.00,
            'currency' => 'INR',
            'payment_method' => PaymentMethod::UPI,
            'gateway' => 'razorpay',
            'gateway_transaction_id' => 'pay_test_' . uniqid(),
            'status' => PaymentStatus::PAID,
        ]);

        $fo = FulfilmentOrder::create([
            'order_id' => $order->id,
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'inventory_location_id' => $this->location->id,
            'fulfilment_order_number' => 'FO-' . strtoupper(uniqid()),
            'fulfilment_type' => FulfilmentType::STANDARD,
            'status' => OrderStatus::DELIVERED,
            'subtotal' => 2999.00,
            'commission_amount' => 299.90,
            'delivered_at' => Carbon::now()->subDays($daysAgo),
        ]);

        $item = OrderItem::create([
            'fulfilment_order_id' => $fo->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'title' => $this->product->title,
            'variant_name' => $this->variant->name,
            'sku' => $this->variant->sku,
            'price' => 2999.00,
            'qty' => 1,
            'tax' => 0.00,
            'discount' => 0.00,
            'total' => 2999.00,
        ]);

        return [$order, $fo, $item, $payment];
    }

    public function test_customer_can_view_return_form_for_delivered_item_within_window(): void
    {
        [$order, $fo, $item] = $this->createDeliveredOrder(daysAgo: 2);

        $response = $this->actingAs($this->customerUser)
            ->get(route('orders.return.form', [$order->order_number, $item->id]));

        $response->assertOk()
            ->assertViewIs('storefront.orders.return-form')
            ->assertSee($this->product->title)
            ->assertSee('Request Item Return');
    }

    public function test_customer_cannot_request_return_when_window_has_expired(): void
    {
        // 10 days ago, with 7 days return window
        [$order, $fo, $item] = $this->createDeliveredOrder(daysAgo: 10);

        $response = $this->actingAs($this->customerUser)
            ->get(route('orders.return.form', [$order->order_number, $item->id]));

        $response->assertRedirect(route('orders.show', $order->order_number))
            ->assertSessionHas('error');
    }

    public function test_customer_cannot_request_return_for_non_returnable_product(): void
    {
        [$order, $fo, $item] = $this->createDeliveredOrder(daysAgo: 1, returnable: false);

        $response = $this->actingAs($this->customerUser)
            ->get(route('orders.return.form', [$order->order_number, $item->id]));

        $response->assertRedirect(route('orders.show', $order->order_number))
            ->assertSessionHas('error');
    }

    public function test_customer_submits_return_with_photos_and_wallet_refund_mode(): void
    {
        [$order, $fo, $item] = $this->createDeliveredOrder(daysAgo: 2);

        $fakePhoto = UploadedFile::fake()->image('defect.jpg');

        $response = $this->actingAs($this->customerUser)
            ->post(route('orders.return.item', [$order->order_number, $item->id]), [
                'reason' => 'Defective / Not working properly',
                'customer_notes' => 'Left earbud has static noise and does not charge.',
                'refund_mode' => 'wallet',
                'photos' => [$fakePhoto],
            ]);

        $this->assertDatabaseHas('returns', [
            'order_item_id' => $item->id,
            'status' => ReturnStatus::REQUESTED->value,
            'refund_mode' => 'wallet',
            'reason' => 'Defective / Not working properly',
        ]);

        $return = OrderReturn::where('order_item_id', $item->id)->first();
        $this->assertNotNull($return);
        $this->assertNotEmpty($return->photos);

        $response->assertRedirect(route('orders.return.track', [$order->order_number, $return->return_number]));
    }

    public function test_customer_can_cancel_unfulfilled_item_before_dispatch(): void
    {
        Http::fake([
            'https://api.razorpay.com/v1/payments/*/refund' => Http::response([
                'id' => 'rfnd_cancel_test',
                'entity' => 'refund',
                'amount' => 299900,
                'currency' => 'INR',
                'payment_id' => 'pay_cancel_test',
                'status' => 'processed',
            ], 200),
        ]);

        $order = Order::create([
            'order_number' => 'ORD-CANCEL-' . strtoupper(uniqid()),
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
            'subtotal' => 2999.00,
            'tax' => 0.00,
            'delivery_fee' => 0.00,
            'discount' => 0.00,
            'total' => 2999.00,
            'status' => OrderStatus::PLACED,
            'payment_status' => PaymentStatus::PAID,
            'payment_method' => PaymentMethod::UPI,
        ]);

        Payment::create([
            'order_id' => $order->id,
            'user_id' => $this->customerUser->id,
            'amount' => 2999.00,
            'currency' => 'INR',
            'payment_method' => PaymentMethod::UPI,
            'gateway' => 'razorpay',
            'gateway_payment_id' => 'pay_cancel_' . uniqid(),
            'gateway_transaction_id' => 'pay_cancel_' . uniqid(),
            'status' => PaymentStatus::PAID,
        ]);

        $fo = FulfilmentOrder::create([
            'order_id' => $order->id,
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'inventory_location_id' => $this->location->id,
            'fulfilment_order_number' => 'FO-CAN-' . strtoupper(uniqid()),
            'fulfilment_type' => FulfilmentType::STANDARD,
            'status' => OrderStatus::PLACED,
            'subtotal' => 2999.00,
            'commission_amount' => 299.90,
        ]);

        $item = OrderItem::create([
            'fulfilment_order_id' => $fo->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'title' => $this->product->title,
            'variant_name' => $this->variant->name,
            'sku' => $this->variant->sku,
            'price' => 2999.00,
            'qty' => 1,
            'tax' => 0.00,
            'discount' => 0.00,
            'total' => 2999.00,
        ]);

        $response = $this->actingAs($this->customerUser)
            ->post(route('orders.cancel.item', [$order->order_number, $item->id]), [
                'reason' => 'Found a better price elsewhere',
            ]);

        $response->assertRedirect(route('orders.show', $order->order_number));

        $this->assertDatabaseHas('returns', [
            'order_item_id' => $item->id,
            'type' => 'cancellation',
            'status' => ReturnStatus::REFUNDED->value,
        ]);
    }

    public function test_customer_can_track_return_status(): void
    {
        [$order, $fo, $item] = $this->createDeliveredOrder(daysAgo: 1);

        $return = OrderReturn::create([
            'return_number' => 'RET-TEST-001',
            'order_item_id' => $item->id,
            'type' => 'return',
            'reason' => 'Defective piece',
            'status' => ReturnStatus::REQUESTED,
            'qc_status' => QCStatus::PENDING,
            'refund_mode' => 'original',
            'refund_amount' => 2999.00,
        ]);

        $response = $this->actingAs($this->customerUser)
            ->get(route('orders.return.track', [$order->order_number, $return->return_number]));

        $response->assertOk()
            ->assertViewIs('storefront.orders.return-track')
            ->assertSee('RET-TEST-001')
            ->assertSee('Return Requested');
    }

    public function test_seller_can_view_returns_and_detail_screen(): void
    {
        [$order, $fo, $item] = $this->createDeliveredOrder(daysAgo: 1);

        $return = OrderReturn::create([
            'return_number' => 'RET-SELLER-01',
            'order_item_id' => $item->id,
            'type' => 'return',
            'reason' => 'Wrong color sent',
            'status' => ReturnStatus::REQUESTED,
            'qc_status' => QCStatus::PENDING,
            'refund_mode' => 'original',
            'refund_amount' => 2999.00,
        ]);

        // Seller index
        $response = $this->actingAs($this->sellerUser)
            ->get(route('seller.returns.index'));

        $response->assertOk()
            ->assertViewIs('seller.returns.index')
            ->assertSee('RET-SELLER-01');

        // Seller show
        $showResponse = $this->actingAs($this->sellerUser)
            ->get(route('seller.returns.show', $return));

        $showResponse->assertOk()
            ->assertViewIs('seller.returns.show')
            ->assertSee('RET-SELLER-01')
            ->assertSee('Approve Return');
    }

    public function test_seller_approves_return_and_schedules_cargo_pickup(): void
    {
        [$order, $fo, $item] = $this->createDeliveredOrder(daysAgo: 1);

        $return = OrderReturn::create([
            'return_number' => 'RET-SELLER-02',
            'order_item_id' => $item->id,
            'type' => 'return',
            'reason' => 'Defective',
            'status' => ReturnStatus::REQUESTED,
            'qc_status' => QCStatus::PENDING,
            'refund_mode' => 'original',
            'refund_amount' => 2999.00,
        ]);

        $response = $this->actingAs($this->sellerUser)
            ->post(route('seller.returns.approve', $return), [
                'seller_notes' => 'Approved. Reverse courier will pick it up tomorrow.',
                'auto_schedule_pickup' => 1,
            ]);

        $response->assertRedirect();

        $return->refresh();
        $this->assertEquals(ReturnStatus::REVERSE_PICKUP_SCHEDULED, $return->status);
        $this->assertNotNull($return->pickup_waybill);
        $this->assertStringStartsWith('REV-', $return->pickup_waybill);
        $this->assertNotNull($return->approved_at);
    }

    public function test_seller_can_reject_return(): void
    {
        [$order, $fo, $item] = $this->createDeliveredOrder(daysAgo: 1);

        $return = OrderReturn::create([
            'return_number' => 'RET-SELLER-03',
            'order_item_id' => $item->id,
            'type' => 'return',
            'reason' => 'No longer needed',
            'status' => ReturnStatus::REQUESTED,
            'qc_status' => QCStatus::PENDING,
            'refund_mode' => 'original',
            'refund_amount' => 2999.00,
        ]);

        $response = $this->actingAs($this->sellerUser)
            ->post(route('seller.returns.reject', $return), [
                'reason' => 'Open-box electronic items cannot be returned for buyer remorse.',
            ]);

        $response->assertRedirect();

        $return->refresh();
        $this->assertEquals(ReturnStatus::REJECTED, $return->status);
        $this->assertStringContainsString('buyer remorse', $return->seller_notes);
    }

    public function test_full_lifecycle_pickup_received_qc_pass_and_restock_refund(): void
    {
        [$order, $fo, $item, $payment] = $this->createDeliveredOrder(daysAgo: 1);

        $initialOnHand = $this->inventory->quantity_on_hand; // 10

        $return = OrderReturn::create([
            'return_number' => 'RET-LIFECYCLE-01',
            'order_item_id' => $item->id,
            'type' => 'return',
            'reason' => 'Ordered by mistake',
            'status' => ReturnStatus::APPROVED,
            'pickup_waybill' => 'REV-999888',
            'qc_status' => QCStatus::PENDING,
            'refund_mode' => 'wallet',
            'refund_amount' => 2999.00,
        ]);

        // 1. Mark Received
        $this->actingAs($this->sellerUser)
            ->post(route('seller.returns.receive', $return))
            ->assertRedirect();

        $return->refresh();
        $this->assertEquals(ReturnStatus::RECEIVED, $return->status);
        $this->assertNotNull($return->received_at);

        // 2. Record QC Passed and Resellable
        $this->actingAs($this->sellerUser)
            ->post(route('seller.returns.record_qc', $return), [
                'outcome' => 'passed',
                'qc_notes' => 'Seals intact, original packaging perfect.',
                'is_resellable' => 1,
            ])
            ->assertRedirect();

        $return->refresh();
        $this->assertEquals(ReturnStatus::QC_PASSED, $return->status);
        $this->assertEquals(QCStatus::PASSED, $return->qc_status);
        $this->assertTrue((bool)$return->is_resellable);

        // 3. Process Refund
        $this->actingAs($this->sellerUser)
            ->post(route('seller.returns.refund', $return))
            ->assertRedirect();

        $return->refresh();
        $this->assertEquals(ReturnStatus::REFUNDED, $return->status);
        $this->assertNotNull($return->refunded_at);

        // 4. Verify Wallet Credited
        $this->customerUser->refresh();
        $this->assertEquals(2999.00, (float) $this->customerUser->wallet_balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $this->customerUser->id,
            'type' => 'credit',
            'amount' => 2999.00,
        ]);

        // 5. Verify Inventory Restocked
        $this->inventory->refresh();
        $this->assertEquals($initialOnHand + 1, $this->inventory->quantity_on_hand);
        $this->assertDatabaseHas('stock_movements', [
            'inventory_id' => $this->inventory->id,
            'type' => 'restock',
            'quantity' => 1,
        ]);
    }

    public function test_admin_dispute_override_and_force_refund(): void
    {
        [$order, $fo, $item] = $this->createDeliveredOrder(daysAgo: 1);

        $return = OrderReturn::create([
            'return_number' => 'RET-DISPUTE-01',
            'order_item_id' => $item->id,
            'type' => 'return',
            'reason' => 'Damaged in courier',
            'status' => ReturnStatus::REJECTED,
            'qc_status' => QCStatus::FAILED,
            'seller_notes' => 'Merchant rejected claim claiming external damage.',
            'refund_mode' => 'wallet',
            'refund_amount' => 2999.00,
        ]);

        // Admin checks index
        $this->actingAs($this->adminUser)
            ->get(route('admin.returns.index'))
            ->assertOk()
            ->assertViewIs('admin.returns.index')
            ->assertSee('RET-DISPUTE-01');

        // Admin overrides and force-refunds
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.returns.force_refund', $return), [
                'amount' => 2999.00,
                'is_resellable' => 0,
                'notes' => 'Customer provided unboxing video proof showing shipping damage prior to delivery.',
            ]);

        $response->assertRedirect();

        $return->refresh();
        $this->assertEquals(ReturnStatus::REFUNDED, $return->status);
        $this->assertStringContainsString('unboxing video proof', $return->admin_notes);

        // Wallet was credited
        $this->customerUser->refresh();
        $this->assertEquals(2999.00, (float) $this->customerUser->wallet_balance);
    }

    public function test_csv_exports_stream_successfully(): void
    {
        [$order, $fo, $item] = $this->createDeliveredOrder(daysAgo: 1);

        OrderReturn::create([
            'return_number' => 'RET-CSV-01',
            'order_item_id' => $item->id,
            'type' => 'return',
            'reason' => 'Defective',
            'status' => ReturnStatus::REQUESTED,
            'qc_status' => QCStatus::PENDING,
            'refund_mode' => 'original',
            'refund_amount' => 2999.00,
        ]);

        // Seller CSV
        $sellerCsv = $this->actingAs($this->sellerUser)
            ->get(route('seller.returns.export'));
        $sellerCsv->assertOk();
        $this->assertEquals('text/csv; charset=utf-8', $sellerCsv->headers->get('content-type'));

        // Admin CSV
        $adminCsv = $this->actingAs($this->adminUser)
            ->get(route('admin.returns.export'));
        $adminCsv->assertOk();
        $this->assertEquals('text/csv; charset=utf-8', $adminCsv->headers->get('content-type'));
    }
}
