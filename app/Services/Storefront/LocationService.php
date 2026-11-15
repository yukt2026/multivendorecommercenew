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
            'city' => 'West Delhi',
            'state' => 'Delhi',
            'pincode' => '110059',
            'lat' => 28.6222,
            'lng' => 77.0672,
            'label' => 'Janakpuri / West Delhi (110059)',
        ],
        [
            'city' => 'Noida',
            'state' => 'Uttar Pradesh',
            'pincode' => '201301',
            'lat' => 28.5706,
            'lng' => 77.3272,
            'label' => 'Noida Sector 18 (201301)',
        ],
        [
            'city' => 'Gurugram',
            'state' => 'Haryana',
            'pincode' => '122001',
            'lat' => 28.4595,
            'lng' => 77.0266,
            'label' => 'Gurugram Cyber City (122001)',
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
        [
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'pincode' => '380001',
            'lat' => 23.0225,
            'lng' => 72.5714,
            'label' => 'Ahmedabad Central (380001)',
        ],
        [
            'city' => 'Jaipur',
            'state' => 'Rajasthan',
            'pincode' => '302001',
            'lat' => 26.9124,
            'lng' => 75.7873,
            'label' => 'Jaipur Pink City (302001)',
        ],
        [
            'city' => 'Lucknow',
            'state' => 'Uttar Pradesh',
            'pincode' => '226001',
            'lat' => 26.8467,
            'lng' => 80.9462,
            'label' => 'Lucknow Central (226001)',
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
            $decoded = is_array($cookie) ? $cookie : json_decode($cookie, true);
            if (is_array($decoded) && (!empty($decoded['pincode']) || !empty($decoded['city']) || !empty($decoded['lat']))) {
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
     * Reverse geocode coordinates to human-readable city, state and pincode.
     * Uses BigDataCloud with nearest Indian city fallback.
     *
     * @param float $lat
     * @param float $lng
     * @return array
     */
    public function reverseGeocode(float $lat, float $lng): array
    {
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(3.5)
                ->withHeaders(['User-Agent' => 'OneStall/2.0 (Location Resolver)'])
                ->get("https://api.bigdatacloud.net/data/reverse-geocode-client?latitude={$lat}&longitude={$lng}&localityLanguage=en");

            if ($response->successful()) {
                $data = $response->json();
                $city = $data['city'] ?? $data['locality'] ?? $data['principalSubdivision'] ?? 'Current Location';
                $pincode = !empty($data['postcode']) ? $data['postcode'] : '';
                $state = $data['principalSubdivision'] ?? 'India';

                // If pincode was not in the reverse geocode, match against popular cities
                if (empty($pincode)) {
                    foreach (self::POPULAR_CITIES as $pc) {
                        $cleanPc = str_replace(' ncr', '', strtolower($pc['city']));
                        if (str_contains(strtolower($city), $cleanPc) || str_contains($cleanPc, strtolower($city))) {
                            $pincode = $pc['pincode'];
                            break;
                        }
                    }
                    if (empty($pincode)) {
                        $pincode = '110001';
                    }
                }

                $label = !empty($data['postcode']) ? "{$city} {$data['postcode']}" : "{$city} {$pincode}";

                return [
                    'city' => $city,
                    'pincode' => $pincode,
                    'state' => $state,
                    'lat' => $lat,
                    'lng' => $lng,
                    'address_label' => $label,
                    'is_detected' => true,
                ];
            }
        } catch (\Throwable $e) {
            // Log fallback
        }

        // Fallback: match nearest curated city using euclidean distance
        $nearest = self::DEFAULT_LOCATION;
        $minDist = 999999.0;
        foreach (self::POPULAR_CITIES as $c) {
            $dist = hypot($c['lat'] - $lat, $c['lng'] - $lng);
            if ($dist < $minDist) {
                $minDist = $dist;
                $nearest = $c;
            }
        }

        return [
            'city' => $nearest['city'],
            'pincode' => $nearest['pincode'],
            'state' => $nearest['state'],
            'lat' => $lat,
            'lng' => $lng,
            'address_label' => "GPS: {$nearest['city']} ({$nearest['pincode']})",
            'is_detected' => true,
        ];
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
                str_contains(strtolower($city['state']), $q) ||
                (isset($city['label']) && str_contains(strtolower($city['label']), $q))
            ) {
                $results[] = $city;
            }
        }

        // Search registered physical store locations from database
        try {
            $storeLocations = \App\Models\StoreLocation::query()
                ->whereNotNull('lat')
                ->whereNotNull('lng')
                ->where(function ($sub) use ($q) {
                    $sub->whereRaw('LOWER(city) LIKE ?', ["%{$q}%"])
                        ->orWhereRaw('LOWER(state) LIKE ?', ["%{$q}%"])
                        ->orWhere('pincode', 'LIKE', "%{$q}%")
                        ->orWhereRaw('LOWER(address_line1) LIKE ?', ["%{$q}%"]);
                })
                ->take(6)
                ->get();

            foreach ($storeLocations as $sl) {
                $exists = false;
                foreach ($results as $r) {
                    if ($r['pincode'] === $sl->pincode && strtolower($r['city']) === strtolower($sl->city)) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $results[] = [
                        'city' => $sl->city,
                        'state' => $sl->state ?? 'India',
                        'pincode' => $sl->pincode,
                        'lat' => (float) $sl->lat,
                        'lng' => (float) $sl->lng,
                        'label' => "{$sl->city} ({$sl->pincode})",
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Ignore DB errors during testing or migrations
        }

        // If numeric 6-digit pincode entered that's not in popular cities, resolve via prefix mapping
        if (preg_match('/^[1-9][0-9]{5}$/', $q)) {
            $hasExact = false;
            foreach ($results as $r) {
                if ($r['pincode'] === $q) {
                    $hasExact = true;
                    break;
                }
            }
            if (!$hasExact) {
                $prefix = substr($q, 0, 2);
                $pincodeCoords = [
                    '11' => ['city' => 'Delhi', 'state' => 'Delhi', 'lat' => 28.6139, 'lng' => 77.2090],
                    '12' => ['city' => 'Gurugram/Faridabad', 'state' => 'Haryana', 'lat' => 28.4595, 'lng' => 77.0266],
                    '13' => ['city' => 'Ambala/Panipat', 'state' => 'Haryana', 'lat' => 30.3782, 'lng' => 76.7767],
                    '14' => ['city' => 'Punjab', 'state' => 'Punjab', 'lat' => 30.9010, 'lng' => 75.8573],
                    '16' => ['city' => 'Chandigarh', 'state' => 'Chandigarh', 'lat' => 30.7333, 'lng' => 76.7794],
                    '20' => ['city' => 'Noida/Ghaziabad', 'state' => 'Uttar Pradesh', 'lat' => 28.5355, 'lng' => 77.3910],
                    '22' => ['city' => 'Lucknow/Amethi', 'state' => 'Uttar Pradesh', 'lat' => 26.8467, 'lng' => 80.9462],
                    '24' => ['city' => 'Dehradun/Moradabad', 'state' => 'Uttarakhand/UP', 'lat' => 30.3165, 'lng' => 78.0322],
                    '30' => ['city' => 'Jaipur', 'state' => 'Rajasthan', 'lat' => 26.9124, 'lng' => 75.7873],
                    '38' => ['city' => 'Ahmedabad', 'state' => 'Gujarat', 'lat' => 23.0225, 'lng' => 72.5714],
                    '40' => ['city' => 'Mumbai', 'state' => 'Maharashtra', 'lat' => 18.9388, 'lng' => 72.8354],
                    '41' => ['city' => 'Pune', 'state' => 'Maharashtra', 'lat' => 18.5204, 'lng' => 73.8567],
                    '50' => ['city' => 'Hyderabad', 'state' => 'Telangana', 'lat' => 17.3850, 'lng' => 78.4867],
                    '56' => ['city' => 'Bengaluru', 'state' => 'Karnataka', 'lat' => 12.9716, 'lng' => 77.5946],
                    '60' => ['city' => 'Chennai', 'state' => 'Tamil Nadu', 'lat' => 13.0827, 'lng' => 80.2707],
                    '70' => ['city' => 'Kolkata', 'state' => 'West Bengal', 'lat' => 22.5726, 'lng' => 88.3639],
                ];
                $meta = $pincodeCoords[$prefix] ?? [
                    'city' => 'Pincode ' . $q,
                    'state' => 'India',
                    'lat' => 20.5937,
                    'lng' => 78.9629,
                ];

                array_unshift($results, [
                    'city' => $meta['city'],
                    'state' => $meta['state'],
                    'pincode' => $q,
                    'lat' => $meta['lat'],
                    'lng' => $meta['lng'],
                    'label' => "{$meta['city']} ({$q})",
                ]);
            }
        }

        return $results;
    }
}
