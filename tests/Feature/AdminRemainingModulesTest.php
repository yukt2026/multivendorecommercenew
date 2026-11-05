<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SellerStatus;
use App\Enums\StoreStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Banner;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\CmsPage;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\Store;
use App\Models\StoreLocation;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminRemainingModulesTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $customerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->adminUser = User::factory()->create([
            'email' => 'admin@onestall.in',
            'name' => 'Platform Super Admin',
        ]);
        $this->adminUser->assignRole('super_admin');

        $this->customerUser = User::factory()->create([
            'email' => 'customer@test.in',
            'name' => 'Test Customer',
        ]);
        $this->customerUser->assignRole('customer');
    }

    /* -------------------------------------------------------------------------
     | 1. Customer Management
     | ---------------------------------------------------------------------- */

    public function test_admin_can_view_customers_list_and_details(): void
    {
        $customer = Customer::create([
            'user_id' => $this->customerUser->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.customers.index'));
        $response->assertStatus(200);
        $response->assertViewIs('admin.customers.index');
        $response->assertSee('Test Customer');

        $showResponse = $this->actingAs($this->adminUser)->get(route('admin.customers.show', $customer));
        $showResponse->assertStatus(200);
        $showResponse->assertViewIs('admin.customers.show');
        $showResponse->assertSee('customer@test.in');
    }

    public function test_admin_can_toggle_customer_status_and_export(): void
    {
        $customer = Customer::create([
            'user_id' => $this->customerUser->id,
        ]);

        $toggleResponse = $this->actingAs($this->adminUser)->post(route('admin.customers.toggle_status', $customer));
        $toggleResponse->assertRedirect();
        $this->assertFalse($customer->fresh()->is_active);

        $exportResponse = $this->actingAs($this->adminUser)->get(route('admin.customers.export'));
        $exportResponse->assertStatus(200);
        $this->assertStringContainsString('text/csv', strtolower($exportResponse->headers->get('content-type')));
    }

    /* -------------------------------------------------------------------------
     | 2. Physical & Dark Stores Management
     | ---------------------------------------------------------------------- */

    public function test_admin_can_manage_stores_and_view_map(): void
    {
        $seller = Seller::factory()->create(['status' => SellerStatus::APPROVED]);
        $store = Store::create([
            'seller_id' => $seller->id,
            'name' => 'Metro Express Darkstore',
            'slug' => 'metro-express-darkstore',
            'status' => StoreStatus::ACTIVE,
            'is_dark_store' => true,
            'is_quick_enabled' => true,
            'quick_delivery_radius_km' => 7.5,
        ]);

        StoreLocation::create([
            'store_id' => $store->id,
            'address' => 'Plot 44, Tech Zone',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560001',
            'lat' => 12.9716,
            'lng' => 77.5946,
        ]);

        $indexResponse = $this->actingAs($this->adminUser)->get(route('admin.stores.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('Metro Express Darkstore');

        $mapResponse = $this->actingAs($this->adminUser)->get(route('admin.stores.map'));
        $mapResponse->assertStatus(200);
        $mapResponse->assertViewIs('admin.stores.map');
        $mapResponse->assertSee('Metro Express Darkstore');

        $showResponse = $this->actingAs($this->adminUser)->get(route('admin.stores.show', $store));
        $showResponse->assertStatus(200);

        // Suspend store
        $suspendResponse = $this->actingAs($this->adminUser)->post(route('admin.stores.suspend', $store));
        $suspendResponse->assertRedirect();
        $this->assertEquals(StoreStatus::SUSPENDED, $store->fresh()->status);

        // Approve store
        $approveResponse = $this->actingAs($this->adminUser)->post(route('admin.stores.approve', $store));
        $approveResponse->assertRedirect();
        $this->assertEquals(StoreStatus::ACTIVE, $store->fresh()->status);

        // Export stores
        $exportResponse = $this->actingAs($this->adminUser)->get(route('admin.stores.export'));
        $exportResponse->assertStatus(200);
    }

    /* -------------------------------------------------------------------------
     | 3. Payments, Refunds & Ledger Inspection
     | ---------------------------------------------------------------------- */

    public function test_admin_can_view_payments_and_process_refund(): void
    {
        $customer = Customer::create([
            'user_id' => $this->customerUser->id,
        ]);

        $order = Order::create([
            'user_id' => $this->customerUser->id,
            'customer_id' => $customer->id,
            'order_number' => 'ORD-TEST-PAY-01',
            'subtotal' => 200.00,
            'discount' => 0.00,
            'delivery_fee' => 0.00,
            'tax' => 0.00,
            'total' => 200.00,
            'status' => OrderStatus::DELIVERED,
            'payment_status' => PaymentStatus::PAID,
            'payment_method' => PaymentMethod::CARD,
            'address_snapshot' => ['city' => 'Bengaluru', 'pincode' => '560001'],
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'razorpay',
            'gateway_payment_id' => 'pay_rzp_mock_123',
            'gateway_order_id' => 'order_rzp_123',
            'amount' => 200.00,
            'currency' => 'INR',
            'status' => PaymentStatus::PAID,
            'raw_response' => ['id' => 'pay_rzp_mock_123', 'status' => 'captured'],
        ]);

        // Payment index
        $indexResponse = $this->actingAs($this->adminUser)->get(route('admin.payments.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('pay_rzp_mock_123');

        // Payment show with raw json
        $showResponse = $this->actingAs($this->adminUser)->get(route('admin.payments.show', $payment));
        $showResponse->assertStatus(200);
        $showResponse->assertSee('captured');

        // Mock refund API
        Http::fake([
            'https://api.razorpay.com/v1/payments/pay_rzp_mock_123/refund' => Http::response([
                'id' => 'rfnd_test_999',
                'entity' => 'refund',
                'amount' => 10000,
                'currency' => 'INR',
                'payment_id' => 'pay_rzp_mock_123',
                'status' => 'processed',
            ], 200),
        ]);

        $refundResponse = $this->actingAs($this->adminUser)->post(route('admin.payments.refund', $payment), [
            'amount' => 100.00,
            'reason' => 'Customer requested partial refund',
        ]);
        $refundResponse->assertRedirect();
        $this->assertDatabaseHas('refunds', [
            'payment_id' => $payment->id,
            'amount' => 100.00,
            'gateway_refund_id' => 'rfnd_test_999',
        ]);

        // Refunds ledger
        $refundsListResponse = $this->actingAs($this->adminUser)->get(route('admin.payments.refunds'));
        $refundsListResponse->assertStatus(200);
        $refundsListResponse->assertSee('rfnd_test_999');
    }

    /* -------------------------------------------------------------------------
     | 4. Coupons & Marketing Campaigns
     | ---------------------------------------------------------------------- */

    public function test_admin_can_manage_coupons_and_campaigns(): void
    {
        // Store coupon
        $couponStore = $this->actingAs($this->adminUser)->post(route('admin.coupons.store'), [
            'code' => 'SUPERMEGA50',
            'type' => 'percent',
            'value' => 50,
            'min_order' => 500,
            'max_discount' => 200,
            'is_active' => true,
        ]);
        $couponStore->assertRedirect();
        $this->assertDatabaseHas('coupons', ['code' => 'SUPERMEGA50']);

        $coupon = Coupon::where('code', 'SUPERMEGA50')->first();

        // Toggle coupon
        $this->actingAs($this->adminUser)->post(route('admin.coupons.toggle', $coupon));
        $this->assertFalse($coupon->fresh()->is_active);

        // Store campaign
        $campaignStore = $this->actingAs($this->adminUser)->post(route('admin.campaigns.store'), [
            'name' => 'Diwali Mega Flash Sale',
            'type' => 'festive_sale',
            'description' => 'Huge seasonal promotional discounts across all departments.',
            'is_active' => true,
        ]);
        $campaignStore->assertRedirect();
        $this->assertDatabaseHas('campaigns', ['name' => 'Diwali Mega Flash Sale']);

        $campaign = Campaign::where('name', 'Diwali Mega Flash Sale')->first();

        // Toggle campaign
        $this->actingAs($this->adminUser)->post(route('admin.campaigns.toggle', $campaign));
        $this->assertFalse($campaign->fresh()->is_active);
    }

    /* -------------------------------------------------------------------------
     | 5. Banners & CMS Static Pages
     | ---------------------------------------------------------------------- */

    public function test_admin_can_manage_banners_and_cms_pages(): void
    {
        // Create banner
        $bannerStore = $this->actingAs($this->adminUser)->post(route('admin.banners.store'), [
            'title' => 'Monsoon Electronics Bonanza',
            'position' => 'hero',
            'link' => '/c/electronics',
            'sort' => 1,
            'is_active' => true,
        ]);
        $bannerStore->assertRedirect();
        $this->assertDatabaseHas('banners', ['title' => 'Monsoon Electronics Bonanza']);

        $banner = Banner::where('title', 'Monsoon Electronics Bonanza')->first();

        // Toggle banner
        $this->actingAs($this->adminUser)->post(route('admin.banners.toggle', $banner));
        $this->assertFalse($banner->fresh()->is_active);

        // CMS Page Create
        $cmsStore = $this->actingAs($this->adminUser)->post(route('admin.cms.store'), [
            'title' => 'Privacy and Security Policy',
            'slug' => 'privacy-policy',
            'content' => 'OneStall complies with DPDP Act 2023.',
            'meta_title' => 'Privacy Policy - OneStall',
            'meta_description' => 'Official privacy policy of OneStall.',
            'is_active' => true,
        ]);
        $cmsStore->assertRedirect();
        $this->assertDatabaseHas('cms_pages', ['slug' => 'privacy-policy']);

        $cmsPage = CmsPage::where('slug', 'privacy-policy')->first();

        // CMS Page Update
        $cmsUpdate = $this->actingAs($this->adminUser)->put(route('admin.cms.update', $cmsPage), [
            'title' => 'Privacy and Security Policy Updated',
            'slug' => 'privacy-policy',
            'content' => 'Updated terms.',
            'is_active' => true,
        ]);
        $cmsUpdate->assertRedirect();
        $this->assertEquals('Privacy and Security Policy Updated', $cmsPage->fresh()->title);
    }

    /* -------------------------------------------------------------------------
     | 6. Shipping & Logistics Settings
     | ---------------------------------------------------------------------- */

    public function test_admin_can_update_shipping_logistics_settings(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.shipping.index'));
        $response->assertStatus(200);
        $response->assertSee('Courier Fallback Priority Chain');

        $update = $this->actingAs($this->adminUser)->post(route('admin.shipping.update'), [
            'default_weight_kg' => 0.75,
            'cod_max_limit' => 4500.00,
            'free_shipping_threshold' => 799.00,
            'quick_max_radius_km' => 10.0,
            'standard_base_fee' => 35.00,
            'rto_max_attempts' => 2,
            'courier_priorities' => ['onestall_cargo', 'delhivery', 'bluedart'],
        ]);
        $update->assertRedirect();

        $this->assertEquals(0.75, (float) Setting::get('shipping_default_weight_kg'));
        $this->assertEquals(4500.00, (float) Setting::get('shipping_cod_max_limit'));
        $this->assertEquals(10.0, (float) Setting::get('shipping_quick_max_radius_km'));
    }

    /* -------------------------------------------------------------------------
     | 7. Reports & Analytics
     | ---------------------------------------------------------------------- */

    public function test_admin_can_view_all_reports_tabs_and_export(): void
    {
        foreach (['sales', 'orders', 'sellers', 'categories', 'tax', 'commission'] as $tab) {
            $response = $this->actingAs($this->adminUser)->get(route('admin.reports.index', ['tab' => $tab]));
            $response->assertStatus(200);
            $response->assertViewIs('admin.reports.index');
        }

        $export = $this->actingAs($this->adminUser)->get(route('admin.reports.export', ['type' => 'sales']));
        $export->assertStatus(200);
        $this->assertStringContainsString('text/csv', strtolower($export->headers->get('content-type')));
    }

    /* -------------------------------------------------------------------------
     | 8. Support Desk & Resolution Thread
     | ---------------------------------------------------------------------- */

    public function test_admin_can_manage_support_tickets_and_post_thread_replies(): void
    {
        $ticket = SupportTicket::create([
            'ticket_number' => 'TCK-88214',
            'user_id' => $this->customerUser->id,
            'subject' => 'Late delivery for birthday gift',
            'status' => TicketStatus::OPEN,
            'priority' => TicketPriority::HIGH,
        ]);

        $indexResponse = $this->actingAs($this->adminUser)->get(route('admin.support.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('TCK-88214');

        $showResponse = $this->actingAs($this->adminUser)->get(route('admin.support.show', $ticket));
        $showResponse->assertStatus(200);

        // Assign to admin
        $assignResponse = $this->actingAs($this->adminUser)->post(route('admin.support.assign', $ticket), [
            'assigned_to' => $this->adminUser->id,
        ]);
        $assignResponse->assertRedirect();
        $this->assertEquals($this->adminUser->id, $ticket->fresh()->assigned_to);

        // Post public reply
        $replyResponse = $this->actingAs($this->adminUser)->post(route('admin.support.reply', $ticket), [
            'message' => 'Our rider is 5 minutes away, we apologize for the short delay.',
            'is_internal' => 0,
        ]);
        $replyResponse->assertRedirect();
        $this->assertDatabaseHas('ticket_messages', [
            'support_ticket_id' => $ticket->id,
            'message' => 'Our rider is 5 minutes away, we apologize for the short delay.',
            'is_internal' => 0,
        ]);

        // Update status to resolved
        $statusResponse = $this->actingAs($this->adminUser)->post(route('admin.support.status', $ticket), [
            'status' => 'resolved',
        ]);
        $statusResponse->assertRedirect();
        $this->assertEquals(TicketStatus::RESOLVED, $ticket->fresh()->status);
    }

    /* -------------------------------------------------------------------------
     | 9. Admin Staff & Permission Matrix
     | ---------------------------------------------------------------------- */

    public function test_admin_can_manage_staff_accounts_and_update_role_permissions(): void
    {
        $staffUserStore = $this->actingAs($this->adminUser)->post(route('admin.staff.store'), [
            'name' => 'Operations Officer Sunita',
            'email' => 'sunita.ops@onestall.in',
            'phone' => '+91 9988776655',
            'password' => 'SecurePass#2026',
            'role' => 'admin',
        ]);
        $staffUserStore->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'sunita.ops@onestall.in']);

        $staff = User::where('email', 'sunita.ops@onestall.in')->first();
        $this->assertTrue($staff->hasRole('admin'));

        // View permission matrix
        $rolesMatrix = $this->actingAs($this->adminUser)->get(route('admin.staff.roles'));
        $rolesMatrix->assertStatus(200);
        $rolesMatrix->assertSee('Access Matrix');

        // Update role permissions
        $role = Role::findByName('support_admin', 'web');
        $permission = Permission::first();
        if ($permission) {
            $updatePerms = $this->actingAs($this->adminUser)->put(route('admin.staff.roles.update', $role), [
                'permissions' => [$permission->name],
            ]);
            $updatePerms->assertRedirect();
            $this->assertTrue($role->fresh()->hasPermissionTo($permission->name));
        }

        // Delete staff (not self)
        $deleteStaff = $this->actingAs($this->adminUser)->delete(route('admin.staff.destroy', $staff));
        $deleteStaff->assertRedirect();
        $this->assertSoftDeleted('users', ['id' => $staff->id]);
    }

    /* -------------------------------------------------------------------------
     | 10. System Settings Console
     | ---------------------------------------------------------------------- */

    public function test_admin_can_view_and_update_tabbed_system_settings(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.settings.index', ['tab' => 'general']));
        $response->assertStatus(200);
        $response->assertSee('Platform Store Name');

        $updateResponse = $this->actingAs($this->adminUser)->post(route('admin.settings.update'), [
            'settings' => [
                'site_name' => 'OneStall HyperCommerce',
                'site_tagline' => 'Delivery in 15 Minutes or Free',
            ],
        ]);
        $updateResponse->assertRedirect();

        $this->assertEquals('OneStall HyperCommerce', Setting::get('site_name'));
        $this->assertEquals('Delivery in 15 Minutes or Free', Setting::get('site_tagline'));
    }

    /* -------------------------------------------------------------------------
     | 11. Security Audit Logs
     | ---------------------------------------------------------------------- */

    public function test_admin_can_view_and_export_audit_logs(): void
    {
        activity('system')
            ->causedBy($this->adminUser)
            ->log('Admin completed system sanity diagnostics');

        $response = $this->actingAs($this->adminUser)->get(route('admin.audit-logs.index'));
        $response->assertStatus(200);
        $response->assertSee('Admin completed system sanity diagnostics');

        $export = $this->actingAs($this->adminUser)->get(route('admin.audit-logs.export'));
        $export->assertStatus(200);
        $this->assertStringContainsString('text/csv', strtolower($export->headers->get('content-type')));
    }

    /* -------------------------------------------------------------------------
     | 12. Security Gating / Role Access Enforcement
     | ---------------------------------------------------------------------- */

    public function test_unauthorized_users_and_guests_are_strictly_blocked(): void
    {
        $restrictedUrls = [
            route('admin.customers.index'),
            route('admin.stores.index'),
            route('admin.payments.index'),
            route('admin.coupons.index'),
            route('admin.campaigns.index'),
            route('admin.banners.index'),
            route('admin.cms.index'),
            route('admin.shipping.index'),
            route('admin.reports.index'),
            route('admin.support.index'),
            route('admin.staff.index'),
            route('admin.settings.index'),
            route('admin.audit-logs.index'),
        ];

        // 1. Unauthenticated guests -> redirected to admin login
        foreach ($restrictedUrls as $url) {
            $guestRes = $this->get($url);
            $guestRes->assertRedirect(route('admin.login'));
        }

        // 2. Authenticated non-admin customer -> 403 Forbidden
        foreach ($restrictedUrls as $url) {
            $customerRes = $this->actingAs($this->customerUser)->get($url);
            $customerRes->assertStatus(403);
        }
    }
}
