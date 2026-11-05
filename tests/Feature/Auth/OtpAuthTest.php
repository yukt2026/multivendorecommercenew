<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Otp\OtpServiceInterface;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OtpAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_otp_login_screen_is_accessible(): void
    {
        $response = $this->get('/login/otp');
        $response->assertStatus(200);
        $response->assertSee('Phone OTP Sign In');
    }

    public function test_otp_can_be_sent_to_phone(): void
    {
        $phone = '+919988771122';

        $response = $this->post('/login/otp/send', [
            'phone' => $phone,
        ]);

        $response->assertRedirect('/login/otp');
        $response->assertSessionHas('otp_sent', true);
        $response->assertSessionHas('phone', $phone);

        // Assert hashed code exists in Cache
        $this->assertTrue(Cache::has('otp:code:' . $phone));
    }

    public function test_valid_otp_authenticates_existing_user(): void
    {
        $phone = '+919876543210';
        $user = User::factory()->create([
            'phone' => $phone,
        ]);
        $user->assignRole('customer');

        // Request OTP
        $otpService = app(OtpServiceInterface::class);
        $otpService->send($phone);

        // Fetch plain code from testing cache
        $code = Cache::get('otp:plain:' . $phone);
        $this->assertNotNull($code);

        // Verify OTP
        $response = $this->post('/login/otp/verify', [
            'phone' => $phone,
            'otp' => $code,
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('home'));

        // Assert OTP is cleared after successful use
        $this->assertFalse(Cache::has('otp:code:' . $phone));
    }

    public function test_invalid_otp_fails_verification(): void
    {
        $phone = '+919876543210';
        User::factory()->create(['phone' => $phone]);

        $otpService = app(OtpServiceInterface::class);
        $otpService->send($phone);

        $response = $this->post('/login/otp/verify', [
            'phone' => $phone,
            'otp' => '000000', // Invalid code
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('otp');
    }

    public function test_expired_otp_fails_verification(): void
    {
        $phone = '+919876543210';
        $otpService = app(OtpServiceInterface::class);
        $otpService->send($phone);

        // Simulate expiry by forgetting cache key (representing 5-minute TTL pass)
        $otpService->clear($phone);

        $response = $this->post('/login/otp/verify', [
            'phone' => $phone,
            'otp' => '123456',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('otp');
    }

    public function test_otp_sending_is_rate_limited(): void
    {
        $phone = '+919123456789';

        // 3 allowed sends
        for ($i = 0; $i < 3; $i++) {
            $this->post('/login/otp/send', ['phone' => $phone]);
        }

        // 4th send attempt should be rate limited
        $response = $this->post('/login/otp/send', ['phone' => $phone]);
        $response->assertSessionHasErrors('phone');
    }
}
