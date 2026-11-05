<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Display categorized tabbed platform settings.
     */
    public function index(Request $request): View
    {
        $currentTab = $request->query('tab', 'general');

        $groups = [
            'general' => 'General & Platform Info',
            'seo' => 'SEO & Meta Tags',
            'payment' => 'Payment Gateways & COD',
            'notification' => 'Notifications & Alerts',
            'fulfilment' => 'Fulfilment Priorities & SLAs',
            'commission' => 'Commissions & Tax Defaults',
            'delivery' => 'Delivery Radius & Thresholds',
        ];

        // Ensure essential defaults exist in DB
        $this->ensureEssentialSettings();

        $settings = Setting::all()->groupBy('group');

        return view('admin.settings.index', compact('settings', 'groups', 'currentTab'));
    }

    /**
     * Update platform configuration settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'settings' => 'required|array',
            'settings.*' => 'nullable',
        ]);

        foreach ($payload['settings'] as $key => $value) {
            Setting::set($key, $value);
        }

        if (function_exists('activity')) {
            activity('settings')
                ->causedBy(auth()->user())
                ->withProperties(['keys' => array_keys($payload['settings'])])
                ->log("System settings updated: " . implode(', ', array_keys($payload['settings'])));
        }

        return redirect()->back()->with('success', 'System platform settings updated successfully.');
    }

    /**
     * Ensure core platform default keys exist in DB.
     */
    protected function ensureEssentialSettings(): void
    {
        $defaults = [
            // General
            ['key' => 'name', 'value' => 'OneStall', 'group' => 'general', 'label' => 'Platform Store Name', 'type' => 'string'],
            ['key' => 'currency', 'value' => 'INR', 'group' => 'general', 'label' => 'Primary Currency', 'type' => 'string'],
            ['key' => 'timezone', 'value' => 'Asia/Kolkata', 'group' => 'general', 'label' => 'System Timezone', 'type' => 'string'],
            ['key' => 'support_email', 'value' => 'support@onestall.com', 'group' => 'general', 'label' => 'Customer Support Email', 'type' => 'string'],

            // SEO
            ['key' => 'meta_title', 'value' => 'OneStall - Unified Hyperlocal & Multi-Vendor Marketplace', 'group' => 'seo', 'label' => 'Default SEO Meta Title', 'type' => 'string'],
            ['key' => 'meta_description', 'value' => 'Shop groceries, electronics, and daily essentials with instant 10-30 min quick delivery or nationwide cargo shipping.', 'group' => 'seo', 'label' => 'Default Meta Description', 'type' => 'string'],
            ['key' => 'google_site_verification', 'value' => '', 'group' => 'seo', 'label' => 'Google Search Console Verification Code', 'type' => 'string'],
            ['key' => 'bing_site_verification', 'value' => '', 'group' => 'seo', 'label' => 'Bing Webmaster Verification Code', 'type' => 'string'],
            ['key' => 'default_og_image', 'value' => '', 'group' => 'seo', 'label' => 'Default Social Share Image (OG Image URL)', 'type' => 'string'],
            ['key' => 'robots_txt', 'value' => '', 'group' => 'seo', 'label' => 'Custom robots.txt Rules (Blank for Auto-Generated)', 'type' => 'text'],

            // Payment
            ['key' => 'razorpay_key_id', 'value' => 'rzp_test_sample', 'group' => 'payment', 'label' => 'Razorpay Key ID', 'type' => 'string'],
            ['key' => 'razorpay_key_secret', 'value' => 'rzp_secret_sample', 'group' => 'payment', 'label' => 'Razorpay Secret Key', 'type' => 'string'],
            ['key' => 'cod_allowed', 'value' => '1', 'group' => 'payment', 'label' => 'Enable Cash On Delivery (COD)', 'type' => 'boolean'],
            ['key' => 'cod_max_limit_amount', 'value' => '5000', 'group' => 'payment', 'label' => 'Maximum COD Order Limit (INR)', 'type' => 'number'],

            // Notification
            ['key' => 'order_sms_notifications', 'value' => '1', 'group' => 'notification', 'label' => 'Enable SMS Alerts', 'type' => 'boolean'],
            ['key' => 'low_stock_threshold_default', 'value' => '5', 'group' => 'notification', 'label' => 'Default Low Stock Alert Threshold', 'type' => 'number'],

            // Fulfilment
            ['key' => 'fulfilment_priority_order', 'value' => 'nearby_store,nearby_seller,onestall_store,regional_warehouse,national_seller', 'group' => 'fulfilment', 'label' => 'Fulfilment Source Priority (CSV)', 'type' => 'string'],
            ['key' => 'quick_delivery_sla_mins', 'value' => '20', 'group' => 'fulfilment', 'label' => 'Quick Commerce Delivery Target SLA (Mins)', 'type' => 'number'],

            // Commission
            ['key' => 'default_commission_pct', 'value' => '10.00', 'group' => 'commission', 'label' => 'Default Platform Commission %', 'type' => 'number'],
            ['key' => 'commission_gst_pct', 'value' => '18.00', 'group' => 'commission', 'label' => 'GST on Platform Commission %', 'type' => 'number'],
            ['key' => 'payment_gateway_fee_pct', 'value' => '2.00', 'group' => 'commission', 'label' => 'Payment Gateway Fee %', 'type' => 'number'],

            // Delivery
            ['key' => 'default_delivery_radius_km', 'value' => '8.00', 'group' => 'delivery', 'label' => 'Standard Delivery Radius (km)', 'type' => 'number'],
            ['key' => 'quick_max_radius_km', 'value' => '5.00', 'group' => 'delivery', 'label' => '10-Minute Dark Store Max Radius (km)', 'type' => 'number'],
            ['key' => 'free_shipping_threshold', 'value' => '999.00', 'group' => 'delivery', 'label' => 'Free Shipping Order Minimum (INR)', 'type' => 'number'],
            ['key' => 'standard_delivery_fee', 'value' => '40.00', 'group' => 'delivery', 'label' => 'Base Delivery Fee (INR)', 'type' => 'number'],
        ];

        foreach ($defaults as $d) {
            Setting::firstOrCreate(
                ['key' => $d['key']],
                [
                    'value' => $d['value'],
                    'group' => $d['group'],
                    'label' => $d['label'],
                    'type' => $d['type'],
                ]
            );
        }
    }
}
