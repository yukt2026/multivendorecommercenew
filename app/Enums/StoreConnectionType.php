<?php

declare(strict_types=1);

namespace App\Enums;

enum StoreConnectionType: string
{
    case PHYSICAL = 'physical';
    case WOOCOMMERCE = 'woocommerce';
    case SHOPIFY = 'shopify';
    case CUSTOM_API = 'custom_api';
    case CSV = 'csv';
    case MANUAL = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::PHYSICAL => 'Physical POS Store Connect',
            self::WOOCOMMERCE => 'WooCommerce Integration',
            self::SHOPIFY => 'Shopify Store',
            self::CUSTOM_API => 'Custom REST API Webhook',
            self::CSV => 'CSV / Excel Catalog Feed',
            self::MANUAL => 'Manual Storefront Entry',
        };
    }
}
