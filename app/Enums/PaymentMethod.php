<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case COD = 'cod';
    case UPI = 'upi';
    case CARD = 'card';
    case NETBANKING = 'netbanking';
    case WALLET = 'wallet';

    public function label(): string
    {
        return match ($this) {
            self::COD => 'Cash on Delivery (COD)',
            self::UPI => 'Unified Payments Interface (UPI)',
            self::CARD => 'Credit / Debit Card',
            self::NETBANKING => 'Net Banking',
            self::WALLET => 'Digital Wallet',
        };
    }
}
