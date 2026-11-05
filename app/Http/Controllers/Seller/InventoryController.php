<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryLocation;
use App\Models\ProductVariant;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Seller inventory table by location with on-hand, reserved, available, and threshold metrics.
     */
    public function index(Request $request): View
    {
        $seller = Auth::user()?->seller;
        abort_if(!$seller, 403, 'No seller profile associated.');

        $sellerLocationIds = InventoryLocation::where('seller_id', $seller->id)->pluck('id');

        $query = Inventory::query()
            ->whereIn('location_id', $sellerLocationIds)
            ->with([
                'variant.product:id,title,slug,is_quick_capable',
                'location:id,name,type',
            ]);

        // Filter by specific location
        if ($request->filled('location_id')) {
            $query->where('location_id', (int) $request->location_id);
        }

        // Filter by stock level
        if ($request->filled('stock_status')) {
            match ($request->stock_status) {
                'out_of_stock' => $query->whereRaw('(quantity_on_hand - quantity_reserved) <= 0'),
                'low_stock' => $query->whereRaw('(quantity_on_hand - quantity_reserved) > 0')
                    ->whereRaw('(quantity_on_hand - quantity_reserved) <= low_stock_threshold'),
                'in_stock' => $query->whereRaw('(quantity_on_hand - quantity_reserved) > low_stock_threshold'),
                default => null,
            };
        }

        // Filter by quick commerce availability
        if ($request->filled('quick_available')) {
            $query->where('is_quick_available', filter_var($request->quick_available, FILTER_VALIDATE_BOOLEAN));
        }

        // Search by Product title, SKU, or barcode
        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->whereHas('variant', function ($vq) use ($search) {
                $vq->where('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhereHas('product', fn ($pq) => $pq->where('title', 'like', "%{$search}%"));
            });
        }

        $inventories = $query->paginate(15)->withQueryString();

        // Location list for filter dropdown and transfer modal
        $locations = InventoryLocation::where('seller_id', $seller->id)
            ->where('is_active', true)
            ->get(['id', 'name', 'type']);

        // KPI aggregates
        $allSellerInventories = Inventory::whereIn('location_id', $sellerLocationIds)->get();
        $totalOnHand = $allSellerInventories->sum('quantity_on_hand');
        $totalReserved = $allSellerInventories->sum('quantity_reserved');
        $totalAvailable = max(0, $totalOnHand - $totalReserved);
        $lowStockCount = $allSellerInventories->filter(fn ($i) => ($i->quantity_on_hand - $i->quantity_reserved) > 0 && ($i->quantity_on_hand - $i->quantity_reserved) <= $i->low_stock_threshold)->count();
        $outOfStockCount = $allSellerInventories->filter(fn ($i) => ($i->quantity_on_hand - $i->quantity_reserved) <= 0)->count();

        return view('seller.inventory.index', [
            'inventories' => $inventories,
            'locations' => $locations,
            'filters' => $request->only(['location_id', 'stock_status', 'quick_available', 'search']),
            'metrics' => [
                'total_on_hand' => $totalOnHand,
                'total_reserved' => $totalReserved,
                'total_available' => $totalAvailable,
                'low_stock_count' => $lowStockCount,
                'out_of_stock_count' => $outOfStockCount,
            ],
        ]);
    }

    /**
     * Inline quick edit for on-hand stock quantity and low-stock threshold.
     */
    public function update(Request $request, Inventory $inventory): JsonResponse|RedirectResponse
    {
        $seller = Auth::user()?->seller;
        abort_if(!$seller || $inventory->location->seller_id !== $seller->id, 403);

        $validated = $request->validate([
            'quantity_on_hand' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['required', 'integer', 'min:1'],
            'is_quick_available' => ['nullable', 'boolean'],
        ]);

        $newQty = (int) $validated['quantity_on_hand'];
        if ($newQty !== $inventory->quantity_on_hand) {
            $this->inventoryService->adjust($inventory, $newQty, 'Inline quick edit via seller inventory table');
        }

        $inventory->update([
            'low_stock_threshold' => (int) $validated['low_stock_threshold'],
            'is_quick_available' => (bool) ($validated['is_quick_available'] ?? false),
        ]);

        if ($request->wantsJson()) {
            $fresh = $inventory->fresh();
            return response()->json([
                'success' => true,
                'message' => "Stock updated for SKU: {$fresh->variant->sku}",
                'inventory' => [
                    'id' => $fresh->id,
                    'quantity_on_hand' => $fresh->quantity_on_hand,
                    'quantity_reserved' => $fresh->quantity_reserved,
                    'available_quantity' => $fresh->available_quantity,
                    'low_stock_threshold' => $fresh->low_stock_threshold,
                    'is_quick_available' => $fresh->is_quick_available,
                ],
            ]);
        }

        return back()->with('success', "Stock details updated for SKU: {$inventory->variant->sku}");
    }

    /**
     * Retrieve stock movements history for an inventory item.
     */
    public function movements(Inventory $inventory): JsonResponse
    {
        $seller = Auth::user()?->seller;
        abort_if(!$seller || $inventory->location->seller_id !== $seller->id, 403);

        $movements = $inventory->movements()
            ->with('creator:id,name')
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'type' => $m->type->value,
                'type_label' => $m->type->label(),
                'quantity' => $m->quantity,
                'note' => $m->note,
                'user' => $m->creator?->name ?? 'System',
                'created_at' => $m->created_at->format('d M Y, h:i A'),
            ]);

        return response()->json([
            'success' => true,
            'sku' => $inventory->variant->sku,
            'product_title' => $inventory->variant->product->title,
            'location' => $inventory->location->name,
            'movements' => $movements,
        ]);
    }

    /**
     * Inter-location stock transfer.
     */
    public function transfer(Request $request): RedirectResponse
    {
        $seller = Auth::user()?->seller;
        abort_if(!$seller, 403);

        $validated = $request->validate([
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'from_location_id' => ['required', 'integer', 'exists:inventory_locations,id'],
            'to_location_id' => ['required', 'integer', 'exists:inventory_locations,id', 'different:from_location_id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $fromLoc = InventoryLocation::where('seller_id', $seller->id)->findOrFail($validated['from_location_id']);
        $toLoc = InventoryLocation::where('seller_id', $seller->id)->findOrFail($validated['to_location_id']);

        $variant = ProductVariant::whereHas('product', fn ($q) => $q->where('seller_id', $seller->id))
            ->findOrFail($validated['variant_id']);

        $this->inventoryService->transfer(
            $variant->id,
            $fromLoc,
            $toLoc,
            (int) $validated['quantity'],
            $validated['note'] ?? 'Internal stock balance re-route'
        );

        return back()->with('success', "Transferred {$validated['quantity']} units of {$variant->sku} from {$fromLoc->name} to {$toLoc->name}.");
    }

    /**
     * Bulk stock update via CSV import with parsing, validation, and error report.
     */
    public function importCsv(Request $request): RedirectResponse
    {
        $seller = Auth::user()?->seller;
        abort_if(!$seller, 403);

        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $file = $request->file('csv_file');
        $handle = fopen($file->getRealPath(), 'r');
        if (!$handle) {
            return back()->withErrors(['csv_file' => 'Unable to read the uploaded CSV file.']);
        }

        // Header check
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return back()->withErrors(['csv_file' => 'CSV file is empty.']);
        }

        // Clean headers: lowercase and trimmed
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $skuCol = array_search('sku', $header, true);
        $qtyCol = array_search('quantity_on_hand', $header, true);
        $locCol = array_search('location_id', $header, true);
        $thresholdCol = array_search('low_stock_threshold', $header, true);

        if ($skuCol === false || $qtyCol === false) {
            fclose($handle);
            return back()->withErrors(['csv_file' => "CSV must contain at least 'sku' and 'quantity_on_hand' columns."]);
        }

        $defaultLocation = InventoryLocation::where('seller_id', $seller->id)->first();
        if (!$defaultLocation) {
            fclose($handle);
            return back()->withErrors(['csv_file' => 'No active inventory location found for this seller account.']);
        }

        $successCount = 0;
        $errors = [];
        $rowNum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (empty(array_filter($row))) {
                continue; // Skip blank rows
            }

            $sku = strtoupper(trim((string) ($row[$skuCol] ?? '')));
            $qty = trim((string) ($row[$qtyCol] ?? ''));
            $locId = ($locCol !== false && !empty($row[$locCol])) ? (int) $row[$locCol] : $defaultLocation->id;
            $threshold = ($thresholdCol !== false && !empty($row[$thresholdCol])) ? (int) $row[$thresholdCol] : 5;

            if (empty($sku)) {
                $errors[] = "Row {$rowNum}: SKU is missing.";
                continue;
            }

            if (!is_numeric($qty) || (int) $qty < 0) {
                $errors[] = "Row {$rowNum}: Invalid quantity '{$qty}' for SKU {$sku}.";
                continue;
            }

            // Verify variant belongs to seller
            $variant = ProductVariant::where('sku', $sku)
                ->whereHas('product', fn ($q) => $q->where('seller_id', $seller->id))
                ->first();

            if (!$variant) {
                $errors[] = "Row {$rowNum}: SKU '{$sku}' not found in your catalogue.";
                continue;
            }

            // Verify location belongs to seller
            $location = InventoryLocation::where('seller_id', $seller->id)->find($locId);
            if (!$location) {
                $errors[] = "Row {$rowNum}: Location ID {$locId} does not exist or does not belong to your store.";
                continue;
            }

            // Upsert inventory & record adjustment
            $inv = Inventory::firstOrCreate(
                ['variant_id' => $variant->id, 'location_id' => $location->id],
                ['quantity_on_hand' => 0, 'quantity_reserved' => 0, 'low_stock_threshold' => $threshold]
            );

            $this->inventoryService->adjust($inv, (int) $qty, "Bulk CSV import (Row {$rowNum})");
            $inv->update(['low_stock_threshold' => $threshold]);
            $successCount++;
        }

        fclose($handle);

        $flashType = empty($errors) ? 'success' : 'warning';
        $message = "CSV Bulk Import complete: {$successCount} item(s) updated successfully.";
        if (!empty($errors)) {
            $message .= " " . count($errors) . " error(s) encountered.";
        }

        return back()->with($flashType, $message)->with('import_errors', array_slice($errors, 0, 15));
    }

    /**
     * Download sample CSV template.
     */
    public function sampleCsv(): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="inventory-import-sample.csv"',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['sku', 'location_id', 'quantity_on_hand', 'low_stock_threshold']);
            fputcsv($handle, ['MILK-500ML', '1', '100', '10']);
            fputcsv($handle, ['GHEE-1L', '1', '50', '5']);
            fclose($handle);
        }, 200, $headers);
    }
}
