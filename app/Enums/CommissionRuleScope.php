<?php

declare(strict_types=1);

namespace App\Enums;

enum CommissionRuleScope: string
{
    case GLOBAL = 'global';
    case CATEGORY = 'category';
    case SELLER = 'seller';
    case PROMO = 'promo';

    public function label(): string
    {
        return match ($this) {
            self::GLOBAL => 'Global Default',
            self::CATEGORY => 'Category Specific',
            self::SELLER => 'Seller Specific',
            self::PROMO => 'Promotional Window',
        };
    }
}
