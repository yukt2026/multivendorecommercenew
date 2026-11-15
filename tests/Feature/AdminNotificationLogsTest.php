<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\NotificationLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminNotificationLogsTest extends TestCase
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
            'name' => 'Systems Auditor',
            'email' => 'auditor@onestall.test',
            'status' => 'active',
        ]);
        $this->adminUser->assignRole('admin');

        $this->customerUser = User::factory()->create([
            'name' => 'Customer Joe',
            'email' => 'joe@onestall.test',
            'status' => 'active',
        ]);
        $this->customerUser->assignRole('customer');
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $response = $this->get(route('admin.notifications.logs.index'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_non_admin_customer_is_forbidden(): void
    {
        $response = $this->actingAs($this->customerUser)->get(route('admin.notifications.logs.index'));
        $response->assertStatus(403);
    }

    public function test_admin_can_view_logs_index_with_metrics_and_telemetry(): void
    {
        NotificationLog::create([
            'recipient' => '+919876543210',
            'channel' => 'sms',
            'event_key' => 'otp',
            'subject' => null,
            'content' => 'Your OTP is 492810',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.notifications.logs.index'));
        $response->assertStatus(200);
        $response->assertSee('Notification Audit &amp; Delivery Logs', false);
        $response->assertSee('Total Logged Dispatches');
        $response->assertSee('+919876543210');
        $response->assertSee('Your OTP is 492810');
    }

    public function test_admin_can_search_logs_by_recipient_subject_content_error(): void
    {
        NotificationLog::create([
            'recipient' => 'special_target@domain.test',
            'channel' => 'mail',
            'event_key' => 'order_confirmed',
            'subject' => 'Target Order Confirmed',
            'content' => 'Amount 1499',
            'status' => 'sent',
        ]);

        NotificationLog::create([
            'recipient' => 'other_customer@domain.test',
            'channel' => 'mail',
            'event_key' => 'order_confirmed',
            'subject' => 'Other Customer Order',
            'content' => 'Amount 499',
            'status' => 'sent',
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.notifications.logs.index', ['search' => 'special_target']));
        $response->assertStatus(200);
        $response->assertSee('special_target@domain.test');
        $response->assertDontSee('other_customer@domain.test');
    }

    public function test_admin_can_filter_logs_by_channel(): void
    {
        NotificationLog::create([
            'recipient' => '+919998887771',
            'channel' => 'whatsapp',
            'event_key' => 'order_confirmed',
            'content' => 'WhatsApp Order confirmed',
            'status' => 'sent',
        ]);

        NotificationLog::create([
            'recipient' => 'mail_user@domain.test',
            'channel' => 'mail',
            'event_key' => 'order_confirmed',
            'content' => 'Mail Order confirmed',
            'status' => 'sent',
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.notifications.logs.index', ['channel' => 'whatsapp']));
        $response->assertStatus(200);
        $response->assertSee('+919998887771');
        $response->assertDontSee('mail_user@domain.test');
    }

    public function test_admin_can_filter_logs_by_status(): void
    {
        NotificationLog::create([
            'recipient' => 'failed_recipient@domain.test',
            'channel' => 'sms',
            'event_key' => 'otp',
            'content' => 'OTP Code 123456',
            'status' => 'failed',
            'error_message' => 'Gateway DLT failure',
        ]);

        NotificationLog::create([
            'recipient' => 'sent_recipient@domain.test',
            'channel' => 'sms',
            'event_key' => 'otp',
            'content' => 'OTP Code 654321',
            'status' => 'sent',
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.notifications.logs.index', ['status' => 'failed']));
        $response->assertStatus(200);
        $response->assertSee('failed_recipient@domain.test');
        $response->assertDontSee('sent_recipient@domain.test');
    }

    public function test_admin_can_filter_logs_by_event(): void
    {
        NotificationLog::create([
            'recipient' => 'outfordelivery@domain.test',
            'channel' => 'sms',
            'event_key' => 'out_for_delivery',
            'content' => 'Your order is out for delivery',
            'status' => 'sent',
        ]);

        NotificationLog::create([
            'recipient' => 'promotional@domain.test',
            'channel' => 'sms',
            'event_key' => 'promotional',
            'content' => 'Get 50% discount today',
            'status' => 'sent',
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.notifications.logs.index', ['event' => 'out_for_delivery']));
        $response->assertStatus(200);
        $response->assertSee('outfordelivery@domain.test');
        $response->assertDontSee('promotional@domain.test');
    }

    public function test_admin_can_filter_logs_by_date(): void
    {
        $logPast = NotificationLog::create([
            'recipient' => 'past@domain.test',
            'channel' => 'sms',
            'event_key' => 'otp',
            'content' => 'Past OTP',
            'status' => 'sent',
        ]);
        NotificationLog::where('id', $logPast->id)->update(['created_at' => Carbon::now()->subDays(4)]);

        NotificationLog::create([
            'recipient' => 'today@domain.test',
            'channel' => 'sms',
            'event_key' => 'otp',
            'content' => 'Today OTP',
            'status' => 'sent',
        ]);

        $targetDate = Carbon::now()->subDays(4)->format('Y-m-d');
        $response = $this->actingAs($this->adminUser)->get(route('admin.notifications.logs.index', ['date' => $targetDate]));
        $response->assertStatus(200);
        $response->assertSee('past@domain.test');
        $response->assertDontSee('today@domain.test');
    }

    public function test_admin_can_retry_single_failed_notification(): void
    {
        $log = NotificationLog::create([
            'recipient' => '+919988776655',
            'channel' => 'sms',
            'event_key' => 'otp',
            'content' => 'Your code is 123456',
            'status' => 'failed',
            'error_message' => 'Simulated gateway timeout',
            'retry_count' => 0,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('admin.notifications.logs.retry', $log));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $fresh = $log->fresh();
        $this->assertEquals('sent', $fresh->status);
        $this->assertEquals(1, $fresh->retry_count);
        $this->assertNull($fresh->error_message);
    }

    public function test_non_failed_log_cannot_be_retried(): void
    {
        $log = NotificationLog::create([
            'recipient' => '+919988776655',
            'channel' => 'sms',
            'event_key' => 'otp',
            'content' => 'Your code is 123456',
            'status' => 'sent',
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('admin.notifications.logs.retry', $log));
        $response->assertRedirect();
        $response->assertSessionHas('error', 'Only failed notification dispatches can be retried.');
    }

    public function test_admin_can_bulk_retry_all_failed_notifications(): void
    {
        $log1 = NotificationLog::create([
            'recipient' => '+919876543201',
            'channel' => 'sms',
            'event_key' => 'otp',
            'content' => 'Code 1',
            'status' => 'failed',
        ]);

        $log2 = NotificationLog::create([
            'recipient' => '+919876543202',
            'channel' => 'sms',
            'event_key' => 'otp',
            'content' => 'Code 2',
            'status' => 'failed',
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('admin.notifications.logs.retry_all'));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals('sent', $log1->fresh()->status);
        $this->assertEquals('sent', $log2->fresh()->status);
    }

    public function test_admin_can_export_logs_csv_honoring_filters_with_bom(): void
    {
        NotificationLog::create([
            'recipient' => 'export_target@domain.test',
            'channel' => 'sms',
            'event_key' => 'order_confirmed',
            'subject' => null,
            'content' => 'Export target message body',
            'status' => 'sent',
        ]);

        NotificationLog::create([
            'recipient' => 'ignored_user@domain.test',
            'channel' => 'mail',
            'event_key' => 'order_confirmed',
            'subject' => null,
            'content' => 'Ignored message body',
            'status' => 'sent',
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.notifications.logs.export', ['channel' => 'sms']));
        $response->assertStatus(200);
        $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));

        $content = $response->streamedContent();

        // Check for UTF-8 BOM
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        // Header and matching rows
        $this->assertStringContainsString('Log ID', $content);
        $this->assertStringContainsString('Recipient', $content);
        $this->assertStringContainsString('export_target@domain.test', $content);
        $this->assertStringNotContainsString('ignored_user@domain.test', $content);
    }
}
