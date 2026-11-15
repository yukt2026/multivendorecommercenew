<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SellerStatus;
use App\Models\Category;
use App\Models\CommissionEntry;
use App\Models\Customer;
use App\Models\FulfilmentOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\SellerSettlement;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReportsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->adminUser = User::factory()->create([
            'email' => 'admin@onestall.in',
            'name' => 'Platform Super Admin',
        ]);
        $this->adminUser->assignRole('super_admin');
    }

    public function test_all_report_tabs_render_cleanly_with_data(): void
    {
        $sellerUser = User::factory()->create();
        $seller = Seller::factory()->create([
            'user_id' => $sellerUser->id,
            'business_name' => 'Koramangala SuperMart',
            'status' => SellerStatus::APPROVED,
        ]);

        $category = Category::create([
            'name' => 'Groceries & Gourmet',
            'slug' => 'groceries-gourmet',
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Organic Honey 500g',
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'selling_price' => 350.00,
        ]);

        $customerUser = User::factory()->create();
        $customer = Customer::create(['user_id' => $customerUser->id]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-REPORT-TEST-1',
            'subtotal' => 350.00,
            'tax' => 17.50,
            'delivery_fee' => 30.00,
            'total' => 397.50,
            'payment_status' => PaymentStatus::PAID,
            'payment_method' => \App\Enums\PaymentMethod::UPI,
            'status' => OrderStatus::DELIVERED,
            'address_snapshot' => ['name' => 'John Doe', 'city' => 'Bengaluru', 'pincode' => '560034'],
        ]);

        $fo = FulfilmentOrder::create([
            'order_id' => $order->id,
            'fulfilment_order_number' => 'PKG-REPORT-TEST-1',
            'seller_id' => $seller->id,
            'fulfilment_type' => 'quick',
            'status' => 'delivered',
            'subtotal' => 350.00,
            'commission_amount' => 17.50,
            'seller_payable' => 332.50,
        ]);

        OrderItem::create([
            'fulfilment_order_id' => $fo->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'title' => 'Organic Honey 500g',
            'sku' => 'HONEY-500G',
            'qty' => 1,
            'price' => 350.00,
            'tax' => 17.50,
            'total' => 350.00,
        ]);

        CommissionEntry::create([
            'fulfilment_order_id' => $fo->id,
            'base_amount' => 350.00,
            'commission' => 17.50,
            'tax_on_commission' => 3.15,
            'net_payable' => 329.35,
        ]);

        // Test each tab renders with HTTP 200 and expected data
        $tabs = ['sales', 'orders', 'sellers', 'categories', 'tax', 'commission'];

        foreach ($tabs as $tab) {
            $response = $this->actingAs($this->adminUser)->get(route('admin.reports.index', ['tab' => $tab]));
            $response->assertStatus(200);
            $response->assertViewIs('admin.reports.index');
            $response->assertSee('Business Intelligence & Reports');
        }

        // Test preset filtering
        $presetResponse = $this->actingAs($this->adminUser)->get(route('admin.reports.index', ['tab' => 'sales', 'preset' => 'today']));
        $presetResponse->assertStatus(200);

        // Test every CSV export type
        foreach ($tabs as $type) {
            $export = $this->actingAs($this->adminUser)->get(route('admin.reports.export', ['type' => $type]));
            $export->assertStatus(200);
            $this->assertStringContainsString('text/csv', strtolower((string) $export->headers->get('content-type')));
            $content = $export->streamedContent();
            $this->assertNotEmpty($content);
        }
    }
}
