<?php

declare(strict_types=1);

namespace App\Enums;

enum ReservationStatus: string
{
    case ACTIVE = 'active';
    case CONFIRMED = 'confirmed';
    case EXPIRED = 'expired';
    case RELEASED = 'released';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active Hold (Cart/Checkout)',
            self::CONFIRMED => 'Converted to Confirmed Order',
            self::EXPIRED => 'TTL Expired without Payment',
            self::RELEASED => 'Released back to Available Stock',
        };
    }
}
