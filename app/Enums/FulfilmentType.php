<?php

declare(strict_types=1);

namespace App\Enums;

enum FulfilmentType: string
{
    case STANDARD = 'standard';
    case QUICK = 'quick';
    case PICKUP = 'pickup';

    // Extended types if needed for legacy/sub-categorization
    case QUICK_COMMERCE = 'quick_commerce';
    case PHYSICAL_STORE_CONNECT = 'physical_store_connect';
    case SELLER_DIRECT = 'seller_direct';
    case ONESTALL_HUB = 'onestall_hub';
    case CARGO_LOGISTICS = 'cargo_logistics';

    public function label(): string
    {
        return match ($this) {
            self::STANDARD => 'Standard Delivery',
            self::QUICK => 'Quick Commerce',
            self::PICKUP => 'Store Pickup',
            self::QUICK_COMMERCE => 'Quick Commerce (10-30 Mins)',
            self::PHYSICAL_STORE_CONNECT => 'Physical Store Connect',
            self::SELLER_DIRECT => 'Seller Direct Dispatch',
            self::ONESTALL_HUB => 'OneStall Fulfilment Hub',
            self::CARGO_LOGISTICS => 'OneStall Cargo Logistics',
        };
    }
}
