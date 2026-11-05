<?php

declare(strict_types=1);

namespace App\Services\Fulfilment;

use App\Enums\FulfilmentType;
use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\Setting;
use App\Services\Fulfilment\Strategies\FulfilmentStrategyInterface;
use App\Services\Fulfilment\Strategies\NationalSellerStrategy;
use App\Services\Fulfilment\Strategies\NearbySellerStrategy;
use App\Services\Fulfilment\Strategies\NearbyStoreStrategy;
use App\Services\Fulfilment\Strategies\OneStallStoreStrategy;
use App\Services\Fulfilment\Strategies\RegionalWarehouseStrategy;
use Carbon\Carbon;

class FulfilmentPipeline
{
    /** @var array<string, FulfilmentStrategyInterface> */
    protected array $strategies = [];

    public function __construct()
    {
        $this->registerDefaultStrategies();
    }

    /**
     * Register default out-of-the-box strategies.
     */
    protected function registerDefaultStrategies(): void
    {
        $this->addStrategy(new NearbyStoreStrategy());
        $this->addStrategy(new NearbySellerStrategy());
        $this->addStrategy(new OneStallStoreStrategy());
        $this->addStrategy(new RegionalWarehouseStrategy());
        $this->addStrategy(new NationalSellerStrategy());
    }

    public function addStrategy(FulfilmentStrategyInterface $strategy): self
    {
        $this->strategies[$strategy->getIdentifier()] = $strategy;
        return $this;
    }

    /**
     * Get active strategies ordered by priority list from DB/Settings.
     *
     * @return array<int, FulfilmentStrategyInterface>
     */
    public function getActiveStrategies(): array
    {
        $priorityKeys = Setting::get('fulfilment_priority', [
            'nearby_store',
            'nearby_seller',
            'onestall_store',
            'regional_warehouse',
            'national_seller',
        ]);

        if (is_string($priorityKeys)) {
            $priorityKeys = json_decode($priorityKeys, true) ?? [];
        }

        $ordered = [];
        foreach ($priorityKeys as $key) {
            if (isset($this->strategies[$key])) {
                $ordered[] = $this->strategies[$key];
            }
        }

        // Add any remaining strategies not explicitly listed
        foreach ($this->strategies as $key => $strategy) {
            if (!in_array($key, $priorityKeys, true)) {
                $ordered[] = $strategy;
            }
        }

        return $ordered;
    }

    /**
     * Generate an optimal split fulfilment plan for given cart items and customer location.
     *
     * @param array<int, array{variant_id: int, quantity: int, price?: float}> $items
     * @param array{lat?: float, lng?: float, pincode?: string, city?: string} $customerLocation
     */
    public function plan(array $items, array $customerLocation): FulfilmentPlan
    {
        $strategies = $this->getActiveStrategies();
        $allocatedMatches = [];
        $unfulfillable = [];

        foreach ($items as $item) {
            $variantId = (int) $item['variant_id'];
            $neededQty = (int) $item['quantity'];

            $variant = ProductVariant::with([
                'product.seller',
                'product.media',
                'inventories.location.store.timings',
                'inventories.location.store.location',
            ])->find($variantId);

            if (!$variant) {
                $unfulfillable[] = [
                    'variant_id' => $variantId,
                    'quantity' => $neededQty,
                    'reason' => 'Variant not found',
                ];
                continue;
            }

            // Find all candidate inventories for this variant
            $inventories = $variant->inventories->filter(fn ($inv) => $inv->location && $inv->location->is_active);

            // If no inventory records exist at all (e.g. unmanaged/demo stock), create a virtual candidate match
            if ($inventories->isEmpty()) {
                $seller = $variant->product?->seller;
                $isQuick = (bool) ($variant->product?->is_quick_capable);
                $allocatedMatches[] = [
                    'variant' => $variant,
                    'match' => new CandidateMatch(
                        strategyIdentifier: $isQuick ? 'nearby_store' : 'national_seller',
                        sourceType: $isQuick ? 'nearby_store' : 'national_seller',
                        sellerId: (int) ($seller?->id ?? 1),
                        storeId: null,
                        storeName: $seller?->business_name ?? 'Default Store',
                        inventoryLocationId: 0,
                        locationName: 'Primary Store Hub',
                        inventoryId: 0,
                        fulfilmentType: $isQuick ? FulfilmentType::QUICK : FulfilmentType::STANDARD,
                        distanceKm: 2.5,
                        estimatedMinutes: $isQuick ? 25 : 2880,
                        allocatedQuantity: $neededQty,
                        priorityScore: $isQuick ? 10 : 50
                    ),
                    'quantity' => $neededQty,
                ];
                continue;
            }

            $remainingQty = $neededQty;
            $matchedForThisItem = [];
            $inventoryAllocated = [];

            // Run candidate inventories through prioritized strategies
            foreach ($strategies as $strategy) {
                if ($remainingQty <= 0) {
                    break;
                }

                foreach ($inventories as $inv) {
                    if ($remainingQty <= 0) {
                        break;
                    }

                    $alreadyAllocated = $inventoryAllocated[$inv->id] ?? 0;
                    $actualAvailable = (int) (($inv->quantity_on_hand - $inv->quantity_reserved) - $alreadyAllocated);
                    if ($actualAvailable <= 0) {
                        continue;
                    }

                    $match = $strategy->evaluate($variant, min($remainingQty, $actualAvailable), $customerLocation, $inv);
                    if ($match && $match->allocatedQuantity > 0) {
                        $allocatedQty = min($remainingQty, $actualAvailable, $match->allocatedQuantity);
                        $matchedForThisItem[] = [
                            'variant' => $variant,
                            'match' => $match,
                            'quantity' => $allocatedQty,
                        ];
                        $remainingQty -= $allocatedQty;
                        $inventoryAllocated[$inv->id] = ($inventoryAllocated[$inv->id] ?? 0) + $allocatedQty;
                    }
                }
            }

            if ($remainingQty > 0) {
                $unfulfillable[] = [
                    'variant_id' => $variant->id,
                    'product_title' => $variant->product?->title,
                    'variant_name' => $variant->name,
                    'requested_quantity' => $neededQty,
                    'unfulfilled_quantity' => $remainingQty,
                    'reason' => 'Insufficient stock across all candidate locations or store closed',
                ];
            } else {
                foreach ($matchedForThisItem as $m) {
                    $allocatedMatches[] = $m;
                }
            }
        }

        // Group allocated matches into FulfilmentPlanGroup packages
        $groupedPackages = $this->buildGroups($allocatedMatches);

        // Calculate delivery fees
        $baseDeliveryFee = (float) Setting::get('base_delivery_fee', 40.00);
        $freeThreshold = (float) Setting::get('free_delivery_threshold', 499.00);

        $orderSubtotal = array_sum(array_map(fn (FulfilmentPlanGroup $g) => $g->subtotal, $groupedPackages));
        $isFreeDelivery = $orderSubtotal >= $freeThreshold;

        $totalFee = 0.0;
        foreach ($groupedPackages as $index => $group) {
            if ($isFreeDelivery) {
                $group->deliveryFee = 0.0;
            } else {
                // First package carries the base delivery fee
                $group->deliveryFee = ($index === 0) ? $baseDeliveryFee : 0.0;
                $totalFee += $group->deliveryFee;
            }
        }

        $allQuick = !empty($groupedPackages) && collect($groupedPackages)->every(fn ($g) => $g->fulfilmentType === FulfilmentType::QUICK);

        return new FulfilmentPlan(
            groups: $groupedPackages,
            unfulfillableItems: $unfulfillable,
            totalDeliveryFee: $totalFee,
            allQuick: $allQuick
        );
    }

