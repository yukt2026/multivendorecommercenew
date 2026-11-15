<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminBannersTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->adminUser = User::factory()->create([
            'email' => 'admin.banners@onestall.in',
            'name' => 'Visual Merchandising Director',
        ]);
        $this->adminUser->assignRole('super_admin');
    }

    public function test_banners_index_page_renders_with_metrics_and_positions(): void
    {
        Banner::create([
            'title' => 'Festive Hero Slider',
            'position' => 'home_hero',
            'image' => 'banners/hero_sample.jpg',
            'link' => '/categories/festive',
            'sort' => 1,
            'is_active' => true,
        ]);

        Banner::create([
            'title' => 'Flash Deals Strip',
            'position' => 'home_sub',
            'image' => 'banners/deal_sample.jpg',
            'link' => '/deals',
            'sort' => 2,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.banners.index'));

        $response->assertStatus(200);
        $response->assertSee('Homepage &amp; Promotional Banners', false);
        $response->assertSee('Festive Hero Slider');
        $response->assertSee('Flash Deals Strip');
        $response->assertSee('Hero Carousel');
        $response->assertSee('Deal &amp; Promo Strip', false);
    }

    public function test_banners_can_be_filtered_by_position(): void
    {
        Banner::create([
            'title' => 'Diwali Hero Banner',
            'position' => 'home_hero',
            'image' => 'banners/diwali.jpg',
            'is_active' => true,
        ]);

        Banner::create([
            'title' => 'Electronics Deal Strip',
            'position' => 'home_sub',
            'image' => 'banners/elec.jpg',
            'is_active' => true,
        ]);

        // Filter by hero alias
        $heroRes = $this->actingAs($this->adminUser)->get(route('admin.banners.index', ['position' => 'hero']));
        $heroRes->assertStatus(200);
        $heroRes->assertSee('Diwali Hero Banner');
        $heroRes->assertDontSee('Electronics Deal Strip');

        // Filter by deal_banner alias
        $dealRes = $this->actingAs($this->adminUser)->get(route('admin.banners.index', ['position' => 'deal_banner']));
        $dealRes->assertStatus(200);
        $dealRes->assertSee('Electronics Deal Strip');
        $dealRes->assertDontSee('Diwali Hero Banner');
    }

    public function test_admin_can_create_banner_with_uploaded_file(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('summer_sale.jpg', 1200, 400);

        $response = $this->actingAs($this->adminUser)->post(route('admin.banners.store'), [
            'title' => 'Summer Clearance 60% Off',
            'position' => 'hero',
            'link' => '/sale',
            'sort' => 1,
            'image' => $file,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.banners.index'));
        $this->assertDatabaseHas('banners', [
            'title' => 'Summer Clearance 60% Off',
            'position' => 'home_hero',
        ]);

        $banner = Banner::where('title', 'Summer Clearance 60% Off')->first();
        Storage::disk('public')->assertExists($banner->image);
        $this->assertNotNull($banner->image_url);
    }

    public function test_admin_can_update_banner_without_overwriting_existing_image(): void
    {
        $banner = Banner::create([
            'title' => 'Original Banner Title',
            'position' => 'home_hero',
            'image' => 'banners/hero_keep.jpg',
            'link' => '/old-link',
            'sort' => 5,
            'is_active' => true,
        ]);

        // Update title, position and sort, but no file uploaded and empty image_url
        $response = $this->actingAs($this->adminUser)->put(route('admin.banners.update', $banner), [
            'title' => 'Updated Banner Title',
            'position' => 'deal_banner',
            'link' => '/new-link',
            'sort' => 10,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.banners.index'));
        $banner->refresh();

        $this->assertSame('Updated Banner Title', $banner->title);
        $this->assertSame('home_sub', $banner->position);
        $this->assertSame('/new-link', $banner->link);
        $this->assertSame(10, $banner->sort);
        $this->assertSame('banners/hero_keep.jpg', $banner->image); // Must not be null or overwritten
    }

    public function test_admin_can_toggle_banner_via_form_post_and_json(): void
    {
        $banner = Banner::create([
            'title' => 'Toggle Test Banner',
            'position' => 'home_hero',
            'image' => 'banners/toggle.jpg',
            'is_active' => true,
        ]);

        // Regular browser form post -> redirects back
        $webRes = $this->actingAs($this->adminUser)->post(route('admin.banners.toggle', $banner));
        $webRes->assertRedirect();
        $this->assertFalse($banner->fresh()->is_active);

        // JSON/AJAX post -> returns JSON response
        $jsonRes = $this->actingAs($this->adminUser)->postJson(route('admin.banners.toggle', $banner));
        $jsonRes->assertOk();
        $jsonRes->assertJson([
            'success' => true,
            'is_active' => true,
        ]);
        $this->assertTrue($banner->fresh()->is_active);
    }

    public function test_admin_can_delete_banner(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('to_delete.jpg');
        $path = $file->store('banners', 'public');

        $banner = Banner::create([
            'title' => 'Banner To Delete',
            'position' => 'home_sub',
            'image' => $path,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('banners', ['id' => $banner->id]);
        Storage::disk('public')->assertExists($path);

        $response = $this->actingAs($this->adminUser)->delete(route('admin.banners.destroy', $banner));
        $response->assertRedirect(route('admin.banners.index'));

        $this->assertDatabaseMissing('banners', ['id' => $banner->id]);
        Storage::disk('public')->assertMissing($path);
    }
}
