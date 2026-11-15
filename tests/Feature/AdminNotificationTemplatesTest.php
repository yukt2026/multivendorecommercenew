<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminNotificationTemplatesTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $customerUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        $this->adminUser = User::factory()->create([
            'name' => 'Operations Officer',
            'email' => 'operations@onestall.test',
            'status' => 'active',
        ]);
        $this->adminUser->assignRole('admin');

        $this->customerUser = User::factory()->create([
            'name' => 'Customer Joe',
            'email' => 'customer@onestall.test',
            'status' => 'active',
        ]);
        $this->customerUser->assignRole('customer');
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $response = $this->get(route('admin.notifications.templates.index'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_non_admin_customer_is_forbidden(): void
    {
        $response = $this->actingAs($this->customerUser)->get(route('admin.notifications.templates.index'));
        $response->assertStatus(403);
    }

    public function test_admin_can_view_templates_index_with_metrics_and_defaults(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.notifications.templates.index'));
        $response->assertStatus(200);
        $response->assertSee('Notification Templates');
        $response->assertSee('Configured Templates');
        $response->assertSee('Active Dispatches');
        $response->assertSee('order_confirmed');
        $response->assertSee('otp');

        // Confirm database has defaults
        $this->assertDatabaseHas('notification_templates', [
            'event_key' => 'order_confirmed',
            'channel' => 'mail',
        ]);
    }

    public function test_admin_can_search_templates(): void
    {
        NotificationTemplate::updateOrCreate(
            ['event_key' => 'custom_payout_alert', 'channel' => 'mail'],
            ['subject' => 'Unique Payout Notice', 'body' => 'Your unique payout has been generated.', 'is_active' => true]
        );

        $response = $this->actingAs($this->adminUser)->get(route('admin.notifications.templates.index', ['search' => 'Unique Payout Notice']));
        $response->assertStatus(200);
        $response->assertSee('custom_payout_alert');
        $response->assertSee('Unique Payout Notice');
    }

    public function test_admin_can_filter_templates_by_channel(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.notifications.templates.index', ['channel' => 'sms']));
        $response->assertStatus(200);
        $response->assertSee('SMS');
    }

    public function test_admin_can_filter_templates_by_event(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.notifications.templates.index', ['event' => 'otp']));
        $response->assertStatus(200);
        $response->assertSee('otp');
    }

    public function test_admin_can_filter_templates_by_status(): void
    {
        $this->actingAs($this->adminUser)->get(route('admin.notifications.templates.index'));

        $tpl = NotificationTemplate::first();
        $this->assertNotNull($tpl);
        $tpl->update(['is_active' => false]);

        $activeResp = $this->actingAs($this->adminUser)->get(route('admin.notifications.templates.index', ['status' => 'active']));
        $activeResp->assertStatus(200);

        $disabledResp = $this->actingAs($this->adminUser)->get(route('admin.notifications.templates.index', ['status' => 'disabled']));
        $disabledResp->assertStatus(200);
        $disabledResp->assertSee($tpl->event_key);
    }

    public function test_admin_can_update_template_content_and_subject(): void
    {
        $template = NotificationTemplate::firstOrCreate(
            ['event_key' => 'otp', 'channel' => 'mail'],
            ['subject' => 'Old OTP Subject', 'body' => 'Old OTP Body {otp}', 'is_active' => true]
        );

        $response = $this->actingAs($this->adminUser)->put(route('admin.notifications.templates.update', $template), [
            'subject' => 'New Security Passcode: {otp}',
            'body' => 'Hello {name}, your login passcode is {otp}. Valid for {expires_minutes} minutes.',
            'is_active' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $fresh = $template->fresh();
        $this->assertEquals('New Security Passcode: {otp}', $fresh->subject);
        $this->assertEquals('Hello {name}, your login passcode is {otp}. Valid for {expires_minutes} minutes.', $fresh->body);
        $this->assertTrue($fresh->is_active);
    }

    public function test_admin_can_deactivate_template(): void
    {
        $template = NotificationTemplate::firstOrCreate(
            ['event_key' => 'promotional', 'channel' => 'push'],
            ['subject' => 'Flash Sale', 'body' => 'Big deals today!', 'is_active' => true]
        );

        // When unchecking checkbox in HTML, is_active is omitted from request
        $response = $this->actingAs($this->adminUser)->put(route('admin.notifications.templates.update', $template), [
            'subject' => 'Flash Sale',
            'body' => 'Big deals today!',
            // is_active omitted
        ]);

        $response->assertRedirect();
        $fresh = $template->fresh();
        $this->assertFalse($fresh->is_active);
    }

    public function test_admin_can_toggle_template_status_via_action(): void
    {
        $template = NotificationTemplate::firstOrCreate(
            ['event_key' => 'otp', 'channel' => 'sms'],
            ['subject' => null, 'body' => 'Code: {otp}', 'is_active' => true]
        );

        // Toggle from active -> disabled
        $toggle1 = $this->actingAs($this->adminUser)->post(route('admin.notifications.templates.toggle', $template));
        $toggle1->assertRedirect();
        $this->assertFalse($template->fresh()->is_active);

        // Toggle from disabled -> active
        $toggle2 = $this->actingAs($this->adminUser)->post(route('admin.notifications.templates.toggle', $template));
        $toggle2->assertRedirect();
        $this->assertTrue($template->fresh()->is_active);
    }

    public function test_admin_can_preview_template_with_sample_variables(): void
    {
        $template = NotificationTemplate::firstOrCreate(
            ['event_key' => 'order_confirmed', 'channel' => 'mail'],
            ['subject' => 'Order #{order_number} Placed', 'body' => 'Hello {name}, your order #{order_number} for ₹{amount} is confirmed.', 'is_active' => true]
        );

        $response = $this->actingAs($this->adminUser)->postJson(route('admin.notifications.templates.preview', $template), [
            'subject' => 'Order #{order_number} Confirmed for {name}',
            'body' => 'Thank you {name}, we received ₹{amount} for order #{order_number}.',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'rendered_subject',
            'rendered_body',
            'sample_variables',
        ]);

        $data = $response->json();
        $this->assertStringContainsString('ORD-89421', $data['rendered_subject']);
        $this->assertStringContainsString('Rahul Sharma', $data['rendered_subject']);
        $this->assertStringContainsString('1,499.00', $data['rendered_body']);
    }

    public function test_template_appends_supported_variables_dynamically(): void
    {
        $template = new NotificationTemplate([
            'event_key' => 'order_confirmed',
            'channel' => 'mail',
            'subject' => 'Order #{order_number} for {custom_vip_tag}',
            'body' => 'Total amount {amount} paid via {custom_wallet_source}',
        ]);

        $vars = $template->supported_variables;
        $this->assertIsArray($vars);
        $this->assertContains('order_number', $vars);
        $this->assertContains('amount', $vars);
        $this->assertContains('custom_vip_tag', $vars);
        $this->assertContains('custom_wallet_source', $vars);
    }
}
