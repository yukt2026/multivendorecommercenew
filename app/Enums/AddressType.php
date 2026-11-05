<?php

declare(strict_types=1);

namespace App\Enums;

enum AddressType: string
{
    case HOME = 'home';
    case WORK = 'work';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::HOME => 'Home',
            self::WORK => 'Work / Office',
            self::OTHER => 'Other',
        };
    }
}
