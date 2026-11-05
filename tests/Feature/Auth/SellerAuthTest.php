<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\SellerStatus;
use App\Enums\SellerType;
use App\Models\Seller;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SellerAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_seller_register_screen_is_accessible(): void
    {
        $response = $this->get('/seller/register');
        $response->assertStatus(200);
        $response->assertSee('Register as a Seller');
    }

    public function test_seller_registration_creates_pending_seller_and_redirects_to_onboarding(): void
    {
        $response = $this->post('/seller/register', [
            'business_name' => 'Metro Fresh Organics',
            'owner_name' => 'Kiran Gowda',
            'email' => 'kiran@metrofresh.in',
            'phone' => '+919988776655',
            'seller_type' => SellerType::LOCAL_STORE->value,
            'password' => 'SellerSecret@123',
            'password_confirmation' => 'SellerSecret@123',
        ]);

        $this->assertAuthenticated();
        $user = User::where('email', 'kiran@metrofresh.in')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('seller_owner'));

        $seller = $user->seller;
        $this->assertNotNull($seller);
        $this->assertEquals(SellerStatus::PENDING, $seller->status);
        $this->assertEquals('Metro Fresh Organics', $seller->business_name);

        $response->assertRedirect(route('seller.onboarding'));
    }

    public function test_pending_seller_accessing_dashboard_is_redirected_to_onboarding(): void
    {
        $user = User::factory()->create();
        $user->assignRole('seller_owner');

        $seller = Seller::factory()->create([
            'user_id' => $user->id,
            'status' => SellerStatus::PENDING,
        ]);

        // Accessing dashboard directly should be intercepted by EnsureSellerStatus middleware
        $response = $this->actingAs($user)->get('/seller/dashboard');
        $response->assertRedirect(route('seller.onboarding'));

        // Onboarding page itself must be accessible
        $onboardingResponse = $this->actingAs($user)->get('/seller/onboarding');
        $onboardingResponse->assertStatus(200);
        $onboardingResponse->assertSee('KYC Verification In Progress');
    }

    public function test_suspended_seller_accessing_dashboard_is_redirected_to_suspended_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('seller_owner');

        $seller = Seller::factory()->create([
            'user_id' => $user->id,
            'status' => SellerStatus::SUSPENDED,
        ]);

        // Accessing dashboard should be intercepted
        $response = $this->actingAs($user)->get('/seller/dashboard');
        $response->assertRedirect(route('seller.suspended'));

        // Suspended page itself must be accessible
        $suspendedResponse = $this->actingAs($user)->get('/seller/suspended');
        $suspendedResponse->assertStatus(200);
        $suspendedResponse->assertSee('Seller Access Blocked');
    }

    public function test_approved_seller_can_access_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('seller_owner');

        $seller = Seller::factory()->create([
            'user_id' => $user->id,
            'status' => SellerStatus::APPROVED,
        ]);

        $response = $this->actingAs($user)->get('/seller/dashboard');
        $response->assertStatus(200);
        $response->assertSee('Merchant Operations Hub');
    }

    public function test_approved_seller_login_redirects_to_dashboard(): void
    {
        $user = User::factory()->create([
            'email' => 'approved.vendor@onestall.in',
            'password' => Hash::make('Secret123!'),
        ]);
        $user->assignRole('seller_owner');

        $seller = Seller::factory()->create([
            'user_id' => $user->id,
            'status' => SellerStatus::APPROVED,
        ]);

        $response = $this->post('/seller/login', [
            'email' => 'approved.vendor@onestall.in',
            'password' => 'Secret123!',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('seller.dashboard'));
    }

    public function test_regular_customer_without_seller_role_cannot_login_to_seller_portal(): void
    {
        $user = User::factory()->create([
            'email' => 'regular.shopper@example.com',
            'password' => Hash::make('Secret123!'),
        ]);
        $user->assignRole('customer');

        $response = $this->post('/seller/login', [
            'email' => 'regular.shopper@example.com',
            'password' => 'Secret123!',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }
}
