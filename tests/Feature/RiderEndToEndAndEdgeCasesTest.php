<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DeliveryAssignmentStatus;
use App\Enums\FulfilmentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\SellerStatus;
use App\Enums\SellerType;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryPartner;
use App\Models\FulfilmentOrder;
use App\Models\Order;
use App\Models\RiderEarning;
use App\Models\RiderWallet;
use App\Models\RiderWalletTransaction;
use App\Models\Seller;
use App\Models\Store;
use App\Models\User;
use App\Services\Order\OrderStateMachine;
use App\Services\QuickCommerce\QuickCommerceService;
use App\Services\Rider\RiderEarningsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RiderEndToEndAndEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $sellerUser;
    protected Seller $seller;
    protected Store $store;
    protected User $customerUser;
    protected Customer $customer;
    protected CustomerAddress $customerAddress;

    protected function setUp(): void
    {
        parent::setUp();

        // Roles & Permissions
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'delivery_staff', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);

        Permission::firstOrCreate(['name' => 'cargo.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'cargo.view', 'guard_name' => 'web']);
        $superAdminRole->givePermissionTo(['cargo.manage', 'cargo.view']);

        $this->adminUser = User::factory()->create([
            'email' => 'master_admin@onestall.test',
            'status' => 'active',
        ]);
        $this->adminUser->assignRole($superAdminRole);

        // Seller & Store
        $this->sellerUser = User::factory()->create(['status' => 'active']);
        $this->sellerUser->assignRole('seller');

        $this->seller = Seller::create([
            'user_id' => $this->sellerUser->id,
            'business_name' => 'Super Express Hub',
            'legal_name' => 'Super Express Hub Pvt Ltd',
            'seller_type' => SellerType::MULTI_STORE,
            'slug' => 'super-express-hub',
            'phone' => '9876543210',
            'status' => SellerStatus::APPROVED,
        ]);

        $this->store = Store::create([
            'seller_id' => $this->seller->id,
            'name' => 'Indiranagar Fast Hub',
            'slug' => 'indiranagar-fast-hub',
            'latitude' => 12.9720,
            'longitude' => 77.5950,
            'is_active' => true,
        ]);

        // Customer & Address
        $this->customerUser = User::factory()->create(['status' => 'active']);
        $this->customer = Customer::create(['user_id' => $this->customerUser->id]);
        $this->customerAddress = CustomerAddress::create([
            'user_id' => $this->customerUser->id,
            'customer_id' => $this->customer->id,
            'name' => 'Ananya Sharma',
            'phone' => '9876543299',
            'line1' => 'Flat 302, Palm Heights',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560038',
            'lat' => 12.9790,
            'lng' => 77.6400,
        ]);
    }

    /**
     * Complete Golden E2E Workflow:
     * Admin approves Rider -> Customer places order -> Seller processes order ->
     * Eligible order assigned -> Rider accepts -> Rider picks up -> Rider delivers with OTP ->
     * Order completes -> Rider earnings recorded & wallet credited.
     */
    public function test_complete_golden_rider_workflow(): void
    {
        // 1. Rider applies (Pending KYC)
        $riderUser = User::factory()->create([
            'name' => 'Vimal Quick',
            'phone' => '+919876500010',
            'password' => Hash::make('Password@123'),
            'status' => 'active',
        ]);
        $riderUser->assignRole('delivery_staff');

        $rider = DeliveryPartner::create([
            'user_id' => $riderUser->id,
            'name' => 'Vimal Quick',
            'phone' => '+919876500010',
            'vehicle' => 'Electric Scooter',
            'vehicle_number' => 'KA-01-EQ-5544',
            'status' => 'offline',
            'is_active' => false,
            'approval_status' => 'pending',
            'current_lat' => 12.9722,
            'current_lng' => 77.5952,
        ]);

        // Admin approves Rider
        $approvalResponse = $this->actingAs($this->adminUser)
            ->post(route('admin.delivery_partners.approve', $rider));
        $approvalResponse->assertRedirect();
        $rider->refresh();
        $this->assertEquals('approved', $rider->approval_status);
        $this->assertTrue((bool) $rider->is_active);

        // Rider tests toggle duty (available -> offline -> available)
        $this->withSession(['active_rider_id' => $rider->id])
            ->post(route('rider.toggle_duty'));
        $this->assertEquals('offline', $rider->fresh()->status);

        $this->withSession(['active_rider_id' => $rider->id])
            ->post(route('rider.toggle_duty'));
        $this->assertEquals('available', $rider->fresh()->status);

        // 2. Customer places Quick-Commerce order
        $order = Order::create([
            'order_number' => 'ORD-E2E-GOLDEN-01',
            'customer_id' => $this->customer->id,
            'address_snapshot' => $this->customerAddress->toArray(),
            'subtotal' => 400.00,
            'delivery_fee' => 30.00,
            'total' => 430.00,
            'payment_method' => PaymentMethod::COD,
            'status' => OrderStatus::PROCESSING,
        ]);

        $fo = FulfilmentOrder::create([
            'fulfilment_order_number' => 'FO-E2E-GOLDEN-01',
            'order_id' => $order->id,
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'status' => 'placed',
            'subtotal' => 400.00,
            'delivery_fee' => 30.00,
            'delivery_otp' => '778899',
        ]);

        // 3. Seller processes order: Accept -> Pack -> Ready
        $stateMachine = app(OrderStateMachine::class);
        $stateMachine->transitionFulfilmentOrder($fo, 'store_accepted', 'Seller accepted order', $this->sellerUser->id);
        $stateMachine->transitionFulfilmentOrder($fo, 'packing', 'Seller is packing items', $this->sellerUser->id);
        $stateMachine->transitionFulfilmentOrder($fo, 'ready', 'Items packed and ready for pickup', $this->sellerUser->id);

        $fo->refresh();
        // Transition to 'ready' automatically triggers QuickCommerceService::autoAssignRider()
        $this->assertEquals('rider_assigned', $fo->status);

        $assignment = DeliveryAssignment::where('fulfilment_order_id', $fo->id)->first();
        $this->assertNotNull($assignment);
        $this->assertEquals($rider->id, $assignment->delivery_partner_id);
        $this->assertEquals(DeliveryAssignmentStatus::ASSIGNED, $assignment->status);
        $this->assertNotNull($assignment->pickup_otp);

        $rider->refresh();
        $this->assertEquals('busy', $rider->status);
        $this->assertEquals(1, $rider->active_orders_count);

        // 5. Rider accepts job on mobile portal
        $acceptResponse = $this->withSession(['active_rider_id' => $rider->id])
            ->post(route('rider.accept', $assignment));
        $acceptResponse->assertRedirect();
        $this->assertEquals(DeliveryAssignmentStatus::ACCEPTED, $assignment->fresh()->status);

        // 6. Rider picks up from Dark Store
        $pickupResponse = $this->withSession(['active_rider_id' => $rider->id])
            ->post(route('rider.pickup', $assignment), [
                'pickup_otp' => $assignment->pickup_otp,
            ]);
        $pickupResponse->assertRedirect();

        $assignment->refresh();
        $fo->refresh();
        $this->assertEquals(DeliveryAssignmentStatus::PICKED_UP, $assignment->status);
        $this->assertTrue((bool) $assignment->pickup_otp_verified);
        $this->assertEquals('out_for_delivery', $fo->status);

        // 7. Rider delivers to customer with OTP Handshake
        $deliverResponse = $this->withSession(['active_rider_id' => $rider->id])
            ->post(route('rider.deliver', $assignment), [
                'otp' => '778899',
            ]);
        $deliverResponse->assertRedirect(route('rider.dashboard'));

        $assignment->refresh();
        $fo->refresh();
        $order->refresh();

        // 8. Order & Package completed
        $this->assertEquals(DeliveryAssignmentStatus::DELIVERED, $assignment->status);
        $this->assertEquals('delivered', $fo->status);
        $this->assertEquals(OrderStatus::DELIVERED, $order->status);

        // 9. Rider earnings & wallet recorded
        $earning = $assignment->earnings;
        $this->assertNotNull($earning);
        $this->assertEquals($rider->id, $earning->delivery_partner_id);
        $this->assertGreaterThan(0, (float) $earning->total_payout);
        $this->assertEquals('credited', $earning->status);

        $wallet = $rider->wallet;
        $this->assertNotNull($wallet);
        $this->assertEquals((float) $earning->total_payout, (float) $wallet->balance);

        $tx = RiderWalletTransaction::where('rider_wallet_id', $wallet->id)->first();
        $this->assertNotNull($tx);
        $this->assertEquals('credit', $tx->type);
        $this->assertEquals((float) $earning->total_payout, (float) $tx->amount);

        // COD cash collected recorded
        $this->assertEquals(430.00, (float) $rider->fresh()->current_cash_in_hand);
    }

    /**
     * Test: Rider declines assigned task -> Capacity freed -> Auto-reassigned to next candidate.
     */
    public function test_rejected_assignment_and_automatic_reassignment(): void
    {
        $rider1 = DeliveryPartner::create([
            'name' => 'Rider One',
            'phone' => '+919876500011',
            'vehicle' => 'Motorcycle',
            'status' => 'busy',
            'active_orders_count' => 1,
            'is_active' => true,
            'approval_status' => 'approved',
            'current_lat' => 12.9721,
            'current_lng' => 77.5951,
        ]);

        $rider2 = DeliveryPartner::create([
            'name' => 'Rider Two',
            'phone' => '+919876500012',
            'vehicle' => 'Electric Scooter',
            'status' => 'available',
            'active_orders_count' => 0,
            'is_active' => true,
            'approval_status' => 'approved',
            'current_lat' => 12.9725,
            'current_lng' => 77.5955,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-REASSIGN-01',
            'customer_id' => $this->customer->id,
            'address_snapshot' => $this->customerAddress->toArray(),
            'subtotal' => 200,
            'total' => 200,
            'status' => OrderStatus::PROCESSING,
        ]);

        $fo = FulfilmentOrder::create([
            'fulfilment_order_number' => 'FO-REASSIGN-01',
            'order_id' => $order->id,
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'status' => 'ready',
            'subtotal' => 200,
            'total' => 200,
        ]);

        $assignment = DeliveryAssignment::create([
            'fulfilment_order_id' => $fo->id,
            'delivery_partner_id' => $rider1->id,
            'status' => DeliveryAssignmentStatus::ASSIGNED,
            'assigned_at' => now(),
        ]);

        // Rider 1 declines
        $response = $this->withSession(['active_rider_id' => $rider1->id])
            ->post(route('rider.reject', $assignment), [
                'reason' => 'vehicle_breakdown',
            ]);

        $response->assertRedirect(route('rider.dashboard'));

        // Rider 1 capacity freed
        $rider1->refresh();
        $this->assertEquals('available', $rider1->status);
        $this->assertEquals(0, $rider1->active_orders_count);

        // Package re-assigned to Rider 2
        $newAssignment = DeliveryAssignment::where('fulfilment_order_id', $fo->id)->first();
        $this->assertNotNull($newAssignment);
        $this->assertEquals($rider2->id, $newAssignment->delivery_partner_id);
        $this->assertEquals(DeliveryAssignmentStatus::ASSIGNED, $newAssignment->status);

        $rider2->refresh();
        $this->assertEquals('busy', $rider2->status);
        $this->assertEquals(1, $rider2->active_orders_count);
    }

    /**
     * Test: Duplicate assignment prevention & clean override.
     */
    public function test_duplicate_assignment_prevention(): void
    {
        $rider1 = DeliveryPartner::create([
            'name' => 'Initial Rider',
            'phone' => '+919876500013',
            'vehicle' => 'Motorcycle',
            'status' => 'busy',
            'active_orders_count' => 1,
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        $rider2 = DeliveryPartner::create([
            'name' => 'Override Rider',
            'phone' => '+919876500014',
            'vehicle' => 'Electric Scooter',
            'status' => 'available',
            'active_orders_count' => 0,
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-DUP-01',
            'customer_id' => $this->customer->id,
            'address_snapshot' => $this->customerAddress->toArray(),
            'subtotal' => 150,
            'total' => 150,
            'status' => OrderStatus::PROCESSING,
        ]);

        $fo = FulfilmentOrder::create([
            'fulfilment_order_number' => 'FO-DUP-01',
            'order_id' => $order->id,
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'status' => 'ready',
            'subtotal' => 150,
            'total' => 150,
        ]);

        DeliveryAssignment::create([
            'fulfilment_order_id' => $fo->id,
            'delivery_partner_id' => $rider1->id,
            'status' => DeliveryAssignmentStatus::ASSIGNED,
            'assigned_at' => now(),
        ]);

        // Admin re-assigns to Rider 2
        $this->actingAs($this->adminUser)
            ->post(route('admin.delivery_partners.assign_order', $fo), [
                'delivery_partner_id' => $rider2->id,
            ]);

        // Exactly 1 assignment must exist for this FO
        $assignmentsCount = DeliveryAssignment::where('fulfilment_order_id', $fo->id)->count();
        $this->assertEquals(1, $assignmentsCount);

        // Previous rider capacity freed
        $rider1->refresh();
        $this->assertEquals('available', $rider1->status);
        $this->assertEquals(0, $rider1->active_orders_count);

        // New rider assigned
        $rider2->refresh();
        $this->assertEquals('busy', $rider2->status);
        $this->assertEquals(1, $rider2->active_orders_count);
    }

    /**
     * Test: Failed delivery reporting and dark store return confirmation.
     */
    public function test_failed_delivery_and_store_return_workflow(): void
    {
        $rider = DeliveryPartner::create([
            'name' => 'Dropoff Rider',
            'phone' => '+919876500015',
            'vehicle' => 'Electric Scooter',
            'status' => 'busy',
            'active_orders_count' => 1,
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-RETURN-E2E',
            'customer_id' => $this->customer->id,
            'address_snapshot' => $this->customerAddress->toArray(),
            'subtotal' => 280,
            'total' => 280,
            'status' => OrderStatus::PROCESSING,
        ]);

        $fo = FulfilmentOrder::create([
            'fulfilment_order_number' => 'FO-RETURN-E2E',
            'order_id' => $order->id,
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'status' => 'out_for_delivery',
            'subtotal' => 280,
            'total' => 280,
        ]);

        $assignment = DeliveryAssignment::create([
            'fulfilment_order_id' => $fo->id,
            'delivery_partner_id' => $rider->id,
            'status' => DeliveryAssignmentStatus::PICKED_UP,
            'assigned_at' => now(),
        ]);

        // 1. Rider reports failure
        $failResponse = $this->withSession(['active_rider_id' => $rider->id])
            ->post(route('rider.fail', $assignment), [
                'reason' => 'customer_unreachable',
                'notes' => 'Customer phone switched off after 4 attempts.',
            ]);
        $failResponse->assertRedirect(route('rider.dashboard'));

        $assignment->refresh();
        $fo->refresh();
        $this->assertEquals(DeliveryAssignmentStatus::CANCELLED, $assignment->status);
        $this->assertEquals('customer_unreachable', $assignment->failure_reason);
        $this->assertEquals('delivery_failed', $fo->status);

        // Rider freed
        $rider->refresh();
        $this->assertEquals('available', $rider->status);
        $this->assertEquals(0, $rider->active_orders_count);

        // 2. Rider confirms return to dark store
        $returnResponse = $this->withSession(['active_rider_id' => $rider->id])
            ->post(route('rider.return', $assignment));
        $returnResponse->assertRedirect(route('rider.dashboard'));

        $fo->refresh();
        $this->assertEquals('returned_to_store', $fo->status);
    }

    /**
     * Test: Unauthorized access protection.
     */
    public function test_unauthorized_access_protection(): void
    {
        $regularUser = User::factory()->create(['status' => 'active']);

        // Regular user cannot approve delivery partner
        $rider = DeliveryPartner::create([
            'name' => 'Secret Rider',
            'phone' => '+919876500016',
            'vehicle' => 'Motorcycle',
            'status' => 'offline',
            'approval_status' => 'pending',
        ]);

        $response = $this->actingAs($regularUser)
            ->post(route('admin.delivery_partners.approve', $rider));
        $response->assertStatus(403);
    }

    /**
     * Test: Invalid OTP Handshake failure.
     */
    public function test_invalid_otp_handshake_rejected(): void
    {
        $rider = DeliveryPartner::create([
            'name' => 'Handshake Rider',
            'phone' => '+919876500017',
            'vehicle' => 'Electric Scooter',
            'status' => 'busy',
            'active_orders_count' => 1,
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-OTP-REJECT-01',
            'customer_id' => $this->customer->id,
            'address_snapshot' => $this->customerAddress->toArray(),
            'subtotal' => 100,
            'total' => 100,
            'status' => OrderStatus::PROCESSING,
        ]);

        $fo = FulfilmentOrder::create([
            'fulfilment_order_number' => 'FO-OTP-REJECT-01',
            'order_id' => $order->id,
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'status' => 'out_for_delivery',
            'subtotal' => 100,
            'total' => 100,
            'delivery_otp' => '654321',
        ]);

        $assignment = DeliveryAssignment::create([
            'fulfilment_order_id' => $fo->id,
            'delivery_partner_id' => $rider->id,
            'status' => DeliveryAssignmentStatus::PICKED_UP,
            'delivery_otp' => '654321',
            'assigned_at' => now(),
        ]);

        $response = $this->withSession(['active_rider_id' => $rider->id])
            ->post(route('rider.deliver', $assignment), [
                'otp' => '000000', // Invalid OTP
            ]);

        $response->assertSessionHas('error');
        $this->assertEquals(DeliveryAssignmentStatus::PICKED_UP, $assignment->fresh()->status);
        $this->assertNull($assignment->fresh()->delivered_at);
        $this->assertNull($assignment->fresh()->earnings);
    }

    /**
     * Test: Duplicate delivery confirmation idempotency.
     */
    public function test_duplicate_delivery_confirmation_idempotency(): void
    {
        $rider = DeliveryPartner::create([
            'name' => 'Idempotent Rider',
            'phone' => '+919876500018',
            'vehicle' => 'Electric Scooter',
            'status' => 'busy',
            'active_orders_count' => 1,
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-IDEMPOTENT-01',
            'customer_id' => $this->customer->id,
            'address_snapshot' => $this->customerAddress->toArray(),
            'subtotal' => 200,
            'delivery_fee' => 20,
            'total' => 220,
            'status' => OrderStatus::PROCESSING,
        ]);

        $fo = FulfilmentOrder::create([
            'fulfilment_order_number' => 'FO-IDEMPOTENT-01',
            'order_id' => $order->id,
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'status' => 'out_for_delivery',
            'subtotal' => 200,
            'delivery_fee' => 20,
            'delivery_otp' => '445566',
        ]);

        $assignment = DeliveryAssignment::create([
            'fulfilment_order_id' => $fo->id,
            'delivery_partner_id' => $rider->id,
            'status' => DeliveryAssignmentStatus::PICKED_UP,
            'delivery_otp' => '445566',
            'assigned_at' => now(),
        ]);

        $quickService = app(QuickCommerceService::class);

        // First verification: success
        $res1 = $quickService->verifyDeliveryOtp($fo, '445566');
        $this->assertTrue($res1['success']);

        $earningsCount1 = RiderEarning::where('delivery_assignment_id', $assignment->id)->count();
        $this->assertEquals(1, $earningsCount1);
        $walletBalance1 = (float) $rider->fresh()->wallet->balance;

        // Second verification attempt on already completed assignment
        $res2 = $quickService->verifyDeliveryOtp($fo, '445566');
        $this->assertTrue($res2['success']);

        // Earnings and wallet balance must NOT duplicate
        $earningsCount2 = RiderEarning::where('delivery_assignment_id', $assignment->id)->count();
        $this->assertEquals(1, $earningsCount2);
        $this->assertEquals($walletBalance1, (float) $rider->fresh()->wallet->balance);
    }

    /**
     * Test: Accurate wallet balance, double-entry ledger, and distance payout calculation.
     */
    public function test_wallet_ledger_and_distance_calculation(): void
    {
        $rider = DeliveryPartner::create([
            'name' => 'Math Rider',
            'phone' => '+919876500019',
            'vehicle' => 'Electric Scooter',
            'status' => 'available',
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-MATH-01',
            'customer_id' => $this->customer->id,
            'address_snapshot' => $this->customerAddress->toArray(),
            'subtotal' => 500,
            'total' => 500,
            'status' => OrderStatus::PROCESSING,
        ]);

        $fo = FulfilmentOrder::create([
            'fulfilment_order_number' => 'FO-MATH-01',
            'order_id' => $order->id,
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'status' => 'out_for_delivery',
            'subtotal' => 500,
            'total' => 500,
        ]);

        $assignment = DeliveryAssignment::create([
            'fulfilment_order_id' => $fo->id,
            'delivery_partner_id' => $rider->id,
            'status' => DeliveryAssignmentStatus::PICKED_UP,
            'assigned_at' => now(),
        ]);

        $earningsService = app(RiderEarningsService::class);
        $payoutData = $earningsService->calculatePayout($assignment, 25.00); // 25 INR customer tip

        $this->assertEquals(35.00, $payoutData['base']);
        $this->assertGreaterThanOrEqual(0.5, $payoutData['distance_km']);
        $this->assertEquals(25.00, $payoutData['tip']);
        $this->assertGreaterThanOrEqual(60.00, $payoutData['total']); // 35 base + 25 tip + distance

        // Credit earnings
        $earning = $earningsService->creditEarnings($assignment, 25.00);
        $this->assertNotNull($earning);
        $this->assertEquals($payoutData['total'], (float) $earning->total_payout);

        // Wallet balance matches ledger
        $wallet = $rider->fresh()->wallet;
        $totalCredits = (float) RiderWalletTransaction::where('rider_wallet_id', $wallet->id)->where('type', 'credit')->sum('amount');
        $totalDebits = (float) RiderWalletTransaction::where('rider_wallet_id', $wallet->id)->where('type', 'debit')->sum('amount');
        $expectedBalance = round($totalCredits - $totalDebits, 2);

        $this->assertEquals($expectedBalance, (float) $wallet->balance);
    }
}
