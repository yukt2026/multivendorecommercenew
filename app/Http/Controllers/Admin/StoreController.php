<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\StoreStatus;
use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StoreController extends Controller
{
    /**
     * Display searchable & filterable store directory.
     */
    public function index(Request $request): View
    {
        $query = Store::query()->with(['seller', 'location'])->latest('id');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhereHas('seller', fn($sq) => $sq->where('business_name', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->query('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        if ($request->boolean('quick_only')) {
            $query->where('accepts_quick_commerce', true);
        }

        $stores = $query->paginate(15)->withQueryString();

        $metrics = [
            'total_stores' => Store::count(),
            'active_stores' => Store::where('status', StoreStatus::ACTIVE)->count(),
            'suspended_stores' => Store::where('status', StoreStatus::SUSPENDED)->count(),
            'quick_stores' => Store::where('accepts_quick_commerce', true)->count(),
            'onestall_owned' => Store::where('is_onestall_owned', true)->count(),
        ];

        return view('admin.stores.index', compact('stores', 'metrics'));
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
        $store->load(['seller.user', 'location', 'timings', 'deliveryZones', 'inventoryLocations.inventories']);

        return view('admin.stores.show', compact('store'));
    }

    /**
     * Approve and activate a store.
     */
    public function approve(Store $store): RedirectResponse
    {
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
     * Export stores directory as CSV.
     */
    public function export(): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="stores-' . now()->format('Ymd-His') . '.csv"',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Store ID', 'Name', 'Seller', 'Status', 'Quick Commerce', 'Delivery Radius (km)', 'City', 'Latitude', 'Longitude']);

            Store::with(['seller', 'location'])->chunk(100, function ($stores) use ($handle) {
                foreach ($stores as $s) {
                    fputcsv($handle, [
                        $s->id,
                        $s->name,
                        $s->seller?->business_name ?? 'N/A',
                        $s->status->value,
                        $s->accepts_quick_commerce ? 'Yes' : 'No',
                        $s->effectiveDeliveryRadius(),
                        $s->location?->city ?? 'N/A',
                        $s->location?->lat ?? '',
                        $s->location?->lng ?? '',
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }
}
