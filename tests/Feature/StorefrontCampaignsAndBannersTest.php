<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Campaign;
use App\Models\Coupon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontCampaignsAndBannersTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_home_renders_hero_banners_coupons_and_campaigns(): void
    {
        $banner = Banner::create([
            'title' => 'Big Festive Home Hero Banner',
            'position' => 'home_hero',
            'image' => 'https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?w=1200&q=80',
            'link' => '/campaigns',
            'sort' => 1,
            'is_active' => true,
        ]);

        $campaign = Campaign::create([
            'name' => 'Diwali Mega Sale 2026',
            'slug' => 'diwali-mega-sale-2026',
            'type' => 'festive_season',
            'description' => 'Festive discounts across all stores.',
            'is_active' => true,
        ]);

        $coupon = Coupon::create([
            'code' => 'FESTIVE50',
            'type' => 'percent',
            'value' => 50,
            'min_order' => 499,
            'is_active' => true,
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Big Festive Home Hero Banner');
        $response->assertSee('Diwali Mega Sale 2026');
        $response->assertSee('FESTIVE50');
        $response->assertSee($banner->image_url);
    }

    public function test_public_campaign_page_renders_deals_and_details(): void
    {
        $campaign = Campaign::create([
            'name' => 'Monsoon Clearance 2026',
            'slug' => 'monsoon-clearance-2026',
            'type' => 'flash_sale',
            'description' => 'Up to 70% off during monsoon rush.',
            'is_active' => true,
        ]);

        $response = $this->get(route('campaign.show', 'monsoon-clearance-2026'));

        $response->assertStatus(200);
        $response->assertSee('Monsoon Clearance 2026');
        $response->assertSee('Up to 70% off during monsoon rush');
    }

    public function test_public_campaigns_index_page_renders_active_list(): void
    {
        Campaign::create([
            'name' => 'Winter Carnival',
            'slug' => 'winter-carnival',
            'type' => 'seasonal',
            'description' => 'Winter specials.',
            'is_active' => true,
        ]);

        $response = $this->get(route('campaigns.index'));

        $response->assertStatus(200);
        $response->assertSee('Winter Carnival');
        $response->assertSee('Active Festival');
    }
}
