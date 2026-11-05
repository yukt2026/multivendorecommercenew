<?php

declare(strict_types=1);

namespace App\Enums;

enum StoreType: string
{
    case SELLER_DIGITAL_STORE = 'seller_digital_store'; // Online-only storefront
    case PHYSICAL_STORE_CONNECT = 'physical_store_connect'; // Brick-and-mortar storefront connect
    case ONESTALL_OWNED_STORE = 'onestall_owned_store'; // Flagship / brand direct store
    case DARK_STORE_QUICK = 'dark_store_quick'; // Micro-fulfillment hub for quick commerce

    public function label(): string
    {
        return match ($this) {
            self::SELLER_DIGITAL_STORE => 'Seller Digital Storefront',
            self::PHYSICAL_STORE_CONNECT => 'Physical Store Connect (Local)',
            self::ONESTALL_OWNED_STORE => 'OneStall Direct Store',
            self::DARK_STORE_QUICK => 'Dark Store Quick Commerce',
        };
    }
}
