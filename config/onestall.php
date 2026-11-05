<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OneStall Platform Core Configuration & Defaults
    |--------------------------------------------------------------------------
    |
    | Note: These are platform defaults and fallbacks. Per non-negotiable rules,
    | actual live rates, radius, and fees are configurable in database settings.
    |
    */

    'name' => env('APP_NAME', 'OneStall'),
    'currency' => env('ONESTALL_CURRENCY', 'INR'),
    'currency_symbol' => env('ONESTALL_CURRENCY_SYMBOL', '₹'),
    'timezone' => env('APP_TIMEZONE', 'Asia/Kolkata'),

    // Platform default commission percentage (10.00%)
    'default_commission_pct' => (float) env('ONESTALL_DEFAULT_COMMISSION_PCT', 10.00),

    // Quick commerce default delivery radius in kilometers
    'default_delivery_radius_km' => (float) env('ONESTALL_DEFAULT_DELIVERY_RADIUS_KM', 8.00),

    // Order number prefix (e.g. OS-20261105-0001)
    'order_number_prefix' => env('ONESTALL_ORDER_PREFIX', 'OS-'),

    // Cargo consignment tracking prefix (e.g. OSC-20261105-0001)
    'cargo_tracking_prefix' => env('ONESTALL_CARGO_PREFIX', 'OSC-'),

    // Fulfilment priority list
    // Rule: Never hard-code fulfilment priority. Store defaults here and allow DB/settings overrides.
    'fulfilment_priority' => [
        'PHYSICAL_STORE_CONNECT',  // Blinkit-style quick commerce from nearby local store
        'SELLER_WAREHOUSE',         // Seller direct dispatch
        'ONESTALL_FULFILMENT_HUB',  // OneStall central/regional warehouse
        'CARGO_HUB',                // Bulk/heavy shipments via OneStall Cargo logistics
    ],

    // Default delivery charges & thresholds
    'delivery' => [
        'free_delivery_threshold' => (float) env('ONESTALL_FREE_DELIVERY_THRESHOLD', 499.00),
        'base_delivery_fee' => (float) env('ONESTALL_BASE_DELIVERY_FEE', 40.00),
        'surge_fee' => (float) env('ONESTALL_DEFAULT_SURGE_FEE', 0.00),
    ],

    // Default payment gateway driver ('razorpay', 'cashfree')
    'payment_gateway' => env('PAYMENT_GATEWAY', 'razorpay'),

    // Horizon configuration flag
    'enable_horizon' => (bool) env('HORIZON_ENABLED', false),

    // Money precision
    'money_decimals' => 2,
];
