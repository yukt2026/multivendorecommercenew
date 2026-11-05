<?php

declare(strict_types=1);

namespace App\Services\Otp;

interface OtpServiceInterface
{
    /**
     * Send OTP to the specified phone number.
     *
     * @param string $phone
     * @return array{success: bool, message: string}
     */
    public function send(string $phone): array;

    /**
     * Verify the OTP provided for the phone number.
     *
     * @param string $phone
     * @param string $code
     * @return bool
     */
    public function verify(string $phone, string $code): bool;

    /**
     * Clear active OTP for the phone number.
     */
    public function clear(string $phone): void;
}
