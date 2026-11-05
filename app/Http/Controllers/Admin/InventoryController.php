<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Seller;
use App\Models\StoreConnection;
use App\Models\SyncLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    /**
     * Read-only global inventory overview across all platform merchants.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Inventory::class);

        $query = Inventory::query()
            ->with([
                'variant.product.seller:id,business_name',
                'location:id,name,type,seller_id',
            ])
            ->latest('id');

        // Filter by Seller
        if ($request->filled('seller_id')) {
            $query->whereHas('location', fn ($q) => $q->where('seller_id', (int) $request->seller_id));
        }

        // Filter by Location Type
        if ($request->filled('location_type')) {
            $query->whereHas('location', fn ($q) => $q->where('type', $request->location_type));
        }

        // Filter by Stock Health
        if ($request->filled('stock_status')) {
            match ($request->stock_status) {
                'out_of_stock' => $query->whereRaw('(quantity_on_hand - quantity_reserved) <= 0'),
                'low_stock' => $query->whereRaw('(quantity_on_hand - quantity_reserved) > 0')
                    ->whereRaw('(quantity_on_hand - quantity_reserved) <= low_stock_threshold'),
                'in_stock' => $query->whereRaw('(quantity_on_hand - quantity_reserved) > low_stock_threshold'),
                default => null,
            };
        }

        // Quick Commerce candidate
        if ($request->filled('quick_available')) {
            $query->where('is_quick_available', filter_var($request->quick_available, FILTER_VALIDATE_BOOLEAN));
        }

        // Search by Product title, SKU, or Seller business name
        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->whereHas('variant', function ($vq) use ($search) {
                    $vq->where('sku', 'like', "%{$search}%")
                        ->orWhereHas('product', fn ($pq) => $pq->where('title', 'like', "%{$search}%"));
                })->orWhereHas('location.seller', function ($sq) use ($search) {
                    $sq->where('business_name', 'like', "%{$search}%");
                });
            });
        }

        $inventories = $query->paginate(20)->withQueryString();

        // Platform KPI metrics
        $totalItems = Inventory::count();
        $totalOnHand = (int) Inventory::sum('quantity_on_hand');
        $totalReserved = (int) Inventory::sum('quantity_reserved');
        $outOfStock = Inventory::whereRaw('(quantity_on_hand - quantity_reserved) <= 0')->count();

        $sellers = Seller::orderBy('business_name')->get(['id', 'business_name']);

        return view('admin.inventory.index', [
            'inventories' => $inventories,
            'sellers' => $sellers,
            'filters' => $request->only(['seller_id', 'location_type', 'stock_status', 'quick_available', 'search']),
            'metrics' => [
                'total_items' => $totalItems,
                'total_on_hand' => $totalOnHand,
                'total_reserved' => $totalReserved,
                'out_of_stock' => $outOfStock,
            ],
        ]);
    }

    /**
     * Read-only bi-directional POS and store integration sync logs.
     */
    public function syncLogs(Request $request): View
    {
        $this->authorize('viewAny', Inventory::class);

        $query = SyncLog::query()
            ->with(['connection.store.seller:id,business_name'])
            ->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('entity')) {
            $query->where('entity', $request->entity);
        }

        if ($request->filled('direction')) {
            $query->where('direction', $request->direction);
        }

        $logs = $query->paginate(25)->withQueryString();

        return view('admin.inventory.sync_logs', [
            'logs' => $logs,
            'filters' => $request->only(['status', 'entity', 'direction']),
        ]);
    }
}
