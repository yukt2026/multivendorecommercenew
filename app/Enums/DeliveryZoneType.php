<?php

declare(strict_types=1);

namespace App\Enums;

enum DeliveryZoneType: string
{
    case RADIUS = 'radius';
    case PINCODE_LIST = 'pincode_list';

    public function label(): string
    {
        return match ($this) {
            self::RADIUS => 'Radial Distance (Kilometers)',
            self::PINCODE_LIST => 'Postal Pincode List',
        };
    }
}
