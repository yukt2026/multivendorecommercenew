<?php

namespace Tests\Feature;

use App\Enums\AddressType;
use App\Enums\SellerStatus;
use App\Enums\SellerType;
use App\Enums\StoreStaffRole;
use App\Enums\StoreStatus;
use App\Enums\UserStatus;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Seller;
use App\Models\SellerBankAccount;
use App\Models\SellerDocument;
use App\Models\Store;
use App\Models\StoreDeliveryZone;
use App\Models\StoreFollower;
use App\Models\StoreLocation;
use App\Models\StoreStaff;
use App\Models\StoreTiming;
use App\Models\User;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IdentityAndStoresTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    /**
     * Test User identity and Spatie roles/permissions.
     */
    public function test_user_creation_with_spatie_roles_and_soft_deletes(): void
    {
        $role = Role::create(['name' => 'seller', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'phone' => '9876543210',
            'status' => UserStatus::ACTIVE,
        ]);

        $user->assignRole($role);

        $this->assertTrue($user->hasRole('seller'));
        $this->assertEquals(UserStatus::ACTIVE, $user->status);
        $this->assertEquals('9876543210', $user->phone);

        // Soft deletes test
        $user->delete();
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    /**
     * Test Customer and Address relationships with default scope.
     */
    public function test_customer_and_address_relationship_and_default_scope(): void
    {
        $customer = Customer::factory()->create();

        $address1 = CustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $customer->user_id,
            'is_default' => false,
        ]);

        $address2 = CustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $customer->user_id,
            'is_default' => true,
        ]);

        $this->assertCount(2, $customer->addresses);
        $this->assertEquals($address2->id, $customer->defaultAddress->id);
        $this->assertEquals(1, CustomerAddress::where('customer_id', $customer->id)->default()->count());
    }

    /**
     * Test Seller KYC, Commission fallback and encrypted bank account.
     */
    public function test_seller_with_commission_fallback_and_encrypted_bank_account(): void
    {
        $seller = Seller::factory()->create([
            'seller_type' => SellerType::LOCAL_STORE,
            'status' => SellerStatus::APPROVED,
            'commission_override' => null, // Should fallback to setting('default_commission_pct') = 10.00
        ]);

        $this->assertEquals(10.00, $seller->effectiveCommissionRate());

        // Test custom override
        $seller->commission_override = 7.50;
        $seller->save();
        $this->assertEquals(7.50, $seller->effectiveCommissionRate());

        // Test encrypted bank account
        $bankAccount = SellerBankAccount::factory()->create([
            'seller_id' => $seller->id,
            'account_no' => '123456789012',
            'is_primary' => true,
        ]);

        // Model attribute decrypts cleanly
        $this->assertEquals('123456789012', $bankAccount->fresh()->account_no);

        // Raw database column is encrypted and does not store plaintext
        $rawAccount = DB::table('seller_bank_accounts')->where('id', $bankAccount->id)->value('account_no');
        $this->assertNotEquals('123456789012', $rawAccount);

        // Relationship
        $this->assertEquals($bankAccount->id, $seller->primaryBankAccount->id);
    }

    /**
     * Test Store entity with Haversine nearby query scope.
     */
    public function test_store_and_haversine_nearby_scope(): void
    {
        // 1. Store in Indiranagar, Bengaluru (Lat: 12.9784, Lng: 77.6408)
        $store = Store::factory()->create([
            'name' => 'Indiranagar Quick Mart',
            'status' => StoreStatus::ACTIVE,
            'accepts_quick_commerce' => true,
        ]);

        StoreLocation::factory()->create([
            'store_id' => $store->id,
            'lat' => 12.9784,
            'lng' => 77.6408,
            'city' => 'Bengaluru',
            'pincode' => '560038',
        ]);

        // 2. Search from Domlur (approx 1.5 km away: Lat: 12.9609, Lng: 77.6387)
        $nearbyStores = Store::query()->nearby(12.9609, 77.6387, 5.00)->get();
        $this->assertTrue($nearbyStores->contains('id', $store->id));
        $this->assertLessThan(3.0, (float) $nearbyStores->first()->distance_km);

        // 3. Search from Mumbai (Lat: 19.0760, Lng: 72.8777) with default 8km radius -> Should be empty!
        $farStores = Store::query()->nearby(19.0760, 72.8777)->get();
        $this->assertFalse($farStores->contains('id', $store->id));
    }

    /**
     * Test Store staff and followers relations.
     */
    public function test_store_staff_and_followers_relations(): void
    {
        $store = Store::factory()->create();
        $staffUser = User::factory()->create();
        $followerUser = User::factory()->create();

        StoreStaff::factory()->create([
            'store_id' => $store->id,
            'user_id' => $staffUser->id,
            'role' => StoreStaffRole::MANAGER,
        ]);

        StoreFollower::factory()->create([
            'store_id' => $store->id,
            'user_id' => $followerUser->id,
        ]);

        $this->assertTrue($store->staffUsers->contains('id', $staffUser->id));
        $this->assertTrue($store->followerUsers->contains('id', $followerUser->id));
    }
}
