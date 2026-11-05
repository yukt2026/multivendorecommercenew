<?php

declare(strict_types=1);

namespace App\Enums;

enum QCStatus: string
{
    case PENDING = 'pending';
    case PASSED = 'passed';
    case FAILED = 'failed';
    case WAIVED = 'waived';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'QC Pending',
            self::PASSED => 'QC Passed',
            self::FAILED => 'QC Failed',
            self::WAIVED => 'QC Waived',
        };
    }
}
