<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OneStall Data-Driven Navigation Menus
    |--------------------------------------------------------------------------
    |
    | Used by layouts/dashboard.blade.php to dynamically render sidebar
    | navigation for both Admin and Seller portals with role gating.
    |
    */

    'admin' => [
        [
            'section' => 'Overview & Command',
            'items' => [
                [
                    'title' => 'Dashboard',
                    'route' => 'admin.dashboard',
                    'active' => 'admin.dashboard*',
                    'icon' => 'home',
                    'badge' => null,
                ],
                [
                    'title' => 'Reports & Analytics',
                    'route' => 'admin.reports.index',
                    'active' => 'admin.reports*',
                    'icon' => 'chart-bar',
                    'badge' => null,
                ],
                [
                    'title' => 'UI Kit & Design System',
                    'route' => 'admin.uikit',
                    'active' => 'admin.uikit*',
                    'icon' => 'puzzle',
                    'badge' => 'New',
                    'badge_color' => 'indigo',
                ],
            ],
        ],
        [
            'section' => 'Catalogue & Merchandising',
            'items' => [
                [
                    'title' => 'Product Moderation',
                    'route' => 'admin.catalogue.moderation.index',
                    'active' => 'admin.catalogue.moderation*',
                    'icon' => 'cube',
                    'badge' => null,
                ],
                [
                    'title' => 'Categories Tree',
                    'route' => 'admin.catalogue.categories.index',
                    'active' => 'admin.catalogue.categories*',
                    'icon' => 'puzzle',
                    'badge' => null,
                ],
                [
                    'title' => 'Brands Registry',
                    'route' => 'admin.catalogue.brands.index',
                    'active' => 'admin.catalogue.brands*',
                    'icon' => 'tag',
                    'badge' => null,
                ],
                [
                    'title' => 'Attributes Manager',
                    'route' => 'admin.catalogue.attributes.index',
                    'active' => 'admin.catalogue.attributes*',
                    'icon' => 'cog',
                    'badge' => null,
                ],
                [
                    'title' => 'Banners & Carousels',
                    'route' => 'admin.banners.index',
                    'active' => 'admin.banners*',
                    'icon' => 'photograph',
                    'badge' => null,
                ],
                [
                    'title' => 'Coupons & Discounts',
                    'route' => 'admin.coupons.index',
                    'active' => 'admin.coupons*',
                    'icon' => 'ticket',
                    'badge' => null,
                ],
                [
                    'title' => 'Featured Campaigns',
                    'route' => 'admin.campaigns.index',
                    'active' => 'admin.campaigns*',
                    'icon' => 'sparkles',
                    'badge' => null,
                ],
            ],
        ],
        [
            'section' => 'Commerce & Operations',
            'items' => [
                [
                    'title' => 'Quick Commerce Ops',
                    'route' => 'admin.quick.index',
                    'active' => 'admin.quick*',
                    'icon' => 'bolt',
                    'badge' => 'Live',
                    'badge_color' => 'amber',
                ],
                [
                    'title' => 'Delivery Partners Fleet',
                    'route' => 'admin.delivery_partners.index',
                    'active' => 'admin.delivery_partners*',
                    'icon' => 'truck',
                    'badge' => null,
                ],
                [
                    'title' => 'Orders Central',
                    'route' => 'admin.orders.index',
                    'active' => 'admin.orders*',
                    'icon' => 'truck',
                    'badge' => null,
                ],
                [
                    'title' => 'Returns & RMA',
                    'route' => 'admin.returns.index',
                    'active' => 'admin.returns*',
                    'icon' => 'refresh',
                    'badge' => null,
                ],
                [
                    'title' => 'Customers',
                    'route' => 'admin.customers.index',
                    'active' => 'admin.customers*',
                    'icon' => 'users',
                    'badge' => null,
                ],
                [
                    'title' => 'Sellers & Vendors',
                    'route' => 'admin.sellers.index',
                    'active' => 'admin.sellers*',
                    'icon' => 'users',
                    'badge' => null,
                    'badge_color' => 'slate',
                ],
                [
                    'title' => 'Stores & Dark Stores',
                    'route' => 'admin.stores.index',
                    'active' => 'admin.stores*',
                    'icon' => 'store',
                    'badge' => null,
                ],
                [
                    'title' => 'Stores Live Map',
                    'route' => 'admin.stores.map',
                    'active' => 'admin.stores.map',
                    'icon' => 'map-pin',
                    'badge' => 'Geo',
                    'badge_color' => 'emerald',
                ],
                [
                    'title' => 'Global Inventory',
                    'route' => 'admin.inventory.index',
                    'active' => 'admin.inventory*',
                    'icon' => 'cube',
                    'badge' => null,
                ],
                [
                    'title' => 'Integration Sync Logs',
                    'route' => 'admin.inventory.sync_logs',
                    'active' => 'admin.inventory.sync_logs*',
                    'icon' => 'refresh',
                    'badge' => null,
                ],
            ],
        ],
        [
            'section' => 'Finance & Settlements',
            'items' => [
                [
                    'title' => 'Payments & Transactions',
                    'route' => 'admin.payments.index',
                    'active' => 'admin.payments.index',
                    'icon' => 'credit-card',
                    'badge' => null,
                ],
                [
                    'title' => 'Refunds Audit Ledger',
                    'route' => 'admin.payments.refunds',
                    'active' => 'admin.payments.refunds',
                    'icon' => 'refresh',
                    'badge' => null,
                ],
                [
                    'title' => 'Commission Rules & Simulator',
                    'route' => 'admin.commissions.index',
                    'active' => 'admin.commissions*',
                    'icon' => 'tag',
                    'badge' => null,
                ],
                [
                    'title' => 'Seller Settlements',
                    'route' => 'admin.settlements.index',
                    'active' => 'admin.settlements*',
                    'icon' => 'currency-rupee',
                    'badge' => null,
                ],
            ],
        ],
        [
            'section' => 'Support & Content',
            'items' => [
                [
                    'title' => 'Support Desk Tickets',
                    'route' => 'admin.support.index',
                    'active' => 'admin.support*',
                    'icon' => 'chat-bubble',
                    'badge' => null,
                ],
                [
                    'title' => 'Static CMS Pages',
                    'route' => 'admin.cms.index',
                    'active' => 'admin.cms*',
                    'icon' => 'document-text',
                    'badge' => null,
                ],
            ],
        ],
        [
            'section' => 'Governance & Configuration',
            'items' => [
                [
                    'title' => 'Platform Settings',
                    'route' => 'admin.settings.index',
                    'active' => 'admin.settings*',
                    'icon' => 'cog',
                    'badge' => null,
                ],
                [
                    'title' => 'Shipping & Logistics',
                    'route' => 'admin.shipping.index',
                    'active' => 'admin.shipping*',
                    'icon' => 'truck',
                    'badge' => null,
                ],
                [
                    'title' => 'Staff & Permissions',
                    'route' => 'admin.staff.index',
                    'active' => 'admin.staff*',
                    'icon' => 'shield-check',
                    'badge' => null,
                ],
                [
                    'title' => 'Security Audit Logs',
                    'route' => 'admin.audit-logs.index',
                    'active' => 'admin.audit-logs*',
                    'icon' => 'lock-closed',
                    'badge' => null,
                ],
                [
                    'title' => 'Notification Templates',
                    'route' => 'admin.notifications.templates.index',
                    'active' => 'admin.notifications.templates*',
                    'icon' => 'document-text',
                    'badge' => null,
                ],
                [
                    'title' => 'Notification Logs',
                    'route' => 'admin.notifications.logs.index',
                    'active' => 'admin.notifications.logs*',
                    'icon' => 'chat-bubble',
                    'badge' => null,
                ],
                [
                    'title' => 'SEO & 301 Redirects',
                    'route' => 'admin.redirects.index',
                    'active' => 'admin.redirects*',
                    'icon' => 'arrow-path',
                    'badge' => null,
                ],
            ],
        ],
    ],

    'seller' => [
        [
            'section' => 'Catalogue & Products',
            'items' => [
                [
                    'title' => 'All Products',
                    'route' => 'seller.products.index',
                    'active' => 'seller.products*',
                    'icon' => 'cube',
                    'badge' => null,
                ],
                [
                    'title' => 'Add New Product',
                    'route' => 'seller.products.create',
                    'active' => 'seller.products.create',
                    'icon' => 'plus',
                    'badge' => null,
                ],
            ],
        ],
        [
            'section' => 'Store Operations',
            'items' => [
                [
                    'title' => 'Overview',
                    'route' => 'seller.dashboard',
                    'active' => 'seller.dashboard*',
                    'icon' => 'home',
                    'badge' => null,
                ],
                [
                    'title' => 'Orders Console',
                    'route' => 'seller.orders.index',
                    'active' => 'seller.orders*',
                    'icon' => 'truck',
                    'badge' => 'New',
                    'badge_color' => 'amber',
                ],
                [
                    'title' => 'Returns & RMA',
                    'route' => 'seller.returns.index',
                    'active' => 'seller.returns*',
                    'icon' => 'refresh',
                    'badge' => null,
                ],
                [
                    'title' => 'Quick Live Dispatch',
                    'route' => 'seller.quick.live',
                    'active' => 'seller.quick*',
                    'icon' => 'bolt',
                    'badge' => '⚡ Live',
                    'badge_color' => 'amber',
                ],
                [
                    'title' => 'Store Inventory',
                    'route' => 'seller.inventory.index',
                    'active' => 'seller.inventory*',
                    'icon' => 'cube',
                    'badge' => null,
                ],
                [
                    'title' => 'My Storefront',
                    'route' => 'seller.store.edit',
                    'active' => 'seller.store*',
                    'icon' => 'store',
                    'badge' => 'Online',
                    'badge_color' => 'emerald',
                ],
                [
                    'title' => 'Outlets & Locations',
                    'route' => 'seller.stores.index',
                    'active' => 'seller.stores.index*',
                    'icon' => 'building-storefront',
                    'badge' => null,
                ],
                [
                    'title' => 'Store Staff & Roles',
                    'route' => 'seller.staff.index',
                    'active' => 'seller.staff*',
                    'icon' => 'users',
                    'badge' => null,
                    'store_permission' => 'staff.manage',
                ],
                [
                    'title' => 'Store Analytics',
                    'route' => 'seller.stores.reports',
                    'active' => 'seller.stores.reports*',
                    'icon' => 'chart-bar',
                    'badge' => null,
                    'store_permission' => 'reports.view',
                ],
                [
                    'title' => 'Connect My Shop',
                    'route' => 'seller.connections.index',
                    'active' => 'seller.connections*',
                    'icon' => 'puzzle',
                    'badge' => 'Sync',
                    'badge_color' => 'sky',
                    'store_permission' => 'connections.manage',
                ],
                [
                    'title' => 'Cargo Shipments & AWBs',
                    'route' => 'seller.shipments.index',
                    'active' => 'seller.shipments*',
                    'icon' => 'truck',
                    'badge' => null,
                ],
            ],
        ],
        [
            'section' => 'Financials & Payouts',
            'items' => [
                [
                    'title' => 'Earnings Ledger',
                    'route' => 'seller.payments.index',
                    'active' => 'seller.payments*',
                    'icon' => 'tag',
                    'badge' => null,
                ],
                [
                    'title' => 'Payouts & Settlements',
                    'route' => 'seller.settlements.index',
                    'active' => 'seller.settlements*',
                    'icon' => 'currency-rupee',
                    'badge' => null,
                ],
            ],
        ],
    ],

];
