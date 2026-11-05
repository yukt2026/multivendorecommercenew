<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Storefront\LocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function __construct(
        protected LocationService $locationService
    ) {}

    /**
     * Get current user location and saved addresses.
     */
    public function current(Request $request): JsonResponse
    {
        $current = $this->locationService->getCurrentLocation($request);
        $saved = $this->locationService->getSavedAddresses($request->user());

        return response()->json([
            'current' => $current,
            'popular' => LocationService::POPULAR_CITIES,
            'saved' => $saved->map(fn ($addr) => [
                'id' => $addr->id,
                'name' => $addr->name,
                'type' => $addr->type?->value ?? 'home',
                'line1' => $addr->line1,
                'city' => $addr->city,
                'state' => $addr->state,
                'pincode' => $addr->pincode,
                'lat' => $addr->lat,
                'lng' => $addr->lng,
                'is_default' => (bool) $addr->is_default,
            ]),
        ]);
    }

    /**
     * Set location via session and cookie.
     */
    public function set(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city' => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:10'],
            'state' => ['nullable', 'string', 'max:100'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'address_label' => ['nullable', 'string', 'max:255'],
            'is_detected' => ['nullable', 'boolean'],
        ]);

        $payload = $this->locationService->setLocation($request, $validated);

        // 30 days cookie
        $cookie = cookie('customer_location', json_encode($payload), 60 * 24 * 30);

        return response()->json([
            'status' => 'success',
            'location' => $payload,
            'message' => 'Delivery location updated to ' . $payload['address_label'],
        ])->withCookie($cookie);
    }

    /**
     * Autocomplete search for pincode or city.
     */
    public function search(Request $request): JsonResponse
    {
        $query = (string) $request->input('q', '');
        $results = $this->locationService->searchLocation($query);

        return response()->json([
            'results' => $results,
        ]);
    }
}
