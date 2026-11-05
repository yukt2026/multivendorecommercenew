<?php

declare(strict_types=1);

namespace App\Enums;

enum FulfilmentStatus: string
{
    case ALLOCATED = 'allocated';
    case PICKING = 'picking';
    case PACKED = 'packed';
    case HANDED_OVER_CARGO = 'handed_over_cargo';
    case IN_TRANSIT = 'in_transit';
    case OUT_FOR_DELIVERY = 'out_for_delivery';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
    case RETURNED = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::ALLOCATED => 'Node Allocated',
            self::PICKING => 'Picking in Progress',
            self::PACKED => 'Packed & Ready',
            self::HANDED_OVER_CARGO => 'Handed Over to Cargo',
            self::IN_TRANSIT => 'In Transit',
            self::OUT_FOR_DELIVERY => 'Out for Delivery',
            self::DELIVERED => 'Delivered',
            self::FAILED => 'Delivery Failed',
            self::RETURNED => 'Returned to Origin',
        };
    }
}
