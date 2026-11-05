<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CommissionRuleScope;
use App\Enums\UserStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CommissionRule;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProductionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Roles and Permissions
        $this->call(RolesAndPermissionsSeeder::class);

        // 2. Super Admin User
        $superAdminEmail = env('ADMIN_EMAIL', 'admin@onestall.in');
        $superAdminPassword = env('ADMIN_PASSWORD', 'Password@123');

        $superAdmin = User::firstOrCreate(
            ['email' => $superAdminEmail],
            [
                'name' => 'OneStall Super Admin',
                'phone' => '+919999900000',
                'password' => Hash::make($superAdminPassword),
                'status' => UserStatus::ACTIVE,
                'email_verified_at' => now(),
            ]
        );

        $superAdmin->syncRoles(['super_admin', 'admin']);

        // 3. Platform Settings
        $settings = [
            [
                'key' => 'default_commission_pct',
                'value' => '10.00',
                'type' => 'float',
                'group' => 'commission',
                'label' => 'Default Platform Commission Rate (%)',
                'description' => 'Platform fee percentage charged on completed marketplace orders',
                'is_public' => false,
            ],
            [
                'key' => 'default_delivery_radius_km',
                'value' => '8.00',
                'type' => 'float',
                'group' => 'logistics',
                'label' => 'Quick Commerce Delivery Radius (km)',
                'description' => 'Maximum distance radius for physical store connect and 10-30 min hyper-local deliveries',
                'is_public' => true,
            ],
            [
                'key' => 'base_delivery_fee',
                'value' => '40.00',
                'type' => 'float',
                'group' => 'logistics',
                'label' => 'Base Delivery Fee',
                'description' => 'Standard starting shipping charge for local deliveries',
                'is_public' => true,
            ],
            [
                'key' => 'free_delivery_threshold',
                'value' => '499.00',
                'type' => 'float',
                'group' => 'logistics',
                'label' => 'Free Delivery Cart Minimum',
                'description' => 'Cart subtotal required to qualify for free shipping',
                'is_public' => true,
            ],
            [
                'key' => 'order_number_prefix',
                'value' => 'OS-',
                'type' => 'string',
                'group' => 'orders',
                'label' => 'Order Code Prefix',
                'description' => 'Prefix assigned to customer order identifiers',
                'is_public' => false,
            ],
            [
                'key' => 'cargo_tracking_prefix',
                'value' => 'OSC-',
                'type' => 'string',
                'group' => 'cargo',
                'label' => 'Cargo Waybill Prefix',
                'description' => 'Tracking number prefix for OneStall Cargo logistics consignments',
                'is_public' => true,
            ],
            [
                'key' => 'fulfilment_priority',
                'value' => json_encode([
                    'Nearby Store',
                    'Nearby Seller',
                    'OneStall Store',
                    'Regional Warehouse',
                    'National Seller',
                ]),
                'type' => 'json',
                'group' => 'fulfilment',
                'label' => 'Fulfilment Priority List',
                'description' => 'Order in which routing algorithm searches for stock allocation',
                'is_public' => false,
            ],
            [
                'key' => 'currency',
                'value' => 'INR',
                'type' => 'string',
                'group' => 'general',
                'label' => 'Store Currency Code',
                'description' => 'ISO standard 3-character currency code',
                'is_public' => true,
            ],
            [
                'key' => 'currency_symbol',
                'value' => '₹',
                'type' => 'string',
                'group' => 'general',
                'label' => 'Currency Symbol',
                'description' => 'Visual currency glyph',
                'is_public' => true,
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(['key' => $setting['key']], $setting);
        }

        // 4. Default Global Commission Rule
        CommissionRule::updateOrCreate(
            ['name' => 'Default Global Commission Rule'],
            [
                'scope' => CommissionRuleScope::GLOBAL,
                'category_id' => null,
                'seller_id' => null,
                'percent' => 10.00,
                'fixed_fee' => 0.00,
                'priority' => 0,
                'is_active' => true,
            ]
        );

        // 5. 12 Categories with Subcategories and Category-Specific Commission Rules
        $catalogueData = [
            [
                'name' => 'Groceries & Staples',
                'commission' => 5.00,
                'quick' => true,
                'children' => ['Atta, Rice & Grains', 'Edible Oils & Ghee', 'Dals & Pulses', 'Spices & Masalas'],
            ],
            [
                'name' => 'Fresh Fruits & Vegetables',
                'commission' => 6.00,
                'quick' => true,
                'children' => ['Fresh Vegetables', 'Fresh Fruits', 'Organic Greens', 'Exotic Cuts'],
            ],
            [
                'name' => 'Dairy, Bread & Eggs',
                'commission' => 5.00,
                'quick' => true,
                'children' => ['Milk & Curd', 'Butter & Cheese', 'Bakery & Bread', 'Farm Eggs'],
            ],
            [
                'name' => 'Snacks & Beverages',
                'commission' => 8.00,
                'quick' => true,
                'children' => ['Chips & Namkeen', 'Cold Drinks & Juices', 'Tea & Coffee', 'Biscuits & Cookies'],
            ],
            [
                'name' => 'Personal Care & Hygiene',
                'commission' => 10.00,
                'quick' => true,
                'children' => ['Bathing Soaps & Body Wash', 'Hair Care & Shampoos', 'Oral Care', 'Skin & Face Care'],
            ],
            [
                'name' => 'Electronics & Gadgets',
                'commission' => 8.00,
                'quick' => false,
                'children' => ['Smartphones & Accessories', 'Audio & Headphones', 'Smartwatches & Fitness', 'Computer Accessories'],
            ],
            [
                'name' => 'Home & Kitchen',
                'commission' => 12.00,
                'quick' => false,
                'children' => ['Cookware & Non-Stick', 'Kitchen Storage & Containers', 'Cleaning & Household', 'Home Decor'],
            ],
            [
                'name' => 'Fashion & Apparel',
                'commission' => 15.00,
                'quick' => false,
                'children' => ["Men's Wear", "Women's Ethnic & Western", "Kids' Clothing", 'Footwear & Bags'],
            ],
            [
                'name' => 'Health & Wellness',
                'commission' => 7.00,
                'quick' => true,
                'children' => ['Nutritional Supplements', 'Ayurveda & Herbal', 'First Aid & Medical Devices', 'Immunity Boosters'],
            ],
            [
                'name' => 'Beauty & Cosmetics',
                'commission' => 12.00,
                'quick' => true,
                'children' => ['Makeup Essentials', 'Fragrances & Deodorants', 'Lipsticks & Nails', "Men's Grooming"],
            ],
            [
                'name' => 'Baby Care & Kids',
                'commission' => 8.00,
                'quick' => true,
                'children' => ['Diapers & Wet Wipes', 'Baby Food & Formula', 'Baby Skincare', 'Toys & Early Learning'],
            ],
            [
                'name' => 'Stationery & Office Supplies',
                'commission' => 10.00,
                'quick' => true,
                'children' => ['Notebooks & Diaries', 'Pens, Pencils & Art', 'Desk Organizers', 'Calculators & Tape'],
            ],
        ];

        $sortOrder = 1;
        foreach ($catalogueData as $catItem) {
            $parent = Category::updateOrCreate(
                ['slug' => Str::slug($catItem['name'])],
                [
                    'name' => $catItem['name'],
                    'parent_id' => null,
                    'image' => 'categories/' . Str::slug($catItem['name']) . '.jpg',
                    'commission_percent' => $catItem['commission'],
                    'is_quick_eligible' => $catItem['quick'],
                    'is_active' => true,
                    'sort_order' => $sortOrder++,
                ]
            );

            // Create Category Commission Rule
            CommissionRule::updateOrCreate(
                ['name' => $parent->name . ' Rule'],
                [
                    'scope' => CommissionRuleScope::CATEGORY,
                    'category_id' => $parent->id,
                    'seller_id' => null,
                    'percent' => $catItem['commission'],
                    'fixed_fee' => 0.00,
                    'priority' => 10,
                    'is_active' => true,
                ]
            );

            $childSort = 1;
            foreach ($catItem['children'] as $childName) {
                Category::updateOrCreate(
                    ['slug' => Str::slug($childName)],
                    [
                        'name' => $childName,
                        'parent_id' => $parent->id,
                        'image' => 'categories/' . Str::slug($childName) . '.jpg',
                        'commission_percent' => $catItem['commission'],
                        'is_quick_eligible' => $catItem['quick'],
                        'is_active' => true,
                        'sort_order' => $childSort++,
                    ]
                );
            }
        }

        // 6. 10 Verified Platform Brands
        $brands = [
            ['name' => 'Amul', 'logo' => 'brands/amul.png'],
            ['name' => 'Tata Sampann', 'logo' => 'brands/tatasampann.png'],
            ['name' => 'Aashirvaad', 'logo' => 'brands/aashirvaad.png'],
            ['name' => 'Fortune', 'logo' => 'brands/fortune.png'],
            ['name' => 'Boat', 'logo' => 'brands/boat.png'],
            ['name' => 'Noise', 'logo' => 'brands/noise.png'],
            ['name' => 'OnePlus', 'logo' => 'brands/oneplus.png'],
            ['name' => 'Samsung', 'logo' => 'brands/samsung.png'],
            ['name' => 'Dettol', 'logo' => 'brands/dettol.png'],
            ['name' => 'Milton', 'logo' => 'brands/milton.png'],
        ];

        foreach ($brands as $brand) {
            Brand::updateOrCreate(
                ['slug' => Str::slug($brand['name'])],
                [
                    'name' => $brand['name'],
                    'logo' => $brand['logo'],
                    'seller_id' => null,
                    'is_verified' => true,
                ]
            );
        }
    }
}
