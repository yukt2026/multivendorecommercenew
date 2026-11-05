<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\Order;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Notifications\Channels\PushChannel;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\UnifiedNotification;
use App\Services\Notification\NotificationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UnifiedNotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->user = User::factory()->create([
            'name' => 'Aditi Sharma',
            'email' => 'aditi@example.com',
            'phone' => '9876543210',
        ]);
        $this->user->assignRole('customer');

        $this->admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);
        $this->admin->assignRole('admin');
    }

    public function test_in_app_notification_creation_and_reading_lifecycle(): void
    {
        // 1. Dispatch an in-app database notification
        $this->user->notify(new UnifiedNotification('order_confirmed', [
            'name' => $this->user->name,
            'order_number' => 'OS-1001',
            'amount' => '1,499.00',
        ], ['database']));

        $this->assertCount(1, $this->user->notifications);
        $this->assertCount(1, $this->user->unreadNotifications);

        $notification = $this->user->unreadNotifications->first();
        $this->assertEquals('OS-1001', $notification->data['data']['order_number']);

        // 2. Access notification center page
        $response = $this->actingAs($this->user)->get(route('notifications.index'));
        $response->assertOk();
        $response->assertSee('OS-1001');

        // 3. Mark single notification as read
        $markReadResponse = $this->actingAs($this->user)->post(route('notifications.read', $notification->id));
        $markReadResponse->assertRedirect();
        $this->assertCount(0, $this->user->fresh()->unreadNotifications);

        // 4. Send 2 more unread notifications
        $this->user->notify(new UnifiedNotification('shipment_update', [
            'tracking_number' => 'AWB-888',
        ], ['database']));
        $this->user->notify(new UnifiedNotification('low_stock_alert', [
            'sku' => 'SKU-001',
        ], ['database']));

        $this->assertCount(2, $this->user->fresh()->unreadNotifications);

        // Check unread count API
        $apiResponse = $this->actingAs($this->user)->getJson(route('notifications.unread_count'));
        $apiResponse->assertOk()
            ->assertJsonPath('count', 2);

        // 5. Mark all as read
        $markAllResponse = $this->actingAs($this->user)->post(route('notifications.mark_all_read'));
        $markAllResponse->assertRedirect();
        $this->assertCount(0, $this->user->fresh()->unreadNotifications);

        // 6. Clear all notifications
        $clearResponse = $this->actingAs($this->user)->delete(route('notifications.clear_all'));
        $clearResponse->assertRedirect();
        $this->assertCount(0, $this->user->fresh()->notifications);
    }

    public function test_user_notification_channel_preferences_update_and_routing(): void
    {
        // Set user preferences: disable SMS and Push for orders, enable WhatsApp
        $response = $this->actingAs($this->user)->post(route('notifications.preferences.update'), [
            'preferences' => [
                'orders' => [
                    'database' => '1',
                    'mail' => '1',
                    'sms' => '0',
                    'whatsapp' => '1',
                    'push' => '0',
                ],
                'promotions' => [
                    'database' => '1',
                ],
            ],
            'promotional_consent' => '1',
        ]);

        $response->assertRedirect();

        $pref = UserNotificationPreference::where('user_id', $this->user->id)
            ->where('category', 'orders')
            ->first();

        $this->assertNotNull($pref);
        $this->assertTrue($pref->channel_database);
        $this->assertTrue($pref->channel_mail);
        $this->assertFalse($pref->channel_sms);
        $this->assertTrue($pref->channel_whatsapp);
        $this->assertFalse($pref->channel_push);

        // Promotional consent should be recorded
        $promoPref = UserNotificationPreference::where('user_id', $this->user->id)
            ->where('category', 'promotions')
            ->first();
        $this->assertTrue($promoPref->promotional_consent);
        $this->assertNotNull($promoPref->promotional_consent_at);

        // Verify effective channels
        $notification = new UnifiedNotification('order_confirmed', ['name' => $this->user->name], null, 'orders');
        $via = $notification->via($this->user);

        $this->assertContains('database', $via);
        $this->assertContains('mail', $via);
        $this->assertContains(WhatsAppChannel::class, $via);
        $this->assertNotContains(SmsChannel::class, $via);
        $this->assertNotContains(PushChannel::class, $via);
    }

    public function test_promotional_notifications_strictly_gated_by_stored_consent(): void
    {
        $service = app(NotificationService::class);

        // User without consent
        $this->assertFalse($this->user->hasPromotionalConsent());

        $resultWithoutConsent = $service->sendPromotional($this->user, 'Flash Sale 50% Off Everything!');
        $this->assertFalse($resultWithoutConsent);

        // Now grant consent
        UserNotificationPreference::updateOrCreate(
            ['user_id' => $this->user->id, 'category' => 'promotions'],
            ['promotional_consent' => true, 'promotional_consent_at' => now()]
        );

        $this->assertTrue($this->user->fresh()->hasPromotionalConsent());

        $resultWithConsent = $service->sendPromotional($this->user, 'Flash Sale 50% Off Everything!', [
            'coupon' => 'MEGA50',
            'discount' => '50%',
        ]);
        $this->assertTrue($resultWithConsent);
    }

    public function test_dynamic_template_variable_substitution(): void
    {
        $template = NotificationTemplate::create([
            'event_key' => 'order_confirmed',
            'channel' => 'mail',
            'subject' => 'Hooray {name}! Order #{order_number} confirmed',
            'body' => 'Thank you {name}, your order #{order_number} of Rs {amount} is placed!',
            'supported_variables' => ['name', 'order_number', 'amount'],
            'is_active' => true,
        ]);

        $rendered = $template->render([
            'name' => 'Vikram',
            'order_number' => 'OS-9900',
            'amount' => '4,500',
        ]);

        $this->assertEquals('Hooray Vikram! Order #OS-9900 confirmed', $rendered['subject']);
        $this->assertEquals('Thank you Vikram, your order #OS-9900 of Rs 4,500 is placed!', $rendered['body']);
    }

    public function test_pluggable_sms_whatsapp_and_push_channels_telemetry(): void
    {
        // Notification with explicit SMS and WhatsApp channels
        $notification = new UnifiedNotification('otp', [
            'otp' => '456789',
            'name' => $this->user->name,
        ], ['sms', 'whatsapp']);

        $this->user->notify($notification);

        // Verify logs created in notification_logs
        $logs = NotificationLog::where('recipient', $this->user->phone)->get();
        $this->assertGreaterThanOrEqual(1, $logs->count());

        $smsLog = $logs->firstWhere('channel', 'sms');
        $this->assertNotNull($smsLog);
        $this->assertEquals('sent', $smsLog->status);
        $this->assertStringContainsString('456789', $smsLog->content);
    }

    public function test_failed_notification_retry_command_and_service(): void
    {
        // Create an intentionally failed log
        $failedLog = NotificationLog::create([
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'event_key' => 'otp',
            'channel' => 'sms',
            'recipient' => '9876543210',
            'content' => 'Your OTP is 112233',
            'status' => 'failed',
            'error_message' => 'Gateway timeout',
            'retry_count' => 0,
        ]);

        $this->assertTrue($failedLog->isFailed());

        // Run artisan command
        $this->artisan('notifications:retry-failed', ['--limit' => 10])
            ->expectsOutputToContain('Found 1 failed notification(s) to retry...')
            ->assertExitCode(0);

        $failedLog->refresh();
        $this->assertEquals('sent', $failedLog->status);
        $this->assertEquals(1, $failedLog->retry_count);
        $this->assertNull($failedLog->error_message);
    }

    public function test_admin_templates_and_logs_endpoints(): void
    {
        // 1. Templates index
        $response = $this->actingAs($this->admin)->get(route('admin.notifications.templates.index'));
        $response->assertOk();
        $response->assertSee('Notification Templates');

        // 2. Template update
        $template = NotificationTemplate::firstOrCreate([
            'event_key' => 'otp',
            'channel' => 'sms',
        ], [
            'body' => 'Your OneStall verification OTP is {otp}.',
            'supported_variables' => ['otp', 'name'],
            'is_active' => true,
        ]);

        $updateResponse = $this->actingAs($this->admin)->put(route('admin.notifications.templates.update', $template), [
            'body' => 'Use code {otp} to sign in to OneStall.',
            'is_active' => '1',
        ]);

        $updateResponse->assertRedirect();
        $this->assertEquals('Use code {otp} to sign in to OneStall.', $template->fresh()->body);

        // 3. Logs index
        $logResponse = $this->actingAs($this->admin)->get(route('admin.notifications.logs.index'));
        $logResponse->assertOk();
        $logResponse->assertSee('Notification Audit & Delivery Logs');

        // 4. Admin retry action
        $failedLog = NotificationLog::create([
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'event_key' => 'settlement_paid',
            'channel' => 'sms',
            'recipient' => '9876543210',
            'content' => 'Settlement processed',
            'status' => 'failed',
            'error_message' => 'Service unavailable',
            'retry_count' => 0,
        ]);

        $retryActionResponse = $this->actingAs($this->admin)->post(route('admin.notifications.logs.retry', $failedLog));
        $retryActionResponse->assertRedirect();
        $this->assertEquals('sent', $failedLog->fresh()->status);
    }
}
