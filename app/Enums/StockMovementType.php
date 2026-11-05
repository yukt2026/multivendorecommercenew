<?php

declare(strict_types=1);

namespace App\Enums;

enum StockMovementType: string
{
    case PURCHASE = 'purchase';
    case SALE = 'sale';
    case RESERVE = 'reserve';
    case RELEASE = 'release';
    case ADJUSTMENT = 'adjustment';
    case RETURN = 'return';
    case SYNC = 'sync';
    case RESTOCK = 'restock';
    case TRANSFER = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::PURCHASE => 'Purchase / Inward Stock',
            self::SALE => 'Delivered Sale / Outward',
            self::RESERVE => 'Cart/Order Reservation Lock',
            self::RELEASE => 'Unfulfilled Reservation Release',
            self::ADJUSTMENT => 'Manual Inventory Reconciliation',
            self::RETURN => 'Customer Return / Restock',
            self::SYNC => 'POS Bi-directional Shelf Sync',
            self::RESTOCK => 'Inward Stock Restock',
            self::TRANSFER => 'Inter-location Stock Transfer',
        };
    }
}
