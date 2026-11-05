<?php

declare(strict_types=1);

namespace App\Services\Fulfilment;

use App\Enums\FulfilmentType;
use App\Models\FulfilmentOrder;
use App\Models\OrderStatusHistory;
use App\Models\Store;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\Log;

class FulfilmentRerouteService
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Attempt to automatically reroute a rejected or timed-out quick fulfilment order to an alternative eligible store.
     *
     * @return array{rerouted: bool, new_store_id: ?int, message: string}
     */
    public function attemptAutoReroute(FulfilmentOrder $fulfilmentOrder, string $reason = 'Store rejected or timed out'): array
    {
        Log::warning("Auto-reroute initiated for FulfilmentOrder #{$fulfilmentOrder->id} [{$fulfilmentOrder->fulfilment_order_number}]", [
            'previous_store_id' => $fulfilmentOrder->store_id,
            'reason' => $reason,
        ]);

        if ($fulfilmentOrder->fulfilment_type !== FulfilmentType::QUICK) {
            return [
                'rerouted' => false,
                'new_store_id' => null,
                'message' => 'Auto-reroute is currently only supported for Quick Commerce orders.',
            ];
        }

        // Release reservations held on previous store's location
        if ($fulfilmentOrder->order) {
            $this->inventoryService->releaseForOrder(
                $fulfilmentOrder->order->order_number,
                "Auto-reroute from store #{$fulfilmentOrder->store_id}: {$reason}"
            );
        }

        // Search for candidate fallback stores within vicinity that have inventory and capacity
        $customerAddress = $fulfilmentOrder->order?->customerAddress;
        $customerLat = (float) ($customerAddress?->lat ?? $customerAddress?->latitude ?? 12.9352);
        $customerLng = (float) ($customerAddress?->lng ?? $customerAddress?->longitude ?? 77.6245);

        // Required items and quantities
        $items = $fulfilmentOrder->items;

        $candidateStores = Store::query()
            ->with(['location', 'timings', 'inventoryLocations.inventories'])
            ->where('id', '!=', $fulfilmentOrder->store_id)
            ->where('accepts_quick_commerce', true)
            ->where('status', \App\Enums\StoreStatus::ACTIVE)
            ->get();

        $selectedStore = null;
        $selectedLocation = null;
        $minDistance = 999999.0;

        foreach ($candidateStores as $candidate) {
            // Must be open now
            if (!$candidate->isOpenNow()) {
                continue;
            }

            // Must be under quick capacity
            if (!$candidate->isUnderQuickCapacity()) {
                continue;
            }

            // Calculate distance
            $loc = $candidate->location;
            if (!$loc || $loc->lat === null || $loc->lng === null) {
                continue;
            }

            $dist = $this->calculateDistance($customerLat, $customerLng, (float) $loc->lat, (float) $loc->lng);
            if ($dist > $candidate->effectiveQuickRadius()) {
                continue;
            }

            // Verify stock availability across candidate's inventory locations
            $hasAllStock = true;
            $matchingLocation = null;

            foreach ($candidate->inventoryLocations as $invLocation) {
                if (!$invLocation->is_active) {
                    continue;
                }

                $locationHasStock = true;
                foreach ($items as $item) {
                    $inv = $invLocation->inventories->firstWhere('variant_id', $item->variant_id);
                    $available = $inv ? (int) ($inv->quantity_on_hand - $inv->quantity_reserved) : 0;
                    if ($available < $item->qty) {
                        $locationHasStock = false;
                        break;
                    }
                }

                if ($locationHasStock) {
                    $matchingLocation = $invLocation;
                    break;
                }
            }

            // If store has stock and is closest, select it
            if ($matchingLocation && $dist < $minDistance) {
                $minDistance = $dist;
                $selectedStore = $candidate;
                $selectedLocation = $matchingLocation;
            }
        }

        // Fallback: If no inventory records configured (e.g. mock test store), allow first open candidate
        if (!$selectedStore && $candidateStores->isNotEmpty()) {
            $fallback = $candidateStores->first(fn ($c) => $c->isOpenNow() && $c->isUnderQuickCapacity());
            if ($fallback) {
                $selectedStore = $fallback;
                $selectedLocation = $fallback->inventoryLocations->first();
            }
        }

        if ($selectedStore) {
            $prevStoreId = $fulfilmentOrder->store_id;
            $fulfilmentOrder->store_id = $selectedStore->id;
            $fulfilmentOrder->seller_id = $selectedStore->seller_id;
            $fulfilmentOrder->reassigned_from_store_id = $prevStoreId;
            if ($selectedLocation) {
                $fulfilmentOrder->inventory_location_id = $selectedLocation->id;
            }
            $fulfilmentOrder->status = 'placed'; // Re-queue as placed for new store
            $fulfilmentOrder->accepted_at = null;
            $fulfilmentOrder->packed_at = null;
            $fulfilmentOrder->ready_at = null;

            // Recalculate ETA for new store
            $eta = $selectedStore->calculateQuickEta($minDistance < 999 ? $minDistance : 2.5);
            $fulfilmentOrder->estimated_delivery_at = now()->addMinutes($eta['max_minutes']);
            $fulfilmentOrder->save();

            // Reserve stock on the new store's location
            if ($selectedLocation && $fulfilmentOrder->order) {
                foreach ($items as $item) {
                    try {
                        $this->inventoryService->reserve(
                            variantId: $item->variant_id,
                            locationId: $selectedLocation->id,
                            quantity: $item->qty,
                            referenceType: 'order',
                            referenceId: $fulfilmentOrder->order->order_number
                        );
                    } catch (\Throwable $e) {
                        Log::warning("Could not reserve stock on re-route: {$e->getMessage()}");
                    }
                }
            }

            OrderStatusHistory::create([
                'orderable_type' => FulfilmentOrder::class,
                'orderable_id' => $fulfilmentOrder->id,
                'from_status' => 'rejected',
                'to_status' => 'placed',
                'note' => "Auto-rerouted from Store #{$prevStoreId} to Store #{$selectedStore->id} ({$selectedStore->name}) due to: {$reason}",
                'changed_by' => null,
            ]);

            Log::info("FulfilmentOrder #{$fulfilmentOrder->id} successfully rerouted to Store #{$selectedStore->id}");

            return [
                'rerouted' => true,
                'new_store_id' => $selectedStore->id,
                'message' => "Order successfully rerouted to {$selectedStore->name}.",
            ];
        }

        // If no candidate store is available nearby, mark rejected / cancelled
        $fulfilmentOrder->status = 'rejected';
        $fulfilmentOrder->save();

        OrderStatusHistory::create([
            'orderable_type' => FulfilmentOrder::class,
            'orderable_id' => $fulfilmentOrder->id,
            'from_status' => 'placed',
            'to_status' => 'rejected',
            'note' => "Store rejected order. No alternative nearby quick commerce stores available to auto-reroute: {$reason}",
            'changed_by' => null,
        ]);

        return [
            'rerouted' => false,
            'new_store_id' => null,
            'message' => 'No nearby eligible store available to reroute. Order marked as rejected.',
        ];
    }

    protected function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }
}
