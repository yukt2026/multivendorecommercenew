<?php

declare(strict_types=1);

namespace App\Enums;

enum StandardFulfilmentStatus: string
{
    case PLACED = 'placed';
    case CONFIRMED = 'confirmed';
    case PACKED = 'packed';
    case READY_TO_SHIP = 'ready_to_ship';
    case PICKED_UP = 'picked_up';
    case IN_TRANSIT = 'in_transit';
    case OUT_FOR_DELIVERY = 'out_for_delivery';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';
    case RETURNED = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::PLACED => 'Placed',
            self::CONFIRMED => 'Confirmed',
            self::PACKED => 'Packed',
            self::READY_TO_SHIP => 'Ready to Ship',
            self::PICKED_UP => 'Picked Up',
            self::IN_TRANSIT => 'In Transit',
            self::OUT_FOR_DELIVERY => 'Out for Delivery',
            self::DELIVERED => 'Delivered',
            self::CANCELLED => 'Cancelled',
            self::RETURNED => 'Returned',
        };
    }
}
