<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case ADMIN = 'admin';
    case SELLER = 'seller';
    case STORE_MANAGER = 'store_manager';
    case CARGO_AGENT = 'cargo_agent';
    case CUSTOMER = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Administrator',
            self::ADMIN => 'Platform Admin',
            self::SELLER => 'Verified Seller',
            self::STORE_MANAGER => 'Physical Store Manager',
            self::CARGO_AGENT => 'OneStall Cargo Fleet Agent',
            self::CUSTOMER => 'Customer',
        };
    }
}
