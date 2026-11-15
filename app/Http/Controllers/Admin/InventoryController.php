<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Seller;
use App\Models\StoreConnection;
use App\Models\SyncLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryController extends Controller
{
    /**
     * Read-only global inventory overview across all platform merchants.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Inventory::class);

        $query = $this->buildInventoryQuery($request);
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
     * Build filtered query for global inventory balances.
     */
    protected function buildInventoryQuery(Request $request)
    {
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
            $val = $request->quick_available;
            if ($val === '1' || $val === 'true') {
                $query->where('is_quick_available', true);
            } elseif ($val === '0' || $val === 'false') {
                $query->where('is_quick_available', false);
            }
        }

        // Search by Product title, SKU, location name, or Seller business name
        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->whereHas('variant', function ($vq) use ($search) {
                    $vq->where('sku', 'like', "%{$search}%")
                        ->orWhereHas('product', fn ($pq) => $pq->where('title', 'like', "%{$search}%"));
                })->orWhereHas('location', function ($lq) use ($search) {
                    $lq->where('name', 'like', "%{$search}%")
                       ->orWhereHas('seller', fn ($sq) => $sq->where('business_name', 'like', "%{$search}%"));
                });
            });
        }

        return $query;
    }

    /**
     * Export global inventory balances to CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Inventory::class);

        $filename = 'global-inventory-' . now()->format('Ymd-His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $query = $this->buildInventoryQuery($request);

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, [
                'Inventory ID',
                'SKU',
                'Product Title',
                'Merchant Business',
                'Location Name',
                'Location Type',
                'Quantity On Hand',
                'Quantity Reserved',
                'Quantity Available',
                'Low Stock Threshold',
                'Stock Health',
                'Quick Commerce Eligible',
                'Last Updated',
            ]);

            $query->chunk(200, function ($inventories) use ($handle) {
                foreach ($inventories as $inv) {
                    $available = $inv->quantity_on_hand - $inv->quantity_reserved;
                    $status = $available <= 0 ? 'Out of Stock' : ($available <= $inv->low_stock_threshold ? 'Low Stock' : 'In Stock');

                    fputcsv($handle, [
                        $inv->id,
                        $inv->variant?->sku ?? 'N/A',
                        $inv->variant?->product?->title ?? 'N/A',
                        $inv->location?->seller?->business_name ?? 'OneStall Direct',
                        $inv->location?->name ?? 'N/A',
                        strtoupper((string) ($inv->location?->type?->value ?? $inv->location?->type ?? 'N/A')),
                        $inv->quantity_on_hand,
                        $inv->quantity_reserved,
                        max(0, $available),
                        $inv->low_stock_threshold,
                        $status,
                        $inv->is_quick_available ? 'Yes' : 'No',
                        $inv->updated_at?->toDateTimeString() ?? '',
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Read-only bi-directional POS and store integration sync logs.
     */
    public function syncLogs(Request $request): View
    {
        $this->authorize('viewAny', Inventory::class);

        $query = $this->buildSyncLogsQuery($request);
        $logs = $query->paginate(25)->withQueryString();

        $metrics = [
            'total_logs' => SyncLog::count(),
            'successful_syncs' => SyncLog::where('status', 'success')->count(),
            'failed_syncs' => SyncLog::where('status', 'failed')->count(),
            'partial_syncs' => SyncLog::where('status', 'partial')->count(),
            'last_24h_syncs' => SyncLog::where('created_at', '>=', now()->subDay())->count(),
        ];

        $connections = StoreConnection::with('store:id,name')->orderBy('name')->get(['id', 'store_id', 'name', 'type']);

        return view('admin.inventory.sync_logs', [
            'logs' => $logs,
            'metrics' => $metrics,
            'connections' => $connections,
            'filters' => $request->only(['status', 'entity', 'direction', 'search', 'connection_id', 'date_from', 'date_to']),
        ]);
    }

    /**
     * Build filtered query for sync logs.
     */
    protected function buildSyncLogsQuery(Request $request)
    {
        $query = SyncLog::query()
            ->with(['connection.store.seller:id,business_name,legal_name'])
            ->latest('id');

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                  ->orWhere('entity', 'like', "%{$search}%")
                  ->orWhereHas('connection', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                         ->orWhereHas('store', function ($sq) use ($search) {
                             $sq->where('name', 'like', "%{$search}%")
                                ->orWhereHas('seller', fn ($selq) => $selq->where('business_name', 'like', "%{$search}%")->orWhere('legal_name', 'like', "%{$search}%"));
                         });
                  });
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('entity') && $request->entity !== 'all') {
            $query->where('entity', $request->entity);
        }

        if ($request->filled('direction') && $request->direction !== 'all') {
            $query->where('direction', $request->direction);
        }

        if ($request->filled('connection_id') && $request->connection_id !== 'all') {
            $query->where('connection_id', (int) $request->connection_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return $query;
    }

    /**
     * Export integration sync logs to CSV.
     */
    public function exportSyncLogs(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Inventory::class);

        $filename = 'inventory-sync-logs-' . now()->format('Ymd-His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $query = $this->buildSyncLogsQuery($request);

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Log ID',
                'Connection Name',
                'Store Name',
                'Seller Name',
                'Entity',
                'Direction',
                'Status',
                'Message',
                'Created At',
            ]);

            $query->chunk(200, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    $store = $log->connection?->store;
                    $seller = $store?->seller;

                    fputcsv($handle, [
                        $log->id,
                        $log->connection?->name ?? 'Direct API',
                        $store?->name ?? 'N/A',
                        $seller?->business_name ?? $seller?->legal_name ?? 'N/A',
                        strtoupper((string) $log->entity),
                        strtoupper((string) $log->direction),
                        strtoupper((string) $log->status),
                        $log->message ?? '',
                        $log->created_at->toDateTimeString(),
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Prune historical sync logs older than given retention window.
     */
    public function pruneSyncLogs(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', Inventory::class);

        $days = max(1, (int) $request->input('days', 30));
        $status = $request->input('status', 'all');

        $query = SyncLog::where('created_at', '<', now()->subDays($days));
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $count = $query->delete();

        return redirect()->route('admin.inventory.sync_logs')
            ->with('success', "Cleaned up {$count} historical sync logs older than {$days} days.");
    }
}
