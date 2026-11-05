<?php

declare(strict_types=1);

namespace App\Enums;

enum DeliveryAssignmentStatus: string
{
    case ASSIGNED = 'assigned';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case PICKED_UP = 'picked_up';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::ASSIGNED => 'Assigned',
            self::ACCEPTED => 'Accepted',
            self::REJECTED => 'Rejected',
            self::PICKED_UP => 'Picked Up',
            self::DELIVERED => 'Delivered',
            self::CANCELLED => 'Cancelled',
        };
    }
}
