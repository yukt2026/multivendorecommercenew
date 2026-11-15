<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\StoreStatus;
use App\Http\Controllers\Controller;
use App\Models\FulfilmentOrder;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StoreController extends Controller
{
    /**
     * Display searchable & filterable store directory.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Store::class);

        $query = Store::query()->with(['seller.user', 'location'])->latest('id');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhereHas('seller', fn($sq) => $sq->where('business_name', 'like', "%{$search}%"))
                  ->orWhereHas('location', fn($lq) => $lq->where('city', 'like', "%{$search}%")->orWhere('pincode', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->query('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        if ($request->has('quick_only')) {
            $quickVal = $request->query('quick_only');
            if ($quickVal === '1' || $quickVal === 'true') {
                $query->where('accepts_quick_commerce', true);
            } elseif ($quickVal === '0' || $quickVal === 'false') {
                $query->where('accepts_quick_commerce', false);
            }
        }

        if ($city = $request->query('city')) {
            $query->whereHas('location', fn($lq) => $lq->where('city', $city));
        }

        $stores = $query->paginate(15)->withQueryString();

        $cities = Store::query()
            ->join('store_locations', 'stores.id', '=', 'store_locations.store_id')
            ->whereNotNull('store_locations.city')
            ->distinct()
            ->pluck('store_locations.city')
            ->filter()
            ->values();

        $metrics = [
            'total_stores' => Store::count(),
            'active_stores' => Store::where('status', StoreStatus::ACTIVE)->count(),
            'suspended_stores' => Store::where('status', StoreStatus::SUSPENDED)->count(),
            'quick_stores' => Store::where('accepts_quick_commerce', true)->count(),
            'onestall_owned' => Store::where('is_onestall_owned', true)->count(),
        ];

        return view('admin.stores.index', compact('stores', 'metrics', 'cities'));
    }

    /**
     * Display interactive map view of all stores with geo-coordinates.
     */
    public function map(): View
    {
        $this->authorize('viewAny', Store::class);

        $stores = Store::query()
            ->with(['seller.user', 'location', 'timings'])
            ->whereHas('location', fn($q) => $q->whereNotNull('lat')->whereNotNull('lng'))
            ->get();

        $unmappedCount = Store::whereDoesntHave('location', fn($q) => $q->whereNotNull('lat')->whereNotNull('lng'))->count();

        $metrics = [
            'total_geocoded' => $stores->count(),
            'quick_commerce' => $stores->where('accepts_quick_commerce', true)->count(),
            'standard' => $stores->where('accepts_quick_commerce', false)->count(),
            'active' => $stores->where('status', StoreStatus::ACTIVE)->count(),
            'unmapped' => $unmappedCount,
            'cities_count' => $stores->pluck('location.city')->filter()->unique()->count(),
            'avg_radius' => round((float) ($stores->avg(fn($s) => $s->effectiveDeliveryRadius()) ?? 8.0), 1),
        ];

        $mapData = $stores->map(function ($store) {
            $loc = $store->location;
            return [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'seller' => $store->seller?->business_name ?? 'N/A',
                'lat' => (float) ($loc?->lat ?? 12.9716),
                'lng' => (float) ($loc?->lng ?? 77.5946),
                'address' => $loc?->address ?? 'N/A',
                'city' => $loc?->city ?? 'N/A',
                'state' => $loc?->state ?? '',
                'pincode' => $loc?->pincode ?? '',
                'phone' => $store->phone ?? $store->seller?->user?->phone ?? 'N/A',
                'email' => $store->email ?? $store->seller?->user?->email ?? 'N/A',
                'radius' => (float) $store->effectiveDeliveryRadius(),
                'status' => $store->status->value,
                'status_label' => $store->status->label(),
                'is_active' => $store->status === StoreStatus::ACTIVE,
                'is_quick' => (bool) $store->accepts_quick_commerce,
                'is_pickup' => (bool) $store->accepts_pickup,
                'is_onestall_owned' => (bool) $store->is_onestall_owned,
                'rating_avg' => (float) ($store->rating_avg ?? 0),
                'url' => route('admin.stores.show', $store),
                'storefront_url' => route('store.show', $store->slug),
            ];
        });

        return view('admin.stores.map', compact('stores', 'mapData', 'metrics'));
    }

    /**
     * Display detailed store console.
     */
    public function show(Store $store): View
    {
        $this->authorize('view', $store);

        $store->load([
            'seller.user',
            'location',
            'timings',
            'deliveryZones',
            'inventoryLocations.inventories.variant.product',
        ]);

        $recentOrders = FulfilmentOrder::query()
            ->where('store_id', $store->id)
            ->with(['order.customer.user'])
            ->latest('id')
            ->take(5)
            ->get();

        $activityLogs = class_exists(Activity::class)
            ? Activity::forSubject($store)->latest()->take(10)->get()
            : collect();

        return view('admin.stores.show', compact('store', 'recentOrders', 'activityLogs'));
    }

    /**
     * Approve and activate a store.
     */
    public function approve(Store $store): RedirectResponse
    {
        $this->authorize('update', $store);

        $store->status = StoreStatus::ACTIVE;
        $store->save();

        if (function_exists('activity')) {
            activity('store')
                ->performedOn($store)
                ->causedBy(auth()->user())
                ->log("Store #{$store->id} ({$store->name}) approved and activated");
        }

        return back()->with('success', "Store '{$store->name}' is now active.");
    }

    /**
     * Suspend a store with optional reason.
     */
    public function suspend(Request $request, Store $store): RedirectResponse
    {
        $this->authorize('update', $store);

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $store->status = StoreStatus::SUSPENDED;
        $store->save();

        $reason = $request->input('reason', 'Administrative compliance suspension');

        if (function_exists('activity')) {
            activity('store')
                ->performedOn($store)
                ->causedBy(auth()->user())
                ->withProperties(['reason' => $reason])
                ->log("Store #{$store->id} ({$store->name}) suspended: {$reason}");
        }

        return back()->with('success', "Store '{$store->name}' has been suspended.");
    }

    /**
     * Toggle Quick Commerce capability for a store.
     */
    public function toggleQuick(Store $store): RedirectResponse
    {
        $this->authorize('update', $store);

        $store->accepts_quick_commerce = !$store->accepts_quick_commerce;
        $store->save();

        $state = $store->accepts_quick_commerce ? 'enabled' : 'disabled';

        if (function_exists('activity')) {
            activity('store')
                ->performedOn($store)
                ->causedBy(auth()->user())
                ->log("Store #{$store->id} ({$store->name}) Quick Commerce {$state}");
        }

        return back()->with('success', "Quick commerce has been {$state} for store '{$store->name}'.");
    }

    /**
     * Update delivery radius for a store.
     */
    public function updateRadius(Request $request, Store $store): RedirectResponse
    {
        $this->authorize('update', $store);

        $validated = $request->validate([
            'delivery_radius_km' => 'required|numeric|min:0.5|max:100',
        ]);

        $oldRadius = $store->delivery_radius_km;
        $store->delivery_radius_km = (float) $validated['delivery_radius_km'];
        $store->save();

        if (function_exists('activity')) {
            activity('store')
                ->performedOn($store)
                ->causedBy(auth()->user())
                ->withProperties(['old' => $oldRadius, 'new' => $store->delivery_radius_km])
                ->log("Store #{$store->id} ({$store->name}) delivery radius updated to {$store->delivery_radius_km} km");
        }

        return back()->with('success', "Delivery radius updated to {$store->delivery_radius_km} km for store '{$store->name}'.");
    }

    /**
     * Export stores directory as CSV with active filters.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Store::class);

        $query = Store::query()->with(['seller.user', 'location'])->latest('id');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhereHas('seller', fn($sq) => $sq->where('business_name', 'like', "%{$search}%"))
                  ->orWhereHas('location', fn($lq) => $lq->where('city', 'like', "%{$search}%")->orWhere('pincode', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->query('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        if ($request->has('quick_only')) {
            $quickVal = $request->query('quick_only');
            if ($quickVal === '1' || $quickVal === 'true') {
                $query->where('accepts_quick_commerce', true);
            } elseif ($quickVal === '0' || $quickVal === 'false') {
                $query->where('accepts_quick_commerce', false);
            }
        }

        if ($city = $request->query('city')) {
            $query->whereHas('location', fn($lq) => $lq->where('city', $city));
        }

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="stores-' . now()->format('Ymd-His') . '.csv"',
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');
            // Add UTF-8 BOM
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, [
                'Store ID',
                'Name',
                'Slug',
                'Seller ID',
                'Seller Business',
                'Ownership',
                'Status',
                'Quick Commerce (10-30m)',
                'Customer Pickup',
                'Delivery Radius (km)',
                'Address',
                'City',
                'State',
                'Pincode',
                'Latitude',
                'Longitude',
                'Rating',
                'Rating Count',
                'Registered Date',
            ]);

            $query->chunk(100, function ($stores) use ($handle) {
                foreach ($stores as $s) {
                    $loc = $s->location;
                    fputcsv($handle, [
                        $s->id,
                        $s->name,
                        $s->slug,
                        $s->seller_id,
                        $s->seller?->business_name ?? 'N/A',
                        $s->is_onestall_owned ? 'OneStall Owned' : 'Merchant Store',
                        $s->status->value,
                        $s->accepts_quick_commerce ? 'Yes' : 'No',
                        $s->accepts_pickup ? 'Yes' : 'No',
                        $s->effectiveDeliveryRadius(),
                        $loc?->address ?? '',
                        $loc?->city ?? 'N/A',
                        $loc?->state ?? '',
                        $loc?->pincode ?? '',
                        $loc?->lat ?? '',
                        $loc?->lng ?? '',
                        number_format((float) ($s->rating_avg ?? 0), 2),
                        $s->rating_count ?? 0,
                        $s->created_at?->format('Y-m-d H:i:s') ?? '',
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }
}
