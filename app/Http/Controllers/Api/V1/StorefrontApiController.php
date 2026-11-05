<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class StorefrontApiController extends ApiController
{
    /**
     * Get public platform configuration and localized delivery radius for mobile app / API clients.
     */
    public function config(): JsonResponse
    {
        return $this->success([
            'platform_name' => setting('name', 'OneStall'),
            'currency' => setting('currency', 'INR'),
            'currency_symbol' => setting('currency_symbol', '₹'),
            'quick_commerce_radius_km' => (float) setting('default_delivery_radius_km', 8.00),
            'free_delivery_threshold' => (float) setting('free_delivery_threshold', 499.00),
            'base_delivery_fee' => (float) setting('base_delivery_fee', 40.00),
        ]);
    }
}
