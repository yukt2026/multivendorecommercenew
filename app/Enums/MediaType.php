<?php

declare(strict_types=1);

namespace App\Enums;

enum MediaType: string
{
    case IMAGE = 'image';
    case VIDEO = 'video';

    public function label(): string
    {
        return match ($this) {
            self::IMAGE => 'Product Image',
            self::VIDEO => 'Product Video',
        };
    }
}
