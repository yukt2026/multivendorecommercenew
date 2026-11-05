<?php

declare(strict_types=1);

namespace App\Services\Banking;

interface BankVerificationServiceInterface
{
    /**
     * Perform penny-drop account verification.
     *
     * @param string $accountNumber
     * @param string $ifsc
     * @param string $accountHolder
     * @return array{success: bool, beneficiary_name: string, reference_id: string, message: string}
     */
    public function verifyAccount(string $accountNumber, string $ifsc, string $accountHolder): array;
}
