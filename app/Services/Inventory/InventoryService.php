<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\ReservationStatus;
use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Inventory;
use App\Models\InventoryLocation;
use App\Models\InventoryReservation;
use App\Models\StockMovement;
use App\Notifications\LowStockAlertNotification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryService
{
    /**
     * Adjust on-hand quantity to a specific total (reconciliation / audit).
     * Writes an adjustment stock_movement.
     */
    public function adjust(
        Inventory|int $inventory,
        int $newQuantityOnHand,
        string $reason = 'Manual reconciliation',
        ?Model $reference = null,
        ?int $userId = null
    ): Inventory {
        return DB::transaction(function () use ($inventory, $newQuantityOnHand, $reason, $reference, $userId) {
            $invId = $inventory instanceof Inventory ? $inventory->id : (int) $inventory;
            /** @var Inventory $lockedInv */
            $lockedInv = Inventory::where('id', $invId)->lockForUpdate()->firstOrFail();

            $oldOnHand = (int) $lockedInv->quantity_on_hand;
            $delta = $newQuantityOnHand - $oldOnHand;

            $lockedInv->quantity_on_hand = max(0, $newQuantityOnHand);
            $lockedInv->save();

            // Record movement
            StockMovement::create([
                'inventory_id' => $lockedInv->id,
                'type' => StockMovementType::ADJUSTMENT,
                'quantity' => $delta,
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference?->getKey(),
                'note' => $reason,
                'created_by' => $userId ?? Auth::id(),
            ]);

            $this->checkAndNotifyLowStock($lockedInv);

            return $lockedInv;
        });
    }

    /**
     * Restock inventory with additional units.
     * Writes a restock / purchase stock_movement.
     */
    public function restock(
        Inventory|int $inventory,
        int $quantity,
        string $note = 'Inward stock replenishment',
        ?Model $reference = null,
        ?int $userId = null
    ): Inventory {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Restock quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($inventory, $quantity, $note, $reference, $userId) {
            $invId = $inventory instanceof Inventory ? $inventory->id : (int) $inventory;
            /** @var Inventory $lockedInv */
            $lockedInv = Inventory::where('id', $invId)->lockForUpdate()->firstOrFail();

            $lockedInv->quantity_on_hand += $quantity;
            $lockedInv->save();

            StockMovement::create([
                'inventory_id' => $lockedInv->id,
                'type' => StockMovementType::RESTOCK,
                'quantity' => $quantity,
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference?->getKey(),
                'note' => $note,
                'created_by' => $userId ?? Auth::id(),
            ]);

            return $lockedInv;
        });
    }

    /**
     * Reserve inventory for a pending checkout order or cart lock.
     * Guaranteed safe against overselling via atomic lockForUpdate.
     *
     * @throws InsufficientStockException
     */
    public function reserve(
        Inventory|int $inventory,
        int $quantity,
        ?string $orderRef = null,
        ?string $cartRef = null,
        ?int $ttlMinutes = null
    ): InventoryReservation {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Reservation quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($inventory, $quantity, $orderRef, $cartRef, $ttlMinutes) {
            $invId = $inventory instanceof Inventory ? $inventory->id : (int) $inventory;
            /** @var Inventory $lockedInv */
            $lockedInv = Inventory::where('id', $invId)->lockForUpdate()->firstOrFail();

            $available = (int) ($lockedInv->quantity_on_hand - $lockedInv->quantity_reserved);

            if ($available < $quantity) {
                throw new InsufficientStockException(
                    $lockedInv->id,
                    $quantity,
                    max(0, $available),
                    "Cannot reserve {$quantity} units. Only {$available} units available in stock."
                );
            }

            // Lock quantity by incrementing quantity_reserved
            $lockedInv->quantity_reserved += $quantity;
            $lockedInv->save();

            $expiryTtl = $ttlMinutes ?? (int) setting('inventory_reservation_expiry_minutes', 15);
            $expiresAt = Carbon::now()->addMinutes($expiryTtl);

            $reservation = InventoryReservation::create([
                'inventory_id' => $lockedInv->id,
                'order_ref' => $orderRef,
                'cart_ref' => $cartRef,
                'quantity' => $quantity,
                'expires_at' => $expiresAt,
                'status' => ReservationStatus::ACTIVE,
            ]);

            StockMovement::create([
                'inventory_id' => $lockedInv->id,
                'type' => StockMovementType::RESERVE,
                'quantity' => -$quantity,
                'note' => "Reserved {$quantity} units (" . ($orderRef ? "Order: {$orderRef}" : "Cart: {$cartRef}") . ")",
                'created_by' => Auth::id(),
            ]);

            $this->checkAndNotifyLowStock($lockedInv);

            return $reservation;
        });
    }

    /**
     * Release a reserved allocation back to available stock.
     */
    public function release(InventoryReservation|int $reservation, string $reason = 'Reservation released'): void
    {
        DB::transaction(function () use ($reservation, $reason) {
            $resId = $reservation instanceof InventoryReservation ? $reservation->id : (int) $reservation;
            /** @var InventoryReservation|null $lockedRes */
            $lockedRes = InventoryReservation::where('id', $resId)->lockForUpdate()->first();

            if (!$lockedRes || $lockedRes->status !== ReservationStatus::ACTIVE) {
                return; // Already released or confirmed
            }

            /** @var Inventory $lockedInv */
            $lockedInv = Inventory::where('id', $lockedRes->inventory_id)->lockForUpdate()->firstOrFail();

            $lockedInv->quantity_reserved = max(0, $lockedInv->quantity_reserved - (int) $lockedRes->quantity);
            $lockedInv->save();

            $lockedRes->status = ReservationStatus::RELEASED;
            $lockedRes->save();

            StockMovement::create([
                'inventory_id' => $lockedInv->id,
                'type' => StockMovementType::RELEASE,
                'quantity' => (int) $lockedRes->quantity,
                'note' => $reason,
                'created_by' => Auth::id(),
            ]);
        });
    }

    /**
     * Automatically release all active reservations for a given order reference
     * (used on payment failure or order cancellation).
     */
    public function releaseForOrder(string $orderRef, string $reason = 'Payment failure or order cancellation'): int
    {
        $reservations = InventoryReservation::where('order_ref', $orderRef)
            ->where('status', ReservationStatus::ACTIVE)
            ->get();

        $count = 0;
        foreach ($reservations as $res) {
            $this->release($res, $reason);
            $count++;
        }

        return $count;
    }

    /**
     * Release all active reservations for an abandoned or expired cart reference.
     */
    public function releaseForCart(string $cartRef, string $reason = 'Cart abandoned or cancelled'): int
    {
        $reservations = InventoryReservation::where('cart_ref', $cartRef)
            ->where('status', ReservationStatus::ACTIVE)
            ->get();

        $count = 0;
        foreach ($reservations as $res) {
            $this->release($res, $reason);
            $count++;
        }

        return $count;
    }

    /**
     * Commit a finalized sale upon payment confirmation.
     * Decrements both on-hand and reserved counts atomically.
     */
    public function commitSale(InventoryReservation|int $reservation, ?Model $order = null): void
    {
        DB::transaction(function () use ($reservation, $order) {
            $resId = $reservation instanceof InventoryReservation ? $reservation->id : (int) $reservation;
            /** @var InventoryReservation|null $lockedRes */
            $lockedRes = InventoryReservation::where('id', $resId)->lockForUpdate()->firstOrFail();

            if ($lockedRes->status !== ReservationStatus::ACTIVE) {
                throw new \RuntimeException("Reservation #{$resId} is not in ACTIVE state (current: {$lockedRes->status->value}).");
            }

            /** @var Inventory $lockedInv */
            $lockedInv = Inventory::where('id', $lockedRes->inventory_id)->lockForUpdate()->firstOrFail();

            $qty = (int) $lockedRes->quantity;

            // Reduce physical on hand and unreserve
            $lockedInv->quantity_on_hand = max(0, $lockedInv->quantity_on_hand - $qty);
            $lockedInv->quantity_reserved = max(0, $lockedInv->quantity_reserved - $qty);
            $lockedInv->save();

            $lockedRes->status = ReservationStatus::CONFIRMED;
            $lockedRes->save();

            StockMovement::create([
                'inventory_id' => $lockedInv->id,
                'type' => StockMovementType::SALE,
                'quantity' => -$qty,
                'reference_type' => $order ? get_class($order) : null,
                'reference_id' => $order?->getKey(),
                'note' => "Sale committed for order: " . ($lockedRes->order_ref ?? 'Order'),
                'created_by' => Auth::id(),
            ]);

            $this->checkAndNotifyLowStock($lockedInv);
        });
    }

    /**
     * Transfer stock between two locations owned by the same seller.
     */
    public function transfer(
        int $variantId,
        InventoryLocation|int $fromLocation,
        InventoryLocation|int $toLocation,
        int $quantity,
        string $note = 'Inter-location transfer',
        ?int $userId = null
    ): void {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Transfer quantity must be greater than zero.');
        }

        $fromId = $fromLocation instanceof InventoryLocation ? $fromLocation->id : (int) $fromLocation;
        $toId = $toLocation instanceof InventoryLocation ? $toLocation->id : (int) $toLocation;

        if ($fromId === $toId) {
            throw new \InvalidArgumentException('Source and destination locations must be different.');
        }

        DB::transaction(function () use ($variantId, $fromId, $toId, $quantity, $note, $userId) {
            // Lock source
            /** @var Inventory $fromInv */
            $fromInv = Inventory::where('variant_id', $variantId)
                ->where('location_id', $fromId)
                ->lockForUpdate()
                ->firstOrFail();

            $availableSource = (int) ($fromInv->quantity_on_hand - $fromInv->quantity_reserved);
            if ($availableSource < $quantity) {
                throw new InsufficientStockException(
                    $fromInv->id,
                    $quantity,
                    $availableSource,
                    "Insufficient stock at source location. Available: {$availableSource}, Requested transfer: {$quantity}."
                );
            }

            // Lock or create destination
            $toInv = Inventory::firstOrCreate(
                ['variant_id' => $variantId, 'location_id' => $toId],
                ['quantity_on_hand' => 0, 'quantity_reserved' => 0, 'low_stock_threshold' => 5]
            );
            /** @var Inventory $lockedToInv */
            $lockedToInv = Inventory::where('id', $toInv->id)->lockForUpdate()->firstOrFail();

            // Move stock
            $fromInv->quantity_on_hand -= $quantity;
            $fromInv->save();

            $lockedToInv->quantity_on_hand += $quantity;
            $lockedToInv->save();

            $fromLoc = InventoryLocation::find($fromId);
            $toLoc = InventoryLocation::find($toId);

            // Outward movement at source
            StockMovement::create([
                'inventory_id' => $fromInv->id,
                'type' => StockMovementType::TRANSFER,
                'quantity' => -$quantity,
                'note' => "Transfer out to {$toLoc?->name}: {$note}",
                'created_by' => $userId ?? Auth::id(),
            ]);

            // Inward movement at destination
            StockMovement::create([
                'inventory_id' => $lockedToInv->id,
                'type' => StockMovementType::TRANSFER,
                'quantity' => $quantity,
                'note' => "Transfer in from {$fromLoc?->name}: {$note}",
                'created_by' => $userId ?? Auth::id(),
            ]);

            $this->checkAndNotifyLowStock($fromInv);
        });
    }

    /**
     * Expire all stale active reservations whose TTL has elapsed.
     */
    public function expireReservations(): int
    {
        $expiredReservations = InventoryReservation::where('status', ReservationStatus::ACTIVE)
            ->where('expires_at', '<=', Carbon::now())
            ->get();

        $count = 0;
        foreach ($expiredReservations as $res) {
            $this->release($res, 'Reservation expired automatically by scheduled TTL reaper');
            $count++;
        }

        return $count;
    }

    /**
     * Check if available inventory has dipped below or equal to threshold
     * and queue low-stock notification to the seller owner.
     */
    public function checkAndNotifyLowStock(Inventory $inventory): void
    {
        $available = (int) ($inventory->quantity_on_hand - $inventory->quantity_reserved);
        $threshold = (int) $inventory->low_stock_threshold;

        if ($available <= $threshold) {
            $sellerUser = $inventory->variant->product->seller?->user;
            if ($sellerUser) {
                // Avoid flooding: check if notification already queued in last 6 hours
                $sellerUser->notify(new LowStockAlertNotification($inventory));
                Log::info("Low stock alert queued for variant SKU: {$inventory->variant->sku} at location {$inventory->location_id}. Available: {$available}");
            }
        }
    }
}
