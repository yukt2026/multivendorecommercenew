<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Order\PlaceOrderAction;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\SellerStatus;
use App\Enums\SellerType;
use App\Enums\SettlementStatus;
use App\Enums\StoreStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Inventory;
use App\Models\InventoryLocation;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\SellerBankAccount;
use App\Models\SellerSettlement;
use App\Models\Store;
use App\Models\StoreConnection;
use App\Models\StoreStaff;
use App\Models\StoreTiming;
use App\Models\User;
use App\Services\Order\OrderStateMachine;
use App\Services\Settlement\SettlementService;
use App\Services\Storefront\CartService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityAndHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $userSellerA;
    protected User $userSellerB;
    protected Seller $sellerA;
    protected Seller $sellerB;
    protected Store $storeA;
    protected Store $storeB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // Admin
        $this->adminUser = User::factory()->create([
            'email' => 'superadmin@onestall.in',
            'name' => 'Platform Super Admin',
        ]);
        $this->adminUser->assignRole('super_admin');

        // Seller A
        $this->userSellerA = User::factory()->create([
            'name' => 'Seller Alice',
            'email' => 'alice@seller-a.com',
        ]);
        $this->userSellerA->assignRole('seller_owner');

        $this->sellerA = Seller::create([
            'user_id' => $this->userSellerA->id,
            'business_name' => 'Alice Mart Pvt Ltd',
            'legal_name' => 'Alice Mart Retailers Private Limited',
            'status' => SellerStatus::APPROVED,
            'seller_type' => SellerType::LOCAL_STORE,
            'pan' => 'ABCDE1234F',
            'gstin' => '29ABCDE1234F1Z5',
        ]);

        $this->storeA = Store::create([
            'seller_id' => $this->sellerA->id,
            'name' => 'Alice Express Store',
            'slug' => 'alice-express-store',
            'status' => 'active',
        ]);

        // Seller B (Attacker / Isolated Peer)
        $this->userSellerB = User::factory()->create([
            'name' => 'Seller Bob',
            'email' => 'bob@seller-b.com',
        ]);
        $this->userSellerB->assignRole('seller_owner');

        $this->sellerB = Seller::create([
            'user_id' => $this->userSellerB->id,
            'business_name' => 'Bob Gadgets Ltd',
            'legal_name' => 'Bob Gadgets Limited',
            'status' => SellerStatus::APPROVED,
            'seller_type' => SellerType::MARKETPLACE,
            'pan' => 'VWXYZ5678K',
            'gstin' => '29VWXYZ5678K1Z3',
        ]);

        $this->storeB = Store::create([
            'seller_id' => $this->sellerB->id,
            'name' => 'Bob Tech Store',
            'slug' => 'bob-tech-store',
            'status' => 'active',
        ]);
    }

    public function test_browser_security_headers_enforced(): void
    {
        $response = $this->get('/');
        $response->assertOk();

        // Check required OWASP security headers
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self)');
        
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
    }

    public function test_seller_data_isolation_multi_tenant_authorization(): void
    {
        // 1. Create a product and variant owned by Seller A
        $category = Category::create([
            'name' => 'Groceries',
            'slug' => 'groceries',
            'is_active' => true,
        ]);

        $productA = Product::create([
            'seller_id' => $this->sellerA->id,
            'category_id' => $category->id,
            'title' => 'Organic Honey 500g',
            'slug' => 'organic-honey-500g',
            'status' => ProductStatus::ACTIVE,
            'product_type' => ProductType::SIMPLE,
        ]);

        $variantA = ProductVariant::create([
            'product_id' => $productA->id,
            'name' => '500g Glass Jar',
            'sku' => 'HONEY-500G',
            'selling_price' => 350.00,
            'mrp' => 400.00,
            'is_active' => true,
        ]);

        // Seller B attempts to modify Seller A's variant via quickRestock / quickUpdateVariant
        $attackerResponse = $this->actingAs($this->userSellerB)
            ->patchJson(route('seller.products.quick_update_variant', $variantA->id), [
                'selling_price' => 1.00,
            ]);

        $attackerResponse->assertStatus(403);
        $this->assertEquals(350.00, (float) $variantA->fresh()->selling_price);

        // Seller B attempts bulk actions on Seller A's product
        $bulkResponse = $this->actingAs($this->userSellerB)
            ->post(route('seller.products.bulk_action'), [
                'action' => 'delete',
                'product_ids' => [$productA->id],
            ]);

        $bulkResponse->assertRedirect();
        // Product A must still exist untouched
        $this->assertNotNull(Product::find($productA->id));

        // 2. Staff isolation: Seller A invites a staff member
        $staffUser = User::factory()->create();
        $staffA = StoreStaff::create([
            'store_id' => $this->storeA->id,
            'user_id' => $staffUser->id,
            'role' => \App\Enums\StoreStaffRole::STORE_MANAGER,
            'status' => 'active',
        ]);

        // Seller B attempts to delete Seller A's staff
        $staffDeleteResponse = $this->actingAs($this->userSellerB)
            ->delete(route('seller.staff.destroy', $staffA->id));

        $staffDeleteResponse->assertStatus(403);
        $this->assertNotNull(StoreStaff::find($staffA->id));
    }

    public function test_webhook_hmac_signature_validation_and_anti_forgery(): void
    {
        $connection = StoreConnection::create([
            'store_id' => $this->storeA->id,
            'type' => \App\Enums\StoreConnectionType::WOOCOMMERCE,
            'name' => 'WooCommerce Integration',
            'status' => 'active',
            'webhook_secret' => 'super-secret-hmac-key-12345',
        ]);

        $payload = json_encode(['id' => 101, 'status' => 'completed', 'total' => '1200']);

        // 1. Forged / invalid signature &rarr; rejected with 401
        $forgedResponse = $this->postJson(
            route('integrations.woocommerce.webhook', $connection->id),
            json_decode($payload, true),
            [
                'X-WC-Webhook-Signature' => 'forged_fake_signature',
                'X-WC-Webhook-Topic' => 'order.created',
            ]
        );

        $forgedResponse->assertStatus(401);

        // 2. Legitimate HMAC-SHA256 signature &rarr; accepted with 200
        $validSignature = base64_encode(hash_hmac('sha256', $payload, 'super-secret-hmac-key-12345', true));

        $legitResponse = $this->call(
            'POST',
            route('integrations.woocommerce.webhook', $connection->id),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_WC_WEBHOOK_SIGNATURE' => $validSignature,
                'HTTP_X_WC_WEBHOOK_TOPIC' => 'order.created',
            ],
            $payload
        );

        $legitResponse->assertOk();
    }

    public function test_rich_text_xss_sanitization_helper(): void
    {
        // Vector 1: Script tags
        $input1 = "<p>Clean text</p><script>alert('XSS')</script>";
        $this->assertEquals('<p>Clean text</p>', clean_html($input1));

        // Vector 2: Inline event handler
        $input2 = '<p><a href="https://example.com" onclick="stealCookie()">Link</a></p>';
        $cleaned2 = clean_html($input2);
        $this->assertStringNotContainsString('onclick', $cleaned2);
        $this->assertStringNotContainsString('stealCookie', $cleaned2);

        // Vector 3: Javascript pseudo-protocol
        $input3 = '<a href="javascript:maliciousCode()">Click Me</a>';
        $cleaned3 = clean_html($input3);
        $this->assertStringNotContainsString('javascript:', $cleaned3);
        $this->assertStringContainsString('href="#"', $cleaned3);
    }

    public function test_end_to_end_critical_marketplace_lifecycle(): void
    {
        // 1. Merchant Onboarding & KYC Approval
        $merchantUser = User::factory()->create([
            'email' => 'artisan@freshfoods.in',
            'name' => 'Pooja Hegde',
        ]);
        $merchantUser->assignRole('seller_owner');

        $seller = Seller::create([
            'user_id' => $merchantUser->id,
            'business_name' => 'Fresh Foods Co',
            'legal_name' => 'Fresh Foods Enterprise Pvt Ltd',
            'seller_type' => SellerType::LOCAL_STORE,
            'status' => SellerStatus::PENDING,
            'pan' => 'ABCDE9999Z',
            'gstin' => '29ABCDE9999Z1Z5',
            'commission_override' => 8.00, // 8% commission
        ]);

        $store = Store::create([
            'seller_id' => $seller->id,
            'name' => 'Fresh Foods Outlet Koramangala',
            'slug' => 'fresh-foods-koramangala',
            'status' => StoreStatus::ACTIVE,
            'accepts_quick_commerce' => true,
            'delivery_radius_km' => 10.00,
        ]);

        for ($day = 0; $day <= 6; $day++) {
            StoreTiming::create([
                'store_id' => $store->id,
                'day_of_week' => $day,
                'opens_at' => '00:00:00',
                'closes_at' => '23:59:59',
                'is_closed' => false,
            ]);
        }

        $invLocation = InventoryLocation::create([
            'store_id' => $store->id,
            'seller_id' => $seller->id,
            'name' => 'Koramangala Store Inventory',
            'type' => 'store',
            'is_active' => true,
            'lat' => 12.9352,
            'lng' => 77.6245,
        ]);

        // Bank Account Setup
        $bank = SellerBankAccount::create([
            'seller_id' => $seller->id,
            'account_holder' => 'Fresh Foods Enterprise',
            'account_no' => '987654321098',
            'ifsc' => 'HDFC0001234',
            'bank_name' => 'HDFC Bank',
            'is_primary' => true,
            'verified_at' => now(),
        ]);

        // Admin reviews and approves Seller
        $seller->update([
            'status' => SellerStatus::APPROVED,
            'approved_at' => now(),
        ]);
        $this->assertTrue($seller->isApproved());

        // 2. Product Catalogue Creation & Inventory Loading
        $category = Category::create([
            'name' => 'Dairy & Farm',
            'slug' => 'dairy-farm',
            'is_active' => true,
        ]);

        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Pure A2 Cow Ghee (1L)',
            'slug' => 'pure-a2-cow-ghee-1l',
            'status' => ProductStatus::ACTIVE,
            'product_type' => ProductType::SIMPLE,
            'is_quick_capable' => true,
            'is_cod_allowed' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'name' => '1 Litre Tin',
            'sku' => 'GHEE-A2-1L',
            'selling_price' => 1200.00,
            'mrp' => 1400.00,
            'is_default' => true,
            'is_active' => true,
        ]);

        $inventory = Inventory::create([
            'variant_id' => $variant->id,
            'location_id' => $invLocation->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
            'is_quick_available' => true,
        ]);

        // 3. Customer Place Order (Single-Page Checkout Flow)
        $customerUser = User::factory()->create([
            'name' => 'Rohan Sen',
            'email' => 'rohan@example.com',
            'phone' => '9988776655',
        ]);
        $customerUser->assignRole('customer');

        $customer = Customer::create([
            'user_id' => $customerUser->id,
        ]);

        $address = CustomerAddress::create([
            'customer_id' => $customer->id,
            'user_id' => $customerUser->id,
            'name' => 'Rohan Sen',
            'phone' => '9988776655',
            'line1' => 'Flat 402, Green Glen',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560034',
            'lat' => 12.9352,
            'lng' => 77.6245,
            'is_default' => true,
        ]);

        $cartService = app(CartService::class);
        $cart = $cartService->getActiveCart($customerUser, 'sec_test_session');
        $cartService->addItem($cart, $variant->id, 2);

        $placeOrderAction = app(PlaceOrderAction::class);
        $order = $placeOrderAction->execute(
            customer: $customer,
            address: $address,
            cart: $cart,
            paymentMethod: 'online',
        );

        $this->assertNotNull($order);
        $this->assertEquals(2400.00, (float) $order->subtotal);
        $this->assertCount(1, $order->fulfilmentOrders);

        $fo = $order->fulfilmentOrders->first();
        $this->assertEquals($seller->id, $fo->seller_id);

        // Stock reserved: 2 units
        $inventory->refresh();
        $this->assertEquals(2, $inventory->quantity_reserved);

        // 4. Payment Captured
        $payment = $order->payments()->first();
        $this->assertNotNull($payment);
        $payment->update([
            'status' => PaymentStatus::PAID,
            'gateway_order_id' => 'order_gateway_test_99',
            'gateway_payment_id' => 'pay_gateway_test_99',
            'verified_at' => now(),
        ]);

        $order->update([
            'payment_status' => PaymentStatus::PAID,
            'status' => OrderStatus::PROCESSING,
        ]);

        // Commit inventory sale upon payment confirmation
        $reservations = \App\Models\InventoryReservation::where('order_ref', $order->order_number)->get();
        $invService = app(\App\Services\Inventory\InventoryService::class);
        foreach ($reservations as $res) {
            $invService->commitSale($res, $order);
        }

        // 5. Fulfilment Order Lifecycle Transitions
        $stateMachine = app(OrderStateMachine::class);
        $stateMachine->transitionFulfilmentOrder($fo, 'store_accepted');
        $stateMachine->transitionFulfilmentOrder($fo, 'packing');
        $stateMachine->transitionFulfilmentOrder($fo, 'ready');
        $stateMachine->transitionFulfilmentOrder($fo, 'picked_up');
        $stateMachine->transitionFulfilmentOrder($fo, 'out_for_delivery');
        $stateMachine->transitionFulfilmentOrder($fo, 'delivered');

        $this->assertEquals('delivered', $fo->fresh()->status);

        // Reserved stock deducted upon delivery
        $inventory->refresh();
        $this->assertEquals(0, $inventory->quantity_reserved);
        $this->assertEquals(48, $inventory->quantity_on_hand);

        // 6. Settlement Batch Generation past return window
        $settlementService = app(SettlementService::class);
        $settlementBatch = $settlementService->generateWeeklyBatch(now()->addDays(10), $seller->id);

        $sellerSettlement = SellerSettlement::where('seller_id', $seller->id)->first();
        $this->assertNotNull($sellerSettlement);
        $this->assertGreaterThan(0, (float) $sellerSettlement->net);

        // Admin approves and marks paid with UTR
        $settlementService->approveSettlement($sellerSettlement);
        $settlementService->markPaid($sellerSettlement, 'UTR-HDFC-99112233');

        $this->assertEquals(SettlementStatus::PAID, $sellerSettlement->fresh()->status);
        $this->assertEquals('UTR-HDFC-99112233', $sellerSettlement->fresh()->reference);
    }
}
