<?php

namespace Tests\Feature;

use App\Enums\FulfilmentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\FulfilmentOrder;
use App\Models\Order;
use App\Models\Seller;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrdersTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $customerUser;
    protected Customer $customer;
    protected Seller $seller;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create([
            'email' => 'admin_orders_' . uniqid() . '@example.com',
            'status' => 'active',
        ]);
        $this->admin->assignRole('super_admin');

        $this->customerUser = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john.orders@example.com',
            'phone' => '+919876543210',
            'status' => 'active',
        ]);

        $this->customer = Customer::create([
            'user_id' => $this->customerUser->id,
        ]);

        $sellerUser = User::factory()->create([
            'email' => 'seller_orders_' . uniqid() . '@example.com',
            'status' => 'active',
        ]);

        $this->seller = Seller::create([
            'user_id' => $sellerUser->id,
            'business_name' => 'Metro Superstore',
            'legal_name' => 'Metro Superstore Retail Pvt Ltd',
            'slug' => 'metro-superstore-' . uniqid(),
            'status' => 'approved',
        ]);

        $this->store = Store::create([
            'seller_id' => $this->seller->id,
            'name' => 'Metro Store Indiranagar',
            'slug' => 'metro-indiranagar-' . uniqid(),
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    protected function createSampleOrder(array $attributes = [], array $foAttributes = []): Order
    {
        $order = Order::create(array_merge([
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'customer_id' => $this->customer->id,
            'address_snapshot' => [
                'name' => 'John Doe',
                'phone' => '+919876543210',
                'line1' => '100 Feet Road, Indiranagar',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'pincode' => '560038',
            ],
            'subtotal' => 1200.00,
            'discount' => 100.00,
            'delivery_fee' => 40.00,
            'tax' => 60.00,
            'total' => 1200.00,
            'payment_method' => PaymentMethod::UPI,
            'payment_status' => PaymentStatus::PAID,
            'status' => OrderStatus::PROCESSING,
        ], $attributes));

        FulfilmentOrder::create(array_merge([
            'order_id' => $order->id,
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'fulfilment_order_number' => 'FO-' . strtoupper(uniqid()),
            'fulfilment_type' => FulfilmentType::QUICK,
            'subtotal' => 1200.00,
            'commission_amount' => 120.00,
            'seller_payable' => 1080.00,
            'delivery_fee' => 40.00,
            'status' => 'processing',
        ], $foAttributes));

        return $order;
    }

    public function test_admin_can_view_orders_index_with_metrics(): void
    {
        $this->createSampleOrder(['status' => OrderStatus::PROCESSING]);
        $this->createSampleOrder(['status' => OrderStatus::DELIVERED]);

        $response = $this->actingAs($this->admin)->get(route('admin.orders.index'));

        $response->assertStatus(200);
        $response->assertSee('Orders & Fulfilment Central');
        $response->assertSee('Total Orders');
        $response->assertSee('Gross Merchandise Value');
        $response->assertSee('Quick Commerce');
    }

    public function test_admin_can_filter_orders_by_status_and_payment(): void
    {
        $order1 = $this->createSampleOrder([
            'order_number' => 'ORD-FILTER-1',
            'status' => OrderStatus::DELIVERED,
            'payment_status' => PaymentStatus::PAID,
        ]);

        $order2 = $this->createSampleOrder([
            'order_number' => 'ORD-FILTER-2',
            'status' => OrderStatus::CANCELLED,
            'payment_status' => PaymentStatus::REFUNDED,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.orders.index', [
            'status' => 'delivered',
            'payment_status' => 'paid',
        ]));

        $response->assertStatus(200);
        $response->assertSee('ORD-FILTER-1');
        $response->assertDontSee('ORD-FILTER-2');
    }

    public function test_admin_can_filter_orders_by_fulfilment_type(): void
    {
        $quickOrder = $this->createSampleOrder(
            ['order_number' => 'ORD-QUICK-99'],
            ['fulfilment_type' => FulfilmentType::QUICK]
        );

        $standardOrder = $this->createSampleOrder(
            ['order_number' => 'ORD-STD-99'],
            ['fulfilment_type' => FulfilmentType::STANDARD]
        );

        $response = $this->actingAs($this->admin)->get(route('admin.orders.index', [
            'fulfilment_type' => 'quick',
        ]));

        $response->assertStatus(200);
        $response->assertSee('ORD-QUICK-99');
        $response->assertDontSee('ORD-STD-99');
    }

    public function test_admin_can_search_orders_by_number_or_customer(): void
    {
        $order1 = $this->createSampleOrder(['order_number' => 'ORD-UNIQUE-SEARCH-999']);
        $order2 = $this->createSampleOrder(['order_number' => 'ORD-OTHER-000']);

        $response = $this->actingAs($this->admin)->get(route('admin.orders.index', [
            'search' => 'UNIQUE-SEARCH',
        ]));

        $response->assertStatus(200);
        $response->assertSee('ORD-UNIQUE-SEARCH-999');
        $response->assertDontSee('ORD-OTHER-000');
    }

    public function test_admin_can_export_orders_to_csv(): void
    {
        $order = $this->createSampleOrder(['order_number' => 'ORD-CSV-EXPORT-123']);

        $response = $this->actingAs($this->admin)->get(route('admin.orders.export'));

        $response->assertStatus(200);
        $this->assertTrue(str_contains($response->headers->get('content-type'), 'text/csv'));
        $this->assertTrue(str_contains($response->headers->get('content-disposition'), 'attachment; filename='));

        $content = $response->streamedContent();
        $this->assertStringContainsString('Order Number', $content);
        $this->assertStringContainsString('Date Placed', $content);
        $this->assertStringContainsString('ORD-CSV-EXPORT-123', $content);
    }

    public function test_admin_can_view_order_show_console(): void
    {
        $order = $this->createSampleOrder();

        $response = $this->actingAs($this->admin)->get(route('admin.orders.show', $order));

        $response->assertStatus(200);
        $response->assertSee($order->order_number);
        $response->assertSee('Customer & Shipping Profile');
        $response->assertSee('Payment & Gateway Audit');
        $response->assertSee('Split Fulfilment Packages');
        $response->assertSee('Metro Superstore');
    }

    public function test_admin_can_override_order_status_with_reason(): void
    {
        $order = $this->createSampleOrder(['status' => OrderStatus::PROCESSING]);

        $response = $this->actingAs($this->admin)->post(route('admin.orders.override_status', $order), [
            'status' => 'delivered',
            'reason' => 'Customer confirmed delivery in person at store counter.',
        ]);

        $response->assertRedirect();
        $order->refresh();
        $this->assertEquals(OrderStatus::DELIVERED, $order->status);

        // Verify history logged
        $history = $order->statusHistory()->latest()->first();
        $this->assertNotNull($history);
        $this->assertStringContainsString('[ADMIN OVERRIDE]', $history->note);
    }

    public function test_admin_cannot_override_status_without_reason(): void
    {
        $order = $this->createSampleOrder(['status' => OrderStatus::PROCESSING]);

        $response = $this->actingAs($this->admin)->post(route('admin.orders.override_status', $order), [
            'status' => 'delivered',
            'reason' => '', // Empty reason should fail
        ]);

        $response->assertSessionHasErrors('reason');
        $order->refresh();
        $this->assertEquals(OrderStatus::PROCESSING, $order->status);
    }

    public function test_admin_can_add_internal_note(): void
    {
        $order = $this->createSampleOrder();

        $response = $this->actingAs($this->admin)->post(route('admin.orders.add_note', $order), [
            'note' => 'Spoke with delivery supervisor regarding road construction delay.',
        ]);

        $response->assertRedirect();
        $order->refresh();
        $this->assertStringContainsString('road construction delay', $order->notes);
    }

    public function test_admin_can_download_tax_invoice(): void
    {
        $order = $this->createSampleOrder();

        $response = $this->actingAs($this->admin)->get(route('admin.orders.invoice', $order));

        $response->assertStatus(200);
        $this->assertTrue(str_contains($response->headers->get('content-type'), 'application/pdf'));
    }
}
