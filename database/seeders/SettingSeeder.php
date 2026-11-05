<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'default_commission_pct',
                'value' => '10.00',
                'type' => 'float',
                'group' => 'commission',
                'label' => 'Default Platform Commission Rate (%)',
                'description' => 'Platform fee percentage charged on completed marketplace orders',
                'is_public' => false,
            ],
            [
                'key' => 'default_delivery_radius_km',
                'value' => '8.00',
                'type' => 'float',
                'group' => 'logistics',
                'label' => 'Quick Commerce Delivery Radius (km)',
                'description' => 'Maximum distance radius for physical store connect and 10-30 min hyper-local deliveries',
                'is_public' => true,
            ],
            [
                'key' => 'base_delivery_fee',
                'value' => '40.00',
                'type' => 'float',
                'group' => 'logistics',
                'label' => 'Base Delivery Fee',
                'description' => 'Standard starting shipping charge for local deliveries',
                'is_public' => true,
            ],
            [
                'key' => 'free_delivery_threshold',
                'value' => '499.00',
                'type' => 'float',
                'group' => 'logistics',
                'label' => 'Free Delivery Cart Minimum',
                'description' => 'Cart subtotal required to qualify for free shipping',
                'is_public' => true,
            ],
            [
                'key' => 'order_number_prefix',
                'value' => 'OS-',
                'type' => 'string',
                'group' => 'orders',
                'label' => 'Order Code Prefix',
                'description' => 'Prefix assigned to customer order identifiers',
                'is_public' => false,
            ],
            [
                'key' => 'cargo_tracking_prefix',
                'value' => 'OSC-',
                'type' => 'string',
                'group' => 'cargo',
                'label' => 'Cargo Waybill Prefix',
                'description' => 'Tracking number prefix for OneStall Cargo logistics consignments',
                'is_public' => true,
            ],
            [
                'key' => 'fulfilment_priority',
                'value' => json_encode([
                    'PHYSICAL_STORE_CONNECT',
                    'SELLER_WAREHOUSE',
                    'ONESTALL_FULFILMENT_HUB',
                    'CARGO_HUB',
                ]),
                'type' => 'json',
                'group' => 'fulfilment',
                'label' => 'Fulfilment Node Priority Order',
                'description' => 'Order in which routing algorithm searches for stock allocation',
                'is_public' => false,
            ],
            [
                'key' => 'currency',
                'value' => 'INR',
                'type' => 'string',
                'group' => 'general',
                'label' => 'Store Currency Code',
                'description' => 'ISO standard 3-character currency code',
                'is_public' => true,
            ],
            [
                'key' => 'currency_symbol',
                'value' => '₹',
                'type' => 'string',
                'group' => 'general',
                'label' => 'Currency Symbol',
                'description' => 'Visual currency glyph',
                'is_public' => true,
            ],
        ];

        foreach ($settings as $item) {
            Setting::updateOrCreate(
                ['key' => $item['key']],
                $item
            );
        }
    }
}
