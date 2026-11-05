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
        $stores = Store::query()
            ->with(['seller', 'location'])
            ->whereHas('location', fn($q) => $q->whereNotNull('lat')->whereNotNull('lng'))
            ->get();

        $mapData = $stores->map(function ($store) {
            return [
                'id' => $store->id,
                'name' => $store->name,
                'seller' => $store->seller?->business_name ?? 'N/A',
                'lat' => $store->location?->lat ?? 12.9716,
                'lng' => $store->location?->lng ?? 77.5946,
                'radius' => $store->effectiveDeliveryRadius(),
                'status' => $store->status->value,
                'is_quick' => $store->accepts_quick_commerce,
                'city' => $store->location?->city ?? 'N/A',
                'url' => route('admin.stores.show', $store),
            ];
        });

        return view('admin.stores.map', compact('stores', 'mapData'));
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
