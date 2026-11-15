<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\FulfilmentType;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryPartner;
use App\Models\FulfilmentOrder;
use App\Models\Order;
use App\Models\RiderWallet;
use App\Models\RiderWalletTransaction;
use App\Models\Seller;
use App\Models\Store;
use App\Models\User;
use App\Services\Order\OrderStateMachine;
use App\Services\QuickCommerce\QuickCommerceService;
use App\Services\Rider\RiderEarningsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminFleetManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles and permissions
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $supportRole = Role::firstOrCreate(['name' => 'support_agent', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'delivery_staff', 'guard_name' => 'web']);

        Permission::firstOrCreate(['name' => 'cargo.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'cargo.view', 'guard_name' => 'web']);

        $superAdminRole->givePermissionTo(['cargo.manage', 'cargo.view']);

        $this->adminUser = User::factory()->create([
            'email' => 'fleet_admin@onestall.test',
            'status' => 'active',
        ]);
        $this->adminUser->assignRole($superAdminRole);

        $this->unauthorizedUser = User::factory()->create([
            'email' => 'regular_user@onestall.test',
            'status' => 'active',
        ]);
        $this->unauthorizedUser->assignRole($supportRole);
    }

    public function test_admin_can_view_settlements_overview(): void
    {
        $rider = DeliveryPartner::create([
            'name' => 'Kavita Singh',
            'phone' => '+919988776655',
            'vehicle' => 'Electric Scooter',
            'status' => 'available',
            'is_active' => true,
            'approval_status' => 'approved',
            'current_cash_in_hand' => 1250.00,
        ]);

        $wallet = $rider->ensureWallet();
        $wallet->balance = 3450.00;
        $wallet->total_earned = 5000.00;
        $wallet->save();

        $response = $this->actingAs($this->adminUser)->get(route('admin.delivery_partners.settlements'));

        $response->assertStatus(200);
        $response->assertSee('Rider Wallets &amp; Settlements', false);
        $response->assertSee('Kavita Singh');
        $response->assertSee('3,450.00');
        $response->assertSee('1,250.00');
    }

    public function test_admin_can_approve_pending_rider(): void
    {
        $rider = DeliveryPartner::create([
            'name' => 'Vikas Patil',
            'phone' => '+919876500001',
            'vehicle' => 'Motorcycle',
            'status' => 'offline',
            'is_active' => false,
            'approval_status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.delivery_partners.approve', $rider));

        $response->assertRedirect();
        $rider->refresh();

        $this->assertEquals('approved', $rider->approval_status);
        $this->assertTrue((bool) $rider->is_active);
        $this->assertEquals($this->adminUser->id, $rider->approved_by);
        $this->assertNotNull($rider->approved_at);
    }

    public function test_admin_can_reject_rider_with_reason(): void
    {
        $rider = DeliveryPartner::create([
            'name' => 'Suresh Kumar',
            'phone' => '+919876500002',
            'vehicle' => 'Bicycle',
            'status' => 'offline',
            'is_active' => false,
            'approval_status' => 'under_review',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.delivery_partners.reject', $rider), [
                'rejection_reason' => 'Invalid driving license document submitted.',
            ]);

        $response->assertRedirect();
        $rider->refresh();

        $this->assertEquals('rejected', $rider->approval_status);
        $this->assertFalse((bool) $rider->is_active);
        $this->assertEquals('Invalid driving license document submitted.', $rider->rejection_reason);
    }

    public function test_rejection_requires_valid_reason(): void
    {
        $rider = DeliveryPartner::create([
            'name' => 'Amit Verma',
            'phone' => '+919876500003',
            'vehicle' => 'Electric Scooter',
            'status' => 'offline',
            'approval_status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.delivery_partners.reject', $rider), [
                'rejection_reason' => '',
            ]);

        $response->assertSessionHasErrors();
        $this->assertEquals('pending', $rider->fresh()->approval_status);
    }

    public function test_admin_can_manually_assign_rider_to_quick_order(): void
    {
        $customerUser = User::factory()->create();
        $customer = Customer::create(['user_id' => $customerUser->id]);
        $address = CustomerAddress::create([
            'user_id' => $customerUser->id,
            'customer_id' => $customer->id,
            'name' => 'Test Customer',
            'phone' => '9876543210',
            'line1' => 'MG Road',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560001',
            'lat' => 12.9716,
            'lng' => 77.5946,
        ]);

        $sellerUser = User::factory()->create();
        $seller = Seller::create([
            'user_id' => $sellerUser->id,
            'business_name' => 'Fresh Store',
            'legal_name' => 'Fresh Store Pvt Ltd',
            'seller_type' => \App\Enums\SellerType::MULTI_STORE,
            'slug' => 'fresh-store',
            'phone' => '9876543211',
            'status' => \App\Enums\SellerStatus::APPROVED,
        ]);

        $store = Store::create([
            'seller_id' => $seller->id,
            'name' => 'MG Road Hub',
            'slug' => 'mg-road-hub',
            'latitude' => 12.9720,
            'longitude' => 77.5950,
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-FLT-001',
            'customer_id' => $customer->id,
            'customer_address_id' => $address->id,
            'address_snapshot' => $address->toArray(),
            'subtotal' => 300,
            'shipping_fee' => 30,
            'total' => 330,
            'status' => OrderStatus::PROCESSING,
        ]);

        $fo = FulfilmentOrder::create([
            'fulfilment_order_number' => 'FO-TEST-001',
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'store_id' => $store->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'status' => 'ready_for_pickup',
            'subtotal' => 300,
            'shipping_fee' => 30,
            'total' => 330,
        ]);

        $rider = DeliveryPartner::create([
            'name' => 'Raju Quick',
            'phone' => '+919876500004',
            'vehicle' => 'Electric Scooter',
            'status' => 'available',
            'is_active' => true,
            'approval_status' => 'approved',
            'current_lat' => 12.9722,
            'current_lng' => 77.5952,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.delivery_partners.assign_order', $fo), [
                'delivery_partner_id' => $rider->id,
            ]);

        $response->assertRedirect();

        $assignment = DeliveryAssignment::where('fulfilment_order_id', $fo->id)->first();
        $this->assertNotNull($assignment);
        $this->assertEquals($rider->id, $assignment->delivery_partner_id);
        $this->assertEquals('assigned', $assignment->status->value);
        $this->assertNotNull($assignment->pickup_otp);

        $rider->refresh();
        $this->assertEquals('busy', $rider->status);
        $this->assertEquals(1, $rider->active_orders_count);
    }

    public function test_admin_can_disburse_rider_wallet_payout(): void
    {
        $rider = DeliveryPartner::create([
            'name' => 'Dinesh Chawla',
            'phone' => '+919876500005',
            'vehicle' => 'Motorcycle',
            'status' => 'available',
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        $wallet = $rider->ensureWallet();
        $wallet->balance = 2500.00;
        $wallet->total_earned = 4000.00;
        $wallet->save();

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.delivery_partners.payout', $rider), [
                'amount' => 1500.00,
                'reference' => 'UTR-TEST-8899',
                'notes' => 'Weekly bank transfer disbursement',
            ]);

        $response->assertRedirect();
        $wallet->refresh();

        $this->assertEquals(1000.00, (float) $wallet->balance);

        $tx = RiderWalletTransaction::where('rider_wallet_id', $wallet->id)->latest('id')->first();
        $this->assertNotNull($tx);
        $this->assertEquals('debit', $tx->type);
        $this->assertEquals(1500.00, (float) $tx->amount);
        $this->assertEquals(1000.00, (float) $tx->balance_after);
        $this->assertEquals('bank_withdrawal', $tx->category);
        $this->assertStringContainsString('UTR-TEST-8899', $tx->description);
    }

    public function test_payout_exceeding_wallet_balance_is_blocked(): void
    {
        $rider = DeliveryPartner::create([
            'name' => 'Pooja Nair',
            'phone' => '+919876500006',
            'vehicle' => 'Electric Scooter',
            'status' => 'available',
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        $wallet = $rider->ensureWallet();
        $wallet->balance = 500.00;
        $wallet->save();

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.delivery_partners.payout', $rider), [
                'amount' => 1200.00,
                'reference' => 'UTR-FAIL',
            ]);

        $response->assertSessionHas('error');
        $this->assertEquals(500.00, (float) $wallet->fresh()->balance);
    }

    public function test_unauthorized_user_cannot_manage_fleet(): void
    {
        $rider = DeliveryPartner::create([
            'name' => 'Protected Rider',
            'phone' => '+919876500007',
            'vehicle' => 'Electric Scooter',
            'status' => 'available',
            'is_active' => true,
            'approval_status' => 'pending',
        ]);

        $response = $this->actingAs($this->unauthorizedUser)
            ->post(route('admin.delivery_partners.approve', $rider));

        $response->assertStatus(403);
    }

    public function test_order_cancellation_releases_active_rider_capacity(): void
    {
        $customerUser = User::factory()->create();
        $customer = Customer::create(['user_id' => $customerUser->id]);
        $address = CustomerAddress::create([
            'user_id' => $customerUser->id,
            'customer_id' => $customer->id,
            'name' => 'Indira Customer',
            'phone' => '9876543210',
            'line1' => 'Indiranagar 100ft Rd',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560038',
            'lat' => 12.9716,
            'lng' => 77.5946,
        ]);

        $sellerUser = User::factory()->create();
        $seller = Seller::create([
            'user_id' => $sellerUser->id,
            'business_name' => 'Express Mart',
            'legal_name' => 'Express Mart Pvt Ltd',
            'seller_type' => \App\Enums\SellerType::MULTI_STORE,
            'slug' => 'express-mart',
            'phone' => '9876543212',
            'status' => \App\Enums\SellerStatus::APPROVED,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-FLT-CANCEL-01',
            'customer_id' => $customer->id,
            'customer_address_id' => $address->id,
            'address_snapshot' => $address->toArray(),
            'subtotal' => 200,
            'shipping_fee' => 20,
            'total' => 220,
            'status' => OrderStatus::PROCESSING,
        ]);

        $fo = FulfilmentOrder::create([
            'fulfilment_order_number' => 'FO-TEST-001',
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'status' => 'out_for_delivery',
            'subtotal' => 200,
            'shipping_fee' => 20,
            'total' => 220,
        ]);

        $rider = DeliveryPartner::create([
            'name' => 'Manoj Express',
            'phone' => '+919876500008',
            'vehicle' => 'Electric Scooter',
            'status' => 'busy',
            'active_orders_count' => 1,
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        $assignment = DeliveryAssignment::create([
            'fulfilment_order_id' => $fo->id,
            'delivery_partner_id' => $rider->id,
            'status' => \App\Enums\DeliveryAssignmentStatus::PICKED_UP,
            'delivery_otp' => '1234',
            'assigned_at' => now(),
        ]);

        // Cancel the order via StateMachine
        $stateMachine = app(OrderStateMachine::class);
        $stateMachine->cancelOrder($order, 'Customer requested immediate cancellation', $this->adminUser->id);

        $order->refresh();
        $fo->refresh();
        $rider->refresh();
        $assignment->refresh();

        $this->assertEquals(OrderStatus::CANCELLED, $order->status);
        $this->assertEquals('cancelled', $fo->status);
        $this->assertEquals(\App\Enums\DeliveryAssignmentStatus::CANCELLED, $assignment->status);
        $this->assertStringContainsString('Order cancelled', (string) $assignment->notes);

        // Rider capacity must be released
        $this->assertEquals('available', $rider->status);
        $this->assertEquals(0, $rider->active_orders_count);
    }

    public function test_rider_earnings_and_wallet_crediting_on_delivery(): void
    {
        $customerUser = User::factory()->create();
        $customer = Customer::create(['user_id' => $customerUser->id]);
        $address = CustomerAddress::create([
            'user_id' => $customerUser->id,
            'customer_id' => $customer->id,
            'name' => 'Koramangala Customer',
            'phone' => '9876543210',
            'line1' => 'Koramangala 4th Block',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560034',
            'lat' => 12.9352,
            'lng' => 77.6245,
        ]);

        $sellerUser = User::factory()->create();
        $seller = Seller::create([
            'user_id' => $sellerUser->id,
            'business_name' => 'Fast Store',
            'legal_name' => 'Fast Store Pvt Ltd',
            'seller_type' => \App\Enums\SellerType::MULTI_STORE,
            'slug' => 'fast-store',
            'phone' => '9876543213',
            'status' => \App\Enums\SellerStatus::APPROVED,
        ]);

        $store = Store::create([
            'seller_id' => $seller->id,
            'name' => 'Koramangala Hub',
            'slug' => 'koramangala-hub',
            'latitude' => 12.9340,
            'longitude' => 77.6100,
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-EARNING-01',
            'customer_id' => $customer->id,
            'customer_address_id' => $address->id,
            'address_snapshot' => $address->toArray(),
            'subtotal' => 450,
            'shipping_fee' => 40,
            'total' => 490,
            'status' => OrderStatus::PROCESSING,
        ]);

        $fo = FulfilmentOrder::create([
            'fulfilment_order_number' => 'FO-TEST-001',
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'store_id' => $store->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'status' => 'out_for_delivery',
            'subtotal' => 450,
            'shipping_fee' => 40,
            'total' => 490,
        ]);

        $rider = DeliveryPartner::create([
            'name' => 'Arun Earn',
            'phone' => '+919876500009',
            'vehicle' => 'Electric Scooter',
            'status' => 'busy',
            'active_orders_count' => 1,
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        $fo->delivery_otp = '9988';
        $fo->save();

        $assignment = DeliveryAssignment::create([
            'fulfilment_order_id' => $fo->id,
            'delivery_partner_id' => $rider->id,
            'status' => \App\Enums\DeliveryAssignmentStatus::PICKED_UP,
            'delivery_otp' => '9988',
            'assigned_at' => now(),
        ]);

        $quickService = app(QuickCommerceService::class);
        $result = $quickService->verifyDeliveryOtp($fo, '9988');

        $this->assertTrue($result['success']);
        $assignment->refresh();
        $this->assertEquals(\App\Enums\DeliveryAssignmentStatus::DELIVERED, $assignment->status);
        $this->assertNotNull($assignment->delivered_at);

        // Check Rider Earnings created
        $earning = $assignment->earnings;
        $this->assertNotNull($earning);
        $this->assertEquals($rider->id, $earning->delivery_partner_id);
        $this->assertGreaterThan(0, (float) $earning->total_payout);
        $this->assertEquals('credited', $earning->status);

        // Check Rider Wallet updated
        $wallet = $rider->wallet;
        $this->assertNotNull($wallet);
        $this->assertEquals((float) $earning->total_payout, (float) $wallet->balance);
        $this->assertEquals((float) $earning->total_payout, (float) $wallet->total_earned);

        // Check Wallet Transaction created
        $walletTx = RiderWalletTransaction::where('rider_wallet_id', $wallet->id)->first();
        $this->assertNotNull($walletTx);
        $this->assertEquals('credit', $walletTx->type);
        $this->assertEquals((float) $earning->total_payout, (float) $walletTx->amount);
        $this->assertEquals('delivery_payout', $walletTx->category);
    }

    public function test_admin_can_configure_rider_payout_rates_and_they_apply_dynamically(): void
    {
        // 1. Admin configures new payout rates in Settings
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.settings.update'), [
                'tab' => 'rider_payout',
                'settings' => [
                    'rider_base_payout' => '50.00',
                    'rider_base_threshold_km' => '2.00',
                    'rider_per_km_payout' => '15.00',
                    'rider_surge_bonus' => '25.00',
                    'rider_default_cash_limit' => '8000.00',
                ],
            ]);

        $response->assertRedirect(route('admin.settings.index', ['tab' => 'rider_payout']));
        $this->assertEquals('50.00', \App\Models\Setting::get('rider_base_payout'));
        $this->assertEquals('15.00', \App\Models\Setting::get('rider_per_km_payout'));
        $this->assertEquals('25.00', \App\Models\Setting::get('rider_surge_bonus'));

        // 2. Complete a delivery and verify earnings service calculates according to new rates
        $rider = DeliveryPartner::create([
            'name' => 'Custom Rate Rider',
            'phone' => '+919876500999',
            'vehicle' => 'Electric Bike',
            'status' => 'busy',
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        $seller = Seller::factory()->create();
        $store = Store::factory()->create(['seller_id' => $seller->id]);
        $customer = Customer::factory()->create();
        $address = CustomerAddress::factory()->create(['customer_id' => $customer->id]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-RATE-01',
            'customer_address_id' => $address->id,
            'address_snapshot' => $address->toArray(),
            'subtotal' => 200,
            'shipping_fee' => 25,
            'total' => 225,
            'status' => OrderStatus::PROCESSING,
        ]);

        $fo = FulfilmentOrder::create([
            'fulfilment_order_number' => 'FO-RATE-01',
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'store_id' => $store->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'status' => 'picked_up',
            'delivery_otp' => '1234',
            'subtotal' => 200,
            'shipping_fee' => 25,
            'total' => 225,
        ]);

        $assignment = DeliveryAssignment::create([
            'fulfilment_order_id' => $fo->id,
            'delivery_partner_id' => $rider->id,
            'status' => \App\Enums\DeliveryAssignmentStatus::PICKED_UP,
            'assigned_at' => now(),
        ]);

        $earningsService = app(\App\Services\Rider\RiderEarningsService::class);
        $calc = $earningsService->calculatePayout($assignment, 10.00); // 10 tip

        // Expected: base 50.00 + surge 25.00 + tip 10.00 = 85.00 + distance payout
        $this->assertEquals(50.00, $calc['base']);
        $this->assertEquals(25.00, $calc['surge']);
        $this->assertEquals(10.00, $calc['tip']);
        $this->assertGreaterThanOrEqual(85.00, $calc['total']);

        $earning = $earningsService->creditEarnings($assignment, 10.00);
        $this->assertNotNull($earning);
        $this->assertEquals($calc['total'], (float) $earning->total_payout);
    }
}
