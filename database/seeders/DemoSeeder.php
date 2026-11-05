<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CODRemittanceStatus;
use App\Enums\CouponType;
use App\Enums\DeliveryAssignmentStatus;
use App\Enums\FulfilmentType;
use App\Enums\InventoryLocationType;
use App\Enums\MediaType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\QCStatus;
use App\Enums\QuickFulfilmentStatus;
use App\Enums\RefundStatus;
use App\Enums\ReturnStatus;
use App\Enums\ReviewStatus;
use App\Enums\SellerStatus;
use App\Enums\SellerType;
use App\Enums\ShipmentStatus;
use App\Enums\StandardFulfilmentStatus;
use App\Enums\StoreStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserStatus;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\CodRemittance;
use App\Models\CommissionEntry;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryPartner;
use App\Models\FulfilmentOrder;
use App\Models\Inventory;
use App\Models\InventoryLocation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductSpecification;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\Review;
use App\Models\Seller;
use App\Models\SellerBankAccount;
use App\Models\Shipment;
use App\Models\ShipmentTracking;
use App\Models\Store;
use App\Models\StoreDeliveryZone;
use App\Models\StoreLocation;
use App\Models\StoreTiming;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\Wishlist;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Delivery Partners (Riders for quick commerce)
        $riders = [];
        for ($r = 1; $r <= 3; $r++) {
            $riderUser = User::firstOrCreate(
                ['email' => "rider{$r}@onestall.in"],
                [
                    'name' => "Rider {$r} Kumar",
                    'phone' => "+91980000000{$r}",
                    'password' => Hash::make('Password@123'),
                    'status' => UserStatus::ACTIVE,
                    'email_verified_at' => now(),
                ]
            );
            $riderUser->assignRole('delivery_staff');

            $riders[] = DeliveryPartner::firstOrCreate(
                ['phone' => "+91980000000{$r}"],
                [
                    'user_id' => $riderUser->id,
                    'name' => $riderUser->name,
                    'vehicle' => 'Electric Scooter',
                    'vehicle_number' => "KA-01-EQ-100{$r}",
                    'is_active' => true,
                    'current_lat' => 12.9352 + ($r * 0.005),
                    'current_lng' => 77.6245 + ($r * 0.005),
                ]
            );
        }

        // 2. 5 Demo Sellers & Stores
        $sellerConfigs = [
            [
                'name' => 'Bengaluru SuperMart & Fresh',
                'email' => 'seller.supermart@onestall.in',
                'phone' => '+919811100001',
                'type' => SellerType::LOCAL_STORE,
                'store_name' => 'Bengaluru SuperMart Koramangala',
                'is_onestall' => false,
                'quick' => true,
                'lat' => 12.9352,
                'lng' => 77.6245,
            ],
            [
                'name' => 'Karnataka Agro & Organic Farms',
                'email' => 'seller.agro@onestall.in',
                'phone' => '+919811100002',
                'type' => SellerType::BRAND,
                'store_name' => 'Karnataka Agro Direct',
                'is_onestall' => false,
                'quick' => true,
                'lat' => 12.9716,
                'lng' => 77.5946,
            ],
            [
                'name' => 'TechZone Electronics Hub',
                'email' => 'seller.techzone@onestall.in',
                'phone' => '+919811100003',
                'type' => SellerType::MULTI_STORE,
                'store_name' => 'TechZone Flagship Store Indiranagar',
                'is_onestall' => false,
                'quick' => false,
                'lat' => 12.9784,
                'lng' => 77.6408,
            ],
            [
                'name' => 'OneStall Express Dark Store #1',
                'email' => 'darkstore1@onestall.in',
                'phone' => '+919811100004',
                'type' => SellerType::ONESTALL_STORE,
                'store_name' => 'OneStall Express Quick Pod HSR',
                'is_onestall' => true,
                'quick' => true,
                'lat' => 12.9121,
                'lng' => 77.6446,
            ],
            [
                'name' => 'Deccan Apparel & Lifestyle',
                'email' => 'seller.deccan@onestall.in',
                'phone' => '+919811100005',
                'type' => SellerType::MARKETPLACE,
                'store_name' => 'Deccan Lifestyle Store',
                'is_onestall' => false,
                'quick' => false,
                'lat' => 12.9279,
                'lng' => 77.6271,
            ],
        ];

        $sellers = [];
        $stores = [];
        $locations = [];

        foreach ($sellerConfigs as $idx => $sc) {
            $user = User::firstOrCreate(
                ['email' => $sc['email']],
                [
                    'name' => $sc['name'] . ' Owner',
                    'phone' => $sc['phone'],
                    'password' => Hash::make('Password@123'),
                    'status' => UserStatus::ACTIVE,
                    'email_verified_at' => now(),
                ]
            );
            $user->assignRole('seller_owner');

            $seller = Seller::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'business_name' => $sc['name'],
                    'legal_name' => $sc['name'] . ' Pvt Ltd',
                    'seller_type' => $sc['type'],
                    'pan' => 'ABCDE' . (1000 + $idx) . 'F',
                    'gstin' => '29ABCDE' . (1000 + $idx) . 'F1Z5',
                    'status' => SellerStatus::APPROVED,
                    'commission_override' => $sc['is_onestall'] ? 0.00 : null,
                    'approved_at' => now()->subMonths(6),
                ]
            );

            // Bank Account
            SellerBankAccount::firstOrCreate(
                ['seller_id' => $seller->id],
                [
                    'account_holder' => $sc['name'],
                    'account_no' => '50100' . (8822330 + $idx),
                    'ifsc' => 'HDFC0001234',
                    'bank_name' => 'HDFC Bank Ltd',
                    'is_primary' => true,
                    'verified_at' => now()->subMonths(6),
                ]
            );

            // Store
            $store = Store::firstOrCreate(
                ['seller_id' => $seller->id, 'slug' => Str::slug($sc['store_name'])],
                [
                    'name' => $sc['store_name'],
                    'description' => 'Official verified partner on OneStall.in.',
                    'phone' => $sc['phone'],
                    'email' => $sc['email'],
                    'status' => StoreStatus::ACTIVE,
                    'is_onestall_owned' => $sc['is_onestall'],
                    'accepts_quick_commerce' => $sc['quick'],
                    'accepts_pickup' => true,
                    'rating_avg' => 4.75,
                    'rating_count' => 120,
                ]
            );

            // Store Location
            StoreLocation::firstOrCreate(
                ['store_id' => $store->id],
                [
                    'address' => 'Plot #' . (10 + $idx) . ', Main Commercial Road',
                    'city' => 'Bengaluru',
                    'state' => 'Karnataka',
                    'pincode' => '5600' . (30 + $idx),
                    'lat' => $sc['lat'],
                    'lng' => $sc['lng'],
                ]
            );

            // Store Delivery Zone (Quick commerce 8km)
            StoreDeliveryZone::firstOrCreate(
                ['store_id' => $store->id, 'type' => 'radius'],
                [
                    'radius_km' => 8.00,
                    'pincodes' => null,
                    'min_order_value' => 99.00,
                    'delivery_fee' => 35.00,
                    'is_quick' => $sc['quick'],
                ]
            );

            // Store Timings (Monday to Sunday)
            for ($d = 0; $d <= 6; $d++) {
                StoreTiming::firstOrCreate(
                    ['store_id' => $store->id, 'day_of_week' => $d],
                    [
                        'opens_at' => '07:00:00',
                        'closes_at' => '23:00:00',
                        'is_closed' => false,
                    ]
                );
            }

            // Inventory Location
            $loc = InventoryLocation::firstOrCreate(
                ['seller_id' => $seller->id, 'name' => $sc['store_name'] . ' Fulfillment Center'],
                [
                    'type' => $sc['is_onestall'] ? InventoryLocationType::ONESTALL_STORE : ($sc['quick'] ? InventoryLocationType::STORE : InventoryLocationType::WAREHOUSE),
                    'store_id' => $store->id,
                    'lat' => $sc['lat'],
                    'lng' => $sc['lng'],
                    'is_active' => true,
                ]
            );

            $sellers[] = $seller;
            $stores[] = $store;
            $locations[] = $loc;
        }

        // 3. 40 Demo Products across seeded categories and brands
        $categories = Category::whereNotNull('parent_id')->get();
        if ($categories->isEmpty()) {
            $categories = Category::all();
        }
        $brands = Brand::all();

        $productBlueprints = [
            ['title' => 'Amul Taaza Homogenised Toned Milk 1L', 'brand' => 'Amul', 'mrp' => 74.00, 'sp' => 72.00, 'quick' => true, 'cat' => 'Milk & Curd'],
            ['title' => 'Amul Salted Butter 500g', 'brand' => 'Amul', 'mrp' => 275.00, 'sp' => 265.00, 'quick' => true, 'cat' => 'Butter & Cheese'],
            ['title' => 'Aashirvaad Shudh Chakki Atta 5kg', 'brand' => 'Aashirvaad', 'mrp' => 285.00, 'sp' => 269.00, 'quick' => true, 'cat' => 'Atta, Rice & Grains'],
            ['title' => 'Aashirvaad Select Sharbati Atta 10kg', 'brand' => 'Aashirvaad', 'mrp' => 610.00, 'sp' => 589.00, 'quick' => true, 'cat' => 'Atta, Rice & Grains'],
            ['title' => 'Fortune Sunlite Refined Sunflower Oil 1L', 'brand' => 'Fortune', 'mrp' => 165.00, 'sp' => 148.00, 'quick' => true, 'cat' => 'Edible Oils & Ghee'],
            ['title' => 'Fortune Premium Kachi Ghani Mustard Oil 1L', 'brand' => 'Fortune', 'mrp' => 175.00, 'sp' => 159.00, 'quick' => true, 'cat' => 'Edible Oils & Ghee'],
            ['title' => 'Tata Sampann Unpolished Toor Dal 1kg', 'brand' => 'Tata Sampann', 'mrp' => 195.00, 'sp' => 182.00, 'quick' => true, 'cat' => 'Dals & Pulses'],
            ['title' => 'Tata Sampann Chana Dal 1kg', 'brand' => 'Tata Sampann', 'mrp' => 135.00, 'sp' => 124.00, 'quick' => true, 'cat' => 'Dals & Pulses'],
            ['title' => 'Tata Sampann Turmeric Powder 200g', 'brand' => 'Tata Sampann', 'mrp' => 65.00, 'sp' => 58.00, 'quick' => true, 'cat' => 'Spices & Masalas'],
            ['title' => 'Dettol Original Liquid Handwash Refill 1.5L', 'brand' => 'Dettol', 'mrp' => 249.00, 'sp' => 219.00, 'quick' => true, 'cat' => 'Personal Care & Hygiene'],
            ['title' => 'Dettol Antiseptic Disinfectant Liquid 1L', 'brand' => 'Dettol', 'mrp' => 376.00, 'sp' => 345.00, 'quick' => true, 'cat' => 'Personal Care & Hygiene'],
            ['title' => 'Milton Thermosteel Flip Lid Flask 1000ml', 'brand' => 'Milton', 'mrp' => 1099.00, 'sp' => 899.00, 'quick' => false, 'cat' => 'Home & Kitchen'],
            ['title' => 'Milton Executive Lunch Box with Insulated Bag', 'brand' => 'Milton', 'mrp' => 849.00, 'sp' => 699.00, 'quick' => false, 'cat' => 'Cookware & Non-Stick'],
            ['title' => 'Boat Airdopes 141 Bluetooth Wireless Earbuds', 'brand' => 'Boat', 'mrp' => 4490.00, 'sp' => 1299.00, 'quick' => true, 'cat' => 'Audio & Headphones'],
            ['title' => 'Boat Rockerz 450 Bluetooth On Ear Headphones', 'brand' => 'Boat', 'mrp' => 3990.00, 'sp' => 1499.00, 'quick' => false, 'cat' => 'Audio & Headphones'],
            ['title' => 'Boat Stone 180 5W Portable Bluetooth Speaker', 'brand' => 'Boat', 'mrp' => 2490.00, 'sp' => 999.00, 'quick' => true, 'cat' => 'Audio & Headphones'],
            ['title' => 'Noise ColorFit Pulse Grand Smartwatch 1.69"', 'brand' => 'Noise', 'mrp' => 3999.00, 'sp' => 1499.00, 'quick' => true, 'cat' => 'Smartwatches & Fitness'],
            ['title' => 'Noise Buds VS104 Truly Wireless Earbuds 45h Playtime', 'brand' => 'Noise', 'mrp' => 3499.00, 'sp' => 1199.00, 'quick' => true, 'cat' => 'Audio & Headphones'],
            ['title' => 'OnePlus Nord CE 4 Lite 5G (8GB RAM, 128GB Storage)', 'brand' => 'OnePlus', 'mrp' => 20999.00, 'sp' => 19999.00, 'quick' => false, 'cat' => 'Smartphones & Accessories'],
            ['title' => 'OnePlus 12R 5G (16GB RAM, 256GB Storage)', 'brand' => 'OnePlus', 'mrp' => 45999.00, 'sp' => 42999.00, 'quick' => false, 'cat' => 'Smartphones & Accessories'],
            ['title' => 'Samsung Galaxy M15 5G (6GB RAM, 128GB Storage)', 'brand' => 'Samsung', 'mrp' => 16999.00, 'sp' => 14499.00, 'quick' => false, 'cat' => 'Smartphones & Accessories'],
            ['title' => 'Samsung 25W Fast Type-C Travel Adapter', 'brand' => 'Samsung', 'mrp' => 1699.00, 'sp' => 1199.00, 'quick' => true, 'cat' => 'Computer Accessories'],
            ['title' => 'Fresh Farm Grade A Eggs 30-Egg Tray', 'brand' => 'Amul', 'mrp' => 240.00, 'sp' => 210.00, 'quick' => true, 'cat' => 'Farm Eggs'],
            ['title' => 'Fresh Shimla Royal Delicious Apples 1kg', 'brand' => 'Karnataka Agro & Organic Farms', 'mrp' => 220.00, 'sp' => 189.00, 'quick' => true, 'cat' => 'Fresh Fruits'],
            ['title' => 'Fresh Hybrid Tomatoes 1kg', 'brand' => 'Karnataka Agro & Organic Farms', 'mrp' => 45.00, 'sp' => 36.00, 'quick' => true, 'cat' => 'Fresh Vegetables'],
            ['title' => 'Fresh Jyoti Potato 2kg Polybag', 'brand' => 'Karnataka Agro & Organic Farms', 'mrp' => 80.00, 'sp' => 68.00, 'quick' => true, 'cat' => 'Fresh Vegetables'],
            ['title' => 'Fresh Red Onions 2kg', 'brand' => 'Karnataka Agro & Organic Farms', 'mrp' => 90.00, 'sp' => 78.00, 'quick' => true, 'cat' => 'Fresh Vegetables'],
            ['title' => 'Fresh Coriander Bunch 250g', 'brand' => 'Karnataka Agro & Organic Farms', 'mrp' => 25.00, 'sp' => 18.00, 'quick' => true, 'cat' => 'Organic Greens'],
            ['title' => 'Lipton Green Tea Honey Lemon 100 Tea Bags', 'brand' => 'Tata Sampann', 'mrp' => 590.00, 'sp' => 520.00, 'quick' => true, 'cat' => 'Tea & Coffee'],
            ['title' => 'Nescafe Classic Instant Coffee Jar 200g', 'brand' => 'Tata Sampann', 'mrp' => 485.00, 'sp' => 449.00, 'quick' => true, 'cat' => 'Tea & Coffee'],
            ['title' => 'Lay\'s Classic Salted Potato Chips 115g Pack of 2', 'brand' => 'Amul', 'mrp' => 100.00, 'sp' => 90.00, 'quick' => true, 'cat' => 'Chips & Namkeen'],
            ['title' => 'Haldiram\'s Nagpur Bhujia Sev 400g', 'brand' => 'Tata Sampann', 'mrp' => 135.00, 'sp' => 120.00, 'quick' => true, 'cat' => 'Chips & Namkeen'],
            ['title' => 'Pampers All Round Protection Baby Diaper Pants L 64 Count', 'brand' => 'Dettol', 'mrp' => 1299.00, 'sp' => 999.00, 'quick' => true, 'cat' => 'Diapers & Wet Wipes'],
            ['title' => 'Himalaya Gentle Baby Wipes 72 Sheets Pack of 2', 'brand' => 'Dettol', 'mrp' => 380.00, 'sp' => 299.00, 'quick' => true, 'cat' => 'Baby Care & Kids'],
            ['title' => 'Classmate Pulse 6-Subject Spiral Notebook 300 Pages', 'brand' => 'Milton', 'mrp' => 210.00, 'sp' => 189.00, 'quick' => true, 'cat' => 'Notebooks & Diaries'],
            ['title' => 'Reynolds 045 Fine Carbide Ball Pens Blue Pack of 20', 'brand' => 'Milton', 'mrp' => 160.00, 'sp' => 139.00, 'quick' => true, 'cat' => 'Pens, Pencils & Art'],
            ['title' => 'Deccan Pure Cotton Slim Fit Casual Shirt Navy Blue', 'brand' => 'Deccan Apparel & Lifestyle', 'mrp' => 1899.00, 'sp' => 999.00, 'quick' => false, 'cat' => "Men's Wear"],
            ['title' => 'Deccan Premium Chino Trousers Khaki 32', 'brand' => 'Deccan Apparel & Lifestyle', 'mrp' => 2199.00, 'sp' => 1199.00, 'quick' => false, 'cat' => "Men's Wear"],
            ['title' => 'Prestige Omega Deluxe Granite 3 Pcs Kitchen Non-Stick Cookware Set', 'brand' => 'Milton', 'mrp' => 4390.00, 'sp' => 2799.00, 'quick' => false, 'cat' => 'Cookware & Non-Stick'],
            ['title' => 'Dabur Chyawanprash 2X Immunity 1kg Jar', 'brand' => 'Tata Sampann', 'mrp' => 440.00, 'sp' => 389.00, 'quick' => true, 'cat' => 'Immunity Boosters'],
        ];

        $products = [];
        $variants = [];

        foreach ($productBlueprints as $pIndex => $bp) {
            $seller = $sellers[$pIndex % count($sellers)];
            $brand = $brands->firstWhere('name', $bp['brand']) ?? $brands->first();
            $category = $categories->firstWhere('name', $bp['cat']) ?? $categories->first();

            $product = Product::firstOrCreate(
                ['slug' => Str::slug($bp['title'])],
                [
                    'seller_id' => $seller->id,
                    'brand_id' => $brand?->id,
                    'category_id' => $category?->id,
                    'title' => $bp['title'],
                    'short_desc' => 'High quality authentic product guaranteed with express doorstep dispatch.',
                    'description' => $bp['title'] . ' is carefully sourced and quality certified. Best price and guaranteed authenticity on OneStall.in.',
                    'status' => ProductStatus::ACTIVE,
                    'product_type' => ProductType::SIMPLE,
                    'hsn_code' => 'HSN' . (10000000 + $pIndex),
                    'tax_percent' => 18.00,
                    'return_window_days' => 7,
                    'is_returnable' => true,
                    'is_cod_allowed' => true,
                    'is_quick_capable' => $bp['quick'],
                    'meta_title' => $bp['title'] . ' | Buy Online at Best Price on OneStall',
                    'meta_description' => 'Order ' . $bp['title'] . ' online with fast delivery on OneStall.in.',
                ]
            );

            // Variant
            $variant = ProductVariant::firstOrCreate(
                ['sku' => 'OS-SKU-' . str_pad((string) ($pIndex + 1), 4, '0', STR_PAD_LEFT)],
                [
                    'product_id' => $product->id,
                    'barcode' => '890' . str_pad((string) (1234567890 + $pIndex), 10, '0', STR_PAD_LEFT),
                    'name' => 'Standard Pack',
                    'attributes' => ['standard' => 'true'],
                    'mrp' => $bp['mrp'],
                    'selling_price' => $bp['sp'],
                    'cost_price' => round($bp['sp'] * 0.75, 2),
                    'weight_grams' => 500,
                    'length' => 15.00,
                    'width' => 10.00,
                    'height' => 8.00,
                    'is_default' => true,
                    'is_active' => true,
                ]
            );

            // Media
            ProductMedia::firstOrCreate(
                ['product_id' => $product->id, 'sort_order' => 1],
                [
                    'variant_id' => $variant->id,
                    'type' => MediaType::IMAGE,
                    'path' => 'products/demo-item-' . (($pIndex % 8) + 1) . '.jpg',
                ]
            );

            // Specification
            ProductSpecification::firstOrCreate(
                ['product_id' => $product->id, 'key' => 'Brand'],
                [
                    'group' => 'General',
                    'value' => $brand?->name ?? 'OneStall Assured',
                ]
            );

            // Inventory across seller fulfillment location
            $loc = $locations[$pIndex % count($locations)];
            Inventory::firstOrCreate(
                ['variant_id' => $variant->id, 'location_id' => $loc->id],
                [
                    'quantity_on_hand' => 100,
                    'quantity_reserved' => 5,
                    'low_stock_threshold' => 10,
                    'is_quick_available' => $bp['quick'],
                ]
            );

            $products[] = $product;
            $variants[] = $variant;
        }

        // 4. 20 Demo Customers with Addresses and Active Carts
        $customers = [];
        for ($c = 1; $c <= 20; $c++) {
            $user = User::firstOrCreate(
                ['email' => "customer{$c}@example.com"],
                [
                    'name' => "Customer {$c} Rao",
                    'phone' => "+9199100000" . str_pad((string) $c, 2, '0', STR_PAD_LEFT),
                    'password' => Hash::make('Password@123'),
                    'status' => UserStatus::ACTIVE,
                    'email_verified_at' => now(),
                ]
            );
            $user->assignRole('customer');

            $customer = Customer::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'gender' => ($c % 2 === 0) ? 'female' : 'male',
                    'dob' => '199' . ($c % 10) . '-05-15',
                ]
            );

            // Home Address
            CustomerAddress::firstOrCreate(
                ['customer_id' => $customer->id, 'is_default' => true],
                [
                    'user_id' => $user->id,
                    'type' => 'home',
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'line1' => "Flat #{$c}02, Sunshine Residency",
                    'line2' => "100 Feet Road, 4th Block",
                    'landmark' => 'Near Sony World Junction',
                    'city' => 'Bengaluru',
                    'state' => 'Karnataka',
                    'pincode' => '560034',
                    'lat' => 12.9352 + ($c * 0.001),
                    'lng' => 77.6245 + ($c * 0.001),
                ]
            );

            // Cart & Wishlist
            $cart = Cart::firstOrCreate(
                ['customer_id' => $customer->id, 'is_active' => true],
                ['session_id' => Str::uuid()->toString()]
            );

            // Put 1 item in cart
            $sampleVariant = $variants[$c % count($variants)];
            CartItem::firstOrCreate(
                ['cart_id' => $cart->id, 'variant_id' => $sampleVariant->id],
                [
                    'quantity' => 2,
                    'price_snapshot' => $sampleVariant->selling_price,
                ]
            );

            // Wishlist
            Wishlist::firstOrCreate(
                [
                    'customer_id' => $customer->id,
                    'product_id' => $sampleVariant->product_id,
                    'variant_id' => $sampleVariant->id,
                ]
            );

            $customers[] = $customer;
        }

        // 5. 100 Demo Orders in Mixed Statuses Spread Over Past 30 Days (for Dashboard Analytics)
        $orderStatuses = [
            OrderStatus::DELIVERED,
            OrderStatus::DELIVERED,
            OrderStatus::DELIVERED,
            OrderStatus::OUT_FOR_DELIVERY,
            OrderStatus::PROCESSING,
            OrderStatus::CONFIRMED,
            OrderStatus::PLACED,
            OrderStatus::CANCELLED,
            OrderStatus::REFUNDED,
        ];

        for ($i = 1; $i <= 100; $i++) {
            $customer = $customers[$i % count($customers)];
            $seller = $sellers[$i % count($sellers)];
            $store = $stores[$i % count($stores)];
            $location = $locations[$i % count($locations)];
            $orderStatus = $orderStatuses[$i % count($orderStatuses)];

            // Spread timestamps over last 30 days
            $orderTime = Carbon::now()->subHours(rand(1, 720));

            // Select 1 to 3 items
            $itemCount = rand(1, 3);
            $subtotal = 0.00;
            $orderLines = [];

            for ($j = 0; $j < $itemCount; $j++) {
                $variant = $variants[($i + $j) % count($variants)];
                $qty = rand(1, 2);
                $linePrice = (float) $variant->selling_price;
                $lineTax = round($linePrice * $qty * 0.18, 2);
                $lineTotal = ($linePrice * $qty) + $lineTax;

                $subtotal += ($linePrice * $qty);
                $orderLines[] = [
                    'product_id' => $variant->product_id,
                    'variant_id' => $variant->id,
                    'title' => $variant->product->title,
                    'variant_name' => $variant->name,
                    'sku' => $variant->sku,
                    'qty' => $qty,
                    'price' => $linePrice,
                    'tax' => $lineTax,
                    'discount' => 0.00,
                    'total' => $lineTotal,
                ];
            }

            $taxTotal = round($subtotal * 0.18, 2);
            $deliveryFee = ($subtotal >= 499) ? 0.00 : 40.00;
            $discount = ($i % 5 === 0) ? 50.00 : 0.00;
            $orderTotal = $subtotal + $taxTotal + $deliveryFee - $discount;

            $isPaid = in_array($orderStatus, [OrderStatus::DELIVERED, OrderStatus::OUT_FOR_DELIVERY, OrderStatus::PROCESSING, OrderStatus::CONFIRMED]);
            $isQuick = $store->accepts_quick_commerce && ($i % 2 === 0);

            $order = Order::firstOrCreate(
                ['order_number' => 'OS-ORD-' . str_pad((string) $i, 5, '0', STR_PAD_LEFT)],
                [
                    'customer_id' => $customer->id,
                    'address_snapshot' => [
                        'name' => $customer->user->name,
                        'phone' => $customer->user->phone,
                        'line1' => "Flat #" . rand(101, 909) . ", Koramangala Heights",
                        'city' => 'Bengaluru',
                        'state' => 'Karnataka',
                        'pincode' => '560034',
                    ],
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'delivery_fee' => $deliveryFee,
                    'tax' => $taxTotal,
                    'total' => $orderTotal,
                    'payment_method' => ($i % 4 === 0) ? PaymentMethod::COD : PaymentMethod::UPI,
                    'payment_status' => $isPaid ? PaymentStatus::PAID : ($orderStatus === OrderStatus::REFUNDED ? PaymentStatus::REFUNDED : PaymentStatus::PENDING),
                    'status' => $orderStatus,
                    'created_at' => $orderTime,
                    'updated_at' => $orderTime,
                ]
            );

            // Child Fulfilment Order
            $commissionAmt = round($subtotal * 0.10, 2);
            $sellerPayable = $subtotal - $commissionAmt;

            $foStatus = $isQuick
                ? ($orderStatus === OrderStatus::DELIVERED ? QuickFulfilmentStatus::DELIVERED->value : QuickFulfilmentStatus::RIDER_ASSIGNED->value)
                : ($orderStatus === OrderStatus::DELIVERED ? StandardFulfilmentStatus::DELIVERED->value : StandardFulfilmentStatus::IN_TRANSIT->value);

            $fo = FulfilmentOrder::firstOrCreate(
                ['fulfilment_order_number' => 'OS-FO-' . str_pad((string) $i, 5, '0', STR_PAD_LEFT)],
                [
                    'order_id' => $order->id,
                    'seller_id' => $seller->id,
                    'store_id' => $store->id,
                    'inventory_location_id' => $location->id,
                    'fulfilment_type' => $isQuick ? FulfilmentType::QUICK : FulfilmentType::STANDARD,
                    'status' => $foStatus,
                    'subtotal' => $subtotal,
                    'commission_amount' => $commissionAmt,
                    'seller_payable' => $sellerPayable,
                    'delivery_fee' => $deliveryFee,
                    'estimated_delivery_at' => $orderTime->copy()->addMinutes($isQuick ? 30 : 2880),
                    'delivered_at' => ($orderStatus === OrderStatus::DELIVERED) ? $orderTime->copy()->addMinutes($isQuick ? 25 : 2400) : null,
                    'created_at' => $orderTime,
                    'updated_at' => $orderTime,
                ]
            );

            // Order Items
            foreach ($orderLines as $ol) {
                OrderItem::firstOrCreate(
                    [
                        'fulfilment_order_id' => $fo->id,
                        'variant_id' => $ol['variant_id'],
                    ],
                    array_merge($ol, [
                        'fulfilment_order_id' => $fo->id,
                        'created_at' => $orderTime,
                        'updated_at' => $orderTime,
                    ])
                );
            }

            // Status History
            OrderStatusHistory::create([
                'orderable_type' => FulfilmentOrder::class,
                'orderable_id' => $fo->id,
                'from_status' => 'placed',
                'to_status' => $foStatus,
                'note' => 'System batch event update',
                'created_at' => $orderTime,
                'updated_at' => $orderTime,
            ]);

            // Payment record
            if ($isPaid) {
                Payment::firstOrCreate(
                    ['order_id' => $order->id],
                    [
                        'gateway' => 'razorpay',
                        'gateway_order_id' => 'order_rzp_' . $i,
                        'gateway_payment_id' => 'pay_rzp_' . $i,
                        'method' => PaymentMethod::UPI,
                        'amount' => $orderTotal,
                        'currency' => 'INR',
                        'status' => PaymentStatus::PAID,
                        'raw_response' => ['status' => 'captured'],
                        'verified_at' => $orderTime,
                        'created_at' => $orderTime,
                        'updated_at' => $orderTime,
                    ]
                );
            }

            // Commission Entry
            CommissionEntry::firstOrCreate(
                ['fulfilment_order_id' => $fo->id],
                [
                    'rule_id' => null,
                    'base_amount' => $subtotal,
                    'commission' => $commissionAmt,
                    'gateway_fee' => round($subtotal * 0.02, 2),
                    'shipping_adjustment' => 0.00,
                    'net_payable' => $sellerPayable,
                    'created_at' => $orderTime,
                    'updated_at' => $orderTime,
                ]
            );

            // Shipment
            $shp = Shipment::firstOrCreate(
                ['fulfilment_order_id' => $fo->id],
                [
                    'shipment_number' => 'OSC-SHP-' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                    'provider' => 'onestall_cargo',
                    'awb' => 'AWB' . (9000000000 + $i),
                    'courier' => $isQuick ? 'OneStall Hyperlocal Express' : 'OneStall Cargo Linehaul',
                    'service' => $isQuick ? 'Hyperlocal' : 'Surface Express',
                    'status' => ($orderStatus === OrderStatus::DELIVERED) ? ShipmentStatus::DELIVERED : ShipmentStatus::IN_TRANSIT,
                    'pickup_status' => 'picked_up',
                    'cod_amount' => ($order->payment_method === PaymentMethod::COD) ? $orderTotal : 0.00,
                    'weight' => 1.25,
                    'charges' => 45.00,
                    'created_at' => $orderTime,
                    'updated_at' => $orderTime,
                ]
            );

            // Tracking
            ShipmentTracking::create([
                'shipment_id' => $shp->id,
                'status' => $shp->status->value,
                'location' => 'Bengaluru Distribution Center',
                'event_time' => $orderTime,
                'raw' => ['source' => 'OneStall Cargo Scanner'],
                'created_at' => $orderTime,
                'updated_at' => $orderTime,
            ]);

            // Rider assignment for quick orders
            if ($isQuick) {
                $rider = $riders[$i % count($riders)];
                DeliveryAssignment::firstOrCreate(
                    ['fulfilment_order_id' => $fo->id],
                    [
                        'shipment_id' => $shp->id,
                        'delivery_partner_id' => $rider->id,
                        'status' => ($orderStatus === OrderStatus::DELIVERED) ? DeliveryAssignmentStatus::DELIVERED : DeliveryAssignmentStatus::ACCEPTED,
                        'assigned_at' => $orderTime,
                        'accepted_at' => $orderTime->copy()->addMinutes(2),
                        'picked_up_at' => $orderTime->copy()->addMinutes(8),
                        'delivered_at' => ($orderStatus === OrderStatus::DELIVERED) ? $orderTime->copy()->addMinutes(25) : null,
                        'created_at' => $orderTime,
                        'updated_at' => $orderTime,
                    ]
                );
            }
        }

        // 6. Promotional Coupons
        $coupons = [
            [
                'code' => 'WELCOME100',
                'type' => CouponType::FLAT,
                'value' => 100.00,
                'max_discount' => 100.00,
                'min_order' => 499.00,
                'usage_limit' => 5000,
                'per_user_limit' => 1,
                'starts_at' => now()->subMonth(),
                'ends_at' => now()->addMonths(6),
                'is_active' => true,
            ],
            [
                'code' => 'SUPER50',
                'type' => CouponType::PERCENT,
                'value' => 50.00,
                'max_discount' => 150.00,
                'min_order' => 299.00,
                'usage_limit' => 10000,
                'per_user_limit' => 2,
                'starts_at' => now()->subDays(10),
                'ends_at' => now()->addMonths(3),
                'is_active' => true,
            ],
            [
                'code' => 'FESTIVE20',
                'type' => CouponType::PERCENT,
                'value' => 20.00,
                'max_discount' => 500.00,
                'min_order' => 999.00,
                'usage_limit' => 20000,
                'per_user_limit' => 3,
                'starts_at' => now()->subDays(5),
                'ends_at' => now()->addMonths(2),
                'is_active' => true,
            ],
        ];

        foreach ($coupons as $cp) {
            Coupon::updateOrCreate(['code' => $cp['code']], $cp);
        }

        // 7. Storefront Banners
        $banners = [
            [
                'title' => 'Big Savings Festival on Groceries & Staples',
                'position' => 'home_hero',
                'image' => 'banners/hero_groceries.jpg',
                'link' => '/categories/groceries-staples',
                'sort' => 1,
                'is_active' => true,
            ],
            [
                'title' => 'Hyper-Local 10-Minute Delivery from Local Stores',
                'position' => 'home_hero',
                'image' => 'banners/hero_quick_delivery.jpg',
                'link' => '/quick-commerce',
                'sort' => 2,
                'is_active' => true,
            ],
            [
                'title' => 'Latest Audio & Smartwatches on Sale',
                'position' => 'home_sub',
                'image' => 'banners/sub_electronics.jpg',
                'link' => '/categories/electronics-gadgets',
                'sort' => 1,
                'is_active' => true,
            ],
        ];

        foreach ($banners as $bn) {
            Banner::updateOrCreate(['title' => $bn['title']], $bn);
        }

        // 8. Product Reviews
        foreach (array_slice($products, 0, 10) as $revProduct) {
            Review::firstOrCreate(
                [
                    'product_id' => $revProduct->id,
                    'user_id' => $customers[0]->user_id,
                ],
                [
                    'rating' => 5,
                    'title' => 'Excellent quality and super fast delivery!',
                    'body' => 'Received the exact genuine product within 30 minutes from the nearby store. Highly recommended!',
                    'is_verified_purchase' => true,
                    'status' => ReviewStatus::APPROVED,
                ]
            );
        }

        // 9. Support Tickets
        $ticket = SupportTicket::firstOrCreate(
            ['ticket_number' => 'TKT-2026-0001'],
            [
                'user_id' => $customers[0]->user_id,
                'seller_id' => $sellers[0]->id,
                'order_id' => 1,
                'subject' => 'Need updated GST invoice for Order #OS-ORD-00001',
                'category' => 'order',
                'priority' => TicketPriority::MEDIUM,
                'status' => TicketStatus::IN_PROGRESS,
            ]
        );

        TicketMessage::firstOrCreate(
            ['support_ticket_id' => $ticket->id],
            [
                'user_id' => $customers[0]->user_id,
                'message' => 'Hi OneStall team, could you please email me the GST invoice with business PAN added?',
                'is_internal' => false,
            ]
        );
    }
}
