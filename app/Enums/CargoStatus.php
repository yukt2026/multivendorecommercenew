<?php

declare(strict_types=1);

namespace App\Enums;

enum CargoStatus: string
{
    case BOOKED = 'booked';
    case MANIFESTED = 'manifested';
    case PICKED_UP = 'picked_up';
    case AT_ORIGIN_HUB = 'at_origin_hub';
    case LINEHAUL_IN_TRANSIT = 'linehaul_in_transit';
    case AT_DESTINATION_HUB = 'at_destination_hub';
    case OUT_FOR_DELIVERY = 'out_for_delivery';
    case DELIVERED = 'delivered';
    case RTO_INITIATED = 'rto_initiated';
    case RETURNED_TO_ORIGIN = 'returned_to_origin';

    public function label(): string
    {
        return match ($this) {
            self::BOOKED => 'Consignment Booked',
            self::MANIFESTED => 'Cargo Manifest Created',
            self::PICKED_UP => 'Picked Up by Cargo Agent',
            self::AT_ORIGIN_HUB => 'Arrived at Origin Hub',
            self::LINEHAUL_IN_TRANSIT => 'Linehaul in Transit',
            self::AT_DESTINATION_HUB => 'Arrived at Destination Hub',
            self::OUT_FOR_DELIVERY => 'Out for Cargo Delivery',
            self::DELIVERED => 'Consignment Delivered',
            self::RTO_INITIATED => 'Return to Origin Initiated',
            self::RETURNED_TO_ORIGIN => 'Returned to Origin',
        };
    }
}
