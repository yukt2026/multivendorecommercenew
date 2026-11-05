<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class CustomerAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_customer_login_screen_is_accessible(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Welcome back');
    }

    public function test_customer_register_screen_is_accessible(): void
    {
        $response = $this->get('/register');
        $response->assertStatus(200);
        $response->assertSee('Create an account');
    }

    public function test_customer_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Rahul Sharma',
            'email' => 'rahul@example.com',
            'phone' => '+919876543210',
            'password' => 'SecurePass@123',
            'password_confirmation' => 'SecurePass@123',
        ]);

        $this->assertAuthenticated();
        $user = User::where('email', 'rahul@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('customer'));
        $this->assertNotNull($user->customer);

        $response->assertRedirect(route('home'));
    }

    public function test_customer_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'customer.test@example.com',
            'password' => Hash::make('Secret123!'),
        ]);
        $user->assignRole('customer');

        $this->assertNull($user->last_login_at);

        $response = $this->post('/login', [
            'email' => 'customer.test@example.com',
            'password' => 'Secret123!',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('home'));

        // Verify last_login_at was updated
        $user->refresh();
        $this->assertNotNull($user->last_login_at);

        // Verify audit log entry exists
        $auditExists = Activity::where('causer_id', $user->id)
            ->where('log_name', 'auth')
            ->exists();
        $this->assertTrue($auditExists);
    }

    public function test_customer_cannot_login_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'email' => 'customer.fail@example.com',
            'password' => Hash::make('Secret123!'),
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'customer.fail@example.com',
            'password' => 'WrongPassword',
        ]);

        $this->assertGuest();
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
    }

    public function test_customer_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/login');
    }
}
