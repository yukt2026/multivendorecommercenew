<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CmsPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminCmsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $customerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        $this->adminUser = User::factory()->create(['name' => 'Admin User', 'email' => 'admin@onestall.test']);
        $this->adminUser->assignRole($adminRole);

        $this->customerUser = User::factory()->create(['name' => 'Customer User', 'email' => 'customer@onestall.test']);
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $response = $this->get(route('admin.cms.index'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_non_admin_customer_is_forbidden(): void
    {
        $response = $this->actingAs($this->customerUser)->get(route('admin.cms.index'));
        $response->assertStatus(403);
    }

    public function test_admin_can_view_cms_index_with_metrics_and_pages(): void
    {
        CmsPage::create([
            'title' => 'About OneStall Platform',
            'slug' => 'about-onestall',
            'content' => 'OneStall platform connects sellers and customers.',
            'meta_title' => 'About OneStall',
            'meta_description' => 'Platform details.',
            'is_active' => true,
        ]);

        CmsPage::create([
            'title' => 'Draft Secret Policy',
            'slug' => 'draft-secret-policy',
            'content' => 'Internal draft content.',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.cms.index'));
        $response->assertStatus(200);
        $response->assertSee('Content Management (CMS)', false);
        $response->assertSee('About OneStall Platform');
        $response->assertSee('/page/about-onestall');
        $response->assertSee('Draft Secret Policy');
        $response->assertSee('Published Pages');
        $response->assertSee('Draft / Offline Pages');
    }

    public function test_admin_can_filter_cms_pages_by_status(): void
    {
        CmsPage::create([
            'title' => 'Active Policy Page',
            'slug' => 'active-policy',
            'content' => 'Published content.',
            'is_active' => true,
        ]);

        CmsPage::create([
            'title' => 'Draft Unfinished Page',
            'slug' => 'draft-unfinished',
            'content' => 'Draft content.',
            'is_active' => false,
        ]);

        $publishedRes = $this->actingAs($this->adminUser)->get(route('admin.cms.index', ['status' => 'published']));
        $publishedRes->assertStatus(200);
        $publishedRes->assertSee('Active Policy Page');
        $publishedRes->assertDontSee('Draft Unfinished Page');

        $draftRes = $this->actingAs($this->adminUser)->get(route('admin.cms.index', ['status' => 'draft']));
        $draftRes->assertStatus(200);
        $draftRes->assertSee('Draft Unfinished Page');
        $draftRes->assertDontSee('Active Policy Page');
    }

    public function test_admin_can_search_cms_pages(): void
    {
        CmsPage::create([
            'title' => 'Return and Exchange Guidelines',
            'slug' => 'return-guidelines',
            'content' => '7-day returns.',
            'is_active' => true,
        ]);

        CmsPage::create([
            'title' => 'Terms of Merchant Service',
            'slug' => 'merchant-terms',
            'content' => 'Merchant policies.',
            'is_active' => true,
        ]);

        $searchRes = $this->actingAs($this->adminUser)->get(route('admin.cms.index', ['search' => 'Return and Exchange']));
        $searchRes->assertStatus(200);
        $searchRes->assertSee('Return and Exchange Guidelines');
        $searchRes->assertDontSee('Terms of Merchant Service');
    }

    public function test_admin_can_view_create_page_form(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.cms.create'));
        $response->assertStatus(200);
        $response->assertSee('Create Static Content Page');
        $response->assertSee('Save &amp; Publish', false);
    }

    public function test_admin_can_store_new_cms_page_with_explicit_slug(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.cms.store'), [
            'title' => 'Payment Safety Guarantee',
            'slug' => 'payment-safety',
            'content' => '<h3>Secure Transactions</h3><p>Protected by 256-bit encryption.</p>',
            'meta_title' => 'Payment Safety Guarantee - OneStall',
            'meta_description' => 'Details on payment safety.',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('admin.cms.index'));
        $this->assertDatabaseHas('cms_pages', [
            'title' => 'Payment Safety Guarantee',
            'slug' => 'payment-safety',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_store_new_cms_page_with_auto_generated_slug(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.cms.store'), [
            'title' => 'Hyperlocal Logistics Guarantee',
            'content' => 'We deliver quickly.',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('admin.cms.index'));
        $this->assertDatabaseHas('cms_pages', [
            'title' => 'Hyperlocal Logistics Guarantee',
            'slug' => 'hyperlocal-logistics-guarantee',
            'is_active' => true,
        ]);
    }

    public function test_duplicate_slug_is_rejected_with_validation_error(): void
    {
        CmsPage::create([
            'title' => 'Terms of Service',
            'slug' => 'terms-of-service',
            'content' => 'Original terms.',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('admin.cms.store'), [
            'title' => 'Terms of Service Duplicate',
            'slug' => 'terms-of-service',
            'content' => 'Duplicate terms.',
            'is_active' => 1,
        ]);

        $response->assertSessionHasErrors(['slug']);
    }

    public function test_admin_can_view_edit_form_and_update_page(): void
    {
        $page = CmsPage::create([
            'title' => 'Original Title',
            'slug' => 'original-slug',
            'content' => 'Original content.',
            'is_active' => true,
        ]);

        $editRes = $this->actingAs($this->adminUser)->get(route('admin.cms.edit', $page));
        $editRes->assertStatus(200);
        $editRes->assertSee('Edit CMS Page: Original Title');

        $updateRes = $this->actingAs($this->adminUser)->put(route('admin.cms.update', $page), [
            'title' => 'Updated Title',
            'slug' => 'updated-slug',
            'content' => 'Updated content text.',
            'meta_title' => 'Updated Meta Title',
            'meta_description' => 'Updated meta description.',
            'is_active' => 0,
        ]);

        $updateRes->assertRedirect(route('admin.cms.index'));
        $freshPage = $page->fresh();
        $this->assertEquals('Updated Title', $freshPage->title);
        $this->assertEquals('updated-slug', $freshPage->slug);
        $this->assertFalse($freshPage->is_active);
    }

    public function test_admin_can_toggle_published_status(): void
    {
        $page = CmsPage::create([
            'title' => 'Toggle Test Page',
            'slug' => 'toggle-test-page',
            'content' => 'Toggle testing.',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('admin.cms.toggle', $page));
        $response->assertRedirect();
        $this->assertFalse($page->fresh()->is_active);

        $response2 = $this->actingAs($this->adminUser)->post(route('admin.cms.toggle', $page));
        $response2->assertRedirect();
        $this->assertTrue($page->fresh()->is_active);
    }

    public function test_admin_can_delete_cms_page(): void
    {
        $page = CmsPage::create([
            'title' => 'To Be Deleted Page',
            'slug' => 'to-be-deleted',
            'content' => 'Ephemeral content.',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->delete(route('admin.cms.destroy', $page));
        $response->assertRedirect(route('admin.cms.index'));
        $this->assertDatabaseMissing('cms_pages', ['id' => $page->id]);
    }

    public function test_storefront_displays_published_cms_page_and_handles_html_safely(): void
    {
        $page = CmsPage::create([
            'title' => 'Customer Safety Guidelines',
            'slug' => 'customer-safety',
            'content' => '<h2>Safety First</h2><p>Always verify rider OTP before receiving orders.</p>',
            'meta_title' => 'Safety Guidelines - OneStall',
            'is_active' => true,
        ]);

        $response = $this->get(route('pages.show', $page->slug));
        $response->assertStatus(200);
        $response->assertSee('Customer Safety Guidelines');
        $response->assertSee('<h2>Safety First</h2>', false);
        $response->assertSee('Always verify rider OTP before receiving orders.');
    }

    public function test_storefront_pages_alias_redirects_with_301(): void
    {
        $page = CmsPage::create([
            'title' => 'Alias Redirection Test',
            'slug' => 'alias-test',
            'content' => 'Testing 301 alias.',
            'is_active' => true,
        ]);

        $response = $this->get('/pages/' . $page->slug);
        $response->assertRedirect(route('pages.show', $page->slug));
        $response->assertStatus(301);
    }

    public function test_storefront_returns_404_for_draft_page(): void
    {
        $page = CmsPage::create([
            'title' => 'Draft Inactive Page',
            'slug' => 'draft-inactive',
            'content' => 'This page is offline.',
            'is_active' => false,
        ]);

        $response = $this->get(route('pages.show', $page->slug));
        $response->assertStatus(404);
    }
}
