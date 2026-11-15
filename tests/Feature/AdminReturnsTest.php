<?php

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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminReturnsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $customerUser;
    protected Customer $customer;
    protected CustomerAddress $address;
    protected Seller $seller;
    protected Store $store;
    protected InventoryLocation $location;
    protected Product $product;
    protected ProductVariant $variant;
    protected Inventory $inventory;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create([
            'email' => 'admin_returns_' . uniqid() . '@example.com',
            'status' => 'active',
        ]);
        $this->admin->assignRole('super_admin');

        $this->customerUser = User::factory()->create([
            'name' => 'Neha Gupta',
            'email' => 'neha.returns@example.com',
            'wallet_balance' => 0.00,
        ]);
        $this->customer = Customer::create([
            'user_id' => $this->customerUser->id,
        ]);
        $this->address = CustomerAddress::create([
            'user_id' => $this->customerUser->id,
            'customer_id' => $this->customer->id,
            'name' => 'Neha Gupta',
            'phone' => '9876543210',
            'line1' => 'Flat 101, Lake View',
            'city' => 'Delhi',
            'state' => 'Delhi',
            'pincode' => '110001',
            'is_default' => true,
        ]);

        $sellerUser = User::factory()->create();
        $this->seller = Seller::create([
            'user_id' => $sellerUser->id,
            'company_name' => 'Tech Retailers Hub',
            'business_name' => 'Tech Hub Online',
            'legal_name' => 'Tech Hub Retailers Pvt Ltd',
            'slug' => 'tech-hub-online',
            'status' => SellerStatus::APPROVED,
        ]);
        $this->store = Store::create([
            'seller_id' => $this->seller->id,
            'name' => 'Tech Store Delhi',
            'slug' => 'tech-store-delhi',
            'status' => StoreStatus::ACTIVE,
            'delivery_radius_km' => 15.0,
        ]);
        $this->location = InventoryLocation::create([
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'name' => 'Delhi Main Hub',
            'code' => 'DEL-01',
            'type' => InventoryLocationType::WAREHOUSE,
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Smart Gadgets',
            'slug' => 'smart-gadgets',
            'is_active' => true,
        ]);
        $this->product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $category->id,
            'title' => 'Smart Fitness Band 5',
            'slug' => 'smart-fitness-band-5',
            'is_active' => true,
            'is_returnable' => true,
            'return_window_days' => 7,
        ]);
        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'SFB-05-BLK',
            'name' => 'Black Strap',
            'selling_price' => 1499.00,
            'mrp' => 1999.00,
            'cost_price' => 900.00,
            'is_active' => true,
        ]);
        $this->inventory = Inventory::create([
            'variant_id' => $this->variant->id,
            'location_id' => $this->location->id,
            'quantity_on_hand' => 15,
            'quantity_reserved' => 0,
            'reorder_threshold' => 2,
        ]);
    }

    protected function createReturn(ReturnStatus $status = ReturnStatus::REQUESTED, QCStatus $qc = QCStatus::PENDING): OrderReturn
    {
        $order = Order::create([
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'customer_id' => $this->customer->id,
            'customer_address_id' => $this->address->id,
            'address_snapshot' => ['name' => 'Neha Gupta', 'phone' => '9876543210'],
            'subtotal' => 1499.00,
            'tax' => 0.00,
            'delivery_fee' => 0.00,
            'discount' => 0.00,
            'total' => 1499.00,
            'status' => OrderStatus::DELIVERED,
            'payment_status' => PaymentStatus::PAID,
            'payment_method' => PaymentMethod::UPI,
        ]);

        Payment::create([
            'order_id' => $order->id,
            'user_id' => $this->customerUser->id,
            'amount' => 1499.00,
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
            'subtotal' => 1499.00,
            'commission_amount' => 149.90,
            'delivered_at' => Carbon::now()->subDays(2),
        ]);

        $item = OrderItem::create([
            'fulfilment_order_id' => $fo->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'title' => $this->product->title,
            'variant_name' => $this->variant->name,
            'sku' => $this->variant->sku,
            'price' => 1499.00,
            'qty' => 1,
            'tax' => 0.00,
            'discount' => 0.00,
            'total' => 1499.00,
        ]);

        return OrderReturn::create([
            'return_number' => 'RET-' . strtoupper(uniqid()),
            'order_item_id' => $item->id,
            'user_id' => $this->customerUser->id,
            'qty' => 1,
            'reason' => 'Defective battery backup',
            'customer_notes' => 'Battery drains within 2 hours',
            'refund_amount' => 1499.00,
            'refund_mode' => 'wallet',
            'status' => $status,
            'qc_status' => $qc,
        ]);
    }

    public function test_admin_can_view_returns_list_with_metrics(): void
    {
        $return = $this->createReturn();

        $response = $this->actingAs($this->admin)->get(route('admin.returns.index'));

        $response->assertOk();
        $response->assertSee('Returns & RMA Oversight');
        $response->assertSee($return->return_number);
        $response->assertSee('Neha Gupta');
        $response->assertSee('Smart Fitness Band 5');
    }

    public function test_admin_can_filter_returns_by_status_and_search(): void
    {
        $ret1 = $this->createReturn(ReturnStatus::REQUESTED);
        $ret2 = $this->createReturn(ReturnStatus::REJECTED);

        // Filter by status
        $response = $this->actingAs($this->admin)->get(route('admin.returns.index', ['status' => 'requested']));
        $response->assertOk();
        $response->assertSee($ret1->return_number);
        $response->assertDontSee($ret2->return_number);

        // Search by return number
        $response = $this->actingAs($this->admin)->get(route('admin.returns.index', ['search' => $ret2->return_number]));
        $response->assertOk();
        $response->assertSee($ret2->return_number);
        $response->assertDontSee($ret1->return_number);
    }

    public function test_admin_can_view_return_details_page(): void
    {
        $return = $this->createReturn();

        $response = $this->actingAs($this->admin)->get(route('admin.returns.show', $return));

        $response->assertOk();
        $response->assertSee($return->return_number);
        $response->assertSee('Dispute resolution console');
        $response->assertSee('Defective battery backup');
        $response->assertSee('Tech Hub Online');
    }

    public function test_admin_can_override_and_approve_return(): void
    {
        $return = $this->createReturn(ReturnStatus::REJECTED);

        $response = $this->actingAs($this->admin)->post(route('admin.returns.override', $return), [
            'action' => 'approve',
            'notes' => 'Seller unfairly rejected valid battery defect claim',
        ]);

        $response->assertRedirect();
        $this->assertContains($return->fresh()->status, [ReturnStatus::APPROVED, ReturnStatus::REVERSE_PICKUP_SCHEDULED]);
        $this->assertStringContainsString('Seller unfairly rejected', $return->fresh()->admin_notes);
    }

    public function test_admin_can_override_and_reject_return(): void
    {
        $return = $this->createReturn(ReturnStatus::REQUESTED);

        $response = $this->actingAs($this->admin)->post(route('admin.returns.override', $return), [
            'action' => 'reject',
            'notes' => 'Customer reported physical damage not covered',
        ]);

        $response->assertRedirect();
        $this->assertEquals(ReturnStatus::REJECTED, $return->fresh()->status);
        $this->assertStringContainsString('physical damage not covered', $return->fresh()->admin_notes);
    }

    public function test_admin_can_force_refund_return(): void
    {
        $return = $this->createReturn(ReturnStatus::REQUESTED);

        $response = $this->actingAs($this->admin)->post(route('admin.returns.force_refund', $return), [
            'amount' => 1499.00,
            'is_resellable' => 1,
            'notes' => 'Executive override for VIP customer satisfaction',
        ]);

        $response->assertRedirect();
        $this->assertEquals(ReturnStatus::REFUNDED, $return->fresh()->status);
        $this->assertEquals(1499.00, (float) $this->customerUser->fresh()->wallet_balance);
    }

    public function test_admin_can_export_returns_csv(): void
    {
        $return = $this->createReturn();

        $response = $this->actingAs($this->admin)->get(route('admin.returns.export'));

        $response->assertOk();
        $this->assertTrue(str_contains((string) $response->headers->get('content-type'), 'text/csv'));
        $this->assertStringContainsString($return->return_number, $response->streamedContent());
        $this->assertStringContainsString('Smart Fitness Band 5', $response->streamedContent());
    }
}
