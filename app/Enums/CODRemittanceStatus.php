<?php

declare(strict_types=1);

namespace App\Enums;

enum CODRemittanceStatus: string
{
    case PENDING = 'pending';
    case COLLECTED = 'collected';
    case REMITTED = 'remitted';
    case DISPUTED = 'disputed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending Collection',
            self::COLLECTED => 'Cash Collected',
            self::REMITTED => 'Remitted to Seller',
            self::DISPUTED => 'Disputed',
        };
    }
}
