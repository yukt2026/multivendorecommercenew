<?php

declare(strict_types=1);

namespace App\Enums;

enum InventoryLocationType: string
{
    case STORE = 'store';
    case WAREHOUSE = 'warehouse';
    case ONESTALL_STORE = 'onestall_store';

    public function label(): string
    {
        return match ($this) {
            self::STORE => 'Physical Retail Store',
            self::WAREHOUSE => 'Seller Fulfillment Warehouse',
            self::ONESTALL_STORE => 'OneStall Dark Store / Hub',
        };
    }
}
