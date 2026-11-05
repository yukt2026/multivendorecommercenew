<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_login_screen_is_accessible_at_dedicated_url(): void
    {
        $response = $this->get('/admin/login');
        $response->assertStatus(200);
        $response->assertSee('Administration Portal');
        $response->assertSee('Restricted Access: Authorized Personnel Only', false);
    }

    public function test_admin_can_login_and_is_redirected_to_admin_dashboard(): void
    {
        $admin = User::factory()->create([
            'email' => 'operations.lead@onestall.in',
            'password' => Hash::make('AdminSecret@2026'),
        ]);
        $admin->assignRole('admin');

        $response = $this->post('/admin/login', [
            'email' => 'operations.lead@onestall.in',
            'password' => 'AdminSecret@2026',
        ]);

        $this->assertAuthenticatedAs($admin);
        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_non_admin_user_cannot_login_at_admin_portal(): void
    {
        $customer = User::factory()->create([
            'email' => 'shopper@example.com',
            'password' => Hash::make('ShopperPass@123'),
        ]);
        $customer->assignRole('customer');

        $response = $this->post('/admin/login', [
            'email' => 'shopper@example.com',
            'password' => 'ShopperPass@123',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_unauthenticated_user_accessing_admin_dashboard_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/dashboard');
        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_logout(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->post('/admin/logout');

        $this->assertGuest();
        $response->assertRedirect(route('admin.login'));
    }
}
