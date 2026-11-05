<?php

declare(strict_types=1);

namespace App\Enums;

enum CouponType: string
{
    case PERCENT = 'percent';
    case FLAT = 'flat';

    public function label(): string
    {
        return match ($this) {
            self::PERCENT => 'Percentage Discount',
            self::FLAT => 'Flat Amount Discount',
        };
    }
}
