<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminPaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $customerUser;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create([
            'email' => 'admin_payments_' . uniqid() . '@example.com',
            'status' => 'active',
        ]);
        $this->admin->assignRole('super_admin');

        $this->customerUser = User::factory()->create([
            'name' => 'Ranjeet Maurya',
            'email' => 'ranjeet.payments@example.com',
            'phone' => '+919876543210',
            'status' => 'active',
        ]);

        $this->customer = Customer::create([
            'user_id' => $this->customerUser->id,
        ]);
    }

    protected function createOrder(float $total = 1500.00): Order
    {
        return Order::create([
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'customer_id' => $this->customer->id,
            'address_snapshot' => [
                'name' => 'Ranjeet Maurya',
                'phone' => '+919876543210',
                'line1' => 'MG Road',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'pincode' => '560001',
            ],
            'subtotal' => $total,
            'discount' => 0.00,
            'delivery_fee' => 0.00,
            'tax' => 0.00,
            'total' => $total,
            'grand_total' => $total,
            'payment_method' => PaymentMethod::UPI,
            'payment_status' => PaymentStatus::PAID,
            'status' => 'processing',
        ]);
    }

    protected function createPayment(array $attributes = []): Payment
    {
        $order = $attributes['order_id'] ?? null ? null : $this->createOrder();

        return Payment::create(array_merge([
            'order_id' => $order ? $order->id : $attributes['order_id'],
            'gateway' => 'razorpay',
            'gateway_order_id' => 'order_rzp_' . uniqid(),
            'gateway_payment_id' => 'pay_rzp_' . uniqid(),
            'method' => PaymentMethod::UPI,
            'amount' => 1500.00,
            'currency' => 'INR',
            'status' => PaymentStatus::PAID,
            'raw_response' => ['status' => 'captured', 'method' => 'upi'],
            'verified_at' => now(),
        ], $attributes));
    }

    public function test_admin_can_view_payments_list_with_metrics(): void
    {
        $this->createPayment(['amount' => 2000.00, 'status' => PaymentStatus::PAID]);
        $this->createPayment(['amount' => 500.00, 'status' => PaymentStatus::PENDING]);

        $response = $this->actingAs($this->admin)->get(route('admin.payments.index'));

        $response->assertOk()
            ->assertViewIs('admin.payments.index')
            ->assertSee('Payments &amp; Transactions Ledger', false)
            ->assertSee('Captured Volume')
            ->assertSee(format_currency(2000.00))
            ->assertSee('Refunds Audit')
            ->assertSee('Export CSV');
    }

    public function test_admin_can_filter_payments_by_gateway_and_status_and_method(): void
    {
        $pRazorpay = $this->createPayment([
            'gateway' => 'razorpay',
            'status' => PaymentStatus::PAID,
            'method' => PaymentMethod::UPI,
            'gateway_payment_id' => 'pay_filter_rzp_1',
        ]);

        $pCashfree = $this->createPayment([
            'gateway' => 'cashfree',
            'status' => PaymentStatus::PENDING,
            'method' => PaymentMethod::CARD,
            'gateway_payment_id' => 'pay_filter_cf_2',
        ]);

        // Filter by gateway
        $resGateway = $this->actingAs($this->admin)->get(route('admin.payments.index', ['gateway' => 'razorpay']));
        $resGateway->assertOk()
            ->assertSee('pay_filter_rzp_1')
            ->assertDontSee('pay_filter_cf_2');

        // Filter by status
        $resStatus = $this->actingAs($this->admin)->get(route('admin.payments.index', ['status' => 'pending']));
        $resStatus->assertOk()
            ->assertSee('pay_filter_cf_2')
            ->assertDontSee('pay_filter_rzp_1');

        // Filter by method
        $resMethod = $this->actingAs($this->admin)->get(route('admin.payments.index', ['method' => 'card']));
        $resMethod->assertOk()
            ->assertSee('pay_filter_cf_2')
            ->assertDontSee('pay_filter_rzp_1');
    }

    public function test_admin_can_search_payments(): void
    {
        $order = $this->createOrder(3000.00);
        $order->update(['order_number' => 'ORD-SPECIAL-8888']);

        $payment = $this->createPayment([
            'order_id' => $order->id,
            'gateway_payment_id' => 'pay_unique_xyz_999',
        ]);

        $otherPayment = $this->createPayment([
            'gateway_payment_id' => 'pay_other_gamma_delta',
        ]);

        // Search by payment ID
        $resId = $this->actingAs($this->admin)->get(route('admin.payments.index', ['search' => '#' . $payment->id]));
        $resId->assertOk()->assertSee('pay_unique_xyz_999')->assertDontSee('pay_other_gamma_delta');

        // Search by gateway payment ID
        $resPayId = $this->actingAs($this->admin)->get(route('admin.payments.index', ['search' => 'pay_unique_xyz_999']));
        $resPayId->assertOk()->assertSee('ORD-SPECIAL-8888')->assertDontSee('pay_other_gamma_delta');

        // Search by order number
        $resOrder = $this->actingAs($this->admin)->get(route('admin.payments.index', ['search' => 'ORD-SPECIAL-8888']));
        $resOrder->assertOk()->assertSee('pay_unique_xyz_999')->assertDontSee('pay_other_gamma_delta');

        // Search by customer name
        $resCust = $this->actingAs($this->admin)->get(route('admin.payments.index', ['search' => 'Ranjeet Maurya']));
        $resCust->assertOk()->assertSee('pay_unique_xyz_999');
    }

    public function test_admin_can_export_payments_csv_respecting_filters(): void
    {
        $this->createPayment(['gateway' => 'razorpay', 'gateway_payment_id' => 'pay_export_target_1']);
        $this->createPayment(['gateway' => 'cashfree', 'gateway_payment_id' => 'pay_export_ignored_2']);

        $response = $this->actingAs($this->admin)->get(route('admin.payments.export', ['gateway' => 'razorpay']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type') ?? '');

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('Payment ID', $content);
        $this->assertStringContainsString('pay_export_target_1', $content);
        $this->assertStringNotContainsString('pay_export_ignored_2', $content);
    }

    public function test_admin_can_view_payment_details_page(): void
    {
        $payment = $this->createPayment([
            'amount' => 1250.00,
            'gateway' => 'razorpay',
            'gateway_payment_id' => 'pay_show_detail_77',
            'gateway_order_id' => 'order_show_detail_77',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.payments.show', $payment));

        $response->assertOk()
            ->assertViewIs('admin.payments.show')
            ->assertSee('Payment Transaction #' . $payment->id)
            ->assertSee('pay_show_detail_77')
            ->assertSee('order_show_detail_77')
            ->assertSee(format_currency(1250.00))
            ->assertSee('Process Refund');
    }

    public function test_admin_can_process_refund_on_paid_payment(): void
    {
        $payment = $this->createPayment([
            'amount' => 1000.00,
            'gateway' => 'razorpay',
            'gateway_payment_id' => 'pay_refund_flow_123',
            'status' => PaymentStatus::PAID,
        ]);

        Http::fake([
            'https://api.razorpay.com/v1/payments/pay_refund_flow_123/refund' => Http::response([
                'id' => 'rfnd_api_success_1',
                'entity' => 'refund',
                'amount' => 40000,
                'currency' => 'INR',
                'payment_id' => 'pay_refund_flow_123',
                'status' => 'processed',
            ], 200),
        ]);

        // Partial refund of 400.00
        $response = $this->actingAs($this->admin)->post(route('admin.payments.refund', $payment), [
            'amount' => 400.00,
            'reason' => 'Defective accessory item returned',
        ]);

        $response->assertRedirect()->assertSessionHas('success');

        $payment->refresh();
        $this->assertEquals(PaymentStatus::PARTIALLY_REFUNDED, $payment->status);
        $this->assertCount(1, $payment->refunds);

        $refund = $payment->refunds->first();
        $this->assertEquals(400.00, (float) $refund->amount);
        $this->assertEquals(RefundStatus::PROCESSED, $refund->status);
        $this->assertEquals('rfnd_api_success_1', $refund->gateway_refund_id);
    }

    public function test_admin_can_process_refund_on_cod_payment(): void
    {
        $payment = $this->createPayment([
            'amount' => 750.00,
            'gateway' => 'cod',
            'method' => PaymentMethod::COD,
            'status' => PaymentStatus::PAID,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.payments.refund', $payment), [
            'amount' => 750.00,
            'reason' => 'Doorstep rejection after delivery fee settled',
        ]);

        $response->assertRedirect()->assertSessionHas('success');

        $payment->refresh();
        $this->assertEquals(PaymentStatus::REFUNDED, $payment->status);
        $this->assertCount(1, $payment->refunds);
        $this->assertEquals('processed', $payment->refunds->first()->status->value);
        $this->assertStringStartsWith('cod_ref_', $payment->refunds->first()->gateway_refund_id);
    }

    public function test_admin_cannot_process_refund_exceeding_balance(): void
    {
        $payment = $this->createPayment([
            'amount' => 500.00,
            'status' => PaymentStatus::PAID,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.payments.refund', $payment), [
            'amount' => 999.00,
            'reason' => 'Excess refund attempt',
        ]);

        $response->assertRedirect()->assertSessionHas('error');
        $this->assertCount(0, $payment->fresh()->refunds);
    }

    public function test_admin_can_view_refunds_audit_page(): void
    {
        $payment = $this->createPayment(['amount' => 800.00]);

        Refund::create([
            'payment_id' => $payment->id,
            'amount' => 800.00,
            'reason' => 'Customer cancelled whole order',
            'status' => RefundStatus::PROCESSED,
            'gateway_refund_id' => 'rfnd_audit_test_99',
            'processed_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.payments.refunds'));

        $response->assertOk()
            ->assertViewIs('admin.payments.refunds')
            ->assertSee('Refunds Audit &amp; Reconciliations', false)
            ->assertSee('rfnd_audit_test_99')
            ->assertSee('Customer cancelled whole order')
            ->assertSee(format_currency(800.00))
            ->assertSee('Export CSV');
    }

    public function test_admin_can_export_refunds_csv(): void
    {
        $payment = $this->createPayment();

        Refund::create([
            'payment_id' => $payment->id,
            'amount' => 350.00,
            'reason' => 'Partial item cancellation',
            'status' => RefundStatus::PROCESSED,
            'gateway_refund_id' => 'rfnd_export_target_350',
            'processed_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.payments.refunds.export'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type') ?? '');

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('Refund ID', $content);
        $this->assertStringContainsString('rfnd_export_target_350', $content);
        $this->assertStringContainsString('Partial item cancellation', $content);
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $response = $this->get(route('admin.payments.index'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_unauthorized_user_cannot_access_payments_module(): void
    {
        $customerUser = User::factory()->create([
            'status' => 'active',
        ]);

        $response = $this->actingAs($customerUser)->get(route('admin.payments.index'));
        $response->assertForbidden();
    }
}
