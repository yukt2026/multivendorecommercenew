<?php

declare(strict_types=1);

namespace App\Services\Otp;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class LogOtpDriver implements OtpServiceInterface
{
    protected const TTL_SECONDS = 300; // 5-minute expiry per specification
    protected const MAX_ATTEMPTS = 3;   // Rate limit: 3 sends per 10 minutes
    protected const DECAY_SECONDS = 600;

    /**
     * Send OTP to phone (hashed storage, 5-min expiry, rate-limited).
     */
    public function send(string $phone): array
    {
        $normalizedPhone = $this->normalizePhone($phone);
        $rateLimitKey = 'otp:send:rate:' . $normalizedPhone;

        if (RateLimiter::tooManyAttempts($rateLimitKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return [
                'success' => false,
                'message' => "Too many OTP requests. Please wait {$seconds} seconds before trying again.",
            ];
        }

        RateLimiter::hit($rateLimitKey, self::DECAY_SECONDS);

        // Generate 6-digit numeric OTP
        $otpCode = (string) random_int(100000, 999999);

        // Store hashed OTP in cache with 5-minute TTL
        $cacheKey = 'otp:code:' . $normalizedPhone;
        Cache::put($cacheKey, Hash::make($otpCode), self::TTL_SECONDS);

        // If in local/testing environment, keep track of plain code for test assertions
        if (app()->environment('local', 'testing')) {
            Cache::put('otp:plain:' . $normalizedPhone, $otpCode, self::TTL_SECONDS);
        }

        // Log to application log (Log driver implementation)
        Log::info("OneStall Security OTP for [{$normalizedPhone}]: [{$otpCode}] (Valid for 5 minutes)");

        return [
            'success' => true,
            'message' => 'OTP sent successfully to your phone number.',
        ];
    }

    /**
     * Verify provided OTP against hashed cache entry.
     */
    public function verify(string $phone, string $code): bool
    {
        $normalizedPhone = $this->normalizePhone($phone);
        $verifyRateKey = 'otp:verify:rate:' . $normalizedPhone;

        // Prevent brute-force OTP guessing: max 5 verify attempts per 5 minutes
        if (RateLimiter::tooManyAttempts($verifyRateKey, 5)) {
            return false;
        }

        RateLimiter::hit($verifyRateKey, self::TTL_SECONDS);

        $cacheKey = 'otp:code:' . $normalizedPhone;
        $hashedOtp = Cache::get($cacheKey);

        if (!$hashedOtp) {
            return false; // Expired or does not exist
        }

        if (Hash::check($code, $hashedOtp)) {
            $this->clear($normalizedPhone);
            RateLimiter::clear($verifyRateKey);
            return true;
        }

        return false;
    }

    /**
     * Clear OTP entries.
     */
    public function clear(string $phone): void
    {
        $normalizedPhone = $this->normalizePhone($phone);
        Cache::forget('otp:code:' . $normalizedPhone);
        Cache::forget('otp:plain:' . $normalizedPhone);
    }

    /**
     * Normalize Indian or international phone format.
     */
    protected function normalizePhone(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($cleaned) === 10) {
            return '+91' . $cleaned;
        }
        if (str_starts_with($cleaned, '91') && strlen($cleaned) === 12) {
            return '+' . $cleaned;
        }
        return '+' . $cleaned;
    }
}
