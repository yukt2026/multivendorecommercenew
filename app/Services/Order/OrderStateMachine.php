<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Enums\FulfilmentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\FulfilmentOrder;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Notifications\OrderStatusUpdatedNotification;
use App\Services\Commission\CommissionService;
use App\Services\Fulfilment\FulfilmentRerouteService;
use App\Services\Inventory\InventoryService;
use App\Services\Order\Exceptions\IllegalOrderTransitionException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderStateMachine
{
    /**
     * Allowed transitions for Quick Commerce Fulfilment Orders.
     *
     * @var array<string, array<string>>
     */
    protected const QUICK_ALLOWED_TRANSITIONS = [
        'placed' => ['store_accepted', 'rejected', 'cancelled'],
        'store_accepted' => ['packing', 'cancelled', 'rejected'],
        'packing' => ['ready', 'cancelled'],
        'ready' => ['rider_assigned', 'picked_up', 'cancelled'],
        'rider_assigned' => ['picked_up', 'cancelled'],
        'picked_up' => ['out_for_delivery'],
        'out_for_delivery' => ['delivered', 'cancelled'],
        'delivered' => [],
        'cancelled' => [],
        'rejected' => ['placed'], // Allow re-queue on auto-reroute
    ];

    /**
     * Allowed transitions for Standard Online / OneStall Cargo Fulfilment Orders.
     *
     * @var array<string, array<string>>
     */
    protected const STANDARD_ALLOWED_TRANSITIONS = [
        'placed' => ['confirmed', 'cancelled'],
        'confirmed' => ['packed', 'cancelled'],
        'packed' => ['ready_to_ship', 'cancelled'],
        'ready_to_ship' => ['picked_up', 'cancelled'],
        'picked_up' => ['in_transit'],
        'in_transit' => ['out_for_delivery'],
        'out_for_delivery' => ['delivered', 'cancelled'],
        'delivered' => ['returned'],
        'returned' => [],
        'cancelled' => [],
    ];

    /**
     * Allowed transitions for Parent Orders.
     *
     * @var array<string, array<string>>
     */
    protected const PARENT_ALLOWED_TRANSITIONS = [
        'pending' => ['placed', 'cancelled'],
        'placed' => ['processing', 'cancelled'],
        'confirmed' => ['processing', 'cancelled'],
        'processing' => ['ready_for_pickup', 'out_for_delivery', 'delivered', 'cancelled'],
        'ready_for_pickup' => ['delivered', 'cancelled'],
        'out_for_delivery' => ['delivered', 'cancelled'],
        'delivered' => ['refunded'],
        'cancelled' => [],
        'refunded' => [],
    ];

    public function __construct(
        protected InventoryService $inventoryService,
        protected FulfilmentRerouteService $rerouteService,
        protected CommissionService $commissionService
    ) {}

    /**
     * Check if a FulfilmentOrder can transition to the given status.
     */
    public function canTransitionFulfilment(FulfilmentOrder $fo, string $targetStatus): bool
    {
        $current = strtolower($fo->status ?? 'placed');
        $target = strtolower($targetStatus);

        if ($current === $target) {
            return true;
        }

        $matrix = $fo->fulfilment_type === FulfilmentType::QUICK
            ? self::QUICK_ALLOWED_TRANSITIONS
            : self::STANDARD_ALLOWED_TRANSITIONS;

        return in_array($target, $matrix[$current] ?? [], true);
    }

    /**
     * Check if a Parent Order can transition to the given status.
     */
    public function canTransitionOrder(Order $order, OrderStatus|string $targetStatus): bool
    {
        $current = $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status;
        $target = $targetStatus instanceof OrderStatus ? $targetStatus->value : (string) $targetStatus;

        if ($current === $target) {
            return true;
        }

        return in_array($target, self::PARENT_ALLOWED_TRANSITIONS[$current] ?? [], true);
    }

    /**
     * Get allowed next statuses for a FulfilmentOrder.
     *
     * @return array<string>
     */
    public function getAllowedNextFulfilmentStatuses(FulfilmentOrder $fo): array
    {
        $current = strtolower($fo->status ?? 'placed');
        $matrix = $fo->fulfilment_type === FulfilmentType::QUICK
            ? self::QUICK_ALLOWED_TRANSITIONS
            : self::STANDARD_ALLOWED_TRANSITIONS;

        return $matrix[$current] ?? [];
    }

    /**
     * Transition a FulfilmentOrder to a new status with validation, audit logging, and parent reconciliation.
     *
     * @throws IllegalOrderTransitionException
     */
    public function transitionFulfilmentOrder(
        FulfilmentOrder $fo,
        string $newStatus,
        ?string $note = null,
        ?int $userId = null
    ): FulfilmentOrder {
        $fromStatus = strtolower($fo->status ?? 'placed');
        $toStatus = strtolower($newStatus);

        if ($fromStatus === $toStatus) {
            return $fo;
        }

        if (!$this->canTransitionFulfilment($fo, $toStatus)) {
            throw new IllegalOrderTransitionException(
                'FulfilmentOrder',
                $fo->id,
                $fromStatus,
                $toStatus,
                "Cannot transition package #{$fo->fulfilment_order_number} ({$fo->fulfilment_type->value}) from '{$fromStatus}' to '{$toStatus}'."
            );
        }

        return DB::transaction(function () use ($fo, $fromStatus, $toStatus, $note, $userId) {
            $fo->status = $toStatus;

            if ($toStatus === 'delivered') {
                $fo->delivered_at = now();
            } elseif ($toStatus === 'cancelled') {
                $fo->cancelled_at = now();
            } elseif ($toStatus === 'store_accepted') {
                $fo->accepted_at = now();
            } elseif ($toStatus === 'packing') {
                $fo->packed_at = now();
            } elseif ($toStatus === 'ready') {
                $fo->ready_at = now();
            }

            if ($fo->fulfilment_type === FulfilmentType::QUICK && empty($fo->delivery_otp)) {
                $fo->delivery_otp = sprintf('%06d', mt_rand(100000, 999999));
            }

            $fo->save();

            // 1. Audit History
            OrderStatusHistory::create([
                'orderable_type' => FulfilmentOrder::class,
                'orderable_id' => $fo->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'note' => $note ?: "Package status updated to " . ucwords(str_replace('_', ' ', $toStatus)),
                'changed_by' => $userId ?? Auth::id(),
            ]);

            // 2. Handle cancellation stock release
            if ($toStatus === 'cancelled' && $fo->order) {
                $this->inventoryService->releaseForOrder(
                    $fo->order->order_number,
                    "Fulfilment package #{$fo->fulfilment_order_number} cancelled"
                );
            }

            // 3. Handle quick order rejection with auto-reroute hook
            if ($toStatus === 'rejected') {
                $this->rerouteService->attemptAutoReroute($fo, $note ?: 'Store rejected order');
            }

            // 4. Commission ledger hook on delivery or refund
            if ($toStatus === 'delivered') {
                $this->commissionService->recordForDeliveredFulfilmentOrder($fo);
            } elseif ($toStatus === 'refunded') {
                $this->commissionService->recordReversal($fo, (float) $fo->total_amount, $note ?: 'Package refunded');
            }

            // 5. Auto-trigger OneStall Cargo shipment for standard delivery
            if ($toStatus === 'ready_to_ship' && $fo->fulfilment_type !== FulfilmentType::QUICK) {
                \App\Jobs\Cargo\CreateShipmentJob::dispatch($fo);
            }

            // 5b. Quick commerce: Auto-dispatch nearest available rider when order is ready
            if ($toStatus === 'ready' && $fo->fulfilment_type === FulfilmentType::QUICK) {
                if ($fo->store?->quick_auto_assign_rider ?? true) {
                    try {
                        app(\App\Services\QuickCommerce\QuickCommerceService::class)->autoAssignRider($fo);
                    } catch (\Throwable $e) {
                        Log::warning("Quick commerce auto rider assignment notice for FO #{$fo->id}: {$e->getMessage()}");
                    }
                }
            }

            // 5c. Quick commerce: Free delivery partner if delivered
            if ($toStatus === 'delivered' && $fo->deliveryAssignment) {
                $assignment = $fo->deliveryAssignment;
                $assignment->status = \App\Enums\DeliveryAssignmentStatus::DELIVERED;
                $assignment->delivered_at = now();
                $assignment->save();

                if ($assignment->deliveryPartner) {
                    $partner = $assignment->deliveryPartner;
                    $partner->active_orders_count = max(0, $partner->active_orders_count - 1);
                    if ($partner->active_orders_count === 0) {
                        $partner->status = 'available';
                    }
                    $partner->save();
                }
            }

            // 6. Reconcile Parent Order
            if ($fo->order) {
                $this->reconcileParentOrder($fo->order, $userId);
            }

            Log::info("FulfilmentOrder #{$fo->id} transitioned from '{$fromStatus}' to '{$toStatus}'");

            return $fo;
        });
    }

    /**
     * Transition a Parent Order to a new status.
     *
     * @throws IllegalOrderTransitionException
     */
    public function transitionOrder(
        Order $order,
        OrderStatus|string $newStatus,
        ?string $note = null,
        ?int $userId = null
    ): Order {
        $fromStatus = $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status;
        $targetEnum = $newStatus instanceof OrderStatus ? $newStatus : OrderStatus::from((string) $newStatus);
        $toStatus = $targetEnum->value;

        if ($fromStatus === $toStatus) {
            return $order;
        }

        if (!$this->canTransitionOrder($order, $toStatus)) {
            throw new IllegalOrderTransitionException(
                'Order',
                $order->id,
                $fromStatus,
                $toStatus,
                "Cannot transition order #{$order->order_number} from '{$fromStatus}' to '{$toStatus}'."
            );
        }

        return DB::transaction(function () use ($order, $fromStatus, $toStatus, $targetEnum, $note, $userId) {
            $order->status = $targetEnum;
            $order->save();

            OrderStatusHistory::create([
                'orderable_type' => Order::class,
                'orderable_id' => $order->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'note' => $note ?: "Order status updated to {$targetEnum->label()}",
                'changed_by' => $userId ?? Auth::id(),
            ]);

            // Send notification to customer
            if ($order->customer?->user) {
                try {
                    $order->customer->user->notify(new OrderStatusUpdatedNotification($order, $fromStatus, $toStatus, $note));
                } catch (\Throwable $e) {
                    Log::warning("Could not dispatch OrderStatusUpdatedNotification for order #{$order->id}: {$e->getMessage()}");
                }
            }

            Log::info("Order #{$order->id} [{$order->order_number}] transitioned from '{$fromStatus}' to '{$toStatus}'");

            return $order;
        });
    }

    /**
     * Synchronize and reconcile the parent Order status based on all its fulfilment orders.
     */
    public function reconcileParentOrder(Order $order, ?int $userId = null): void
    {
        $fulfilments = $order->fulfilmentOrders()->get();
        if ($fulfilments->isEmpty()) {
            return;
        }

        $statuses = $fulfilments->pluck('status')->map(fn ($s) => strtolower((string) $s))->all();

        // 1. If all fulfilment packages are delivered, parent order is DELIVERED
        $allDelivered = !empty($statuses) && count(array_filter($statuses, fn ($s) => $s === 'delivered')) === count($statuses);
        if ($allDelivered && $order->status !== OrderStatus::DELIVERED) {
            $this->transitionOrder($order, OrderStatus::DELIVERED, 'All packages delivered successfully to customer.', $userId);
            return;
        }

        // 2. If all fulfilment packages are cancelled, parent order is CANCELLED
        $allCancelled = !empty($statuses) && count(array_filter($statuses, fn ($s) => in_array($s, ['cancelled', 'rejected'], true))) === count($statuses);
        if ($allCancelled && $order->status !== OrderStatus::CANCELLED) {
            $this->transitionOrder($order, OrderStatus::CANCELLED, 'All fulfilment packages were cancelled.', $userId);
            return;
        }

        // 3. If any package is out for delivery
        $anyOut = in_array('out_for_delivery', $statuses, true);
        if ($anyOut && $order->status !== OrderStatus::OUT_FOR_DELIVERY && $order->status !== OrderStatus::DELIVERED) {
            $this->transitionOrder($order, OrderStatus::OUT_FOR_DELIVERY, 'One or more packages are out for delivery.', $userId);
            return;
        }

        // 4. If any package is in transit or packed or accepted, order is PROCESSING
        $inProgress = array_intersect($statuses, ['store_accepted', 'packing', 'ready', 'rider_assigned', 'picked_up', 'confirmed', 'packed', 'ready_to_ship', 'in_transit']);
        if (!empty($inProgress) && $order->status === OrderStatus::PLACED) {
            $this->transitionOrder($order, OrderStatus::PROCESSING, 'Fulfilment started on order packages.', $userId);
        }
    }

    /**
     * Customer or admin cancels order if allowed (i.e. not yet delivered or out for delivery).
     */
    public function cancelOrder(Order $order, string $reason, ?int $userId = null): Order
    {
        if (in_array($order->status, [OrderStatus::DELIVERED, OrderStatus::CANCELLED, OrderStatus::REFUNDED], true)) {
            throw new IllegalOrderTransitionException(
                'Order',
                $order->id,
                $order->status->value,
                'cancelled',
                "Order #{$order->order_number} cannot be cancelled because it is already {$order->status->label()}."
            );
        }

        return DB::transaction(function () use ($order, $reason, $userId) {
            // Cancel all child packages
            foreach ($order->fulfilmentOrders as $fo) {
                if (!in_array($fo->status, ['delivered', 'cancelled'], true)) {
                    $fo->status = 'cancelled';
                    $fo->cancelled_at = now();
                    $fo->save();

                    OrderStatusHistory::create([
                        'orderable_type' => FulfilmentOrder::class,
                        'orderable_id' => $fo->id,
                        'from_status' => (string) $fo->getOriginal('status'),
                        'to_status' => 'cancelled',
                        'note' => "Package cancelled: {$reason}",
                        'changed_by' => $userId ?? Auth::id(),
                    ]);
                }
            }

            // Release all active inventory reservations
            $this->inventoryService->releaseForOrder($order->order_number, "Order cancelled by user: {$reason}");

            // Update Parent Order
            return $this->transitionOrder($order, OrderStatus::CANCELLED, $reason, $userId);
        });
    }

    /**
     * Admin Force Override Order Status with mandatory audit reason.
     */
    public function adminOverride(Order $order, OrderStatus $newStatus, string $reason, int $adminUserId): Order
    {
        return DB::transaction(function () use ($order, $newStatus, $reason, $adminUserId) {
            $fromStatus = $order->status->value;
            $order->status = $newStatus;
            $order->save();

            OrderStatusHistory::create([
                'orderable_type' => Order::class,
                'orderable_id' => $order->id,
                'from_status' => $fromStatus,
                'to_status' => $newStatus->value,
                'note' => "[ADMIN OVERRIDE]: {$reason}",
                'changed_by' => $adminUserId,
            ]);

            Log::warning("Order #{$order->id} status OVERRIDDEN by Admin #{$adminUserId} to {$newStatus->value}. Reason: {$reason}");

            return $order;
        });
    }
}
