<?php

declare(strict_types=1);

namespace App\Enums;

enum SettlementStatus: string
{
    case PENDING = 'pending';
    case ON_HOLD = 'on_hold';
    case PROCESSING = 'processing';
    case PAID = 'paid';
    case SETTLED = 'settled';
    case DISPUTED = 'disputed';
    case REVERSED = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending Settlement',
            self::ON_HOLD => 'On Hold',
            self::PROCESSING => 'Processing Payout',
            self::PAID => 'Paid',
            self::SETTLED => 'Settled',
            self::DISPUTED => 'Disputed',
            self::REVERSED => 'Reversed',
        };
    }
}
