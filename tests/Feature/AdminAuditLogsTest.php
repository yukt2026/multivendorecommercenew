<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAuditLogsTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $adminUser;
    protected User $customerUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'support_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        $this->superAdmin = User::factory()->create([
            'name' => 'Chief Compliance Officer',
            'email' => 'cco@onestall.test',
            'status' => 'active',
        ]);
        $this->superAdmin->assignRole('super_admin');

        $this->adminUser = User::factory()->create([
            'name' => 'Operational Admin',
            'email' => 'opadmin@onestall.test',
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
        $response = $this->get(route('admin.audit-logs.index'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_non_admin_customer_is_forbidden(): void
    {
        $response = $this->actingAs($this->customerUser)->get(route('admin.audit-logs.index'));
        $response->assertStatus(403);
    }

    public function test_admin_can_view_audit_logs_index_with_metrics_and_table(): void
    {
        activity('settings')
            ->causedBy($this->adminUser)
            ->event('updated')
            ->withProperties(['key' => 'currency_code', 'value' => 'INR'])
            ->log('Platform default currency configured to INR');

        $response = $this->actingAs($this->superAdmin)->get(route('admin.audit-logs.index'));
        $response->assertStatus(200);
        $response->assertSee('Security &amp; Operational Audit Trail', false);
        $response->assertSee('Platform default currency configured to INR');
        $response->assertSee('Operational Admin');
        $response->assertSee('opadmin@onestall.test');
        $response->assertSee('settings');
        $response->assertSee('updated');
    }

    public function test_admin_can_search_audit_logs_by_description(): void
    {
        activity('store')
            ->causedBy($this->adminUser)
            ->log('Store #104 status switched to suspended');

        activity('payment')
            ->causedBy($this->adminUser)
            ->log('Razorpay webhook verified and settled');

        $response = $this->actingAs($this->adminUser)->get(route('admin.audit-logs.index', ['search' => 'Store #104']));
        $response->assertStatus(200);
        $response->assertSee('Store #104 status switched to suspended');
        $response->assertDontSee('Razorpay webhook verified and settled');
    }

    public function test_admin_can_search_audit_logs_by_actor_name_and_email(): void
    {
        $actorA = User::factory()->create(['name' => 'Kavita Patel', 'email' => 'kavita@onestall.test']);
        $actorB = User::factory()->create(['name' => 'Deepak Sharma', 'email' => 'deepak@onestall.test']);

        activity('support')
            ->causedBy($actorA)
            ->log('Support ticket #501 resolved');

        activity('support')
            ->causedBy($actorB)
            ->log('Support ticket #502 assigned');

        $responseName = $this->actingAs($this->adminUser)->get(route('admin.audit-logs.index', ['search' => 'Kavita']));
        $responseName->assertStatus(200);
        $responseName->assertSee('Support ticket #501 resolved');
        $responseName->assertDontSee('Support ticket #502 assigned');

        $responseEmail = $this->actingAs($this->adminUser)->get(route('admin.audit-logs.index', ['search' => 'deepak@onestall.test']));
        $responseEmail->assertStatus(200);
        $responseEmail->assertSee('Support ticket #502 assigned');
        $responseEmail->assertDontSee('Support ticket #501 resolved');
    }

    public function test_admin_can_filter_audit_logs_by_module(): void
    {
        activity('cms')
            ->causedBy($this->adminUser)
            ->log('Published terms of service v2');

        activity('coupon')
            ->causedBy($this->adminUser)
            ->log('Generated festive coupon DIWALI50');

        $response = $this->actingAs($this->adminUser)->get(route('admin.audit-logs.index', ['log_name' => 'cms']));
        $response->assertStatus(200);
        $response->assertSee('Published terms of service v2');
        $response->assertDontSee('Generated festive coupon DIWALI50');
    }

    public function test_admin_can_filter_audit_logs_by_event(): void
    {
        activity('staff')
            ->causedBy($this->adminUser)
            ->event('created')
            ->log('Created sub-admin account');

        activity('staff')
            ->causedBy($this->adminUser)
            ->event('deleted')
            ->log('Deleted inactive staff account');

        $response = $this->actingAs($this->adminUser)->get(route('admin.audit-logs.index', ['event' => 'created']));
        $response->assertStatus(200);
        $response->assertSee('Created sub-admin account');
        $response->assertDontSee('Deleted inactive staff account');
    }

    public function test_admin_can_filter_audit_logs_by_date(): void
    {
        $pastLog = activity('audit')
            ->causedBy($this->adminUser)
            ->log('Historical log from 5 days ago');

        // Manually adjust created_at
        Activity::where('id', $pastLog->id)->update(['created_at' => Carbon::now()->subDays(5)]);

        activity('audit')
            ->causedBy($this->adminUser)
            ->log('Today log');

        $targetDate = Carbon::now()->subDays(5)->format('Y-m-d');
        $response = $this->actingAs($this->adminUser)->get(route('admin.audit-logs.index', ['date' => $targetDate]));
        $response->assertStatus(200);
        $response->assertSee('Historical log from 5 days ago');
        $response->assertDontSee('Today log');
    }

    public function test_admin_can_export_audit_logs_csv_honoring_filters(): void
    {
        activity('settings')
            ->causedBy($this->adminUser)
            ->event('updated')
            ->withProperties(['test_key' => '123'])
            ->log('Setting export target item');

        activity('inventory')
            ->causedBy($this->adminUser)
            ->event('updated')
            ->log('Inventory ignored item');

        $response = $this->actingAs($this->adminUser)->get(route('admin.audit-logs.export', ['log_name' => 'settings']));

        $response->assertStatus(200);
        $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertTrue(str_contains((string) $response->headers->get('Content-Disposition'), 'attachment; filename="audit-logs-'));

        $content = $response->streamedContent();

        // Check for UTF-8 BOM
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        // Header and matching rows
        $this->assertStringContainsString('Log ID', $content);
        $this->assertStringContainsString('Module / Category', $content);
        $this->assertStringContainsString('Setting export target item', $content);
        $this->assertStringNotContainsString('Inventory ignored item', $content);
    }

    public function test_non_super_admin_cannot_purge_audit_logs(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.audit-logs.purge'), [
            'days' => 90,
        ]);

        $response->assertStatus(403);
    }

    public function test_super_admin_can_purge_historical_audit_logs(): void
    {
        $oldActivity = activity('auth')
            ->causedBy($this->adminUser)
            ->log('Very old login attempt from 120 days ago');

        Activity::where('id', $oldActivity->id)->update([
            'created_at' => Carbon::now()->subDays(120),
        ]);

        $recentActivity = activity('auth')
            ->causedBy($this->adminUser)
            ->log('Recent login attempt from yesterday');

        $response = $this->actingAs($this->superAdmin)->post(route('admin.audit-logs.purge'), [
            'days' => 90,
        ]);

        $response->assertRedirect(route('admin.audit-logs.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('activity_log', ['id' => $oldActivity->id]);
        $this->assertDatabaseHas('activity_log', ['id' => $recentActivity->id]);

        // Verify that the purge action itself was logged
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'audit',
            'causer_id' => $this->superAdmin->id,
        ]);
    }

    public function test_purge_validates_retention_days(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(route('admin.audit-logs.purge'), [
            'days' => 7, // Not allowed retention value
        ]);

        $response->assertSessionHasErrors(['days']);
    }
}
