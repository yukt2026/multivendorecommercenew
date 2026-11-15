<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCampaignsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->adminUser = User::factory()->create([
            'email' => 'admin.campaigns@onestall.in',
            'name' => 'Campaign Strategy Director',
        ]);
        $this->adminUser->assignRole('super_admin');
    }

    public function test_campaigns_index_page_renders_with_metrics_and_types(): void
    {
        Campaign::create([
            'name' => 'Diwali Mega Dhamaka',
            'slug' => 'diwali-dhamaka',
            'type' => 'festive_season',
            'description' => 'Unbeatable festive deals across categories.',
            'is_active' => true,
        ]);

        Campaign::create([
            'name' => 'Midnight Flash Rush',
            'slug' => 'midnight-rush',
            'type' => 'flash_sale',
            'description' => '2-hour hyper flash deal event.',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.campaigns.index'));

        $response->assertStatus(200);
        $response->assertSee('Marketing Campaigns &amp; Featured Events', false);
        $response->assertSee('Diwali Mega Dhamaka');
        $response->assertSee('Midnight Flash Rush');
        $response->assertSee('Festive Season');
        $response->assertSee('Flash Sale');
    }

    public function test_campaigns_can_be_filtered_and_searched(): void
    {
        Campaign::create([
            'name' => 'Summer Clearance Bonanza',
            'slug' => 'summer-clearance',
            'type' => 'clearance',
            'is_active' => true,
        ]);

        Campaign::create([
            'name' => 'Independence Day Sale',
            'slug' => 'independence-day',
            'type' => 'festive_season',
            'is_active' => true,
        ]);

        // Search by name
        $searchRes = $this->actingAs($this->adminUser)->get(route('admin.campaigns.index', ['search' => 'Summer']));
        $searchRes->assertStatus(200);
        $searchRes->assertSee('Summer Clearance Bonanza');
        $searchRes->assertDontSee('Independence Day Sale');

        // Filter by type
        $typeRes = $this->actingAs($this->adminUser)->get(route('admin.campaigns.index', ['type' => 'clearance']));
        $typeRes->assertStatus(200);
        $typeRes->assertSee('Summer Clearance Bonanza');
        $typeRes->assertDontSee('Independence Day Sale');
    }

    public function test_admin_can_create_campaign(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.campaigns.store'), [
            'name' => 'Holi Colors & Joy Sale',
            'type' => 'festive_season',
            'description' => 'Special organic gulal and sweet box bundles.',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.campaigns.index'));
        $this->assertDatabaseHas('campaigns', [
            'name' => 'Holi Colors & Joy Sale',
            'type' => 'festive_season',
        ]);

        $campaign = Campaign::where('name', 'Holi Colors & Joy Sale')->first();
        $this->assertNotNull($campaign->slug);
    }

    public function test_admin_can_update_campaign_with_quotes_and_special_chars(): void
    {
        $campaign = Campaign::create([
            'name' => "Men's Winter Jackets Special",
            'slug' => 'mens-winter',
            'type' => 'flash_sale',
            'description' => "Stay warm with 50% off India's finest woolens.",
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->put(route('admin.campaigns.update', $campaign), [
            'name' => "Men's & Women's Winter Mega Carnival",
            'type' => 'seasonal',
            'description' => "Updated description with apostrophe's and symbols & discounts.",
            'is_active' => false,
        ]);

        $response->assertRedirect(route('admin.campaigns.index'));
        $campaign->refresh();

        $this->assertSame("Men's & Women's Winter Mega Carnival", $campaign->name);
        $this->assertSame('seasonal', $campaign->type);
        $this->assertFalse($campaign->is_active);
    }

    public function test_admin_can_toggle_campaign_status_via_web_and_json(): void
    {
        $campaign = Campaign::create([
            'name' => 'Toggle Campaign',
            'slug' => 'toggle-campaign',
            'type' => 'flash_sale',
            'is_active' => true,
        ]);

        // Regular browser web form POST -> redirects
        $webRes = $this->actingAs($this->adminUser)->post(route('admin.campaigns.toggle', $campaign));
        $webRes->assertRedirect();
        $this->assertFalse($campaign->fresh()->is_active);

        // JSON/AJAX post -> returns JSON
        $jsonRes = $this->actingAs($this->adminUser)->postJson(route('admin.campaigns.toggle', $campaign));
        $jsonRes->assertOk();
        $jsonRes->assertJson([
            'success' => true,
            'is_active' => true,
        ]);
        $this->assertTrue($campaign->fresh()->is_active);
    }

    public function test_admin_can_delete_campaign(): void
    {
        $campaign = Campaign::create([
            'name' => 'Delete Me Campaign',
            'slug' => 'delete-me',
            'type' => 'flash_sale',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->delete(route('admin.campaigns.destroy', $campaign));
        $response->assertRedirect(route('admin.campaigns.index'));

        $this->assertDatabaseMissing('campaigns', ['name' => 'Delete Me Campaign']);
    }
}
