<?php

declare(strict_types=1);

namespace App\Enums;

enum SellerType: string
{
    case MARKETPLACE = 'marketplace';
    case LOCAL_STORE = 'local_store';
    case BRAND = 'brand';
    case ONESTALL_STORE = 'onestall_store';
    case MULTI_STORE = 'multi_store';

    public function label(): string
    {
        return match ($this) {
            self::MARKETPLACE => 'Marketplace Vendor',
            self::LOCAL_STORE => 'Local Retail Store',
            self::BRAND => 'Direct Brand Store',
            self::ONESTALL_STORE => 'OneStall Direct Store',
            self::MULTI_STORE => 'Multi-Store Merchant Chain',
        };
    }
}
