<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DeliveryAssignmentStatus;
use App\Enums\FulfilmentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryDocument;
use App\Models\DeliveryPartner;
use App\Models\FulfilmentOrder;
use App\Models\Order;
use App\Models\RiderEarning;
use App\Models\RiderLocationHistory;
use App\Models\RiderWallet;
use App\Models\RiderWalletTransaction;
use App\Models\Seller;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RiderPortalTest extends TestCase
{
    use RefreshDatabase;

    protected User $riderUser;
    protected DeliveryPartner $rider;
    protected Seller $seller;
    protected Store $store;
    protected Customer $customer;
    protected CustomerAddress $customerAddress;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'delivery_staff', 'guard_name' => 'web']);

        $this->riderUser = User::factory()->create([
            'name' => 'Rohan Rider',
            'phone' => '+919876543299',
            'email' => 'rohan.rider@onestall.test',
            'password' => Hash::make('Secret@123'),
            'status' => 'active',
        ]);
        $this->riderUser->assignRole('delivery_staff');

        $this->rider = DeliveryPartner::create([
            'user_id' => $this->riderUser->id,
            'name' => 'Rohan Rider',
            'phone' => '+919876543299',
            'vehicle' => 'Electric Scooter',
            'vehicle_number' => 'KA-01-EQ-9999',
            'status' => 'available',
            'is_active' => true,
            'approval_status' => 'approved',
            'current_lat' => 12.9716,
            'current_lng' => 77.5946,
        ]);

        $sellerUser = User::factory()->create();
        $this->seller = Seller::create([
            'user_id' => $sellerUser->id,
            'business_name' => 'City Dark Store',
            'legal_name' => 'City Dark Store Pvt Ltd',
            'seller_type' => \App\Enums\SellerType::MULTI_STORE,
            'slug' => 'city-dark-store',
            'phone' => '9876543298',
            'status' => \App\Enums\SellerStatus::APPROVED,
        ]);

        $this->store = Store::create([
            'seller_id' => $this->seller->id,
            'name' => 'Indiranagar Hub',
            'slug' => 'indiranagar-hub',
            'latitude' => 12.9720,
            'longitude' => 77.5950,
            'is_active' => true,
        ]);

        $customerUser = User::factory()->create();
        $this->customer = Customer::create(['user_id' => $customerUser->id]);
        $this->customerAddress = CustomerAddress::create([
            'user_id' => $customerUser->id,
            'customer_id' => $this->customer->id,
            'name' => 'Pooja Customer',
            'phone' => '9876543297',
            'line1' => '100ft Road, HAL 2nd Stage',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560038',
            'lat' => 12.9750,
            'lng' => 77.6400,
        ]);
    }

    public function test_rider_can_view_login_page(): void
    {
        $response = $this->get(route('rider.login'));
        $response->assertStatus(200);
        $response->assertSee('OneStall Rider');
        $response->assertSee('Sign In to Dashboard');
    }

    public function test_rider_can_login_with_phone_and_password(): void
    {
        $response = $this->post(route('rider.login.submit'), [
            'login' => '+919876543299',
            'password' => 'Secret@123',
        ]);

        $response->assertRedirect(route('rider.dashboard'));
        $this->assertEquals($this->rider->id, session('active_rider_id'));
    }

    public function test_rider_can_logout_and_is_redirected_to_login(): void
    {
        $response = $this->withSession(['active_rider_id' => $this->rider->id])
            ->actingAs($this->riderUser)
            ->post(route('rider.logout'));

        $response->assertRedirect(route('rider.login'));
        $this->assertNull(session('active_rider_id'));
        $this->assertFalse(auth()->check());
    }

    public function test_logged_out_rider_cannot_access_protected_routes(): void
    {
        $response = $this->get(route('rider.dashboard'));
        $response->assertRedirect(route('rider.login'));

        $profileResponse = $this->get(route('rider.profile'));
        $profileResponse->assertRedirect(route('rider.login'));

        $walletResponse = $this->get(route('rider.wallet'));
        $walletResponse->assertRedirect(route('rider.login'));
    }

    public function test_rider_can_upload_and_change_profile_photo(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('avatar.jpg', 400, 400);

        $response = $this->withSession(['active_rider_id' => $this->rider->id])
            ->post(route('rider.profile.avatar'), [
                'avatar' => $file,
            ]);

        $response->assertRedirect();
        $this->rider->refresh();
        $this->assertNotNull($this->rider->avatar);
        $this->assertNotNull($this->rider->avatar_url);
        Storage::disk('public')->assertExists($this->rider->avatar);

        // Upload replacement photo
        $newFile = UploadedFile::fake()->image('new_avatar.png', 400, 400);
        $replaceResponse = $this->withSession(['active_rider_id' => $this->rider->id])
            ->post(route('rider.profile.avatar'), [
                'avatar' => $newFile,
            ]);

        $replaceResponse->assertRedirect();
        $oldAvatar = $this->rider->avatar;
        $this->rider->refresh();
        Storage::disk('public')->assertExists($this->rider->avatar);
    }

    public function test_rider_can_remove_profile_photo(): void
    {
        Storage::fake('public');

        $this->rider->update(['avatar' => 'riders/avatars/test.jpg']);
        Storage::disk('public')->put('riders/avatars/test.jpg', 'fake-image-content');

        $response = $this->withSession(['active_rider_id' => $this->rider->id])
            ->delete(route('rider.profile.avatar.remove'));

        $response->assertRedirect();
        $this->rider->refresh();
        $this->assertNull($this->rider->avatar);
        Storage::disk('public')->assertMissing('riders/avatars/test.jpg');
    }

    public function test_rider_can_change_password(): void
    {
        $response = $this->withSession(['active_rider_id' => $this->rider->id])
            ->put(route('rider.profile.password'), [
                'current_password' => 'Secret@123',
                'password' => 'NewSecret@456',
                'password_confirmation' => 'NewSecret@456',
            ]);

        $response->assertRedirect();
        $this->riderUser->refresh();
        $this->assertTrue(Hash::check('NewSecret@456', $this->riderUser->password));

        // Wrong current password fails
        $failResponse = $this->withSession(['active_rider_id' => $this->rider->id])
            ->put(route('rider.profile.password'), [
                'current_password' => 'WrongPassword',
                'password' => 'AnotherPassword@789',
                'password_confirmation' => 'AnotherPassword@789',
            ]);

        $failResponse->assertSessionHasErrors(['current_password']);
    }

    public function test_rider_can_view_dashboard_with_metrics(): void
    {
        $response = $this->withSession(['active_rider_id' => $this->rider->id])
            ->get(route('rider.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Rohan Rider');
        $response->assertSee('Duty Availability');
        $response->assertSee('Assigned Tasks');
    }

    public function test_rider_can_toggle_duty_online_offline(): void
    {
        $this->assertEquals('available', $this->rider->status);

        $response = $this->withSession(['active_rider_id' => $this->rider->id])
            ->post(route('rider.toggle_duty'));

        $response->assertRedirect();
        $this->rider->refresh();
        $this->assertEquals('offline', $this->rider->status);
        $this->assertFalse((bool) $this->rider->is_active);

        // Toggle back online
        $this->withSession(['active_rider_id' => $this->rider->id])
            ->post(route('rider.toggle_duty'));

        $this->rider->refresh();
        $this->assertEquals('available', $this->rider->status);
        $this->assertTrue((bool) $this->rider->is_active);
    }

    public function test_busy_rider_cannot_toggle_offline(): void
    {
        $this->rider->status = 'busy';
        $this->rider->active_orders_count = 1;
        $this->rider->save();

        $response = $this->withSession(['active_rider_id' => $this->rider->id])
            ->post(route('rider.toggle_duty'));

        $response->assertSessionHas('error');
        $this->assertEquals('busy', $this->rider->fresh()->status);
    }

    public function test_rider_can_view_assignment_details(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-RIDER-VIEW-01',
            'customer_id' => $this->customer->id,
            'customer_address_id' => $this->customerAddress->id,
            'address_snapshot' => $this->customerAddress->toArray(),
            'subtotal' => 250,
            'shipping_fee' => 25,
            'total' => 275,
            'status' => OrderStatus::PROCESSING,
        ]);

        $fo = FulfilmentOrder::create([
            'fulfilment_order_number' => 'FO-RIDER-VIEW-01',
            'order_id' => $order->id,
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'status' => 'ready',
            'subtotal' => 250,
            'shipping_fee' => 25,
            'total' => 275,
            'delivery_otp' => '654321',
        ]);

        $assignment = DeliveryAssignment::create([
            'fulfilment_order_id' => $fo->id,
            'delivery_partner_id' => $this->rider->id,
            'status' => DeliveryAssignmentStatus::ASSIGNED,
            'assigned_at' => now(),
            'pickup_otp' => '4321',
        ]);

        $response = $this->withSession(['active_rider_id' => $this->rider->id])
            ->get(route('rider.assignment', $assignment));

        $response->assertStatus(200);
        $response->assertSee('FO-RIDER-VIEW-01');
        $response->assertSee('Indiranagar Hub');
        $response->assertSee('Pooja Customer');
    }

    public function test_rider_can_view_assignment_details_with_snapshot_address_without_lat_lng(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-RIDER-SNAPSHOT-01',
            'customer_id' => $this->customer->id,
            'address_snapshot' => [
                'name' => 'Customer 9 Rao',
                'phone' => '+919910000009',
                'line1' => 'Flat #436, Koramangala Heights',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'pincode' => '560034',
            ],
            'subtotal' => 300,
            'shipping_fee' => 30,
            'total' => 330,
            'status' => OrderStatus::PROCESSING,
        ]);

        $fo = FulfilmentOrder::create([
            'fulfilment_order_number' => 'FO-RIDER-SNAP-01',
            'order_id' => $order->id,
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'status' => 'ready',
            'subtotal' => 300,
            'shipping_fee' => 30,
            'total' => 330,
            'delivery_otp' => '123456',
        ]);

        $assignment = DeliveryAssignment::create([
            'fulfilment_order_id' => $fo->id,
            'delivery_partner_id' => $this->rider->id,
            'status' => DeliveryAssignmentStatus::ASSIGNED,
            'assigned_at' => now(),
            'pickup_otp' => '7890',
        ]);

        $response = $this->withSession(['active_rider_id' => $this->rider->id])
            ->get(route('rider.assignment', $assignment));

        $response->assertStatus(200);
        $response->assertSee('Customer 9 Rao');
        $response->assertSee('Flat #436, Koramangala Heights');
        $response->assertSee('+919910000009');
    }

    public function test_rider_can_accept_assigned_job(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-ACCEPT-01',
            'customer_id' => $this->customer->id,
            'customer_address_id' => $this->customerAddress->id,
            'address_snapshot' => $this->customerAddress->toArray(),
            'subtotal' => 150,
            'shipping_fee' => 20,
            'total' => 170,
            'status' => OrderStatus::PROCESSING,
        ]);

        $fo = FulfilmentOrder::create([
            'fulfilment_order_number' => 'FO-ACCEPT-01',
            'order_id' => $order->id,
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'status' => 'ready',
            'subtotal' => 150,
            'shipping_fee' => 20,
            'total' => 170,
        ]);

        $assignment = DeliveryAssignment::create([
            'fulfilment_order_id' => $fo->id,
            'delivery_partner_id' => $this->rider->id,
            'status' => DeliveryAssignmentStatus::ASSIGNED,
            'assigned_at' => now(),
        ]);

        $response = $this->withSession(['active_rider_id' => $this->rider->id])
            ->post(route('rider.accept', $assignment));

        $response->assertRedirect();
        $assignment->refresh();

        $this->assertEquals(DeliveryAssignmentStatus::ACCEPTED, $assignment->status);
        $this->assertNotNull($assignment->accepted_at);
        $this->assertEquals('busy', $this->rider->fresh()->status);
    }

    public function test_rider_can_reject_assigned_job_with_reason(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-REJECT-01',
            'customer_id' => $this->customer->id,
            'customer_address_id' => $this->customerAddress->id,
            'address_snapshot' => $this->customerAddress->toArray(),
            'subtotal' => 180,
            'shipping_fee' => 20,
            'total' => 200,
            'status' => OrderStatus::PROCESSING,
        ]);

        $fo = FulfilmentOrder::create([
            'fulfilment_order_number' => 'FO-REJECT-01',
            'order_id' => $order->id,
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'status' => 'ready',
            'subtotal' => 180,
            'shipping_fee' => 20,
            'total' => 200,
        ]);

        $assignment = DeliveryAssignment::create([
            'fulfilment_order_id' => $fo->id,
            'delivery_partner_id' => $this->rider->id,
            'status' => DeliveryAssignmentStatus::ASSIGNED,
            'assigned_at' => now(),
        ]);

        $this->rider->active_orders_count = 1;
        $this->rider->status = 'busy';
        $this->rider->save();

        $response = $this->withSession(['active_rider_id' => $this->rider->id])
            ->post(route('rider.reject', $assignment), [
                'reason' => 'vehicle_breakdown',
            ]);

        $response->assertRedirect(route('rider.dashboard'));
        $assignment->refresh();

        $this->assertEquals(DeliveryAssignmentStatus::REJECTED, $assignment->status);
        $this->assertEquals('vehicle_breakdown', $assignment->rejection_reason);

        // Capacity released
        $this->rider->refresh();
        $this->assertEquals('available', $this->rider->status);
        $this->assertEquals(0, $this->rider->active_orders_count);
    }

    public function test_rider_can_pickup_order_from_store(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-PICKUP-01',
            'customer_id' => $this->customer->id,
            'customer_address_id' => $this->customerAddress->id,
            'address_snapshot' => $this->customerAddress->toArray(),
            'subtotal' => 190,
            'shipping_fee' => 20,
            'total' => 210,
            'status' => OrderStatus::PROCESSING,
        ]);

        $fo = FulfilmentOrder::create([
            'fulfilment_order_number' => 'FO-PICKUP-01',
            'order_id' => $order->id,
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'status' => 'ready',
            'subtotal' => 190,
            'shipping_fee' => 20,
            'total' => 210,
        ]);

        $assignment = DeliveryAssignment::create([
            'fulfilment_order_id' => $fo->id,
            'delivery_partner_id' => $this->rider->id,
            'status' => DeliveryAssignmentStatus::ACCEPTED,
            'assigned_at' => now(),
            'pickup_otp' => '5566',
        ]);

        $response = $this->withSession(['active_rider_id' => $this->rider->id])
            ->post(route('rider.pickup', $assignment), [
                'pickup_otp' => '5566',
            ]);

        $response->assertRedirect();
        $assignment->refresh();
        $fo->refresh();

        $this->assertEquals(DeliveryAssignmentStatus::PICKED_UP, $assignment->status);
        $this->assertNotNull($assignment->picked_up_at);
        $this->assertTrue((bool) $assignment->pickup_otp_verified);
        $this->assertEquals('out_for_delivery', $fo->status);
    }

    public function test_rider_can_complete_delivery_with_valid_otp(): void
    {
        Storage::fake('public');

        $order = Order::create([
            'order_number' => 'ORD-DELIVER-01',
            'customer_id' => $this->customer->id,
            'customer_address_id' => $this->customerAddress->id,
            'address_snapshot' => $this->customerAddress->toArray(),
            'subtotal' => 350,
            'shipping_fee' => 30,
            'total' => 380,
            'payment_method' => PaymentMethod::COD,
            'status' => OrderStatus::PROCESSING,
        ]);

        $fo = FulfilmentOrder::create([
            'fulfilment_order_number' => 'FO-DELIVER-01',
            'order_id' => $order->id,
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'status' => 'out_for_delivery',
            'subtotal' => 350,
            'delivery_fee' => 30,
            'delivery_otp' => '889900',
        ]);

        $assignment = DeliveryAssignment::create([
            'fulfilment_order_id' => $fo->id,
            'delivery_partner_id' => $this->rider->id,
            'status' => DeliveryAssignmentStatus::PICKED_UP,
            'delivery_otp' => '889900',
            'assigned_at' => now(),
        ]);

        $fakePhoto = UploadedFile::fake()->image('doorstep.jpg');

        $response = $this->withSession(['active_rider_id' => $this->rider->id])
            ->post(route('rider.deliver', $assignment), [
                'otp' => '889900',
                'proof_image' => $fakePhoto,
            ]);

        $response->assertRedirect(route('rider.dashboard'));
        $assignment->refresh();

        $this->assertEquals(DeliveryAssignmentStatus::DELIVERED, $assignment->status);
        $this->assertNotNull($assignment->delivered_at);
        $this->assertNotNull($assignment->proof_of_delivery_image);
        Storage::disk('public')->assertExists($assignment->proof_of_delivery_image);

        // Earnings and Wallet updated
        $earning = $assignment->earnings;
        $this->assertNotNull($earning);
        $this->assertGreaterThan(0, (float) $earning->total_payout);

        $wallet = $this->rider->wallet;
        $this->assertNotNull($wallet);
        $this->assertEquals((float) $earning->total_payout, (float) $wallet->balance);

        // COD amount collected recorded
        $this->assertEquals(380.00, (float) $this->rider->fresh()->current_cash_in_hand);
    }

    public function test_delivery_fails_with_invalid_otp(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-DELIVER-FAIL-01',
            'customer_id' => $this->customer->id,
            'customer_address_id' => $this->customerAddress->id,
            'address_snapshot' => $this->customerAddress->toArray(),
            'subtotal' => 100,
            'total' => 100,
            'status' => OrderStatus::PROCESSING,
        ]);

        $fo = FulfilmentOrder::create([
            'fulfilment_order_number' => 'FO-DELIVER-FAIL-01',
            'order_id' => $order->id,
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'status' => 'out_for_delivery',
            'subtotal' => 100,
            'total' => 100,
            'delivery_otp' => '112233',
        ]);

        $assignment = DeliveryAssignment::create([
            'fulfilment_order_id' => $fo->id,
            'delivery_partner_id' => $this->rider->id,
            'status' => DeliveryAssignmentStatus::PICKED_UP,
            'delivery_otp' => '112233',
            'assigned_at' => now(),
        ]);

        $response = $this->withSession(['active_rider_id' => $this->rider->id])
            ->post(route('rider.deliver', $assignment), [
                'otp' => '999999', // wrong OTP
            ]);

        $response->assertSessionHas('error');
        $this->assertEquals(DeliveryAssignmentStatus::PICKED_UP, $assignment->fresh()->status);
    }

    public function test_rider_can_report_failed_delivery_attempt(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-FAIL-01',
            'customer_id' => $this->customer->id,
            'customer_address_id' => $this->customerAddress->id,
            'address_snapshot' => $this->customerAddress->toArray(),
            'subtotal' => 200,
            'total' => 200,
            'status' => OrderStatus::PROCESSING,
        ]);

        $fo = FulfilmentOrder::create([
            'fulfilment_order_number' => 'FO-FAIL-01',
            'order_id' => $order->id,
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'status' => 'out_for_delivery',
            'subtotal' => 200,
            'total' => 200,
        ]);

        $assignment = DeliveryAssignment::create([
            'fulfilment_order_id' => $fo->id,
            'delivery_partner_id' => $this->rider->id,
            'status' => DeliveryAssignmentStatus::PICKED_UP,
            'assigned_at' => now(),
        ]);

        $this->rider->active_orders_count = 1;
        $this->rider->status = 'busy';
        $this->rider->save();

        $response = $this->withSession(['active_rider_id' => $this->rider->id])
            ->post(route('rider.fail', $assignment), [
                'reason' => 'customer_unreachable',
                'notes' => 'Attempted 3 phone calls with no response.',
            ]);

        $response->assertRedirect(route('rider.dashboard'));
        $assignment->refresh();
        $fo->refresh();

        $this->assertEquals(DeliveryAssignmentStatus::CANCELLED, $assignment->status);
        $this->assertEquals('customer_unreachable', $assignment->failure_reason);
        $this->assertEquals('delivery_failed', $fo->status);

        // Capacity released
        $this->assertEquals('available', $this->rider->fresh()->status);
        $this->assertEquals(0, $this->rider->fresh()->active_orders_count);
    }

    public function test_rider_can_confirm_package_returned_to_store(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-RETURN-01',
            'customer_id' => $this->customer->id,
            'customer_address_id' => $this->customerAddress->id,
            'address_snapshot' => $this->customerAddress->toArray(),
            'subtotal' => 200,
            'total' => 200,
            'status' => OrderStatus::PROCESSING,
        ]);

        $fo = FulfilmentOrder::create([
            'fulfilment_order_number' => 'FO-RETURN-01',
            'order_id' => $order->id,
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'status' => 'delivery_failed',
            'subtotal' => 200,
            'total' => 200,
        ]);

        $assignment = DeliveryAssignment::create([
            'fulfilment_order_id' => $fo->id,
            'delivery_partner_id' => $this->rider->id,
            'status' => DeliveryAssignmentStatus::CANCELLED,
            'assigned_at' => now(),
            'failure_reason' => 'premises_closed',
        ]);

        $response = $this->withSession(['active_rider_id' => $this->rider->id])
            ->post(route('rider.return', $assignment));

        $response->assertRedirect(route('rider.dashboard'));
        $fo->refresh();

        $this->assertEquals('returned_to_store', $fo->status);
    }

    public function test_rider_can_view_delivery_history(): void
    {
        $response = $this->withSession(['active_rider_id' => $this->rider->id])
            ->get(route('rider.history'));

        $response->assertStatus(200);
        $response->assertSee('Delivery History');
    }

    public function test_rider_can_view_wallet_and_transaction_ledger(): void
    {
        $wallet = $this->rider->ensureWallet();
        $wallet->balance = 1200.00;
        $wallet->total_earned = 2500.00;
        $wallet->save();

        RiderWalletTransaction::create([
            'rider_wallet_id' => $wallet->id,
            'type' => 'credit',
            'amount' => 55.00,
            'balance_after' => 1200.00,
            'category' => 'delivery_payout',
            'description' => 'Trip payout for FO #FO-101',
        ]);

        $response = $this->withSession(['active_rider_id' => $this->rider->id])
            ->get(route('rider.wallet'));

        $response->assertStatus(200);
        $response->assertSee('Rider Wallet &amp; Earnings', false);
        $response->assertSee('1,200.00');
        $response->assertSee('Trip payout for FO #FO-101');
    }

    public function test_rider_can_view_and_update_profile(): void
    {
        $response = $this->withSession(['active_rider_id' => $this->rider->id])
            ->get(route('rider.profile'));

        $response->assertStatus(200);
        $response->assertSee('Partner Profile');

        $updateResponse = $this->withSession(['active_rider_id' => $this->rider->id])
            ->put(route('rider.profile.update'), [
                'vehicle_number' => 'KA-05-EV-1122',
                'emergency_contact_name' => 'Kiran Rider',
                'emergency_contact_phone' => '+919876599999',
            ]);

        $updateResponse->assertRedirect();
        $this->rider->refresh();

        $this->assertEquals('KA-05-EV-1122', $this->rider->vehicle_number);
        $this->assertEquals('Kiran Rider', $this->rider->emergency_contact_name);
        $this->assertEquals('+919876599999', $this->rider->emergency_contact_phone);
    }

    public function test_rider_can_view_support_screen(): void
    {
        $response = $this->withSession(['active_rider_id' => $this->rider->id])
            ->get(route('rider.support'));

        $response->assertStatus(200);
        $response->assertSee('Rider Support &amp; Safety', false);
        $response->assertSee('Emergency Roadside Helpline');
    }

    public function test_rider_can_send_gps_telemetry_ping(): void
    {
        $response = $this->postJson(route('rider.location_api'), [
            'rider_id' => $this->rider->id,
            'lat' => 12.9780,
            'lng' => 77.6010,
            'accuracy' => 8.5,
            'speed' => 22.0,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'rider_id' => $this->rider->id,
                'lat' => 12.978,
                'lng' => 77.601,
            ]);

        $this->rider->refresh();
        $this->assertEquals(12.9780, (float) $this->rider->current_lat);
        $this->assertEquals(77.6010, (float) $this->rider->current_lng);

        // Telemetry history recorded
        $history = RiderLocationHistory::where('delivery_partner_id', $this->rider->id)->latest('id')->first();
        $this->assertNotNull($history);
        $this->assertEquals(12.9780, (float) $history->lat);
        $this->assertEquals(77.6010, (float) $history->lng);
    }

    public function test_guest_can_view_rider_registration_screen(): void
    {
        $response = $this->get(route('rider.register'));

        $response->assertStatus(200);
        $response->assertSee('Join OneStall Delivery Fleet');
        $response->assertSee('Create Partner Account &amp; Proceed to KYC', false);
    }

    public function test_rider_can_self_register_successfully(): void
    {
        $payload = [
            'name' => 'Amit Sharma',
            'phone' => '+919876500001',
            'email' => 'amit.rider@onestall.test',
            'vehicle' => 'Bike',
            'vehicle_number' => 'DL-01-AB-9999',
            'emergency_contact_name' => 'Sunita Sharma',
            'emergency_contact_phone' => '+919876500002',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'terms' => '1',
        ];

        $response = $this->post(route('rider.register.submit'), $payload);

        $response->assertRedirect(route('rider.kyc'));
        $response->assertSessionHas('success');

        // Check user creation
        $user = User::where('phone', '+919876500001')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('delivery_staff'));

        // Check delivery partner creation
        $partner = DeliveryPartner::where('phone', '+919876500001')->first();
        $this->assertNotNull($partner);
        $this->assertEquals('pending', $partner->approval_status);
        $this->assertFalse((bool) $partner->is_active);
        $this->assertEquals('offline', $partner->status);
        $this->assertEquals('DL-01-AB-9999', $partner->vehicle_number);

        // Check session and wallet
        $this->assertEquals($partner->id, session('active_rider_id'));
        $this->assertNotNull($partner->wallet);
    }

    public function test_rider_registration_fails_with_duplicate_phone(): void
    {
        $payload = [
            'name' => 'Duplicate Rohan',
            'phone' => '+919876543299', // already taken by setup rider
            'vehicle' => 'Bike',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'terms' => '1',
        ];

        $response = $this->post(route('rider.register.submit'), $payload);

        $response->assertSessionHasErrors('phone');
    }

    public function test_rider_can_view_kyc_screen(): void
    {
        $response = $this->withSession(['active_rider_id' => $this->rider->id])
            ->get(route('rider.kyc'));

        $response->assertStatus(200);
        $response->assertSee('Document Verification');
        $response->assertSee('Driving License (Front)');
        $response->assertSee('Vehicle Registration (RC)');
    }

    public function test_rider_can_upload_and_replace_kyc_document(): void
    {
        Storage::fake('private');
        $this->rider->update(['approval_status' => 'pending']);

        $file = UploadedFile::fake()->image('driving_license.jpg');

        $response = $this->withSession(['active_rider_id' => $this->rider->id])
            ->post(route('rider.kyc.upload'), [
                'document_type' => DeliveryDocument::TYPE_DRIVING_LICENSE,
                'document_number' => 'DL-9876543210123',
                'document_file' => $file,
            ]);

        $response->assertRedirect(route('rider.kyc'));
        $response->assertSessionHas('success');

        $doc = DeliveryDocument::where('delivery_partner_id', $this->rider->id)
            ->where('document_type', DeliveryDocument::TYPE_DRIVING_LICENSE)
            ->first();

        $this->assertNotNull($doc);
        $this->assertEquals('DL-9876543210123', $doc->document_number);
        $this->assertEquals(DeliveryDocument::STATUS_PENDING, $doc->verification_status);
        $this->assertTrue(Storage::disk('private')->exists($doc->file_path));

        $this->rider->refresh();
        $this->assertEquals('under_review', $this->rider->approval_status);
    }

    public function test_rider_can_delete_unapproved_kyc_document(): void
    {
        Storage::fake('private');
        $file = UploadedFile::fake()->image('rc_book.jpg');

        $this->withSession(['active_rider_id' => $this->rider->id])
            ->post(route('rider.kyc.upload'), [
                'document_type' => DeliveryDocument::TYPE_VEHICLE_RC,
                'document_number' => 'RC-11223344',
                'document_file' => $file,
            ]);

        $doc = DeliveryDocument::where('delivery_partner_id', $this->rider->id)
            ->where('document_type', DeliveryDocument::TYPE_VEHICLE_RC)
            ->first();

        $this->assertNotNull($doc);

        $deleteResponse = $this->withSession(['active_rider_id' => $this->rider->id])
            ->delete(route('rider.kyc.delete', $doc));

        $deleteResponse->assertRedirect(route('rider.kyc'));
        $this->assertDatabaseMissing('delivery_documents', ['id' => $doc->id]);
    }

    public function test_admin_can_review_and_approve_kyc_document(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $doc = DeliveryDocument::create([
            'delivery_partner_id' => $this->rider->id,
            'document_type' => DeliveryDocument::TYPE_DRIVING_LICENSE,
            'document_number' => 'DL-VERIFY-001',
            'file_path' => 'riders/documents/dummy.jpg',
            'verification_status' => DeliveryDocument::STATUS_PENDING,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.delivery_partners.documents.review', [$this->rider, $doc]), [
                'status' => 'approved',
            ]);

        $response->assertRedirect();
        $doc->refresh();

        $this->assertEquals(DeliveryDocument::STATUS_APPROVED, $doc->verification_status);
        $this->assertEquals($admin->id, $doc->verified_by);
        $this->assertNotNull($doc->verified_at);
    }

    public function test_admin_can_reject_kyc_document_with_reason(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $doc = DeliveryDocument::create([
            'delivery_partner_id' => $this->rider->id,
            'document_type' => DeliveryDocument::TYPE_AADHAAR,
            'document_number' => '1234-5678-9012',
            'file_path' => 'riders/documents/dummy_aadhaar.jpg',
            'verification_status' => DeliveryDocument::STATUS_PENDING,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.delivery_partners.documents.review', [$this->rider, $doc]), [
                'status' => 'rejected',
                'rejection_reason' => 'Aadhaar photo is not legible.',
            ]);

        $response->assertRedirect();
        $doc->refresh();

        $this->assertEquals(DeliveryDocument::STATUS_REJECTED, $doc->verification_status);
        $this->assertEquals('Aadhaar photo is not legible.', $doc->rejection_reason);

        $this->rider->refresh();
        $this->assertEquals('rejected', $this->rider->approval_status);
    }
}
