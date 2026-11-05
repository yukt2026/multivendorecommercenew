<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class LocationService
{
    /**
     * Default fallback location when user hasn't selected one.
     */
    public const DEFAULT_LOCATION = [
        'city' => 'Bengaluru',
        'pincode' => '560001',
        'state' => 'Karnataka',
        'lat' => 12.9716,
        'lng' => 77.5946,
        'address_label' => 'Bengaluru, 560001',
        'is_detected' => false,
    ];

    /**
     * Curated popular cities in India for quick selector.
     */
    public const POPULAR_CITIES = [
        [
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560001',
            'lat' => 12.9716,
            'lng' => 77.5946,
            'label' => 'Bengaluru Central (560001)',
        ],
        [
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'pincode' => '400001',
            'lat' => 18.9388,
            'lng' => 72.8354,
            'label' => 'Mumbai South (400001)',
        ],
        [
            'city' => 'Delhi NCR',
            'state' => 'Delhi',
            'pincode' => '110001',
            'lat' => 28.6315,
            'lng' => 77.2167,
            'label' => 'Connaught Place, Delhi (110001)',
        ],
        [
            'city' => 'Hyderabad',
            'state' => 'Telangana',
            'pincode' => '500001',
            'lat' => 17.3850,
            'lng' => 78.4867,
            'label' => 'Hyderabad (500001)',
        ],
        [
            'city' => 'Chennai',
            'state' => 'Tamil Nadu',
            'pincode' => '600001',
            'lat' => 13.0827,
            'lng' => 80.2707,
            'label' => 'Chennai Central (600001)',
        ],
        [
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'pincode' => '411001',
            'lat' => 18.5204,
            'lng' => 73.8567,
            'label' => 'Pune City (411001)',
        ],
        [
            'city' => 'Kolkata',
            'state' => 'West Bengal',
            'pincode' => '700001',
            'lat' => 22.5726,
            'lng' => 88.3639,
            'label' => 'Kolkata Central (700001)',
        ],
    ];

    /**
     * Resolve the current user location from session, cookie, or default.
     *
     * @param Request $request
     * @return array{city: string, pincode: string, state: string, lat: float, lng: float, address_label: string, is_detected: bool}
     */
    public function getCurrentLocation(Request $request): array
    {
        if ($request->hasSession() && ($sessionLocation = $request->session()->get('customer_location'))) {
            return $sessionLocation;
        }

        if ($cookie = $request->cookie('customer_location')) {
            $decoded = json_decode($cookie, true);
            if (is_array($decoded) && !empty($decoded['pincode'])) {
                if ($request->hasSession()) {
                    $request->session()->put('customer_location', $decoded);
                }
                return $decoded;
            }
        }

        // Check if authenticated user has a default address
        if ($user = $request->user()) {
            $defaultAddress = CustomerAddress::where('user_id', $user->id)
                ->where('is_default', true)
                ->first()
                ?? CustomerAddress::where('user_id', $user->id)->first();

            if ($defaultAddress) {
                $loc = [
                    'city' => $defaultAddress->city,
                    'pincode' => $defaultAddress->pincode,
                    'state' => $defaultAddress->state ?? 'India',
                    'lat' => (float) ($defaultAddress->lat ?: 12.9716),
                    'lng' => (float) ($defaultAddress->lng ?: 77.5946),
                    'address_label' => "{$defaultAddress->line1}, {$defaultAddress->city} ({$defaultAddress->pincode})",
                    'is_detected' => false,
                ];
                if ($request->hasSession()) {
                    $request->session()->put('customer_location', $loc);
                }
                return $loc;
            }
        }

        return self::DEFAULT_LOCATION;
    }

    /**
     * Set location in session and return payload to attach cookie.
     *
     * @param Request $request
     * @param array $data
     * @return array
     */
    public function setLocation(Request $request, array $data): array
    {
        $city = trim($data['city'] ?? 'Bengaluru');
        $pincode = trim($data['pincode'] ?? '560001');
        $state = trim($data['state'] ?? 'Karnataka');
        $lat = isset($data['lat']) ? (float) $data['lat'] : 12.9716;
        $lng = isset($data['lng']) ? (float) $data['lng'] : 77.5946;
        $label = trim($data['address_label'] ?? "{$city} {$pincode}");
        $isDetected = (bool) ($data['is_detected'] ?? false);

        $payload = [
            'city' => $city,
            'pincode' => $pincode,
            'state' => $state,
            'lat' => $lat,
            'lng' => $lng,
            'address_label' => $label,
            'is_detected' => $isDetected,
        ];

        $request->session()->put('customer_location', $payload);

        return $payload;
    }

    /**
     * Get saved addresses for an authenticated user.
     *
     * @param User|null $user
     * @return Collection
     */
    public function getSavedAddresses(?User $user): Collection
    {
        if (!$user) {
            return collect();
        }

        return CustomerAddress::where('user_id', $user->id)
            ->latest('is_default')
            ->latest('id')
            ->get();
    }

    /**
     * Quick search cities or pincodes matching query.
     *
     * @param string $query
     * @return array
     */
    public function searchLocation(string $query): array
    {
        $q = strtolower(trim($query));
        if ($q === '') {
            return self::POPULAR_CITIES;
        }

        $results = [];
        foreach (self::POPULAR_CITIES as $city) {
            if (
                str_contains(strtolower($city['city']), $q) ||
                str_contains(strtolower($city['pincode']), $q) ||
                str_contains(strtolower($city['state']), $q)
            ) {
                $results[] = $city;
            }
        }

        // If numeric 6-digit pincode entered that's not in popular cities, create dynamic entry
        if (preg_match('/^[1-9][0-9]{5}$/', $q) && empty($results)) {
            $results[] = [
                'city' => 'Pincode ' . $q,
                'state' => 'India',
                'pincode' => $q,
                'lat' => 12.9716, // Fallback default center
                'lng' => 77.5946,
                'label' => "Pincode: {$q}",
            ];
        }

        return $results;
    }
}
