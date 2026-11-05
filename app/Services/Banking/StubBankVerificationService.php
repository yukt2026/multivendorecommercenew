<?php

declare(strict_types=1);

namespace App\Services\Banking;

use Illuminate\Support\Str;

class StubBankVerificationService implements BankVerificationServiceInterface
{
    /**
     * Known bank prefixes for simulated response.
     */
    private const BANK_CODES = [
        'HDFC' => 'HDFC Bank',
        'SBIN' => 'State Bank of India',
        'ICIC' => 'ICICI Bank',
        'UTIB' => 'Axis Bank',
        'KKBK' => 'Kotak Mahindra Bank',
        'PUNB' => 'Punjab National Bank',
        'BARB' => 'Bank of Baroda',
        'YESB' => 'Yes Bank',
        'IDFB' => 'IDFC First Bank',
        'IOBA' => 'Indian Overseas Bank',
    ];

    /**
     * Perform penny-drop account verification.
     *
     * @param string $accountNumber
     * @param string $ifsc
     * @param string $accountHolder
     * @return array{success: bool, beneficiary_name: string, reference_id: string, message: string}
     */
    public function verifyAccount(string $accountNumber, string $ifsc, string $accountHolder): array
    {
        $cleanIfsc = strtoupper(trim($ifsc));
        $cleanAccount = trim($accountNumber);

        // Basic sanity check
        if (!preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $cleanIfsc)) {
            return [
                'success' => false,
                'beneficiary_name' => '',
                'reference_id' => '',
                'message' => 'Invalid IFSC code format.',
            ];
        }

        if (strlen($cleanAccount) < 8 || !ctype_digit($cleanAccount)) {
            return [
                'success' => false,
                'beneficiary_name' => '',
                'reference_id' => '',
                'message' => 'Invalid account number digits.',
            ];
        }

        $prefix = substr($cleanIfsc, 0, 4);
        $bankName = self::BANK_CODES[$prefix] ?? 'Scheduled Commercial Bank';

        $refId = 'PND_' . strtoupper(Str::random(12));

        return [
            'success' => true,
            'beneficiary_name' => strtoupper(trim($accountHolder)),
            'bank_name' => $bankName,
            'reference_id' => $refId,
            'message' => "₹1 Penny-drop test successful. Beneficiary matched with {$bankName}.",
        ];
    }
}
