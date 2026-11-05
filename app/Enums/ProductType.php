<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductType: string
{
    case SIMPLE = 'simple';
    case VARIABLE = 'variable';

    public function label(): string
    {
        return match ($this) {
            self::SIMPLE => 'Simple Standalone Product',
            self::VARIABLE => 'Variable Product (Multi-SKU)',
        };
    }
}
