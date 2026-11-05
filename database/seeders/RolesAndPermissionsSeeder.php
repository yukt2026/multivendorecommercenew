<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Platform permissions list
        $permissions = [
            // Settings & Platform
            'settings.view', 'settings.manage',
            'audit_logs.view',
            // Users & Staff
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'roles.manage',
            // Sellers & Verification
            'sellers.view', 'sellers.approve', 'sellers.suspend', 'sellers.manage',
            // Stores
            'stores.view', 'stores.create', 'stores.edit', 'stores.manage',
            // Catalogue
            'categories.manage', 'brands.manage',
            'products.view', 'products.create', 'products.edit', 'products.delete', 'products.approve',
            // Inventory
            'inventory.view', 'inventory.manage', 'inventory.transfer',
            // Orders & Fulfilment
            'orders.view', 'orders.create', 'orders.manage', 'orders.cancel',
            'fulfilment.view', 'fulfilment.accept', 'fulfilment.pack', 'fulfilment.dispatch',
            // Logistics & Delivery
            'cargo.view', 'cargo.manage', 'cargo.assign_rider', 'cargo.update_status',
            // Payments & Settlements
            'payments.view', 'payments.refund',
            'commissions.manage',
            'settlements.view', 'settlements.process',
            // Marketing
            'coupons.manage', 'banners.manage', 'reviews.moderate',
            // Support
            'tickets.view', 'tickets.reply', 'tickets.assign',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

        // 10 Platform Roles per specification:
        // super_admin, admin, support_admin, seller_owner, store_manager, inventory_staff, packing_staff, delivery_staff, support_staff, customer
        $roles = [
            'super_admin' => $permissions, // all permissions

            'admin' => [
                'settings.view', 'audit_logs.view',
                'users.view', 'users.create', 'users.edit',
                'sellers.view', 'sellers.approve', 'sellers.suspend',
                'stores.view', 'stores.manage',
                'categories.manage', 'brands.manage',
                'products.view', 'products.create', 'products.edit', 'products.delete', 'products.approve',
                'inventory.view',
                'orders.view', 'orders.manage',
                'fulfilment.view',
                'cargo.view', 'cargo.manage',
                'payments.view', 'payments.refund',
                'commissions.manage', 'settlements.view', 'settlements.process',
                'coupons.manage', 'banners.manage', 'reviews.moderate',
                'tickets.view', 'tickets.reply', 'tickets.assign',
            ],

            'support_admin' => [
                'orders.view', 'orders.manage',
                'payments.view', 'payments.refund',
                'tickets.view', 'tickets.reply', 'tickets.assign',
                'reviews.moderate',
            ],

            'seller_owner' => [
                'stores.view', 'stores.edit',
                'products.view', 'products.create', 'products.edit', 'products.delete',
                'inventory.view', 'inventory.manage',
                'orders.view',
                'fulfilment.view', 'fulfilment.accept', 'fulfilment.pack', 'fulfilment.dispatch',
                'settlements.view',
                'tickets.view', 'tickets.reply',
                'coupons.manage',
            ],

            'store_manager' => [
                'stores.view', 'stores.edit',
                'products.view', 'products.create', 'products.edit',
                'inventory.view', 'inventory.manage',
                'orders.view',
                'fulfilment.view', 'fulfilment.accept', 'fulfilment.pack', 'fulfilment.dispatch',
            ],

            'inventory_staff' => [
                'inventory.view', 'inventory.manage', 'inventory.transfer',
                'products.view',
            ],

            'packing_staff' => [
                'fulfilment.view', 'fulfilment.accept', 'fulfilment.pack',
            ],

            'delivery_staff' => [
                'cargo.view', 'cargo.update_status',
            ],

            'support_staff' => [
                'tickets.view', 'tickets.reply',
                'orders.view',
            ],

            'customer' => [
                'orders.view', 'orders.create', 'orders.cancel',
                'tickets.view', 'tickets.reply',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($rolePermissions);
        }
    }
}
