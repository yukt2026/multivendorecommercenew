<?php

declare(strict_types=1);

namespace App\Enums;

enum ReviewStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending Moderation',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
        };
    }
}
