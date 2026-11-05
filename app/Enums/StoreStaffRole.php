<?php

declare(strict_types=1);

namespace App\Enums;

enum StoreStaffRole: string
{
    case OWNER = 'owner';
    case STORE_MANAGER = 'manager';
    case INVENTORY = 'inventory';
    case PACKING = 'packing';
    case DELIVERY = 'delivery';
    case SUPPORT = 'support';
    case CASHIER = 'cashier';
    case PICKER = 'picker';
    case DISPATCHER = 'dispatcher';

    public const MANAGER = self::STORE_MANAGER;

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Store Owner',
            self::STORE_MANAGER => 'Store Operations Manager',
            self::INVENTORY => 'Inventory Controller',
            self::PACKING => 'Order Packaging Staff',
            self::DELIVERY => 'Delivery & Dispatch Associate',
            self::SUPPORT => 'Store Customer Support',
            self::CASHIER => 'POS Cashier / Billing Staff',
            self::PICKER => 'Warehouse & Shelf Picker',
            self::DISPATCHER => 'Quick Dispatch Controller',
        };
    }

    /**
     * Standard baseline permissions for this role.
     *
     * @return array<string>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::OWNER, self::STORE_MANAGER => [
                'store.settings',
                'inventory.view',
                'inventory.manage',
                'orders.view',
                'orders.manage',
                'orders.pack',
                'packing.manage',
                'delivery.manage',
                'staff.manage',
                'connections.manage',
                'reports.view',
                'support.tickets',
            ],
            self::INVENTORY, self::PICKER => [
                'inventory.view',
                'inventory.manage',
                'connections.manage',
            ],
            self::PACKING => [
                'orders.view',
                'orders.manage',
                'orders.pack',
                'packing.manage',
            ],
            self::DELIVERY, self::DISPATCHER => [
                'orders.view',
                'delivery.manage',
            ],
            self::SUPPORT => [
                'orders.view',
                'support.tickets',
            ],
            self::CASHIER => [
                'orders.view',
                'orders.manage',
            ],
        };
    }

    /**
     * All granular permissions recognized in the store staff matrix.
     *
     * @return array<string, string>
     */
    public static function availablePermissions(): array
    {
        return [
            'store.settings' => 'Store Settings (Profile, Timings, Delivery Radius)',
            'inventory.view' => 'View Inventory (Catalog SKUs and Stock Quantities)',
            'inventory.manage' => 'Manage Inventory (Stock Adjustments & Restocking)',
            'orders.view' => 'View Orders (Browse Quick Commerce & Standard Orders)',
            'orders.manage' => 'Manage Orders (Accept, Reject, and Process Orders)',
            'orders.pack' => 'Pack Orders (Update Status to Packing & Packed)',
            'packing.manage' => 'Packing Slips & Thermal Shipping Labels',
            'delivery.manage' => 'Dispatch & Handover to Delivery Partner / Fleet',
            'staff.manage' => 'Staff & Team (Invite, Edit, and Manage Staff)',
            'connections.manage' => 'Connect My Shop (Integrations, Webhooks & Feeds)',
            'reports.view' => 'Store Performance Analytics & Sales Reports',
            'support.tickets' => 'Store Support & RMA Returns Resolution',
        ];
    }
}
