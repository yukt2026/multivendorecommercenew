<?php

declare(strict_types=1);

namespace App\Enums;

enum ShipmentStatus: string
{
    case PENDING = 'pending';
    case MANIFEST_CREATED = 'manifest_created';
    case PICKED_UP = 'picked_up';
    case IN_TRANSIT = 'in_transit';
    case OUT_FOR_DELIVERY = 'out_for_delivery';
    case DELIVERED = 'delivered';
    case NDR_RAISED = 'ndr_raised';
    case RTO_INITIATED = 'rto_initiated';
    case RTO_DELIVERED = 'rto_delivered';
    case CANCELLED = 'cancelled';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Shipment Pending',
            self::MANIFEST_CREATED => 'Manifest Created',
            self::PICKED_UP => 'Picked Up',
            self::IN_TRANSIT => 'In Transit',
            self::OUT_FOR_DELIVERY => 'Out for Delivery',
            self::DELIVERED => 'Delivered',
            self::NDR_RAISED => 'Non-Delivery Report Raised',
            self::RTO_INITIATED => 'Return to Origin Initiated',
            self::RTO_DELIVERED => 'Return to Origin Delivered',
            self::CANCELLED => 'Shipment Cancelled',
            self::FAILED => 'Delivery Failed',
        };
    }
}
