<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\FulfilmentType;
use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\FulfilmentOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->adminUser = User::factory()->create([
            'email' => 'admin@onestall.in',
            'name' => 'Admin Controller',
        ]);
        $this->adminUser->assignRole('admin');
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $response = $this->get('/admin');
        $response->assertRedirect(route('admin.login'));

        $chartResponse = $this->getJson('/admin/dashboard/chart-data');
        $chartResponse->assertStatus(401);
    }

    public function test_customer_user_is_forbidden_from_admin_dashboard(): void
    {
        $customerUser = User::factory()->create();
        $customerUser->assignRole('customer');

        $response = $this->actingAs($customerUser)->get('/admin');
        $response->assertStatus(403);

        $chartResponse = $this->actingAs($customerUser)->getJson('/admin/dashboard/chart-data');
        $chartResponse->assertStatus(403);
    }

    public function test_seller_user_is_forbidden_from_admin_dashboard(): void
    {
        $sellerUser = User::factory()->create();
        $sellerUser->assignRole('seller_owner');

        $response = $this->actingAs($sellerUser)->get('/admin');
        $response->assertStatus(403);
    }

    public function test_admin_dashboard_renders_successfully_with_kpi_and_widgets(): void
    {
        // Seed sample seller and customer to test KPI rendering
        $sellerUser = User::factory()->create();
        $seller = Seller::factory()->create([
            'user_id' => $sellerUser->id,
            'status' => SellerStatus::APPROVED,
            'approved_at' => now(),
        ]);

        $customerUser = User::factory()->create();
        Customer::factory()->create(['user_id' => $customerUser->id]);

        $response = $this->actingAs($this->adminUser)->get('/admin');

        $response->assertStatus(200);
        $response->assertSee('Central Operations & Command Hub', false);
        $response->assertSee('Total GMV');
        $response->assertSee('Total Orders');
        $response->assertSee('Active Sellers');
        $response->assertSee('Active Customers');
        $response->assertSee('Pending KYC');
        $response->assertSee('Pending Products');
        $response->assertSee('Quick Orders');
        $response->assertSee('Commission Earned');
        $response->assertSee('Top 10 Merchants by Sales Volume');
        $response->assertSee('System Health &amp; Infrastructure', false);
        $response->assertViewHas('stats');
        $response->assertViewHas('widgets');
        $response->assertViewHas('topSellers');
        $response->assertViewHas('activeRange', '30d');
    }

    public function test_chart_data_endpoint_returns_json_structure_for_all_charts(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson('/admin/dashboard/chart-data?range=30d&chart=all');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'range',
                'chart',
                'data' => [
                    'stats' => [
                        'total_gmv' => ['value', 'formatted', 'trend'],
                        'total_orders' => ['value', 'formatted', 'trend'],
                        'active_sellers' => ['value', 'formatted', 'trend'],
                        'active_customers' => ['value', 'formatted', 'trend'],
                        'pending_seller_approvals' => ['value', 'formatted', 'trend'],
                        'pending_product_reviews' => ['value', 'formatted', 'trend'],
                        'quick_orders' => ['value', 'formatted', 'trend'],
                        'platform_commission' => ['value', 'formatted', 'trend'],
                    ],
                    'sales_orders' => ['labels', 'datasets'],
                    'order_types' => ['labels', 'datasets'],
                    'category_revenue' => ['labels', 'datasets'],
                    'top_sellers',
                    'status_funnel' => ['labels', 'datasets'],
                ],
            ]);
    }

    public function test_chart_data_endpoint_can_return_specific_chart(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson('/admin/dashboard/chart-data?range=7d&chart=sales_orders');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'range' => '7d',
                'chart' => 'sales_orders',
            ])
            ->assertJsonStructure([
                'data' => ['labels', 'datasets'],
            ]);
    }

    public function test_dashboard_summary_can_be_exported_as_csv(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/dashboard/export?range=30d');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="onestall_dashboard_summary_', (string) $response->headers->get('Content-Disposition'));

        // Read stream content
        $content = $response->streamedContent();
        $this->assertStringContainsString('OneStall Smart Commerce Platform - Executive Dashboard Summary', $content);
        $this->assertStringContainsString('KEY PERFORMANCE INDICATORS', $content);
        $this->assertStringContainsString('Total Platform GMV', $content);
        $this->assertStringContainsString('REVENUE BY CATEGORY', $content);
        $this->assertStringContainsString('TOP 10 MERCHANTS BY REVENUE', $content);
    }
}
