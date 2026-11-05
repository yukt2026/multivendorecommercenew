<?php

declare(strict_types=1);

namespace App\Enums;

enum QuickFulfilmentStatus: string
{
    case PLACED = 'placed';
    case STORE_ACCEPTED = 'store_accepted';
    case PACKING = 'packing';
    case READY = 'ready';
    case RIDER_ASSIGNED = 'rider_assigned';
    case PICKED_UP = 'picked_up';
    case OUT_FOR_DELIVERY = 'out_for_delivery';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PLACED => 'Order Placed',
            self::STORE_ACCEPTED => 'Store Accepted',
            self::PACKING => 'Packing Items',
            self::READY => 'Ready for Pickup',
            self::RIDER_ASSIGNED => 'Rider Assigned',
            self::PICKED_UP => 'Picked Up',
            self::OUT_FOR_DELIVERY => 'Out for Delivery',
            self::DELIVERED => 'Delivered',
            self::CANCELLED => 'Cancelled',
        };
    }
}
