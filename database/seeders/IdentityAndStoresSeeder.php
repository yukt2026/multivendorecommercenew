<?php

namespace Database\Seeders;

use App\Enums\AddressType;
use App\Enums\DeliveryZoneType;
use App\Enums\SellerDocumentStatus;
use App\Enums\SellerDocumentType;
use App\Enums\SellerStatus;
use App\Enums\SellerType;
use App\Enums\StoreStaffRole;
use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Seller;
use App\Models\SellerBankAccount;
use App\Models\SellerDocument;
use App\Models\Store;
use App\Models\StoreDeliveryZone;
use App\Models\StoreLocation;
use App\Models\StoreStaff;
use App\Models\StoreTiming;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class IdentityAndStoresSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Spatie Roles
        foreach (UserRole::cases() as $roleEnum) {
            Role::firstOrCreate(['name' => $roleEnum->value, 'guard_name' => 'web']);
        }

        // 2. Super Admin User
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@onestall.in'],
            [
                'name' => 'OneStall Super Admin',
                'phone' => '9999900001',
                'password' => Hash::make('password'),
                'status' => UserStatus::ACTIVE,
                'email_verified_at' => now(),
            ]
        );
        $adminUser->syncRoles([UserRole::SUPER_ADMIN->value]);

        // 3. Verified Seller Account
        $sellerUser = User::firstOrCreate(
            ['email' => 'seller@onestall.in'],
            [
                'name' => 'Apex Retail Merchant',
                'phone' => '9999900002',
                'password' => Hash::make('password'),
                'status' => UserStatus::ACTIVE,
                'email_verified_at' => now(),
            ]
        );
        $sellerUser->syncRoles([UserRole::SELLER->value]);

        $seller = Seller::firstOrCreate(
            ['user_id' => $sellerUser->id],
            [
                'business_name' => 'Apex Retail Enterprises',
                'legal_name' => 'Apex Retail Enterprises India Pvt Ltd',
                'seller_type' => SellerType::LOCAL_STORE,
                'pan' => 'AAACA1234F',
                'gstin' => '29AAACA1234F1Z5',
                'status' => SellerStatus::APPROVED,
                'commission_override' => null, // Dynamic fallback to platform setting
                'approved_at' => now(),
                'approved_by' => $adminUser->id,
            ]
        );

        // Seller KYC Documents
        SellerDocument::firstOrCreate(
            ['seller_id' => $seller->id, 'type' => SellerDocumentType::GST_CERTIFICATE],
            [
                'file_path' => 'documents/sellers/gst_cert_demo.pdf',
                'status' => SellerDocumentStatus::APPROVED,
                'remarks' => 'Verified with GSTN Registry',
            ]
        );

        SellerDocument::firstOrCreate(
            ['seller_id' => $seller->id, 'type' => SellerDocumentType::PAN_CARD],
            [
                'file_path' => 'documents/sellers/pan_demo.pdf',
                'status' => SellerDocumentStatus::APPROVED,
                'remarks' => 'Verified with NSDL database',
            ]
        );

        // Seller Encrypted Bank Account
        SellerBankAccount::firstOrCreate(
            ['seller_id' => $seller->id, 'account_holder' => 'Apex Retail Enterprises India Pvt Ltd'],
            [
                'account_no' => '50200012345678', // Encrypted by Eloquent
                'ifsc' => 'HDFC0000053',
                'bank_name' => 'HDFC Bank',
                'branch_name' => 'Indiranagar 100ft Road',
                'is_primary' => true,
                'verified_at' => now(),
            ]
        );

        // 4. Physical Store Connect: Indiranagar Central Store (Blinkit-style 10-30m Quick Commerce)
        $store = Store::firstOrCreate(
            ['slug' => 'apex-retail-indiranagar'],
            [
                'seller_id' => $seller->id,
                'name' => 'Apex Retail - Indiranagar Store',
                'description' => 'Flagship physical neighborhood supermarket connected for instant 10-30m deliveries & store walk-ins.',
                'phone' => '08025251122',
                'email' => 'indiranagar@apexretail.in',
                'status' => StoreStatus::ACTIVE,
                'is_onestall_owned' => false,
                'accepts_quick_commerce' => true,
                'accepts_pickup' => true,
                'rating_avg' => 4.85,
                'rating_count' => 128,
            ]
        );

        // Store Geo Location (Indiranagar 100 Feet Road, Bengaluru)
        StoreLocation::firstOrCreate(
            ['store_id' => $store->id],
            [
                'address' => 'Plot 482, 100 Feet Road, HAL 2nd Stage, Indiranagar',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'pincode' => '560038',
                'lat' => 12.9784,
                'lng' => 77.6408,
            ]
        );

        // Store Operational Timings (7 days open 07:00 to 23:00)
        for ($day = 0; $day <= 6; $day++) {
            StoreTiming::firstOrCreate(
                ['store_id' => $store->id, 'day_of_week' => $day],
                [
                    'opens_at' => '07:00:00',
                    'closes_at' => '23:00:00',
                    'is_closed' => false,
                ]
            );
        }

        // Store Delivery Zone (8 km Radial Quick Commerce)
        StoreDeliveryZone::firstOrCreate(
            ['store_id' => $store->id, 'is_quick' => true],
            [
                'type' => DeliveryZoneType::RADIUS,
                'radius_km' => 8.00,
                'pincodes' => ['560038', '560008', '560075', '560071', '560001'],
                'min_order_value' => 99.00,
                'delivery_fee' => 40.00,
            ]
        );

        // Store Staff assignment
        StoreStaff::firstOrCreate(
            ['store_id' => $store->id, 'user_id' => $sellerUser->id],
            [
                'role' => StoreStaffRole::MANAGER,
                'permissions' => ['orders.manage', 'inventory.sync', 'pos.connect', 'dispatch.assign'],
            ]
        );

        // 5. OneStall-Owned Direct Store
        Store::firstOrCreate(
            ['slug' => 'onestall-direct-koramangala'],
            [
                'seller_id' => $seller->id,
                'name' => 'OneStall Direct - Koramangala Dark Hub',
                'description' => 'OneStall platform operated micro-fulfilment dark store.',
                'phone' => '08025253344',
                'email' => 'darkhub-koramangala@onestall.in',
                'status' => StoreStatus::ACTIVE,
                'is_onestall_owned' => true,
                'accepts_quick_commerce' => true,
                'accepts_pickup' => false,
                'rating_avg' => 4.95,
                'rating_count' => 342,
            ]
        );

        // 6. Customer User with Addresses
        $customerUser = User::firstOrCreate(
            ['email' => 'customer@onestall.in'],
            [
                'name' => 'Aarav Mehta',
                'phone' => '9999900003',
                'password' => Hash::make('password'),
                'status' => UserStatus::ACTIVE,
                'email_verified_at' => now(),
            ]
        );
        $customerUser->syncRoles([UserRole::CUSTOMER->value]);

        $customer = Customer::firstOrCreate(
            ['user_id' => $customerUser->id],
            [
                'gender' => 'male',
                'dob' => '1994-08-15',
            ]
        );

        CustomerAddress::firstOrCreate(
            ['customer_id' => $customer->id, 'type' => AddressType::HOME],
            [
                'user_id' => $customerUser->id,
                'name' => 'Aarav Mehta (Home)',
                'phone' => '9999900003',
                'line1' => 'Flat 402, Green Glen Layout, Bellandur',
                'line2' => 'Outer Ring Road',
                'landmark' => 'Near Central Mall',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'pincode' => '560103',
                'lat' => 12.9260,
                'lng' => 77.6762,
                'is_default' => true,
            ]
        );
    }
}
