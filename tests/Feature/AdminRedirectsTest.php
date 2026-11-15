<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RedirectRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminRedirectsTest extends TestCase
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
            'name' => 'SEO Director',
            'email' => 'seo@onestall.test',
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
        $response = $this->get(route('admin.redirects.index'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_non_admin_customer_is_forbidden(): void
    {
        $response = $this->actingAs($this->customerUser)->get(route('admin.redirects.index'));
        $response->assertStatus(403);
    }

    public function test_admin_can_view_redirects_index_with_metrics(): void
    {
        RedirectRule::create([
            'source_path' => '/old-homepage',
            'target_path' => '/new-homepage',
            'status_code' => 301,
            'is_active' => true,
            'hit_count' => 42,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.redirects.index'));
        $response->assertStatus(200);
        $response->assertSee('301 / 302 Redirect Manager');
        $response->assertSee('Total Configured Rules');
        $response->assertSee('Active Live Redirects');
        $response->assertSee('/old-homepage');
        $response->assertSee('/new-homepage');
        $response->assertSee('42');
    }

    public function test_admin_can_search_redirects_by_source_or_target(): void
    {
        RedirectRule::create([
            'source_path' => '/legacy-catalog-item-101',
            'target_path' => '/p/modern-item-101',
            'status_code' => 301,
            'is_active' => true,
        ]);

        RedirectRule::create([
            'source_path' => '/other-old-page',
            'target_path' => '/other-new-page',
            'status_code' => 302,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.redirects.index', ['search' => 'item-101']));
        $response->assertStatus(200);
        $response->assertSee('/legacy-catalog-item-101');
        $response->assertDontSee('/other-old-page');
    }

    public function test_admin_can_filter_redirects_by_status_code_and_active_state(): void
    {
        RedirectRule::create([
            'source_path' => '/perm-page',
            'target_path' => '/target-perm',
            'status_code' => 301,
            'is_active' => true,
        ]);

        RedirectRule::create([
            'source_path' => '/temp-page',
            'target_path' => '/target-temp',
            'status_code' => 302,
            'is_active' => false,
        ]);

        $codeFilter = $this->actingAs($this->adminUser)->get(route('admin.redirects.index', ['code' => '301']));
        $codeFilter->assertStatus(200);
        $codeFilter->assertSee('/perm-page');
        $codeFilter->assertDontSee('/temp-page');

        $statusFilter = $this->actingAs($this->adminUser)->get(route('admin.redirects.index', ['status' => 'disabled']));
        $statusFilter->assertStatus(200);
        $statusFilter->assertSee('/temp-page');
        $statusFilter->assertDontSee('/perm-page');
    }

    public function test_admin_can_create_new_redirect_rule(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.redirects.store'), [
            'source_path' => 'old-category/summer-collection/',
            'target_path' => '/collections/summer-2026',
            'status_code' => '301',
            'is_active' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('redirect_rules', [
            'source_path' => '/old-category/summer-collection',
            'target_path' => '/collections/summer-2026',
            'status_code' => 301,
            'is_active' => true,
        ]);
    }

    public function test_redirect_rule_prevents_self_loop(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.redirects.store'), [
            'source_path' => '/same-path',
            'target_path' => '/same-path',
            'status_code' => '301',
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('redirect_rules', ['source_path' => '/same-path']);
    }

    public function test_redirect_rule_prevents_circular_loop(): void
    {
        RedirectRule::create([
            'source_path' => '/route-a',
            'target_path' => '/route-b',
            'status_code' => 301,
            'is_active' => true,
        ]);

        // Attempt to create circular /route-b -> /route-a
        $response = $this->actingAs($this->adminUser)->post(route('admin.redirects.store'), [
            'source_path' => '/route-b',
            'target_path' => '/route-a',
            'status_code' => '301',
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('redirect_rules', ['source_path' => '/route-b']);
    }

    public function test_admin_can_update_existing_redirect_rule(): void
    {
        $rule = RedirectRule::create([
            'source_path' => '/old-slug-before',
            'target_path' => '/destination-before',
            'status_code' => 302,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->put(route('admin.redirects.update', $rule), [
            'source_path' => '/old-slug-after',
            'target_path' => '/destination-after',
            'status_code' => '301',
            'is_active' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $fresh = $rule->fresh();
        $this->assertEquals('/old-slug-after', $fresh->source_path);
        $this->assertEquals('/destination-after', $fresh->target_path);
        $this->assertEquals(301, $fresh->status_code);
    }

    public function test_admin_can_deactivate_redirect_rule(): void
    {
        $rule = RedirectRule::create([
            'source_path' => '/to-be-deactivated',
            'target_path' => '/dest-deactivated',
            'status_code' => 301,
            'is_active' => true,
        ]);

        // When unchecking checkbox in HTML, is_active is omitted from request
        $response = $this->actingAs($this->adminUser)->put(route('admin.redirects.update', $rule), [
            'source_path' => '/to-be-deactivated',
            'target_path' => '/dest-deactivated',
            'status_code' => '301',
            // is_active omitted
        ]);

        $response->assertRedirect();
        $this->assertFalse($rule->fresh()->is_active);
    }

    public function test_admin_can_toggle_redirect_status_via_action(): void
    {
        $rule = RedirectRule::create([
            'source_path' => '/toggle-rule',
            'target_path' => '/dest-toggle',
            'status_code' => 301,
            'is_active' => true,
        ]);

        // Toggle to disabled
        $toggle1 = $this->actingAs($this->adminUser)->post(route('admin.redirects.toggle', $rule));
        $toggle1->assertRedirect();
        $this->assertFalse($rule->fresh()->is_active);

        // Toggle back to active
        $toggle2 = $this->actingAs($this->adminUser)->post(route('admin.redirects.toggle', $rule));
        $toggle2->assertRedirect();
        $this->assertTrue($rule->fresh()->is_active);
    }

    public function test_admin_can_delete_redirect_rule(): void
    {
        $rule = RedirectRule::create([
            'source_path' => '/delete-me',
            'target_path' => '/dest-delete',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->delete(route('admin.redirects.destroy', $rule));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('redirect_rules', ['id' => $rule->id]);
    }

    public function test_admin_can_export_redirects_csv_with_bom(): void
    {
        RedirectRule::create([
            'source_path' => '/export-source',
            'target_path' => '/export-target',
            'status_code' => 301,
            'is_active' => true,
            'hit_count' => 15,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.redirects.export'));
        $response->assertStatus(200);
        $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));

        $content = $response->streamedContent();

        // Check for UTF-8 BOM
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        // Headers and row content
        $this->assertStringContainsString('Source Path (Old URL)', $content);
        $this->assertStringContainsString('Target Destination (New URL)', $content);
        $this->assertStringContainsString('/export-source', $content);
        $this->assertStringContainsString('/export-target', $content);
    }

    public function test_admin_can_bulk_import_redirects_from_csv(): void
    {
        $csvContent = "source_path,target_path,status_code\n" .
                      "/imported-old-1,/new-dest-1,301\n" .
                      "/imported-old-2,/new-dest-2,302\n" .
                      "/same-loop,/same-loop,301\n"; // Should be skipped

        $file = UploadedFile::fake()->createWithContent('redirects.csv', $csvContent);

        $response = $this->actingAs($this->adminUser)->post(route('admin.redirects.import'), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('redirect_rules', [
            'source_path' => '/imported-old-1',
            'target_path' => '/new-dest-1',
            'status_code' => 301,
        ]);

        $this->assertDatabaseHas('redirect_rules', [
            'source_path' => '/imported-old-2',
            'target_path' => '/new-dest-2',
            'status_code' => 302,
        ]);

        $this->assertDatabaseMissing('redirect_rules', [
            'source_path' => '/same-loop',
        ]);
    }

    public function test_handle_redirects_middleware_intercepts_request_and_increments_hits(): void
    {
        $rule = RedirectRule::create([
            'source_path' => '/legacy-store-product',
            'target_path' => '/products/modern-item',
            'status_code' => 301,
            'is_active' => true,
            'hit_count' => 0,
        ]);

        // Cache is cleared on creation
        $this->assertNull(Cache::get('redirect_rules.map'));

        $response = $this->get('/legacy-store-product?ref=google');
        $response->assertRedirect(url('/products/modern-item?ref=google'));
        $this->assertEquals(301, $response->getStatusCode());

        $fresh = $rule->fresh();
        $this->assertEquals(1, $fresh->hit_count);
        $this->assertNotNull($fresh->last_accessed_at);
    }
}
