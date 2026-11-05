<?php

declare(strict_types=1);

namespace App\Enums;

enum StoreStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::ACTIVE => 'Live & Open',
            self::INACTIVE => 'Temporarily Closed',
            self::SUSPENDED => 'Suspended',
        };
    }
}
