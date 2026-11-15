<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserStatus;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminCustomersTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $customerUser1;
    protected Customer $customer1;
    protected User $customerUser2;
    protected Customer $customer2;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create([
            'email' => 'admin_customers_' . uniqid() . '@example.com',
            'status' => UserStatus::ACTIVE,
        ]);
        $this->admin->assignRole('super_admin');

        $this->customerUser1 = User::factory()->create([
            'name' => 'Rahul Sharma',
            'email' => 'rahul.sharma@example.com',
            'phone' => '+919876500111',
            'status' => UserStatus::ACTIVE,
        ]);
        $this->customer1 = Customer::create([
            'user_id' => $this->customerUser1->id,
        ]);

        $this->customerUser2 = User::factory()->create([
            'name' => 'Pooja Verma',
            'email' => 'pooja.verma@example.com',
            'phone' => '+919876500222',
            'status' => UserStatus::SUSPENDED,
        ]);
        $this->customer2 = Customer::create([
            'user_id' => $this->customerUser2->id,
        ]);
    }

    public function test_admin_can_view_customers_list_with_metrics(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.customers.index'));

        $response->assertOk();
        $response->assertSee('Customers Directory');
        $response->assertSee('Rahul Sharma');
        $response->assertSee('Pooja Verma');
        $response->assertSee('rahul.sharma@example.com');
        $response->assertSee('pooja.verma@example.com');
    }

    public function test_admin_can_filter_customers_by_status_and_search(): void
    {
        // Filter: Active
        $response = $this->actingAs($this->admin)->get(route('admin.customers.index', ['status' => 'active']));
        $response->assertOk();
        $response->assertSee('Rahul Sharma');
        $response->assertDontSee('Pooja Verma');

        // Filter: Blocked / Suspended
        $response = $this->actingAs($this->admin)->get(route('admin.customers.index', ['status' => 'blocked']));
        $response->assertOk();
        $response->assertSee('Pooja Verma');
        $response->assertDontSee('Rahul Sharma');

        // Search by phone
        $response = $this->actingAs($this->admin)->get(route('admin.customers.index', ['search' => '9876500111']));
        $response->assertOk();
        $response->assertSee('Rahul Sharma');
        $response->assertDontSee('Pooja Verma');
    }

    public function test_admin_can_view_customer_profile_and_history(): void
    {
        $address = CustomerAddress::create([
            'user_id' => $this->customerUser1->id,
            'customer_id' => $this->customer1->id,
            'name' => 'Rahul Sharma',
            'phone' => '9876500111',
            'line1' => 'Flat 402, Sunshine Apartments',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'pincode' => '400001',
            'is_default' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-CUST-9999',
            'customer_id' => $this->customer1->id,
            'customer_address_id' => $address->id,
            'address_snapshot' => ['name' => 'Rahul Sharma', 'phone' => '9876500111'],
            'subtotal' => 2500.00,
            'tax' => 0.00,
            'delivery_fee' => 0.00,
            'discount' => 0.00,
            'total' => 2500.00,
            'status' => OrderStatus::DELIVERED,
            'payment_status' => PaymentStatus::PAID,
            'payment_method' => PaymentMethod::UPI,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.customers.show', $this->customer1));

        $response->assertOk();
        $response->assertSee('Rahul Sharma');
        $response->assertSee('ORD-CUST-9999');
        $response->assertSee('Sunshine Apartments');
        $response->assertSee('Mumbai');
    }

    public function test_admin_can_toggle_customer_status(): void
    {
        $this->assertEquals(UserStatus::ACTIVE, $this->customerUser1->fresh()->status);

        // Block customer
        $response = $this->actingAs($this->admin)->post(route('admin.customers.toggle_status', $this->customer1));
        $response->assertRedirect();
        $this->assertEquals(UserStatus::SUSPENDED, $this->customerUser1->fresh()->status);

        // Unblock customer
        $response = $this->actingAs($this->admin)->post(route('admin.customers.toggle_status', $this->customer1));
        $response->assertRedirect();
        $this->assertEquals(UserStatus::ACTIVE, $this->customerUser1->fresh()->status);
    }

    public function test_admin_can_export_customers_csv(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.customers.export'));

        $response->assertOk();
        $this->assertTrue(str_contains((string) $response->headers->get('content-type'), 'text/csv'));
        $this->assertStringContainsString('Rahul Sharma', $response->streamedContent());
        $this->assertStringContainsString('pooja.verma@example.com', $response->streamedContent());
    }
}