    /**
     * Group individual item matches into logical packages / fulfilment orders.
     *
     * @param array<int, array{variant: ProductVariant, match: CandidateMatch, quantity: int}> $matches
     * @return array<int, FulfilmentPlanGroup>
     */
    protected function buildGroups(array $matches): array
    {
        $groupsMap = [];

        foreach ($matches as $row) {
            /** @var ProductVariant $variant */
            $variant = $row['variant'];
            /** @var CandidateMatch $match */
            $match = $row['match'];
            $qty = $row['quantity'];

            // Group key: combination of seller, store, location, and fulfilment type
            $groupKey = sprintf(
                '%d_%s_%s_%s',
                $match->sellerId,
                $match->storeId ?? 'nostore',
                $match->inventoryLocationId,
                $match->fulfilmentType->value
            );

            if (!isset($groupsMap[$groupKey])) {
                $seller = Seller::find($match->sellerId);
                $sellerName = $seller?->business_name ?? 'OneStall Merchant';

                $badge = ($match->fulfilmentType === FulfilmentType::QUICK)
                    ? '⚡ 15-30m Quick Delivery'
                    : '📦 Standard Delivery (2-4 Days)';

                $estDelivery = ($match->fulfilmentType === FulfilmentType::QUICK)
                    ? Carbon::now()->addMinutes($match->estimatedMinutes)
                    : Carbon::now()->addDays((int) ceil($match->estimatedMinutes / 1440));

                $groupsMap[$groupKey] = new FulfilmentPlanGroup(
                    sellerId: $match->sellerId,
                    sellerName: $sellerName,
                    storeId: $match->storeId,
                    storeName: $match->storeName,
                    inventoryLocationId: $match->inventoryLocationId > 0 ? $match->inventoryLocationId : null,
                    inventoryLocationName: $match->locationName,
                    sourceType: $match->sourceType,
                    fulfilmentType: $match->fulfilmentType,
                    deliveryBadge: $badge,
                    distanceKm: $match->distanceKm,
                    estimatedMinutes: $match->estimatedMinutes,
                    estimatedDeliveryAt: $estDelivery,
                    deliveryFee: 0.0,
                    subtotal: 0.0,
                    items: []
                );
            }

            $price = (float) $variant->selling_price;
            $lineSubtotal = round($price * $qty, 2);

            $groupsMap[$groupKey]->items[] = [
                'variant_id' => $variant->id,
                'product_id' => $variant->product_id,
                'title' => $variant->product?->title ?? 'Product',
                'variant_name' => $variant->name,
                'sku' => $variant->sku,
                'quantity' => $qty,
                'price' => $price,
                'subtotal' => $lineSubtotal,
                'inventory_id' => $match->inventoryId,
                'inventory_location_id' => $match->inventoryLocationId,
                'is_quick' => $match->fulfilmentType === FulfilmentType::QUICK,
            ];

            $groupsMap[$groupKey]->subtotal += $lineSubtotal;
        }

        return array_values($groupsMap);
    }
}
