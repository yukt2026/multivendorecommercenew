<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Enums\PaymentStatus;
use App\Enums\QCStatus;
use App\Enums\ReturnStatus;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Notifications\ReturnStatusUpdatedNotification;
use App\Services\Cargo\CargoService;
use App\Services\Commission\CommissionService;
use App\Services\Inventory\InventoryService;
use App\Services\Payment\PaymentService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReturnService
{
    public function __construct(
        protected CargoService $cargoService,
        protected PaymentService $paymentService,
        protected CommissionService $commissionService,
        protected InventoryService $inventoryService
    ) {}

    /**
     * Check if a specific order item is eligible for customer return.
     *
     * @return array{allowed: bool, reason: ?string, window_days: int, expires_at: ?Carbon}
     */
    public function canRequestReturn(OrderItem $item): array
    {
        $fo = $item->fulfilmentOrder;
        $order = $fo?->order;

        if (!$fo || !$order) {
            return [
                'allowed' => false,
                'reason' => 'Fulfilment order record not found.',
                'window_days' => 0,
                'expires_at' => null,
            ];
        }

        // Must be delivered
        $isDelivered = ($fo->delivered_at !== null) || (strtolower((string) $fo->status) === 'delivered');
        if (!$isDelivered) {
            return [
                'allowed' => false,
                'reason' => 'Returns can only be requested once the package has been delivered.',
                'window_days' => 0,
                'expires_at' => null,
            ];
        }

        // Product returnable rule
        $product = $item->product ?? $item->variant?->product;
        if ($product && !$product->is_returnable) {
            return [
                'allowed' => false,
                'reason' => 'This product is marked as non-returnable under its policy.',
                'window_days' => 0,
                'expires_at' => null,
            ];
        }

        // Return window calculation (Product > Category > Setting > Default 7 days)
        $windowDays = $product?->return_window_days 
            ?? (int) Setting::get('default_return_window_days', 7);

        $deliveredAt = $fo->delivered_at ? Carbon::parse($fo->delivered_at) : ($fo->updated_at ?? now());
        $expiresAt = $deliveredAt->copy()->addDays($windowDays);

        if (now()->isAfter($expiresAt)) {
            return [
                'allowed' => false,
                'reason' => "The return window of {$windowDays} days expired on " . $expiresAt->format('d M Y') . ".",
                'window_days' => $windowDays,
                'expires_at' => $expiresAt,
            ];
        }

        // Check if an active return already exists for this item
        $existing = OrderReturn::where('order_item_id', $item->id)
            ->whereNotIn('status', [ReturnStatus::REJECTED, ReturnStatus::CANCELLED])
            ->first();

        if ($existing) {
            return [
                'allowed' => false,
                'reason' => "A return request (#{$existing->return_number}) is already in progress for this item.",
                'window_days' => $windowDays,
                'expires_at' => $expiresAt,
            ];
        }

        return [
            'allowed' => true,
            'reason' => null,
            'window_days' => $windowDays,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Check if a specific item can be cancelled before delivery.
     *
     * @return array{allowed: bool, reason: ?string}
     */
    public function canCancelItem(OrderItem $item): array
    {
        $fo = $item->fulfilmentOrder;
        if (!$fo) {
            return ['allowed' => false, 'reason' => 'Item package record missing.'];
        }

        $nonCancellableStatuses = ['shipped', 'out_for_delivery', 'delivered', 'cancelled'];
        if (in_array(strtolower((string) $fo->status), $nonCancellableStatuses, true)) {
            return ['allowed' => false, 'reason' => 'Item package is already shipped, delivered, or cancelled.'];
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Customer requests a return for a delivered order item.
     *
     * @param array{reason: string, customer_notes?: ?string, photos?: array, refund_mode?: string} $data
     */
    public function requestReturn(OrderItem $item, array $data, ?User $customerUser = null): OrderReturn
    {
        $eligibility = $this->canRequestReturn($item);
        if (!$eligibility['allowed']) {
            throw new \InvalidArgumentException($eligibility['reason'] ?? 'Return is not allowed for this item.');
        }

        // Store photos if uploaded files provided
        $photoPaths = [];
        if (!empty($data['photos'])) {
            foreach ($data['photos'] as $photo) {
                if ($photo instanceof UploadedFile) {
                    $path = $photo->store('returns/' . date('Ym'), 'public');
                    $photoPaths[] = Storage::disk('public')->url($path);
                } elseif (is_string($photo)) {
                    $photoPaths[] = $photo;
                }
            }
        }

        $refundAmount = (float) ($item->total ?? ($item->price * $item->qty));
        $returnNumber = 'RET-' . date('Ymd') . '-' . strtoupper(Str::random(6));

        $orderReturn = OrderReturn::create([
            'return_number' => $returnNumber,
            'type' => 'return',
            'order_item_id' => $item->id,
            'reason' => $data['reason'],
            'customer_notes' => $data['customer_notes'] ?? null,
            'photos' => $photoPaths,
            'status' => ReturnStatus::REQUESTED,
            'qc_status' => QCStatus::PENDING,
            'refund_mode' => $data['refund_mode'] ?? 'gateway',
            'refund_amount' => $refundAmount,
        ]);

        $this->notifyParties($orderReturn, 'Return request received. Pending seller review.');

        if (function_exists('activity')) {
            activity('returns')
                ->performedOn($orderReturn)
                ->causedBy($customerUser ?? auth()->user())
                ->log("Return #{$orderReturn->return_number} submitted for '{$item->title}'");
        }

        return $orderReturn;
    }

    /**
     * Seller or Admin approves return request and schedules reverse pickup.
     */
    public function approve(OrderReturn $return, ?string $sellerNotes = null, ?User $actor = null, bool $autoSchedulePickup = true): OrderReturn
    {
        if ($return->status !== ReturnStatus::REQUESTED) {
            throw new \InvalidArgumentException("Return is not in REQUESTED status (current: {$return->status->value}).");
        }

        $return->status = ReturnStatus::APPROVED;
        $return->seller_notes = $sellerNotes ?? $return->seller_notes;
        $return->approved_at = now();
        $return->save();

        if ($autoSchedulePickup) {
            $this->scheduleReversePickup($return, $actor);
        } else {
            $this->notifyParties($return, 'Return request approved. Reverse pickup will be arranged shortly.');
        }

        if (function_exists('activity')) {
            activity('returns')
                ->performedOn($return)
                ->causedBy($actor ?? auth()->user())
                ->log("Return #{$return->return_number} approved");
        }

        return $return;
    }

    /**
     * Seller or Admin rejects return request with mandatory remarks.
     */
    public function reject(OrderReturn $return, string $reason, ?User $actor = null): OrderReturn
    {
        if ($return->status->isTerminal()) {
            throw new \InvalidArgumentException("Cannot reject return in terminal state ({$return->status->value}).");
        }

        $return->status = ReturnStatus::REJECTED;
        $return->rejection_reason = $reason;
        $return->seller_notes = $return->seller_notes ?? $reason;
        $return->save();

        $this->notifyParties($return, "Return request was rejected. Reason: {$reason}");

        if (function_exists('activity')) {
            activity('returns')
                ->performedOn($return)
                ->causedBy($actor ?? auth()->user())
                ->log("Return #{$return->return_number} rejected. Reason: {$reason}");
        }

        return $return;
    }

    /**
     * Schedule reverse pickup consignment through Cargo service.
     */
    public function scheduleReversePickup(OrderReturn $return, ?User $actor = null): OrderReturn
    {
        $cargoResult = $this->cargoService->scheduleReversePickup($return);

        $return->pickup_waybill = $cargoResult['waybill'] ?? $cargoResult['tracking_number'] ?? null;
        $return->status = ReturnStatus::REVERSE_PICKUP_SCHEDULED;
        $return->save();

        $this->notifyParties($return, "Reverse pickup scheduled with courier. Waybill: {$return->pickup_waybill}");

        if (function_exists('activity')) {
            activity('returns')
                ->performedOn($return)
                ->causedBy($actor ?? auth()->user())
                ->log("Reverse pickup scheduled for Return #{$return->return_number}. Waybill: {$return->pickup_waybill}");
        }

        return $return;
    }

    /**
     * Courier marks package picked up from customer.
     */
    public function markPickedUp(OrderReturn $return, ?User $actor = null): OrderReturn
    {
        $return->status = ReturnStatus::PICKED_UP;
        $return->picked_up_at = now();
        $return->save();

        $this->notifyParties($return, "Item has been picked up by the logistics carrier.");

        return $return;
    }

    /**
     * Warehouse/Merchant receives returned item.
     */
    public function markReceived(OrderReturn $return, ?User $actor = null): OrderReturn
    {
        $return->status = ReturnStatus::RECEIVED;
        $return->received_at = now();
        $return->save();

        $this->notifyParties($return, "Returned package has arrived at the inspection facility.");

        return $return;
    }

    /**
     * Record Quality Control (QC) inspection outcome.
     */
    public function recordQcOutcome(
        OrderReturn $return,
        string $outcome, // 'passed' or 'failed'
        ?string $qcNotes = null,
        bool $isResellable = true,
        ?User $actor = null
    ): OrderReturn {
        $outcome = strtolower($outcome);
        if (!in_array($outcome, ['passed', 'failed'], true)) {
            throw new \InvalidArgumentException("QC outcome must be 'passed' or 'failed'.");
        }

        $return->qc_notes = $qcNotes;
        $return->is_resellable = $isResellable;
        $return->qc_completed_at = now();

        if ($outcome === 'passed') {
            $return->qc_status = QCStatus::PASSED;
            $return->status = ReturnStatus::QC_PASSED;
            $return->save();

            $this->notifyParties($return, "Item successfully passed quality inspection. Refund processing initiated.");
        } else {
            $return->qc_status = QCStatus::FAILED;
            $return->status = ReturnStatus::QC_FAILED;
            $return->rejection_reason = $qcNotes ?? 'Item failed quality verification';
            $return->save();

            $this->notifyParties($return, "Item did not pass quality verification. Note: {$return->qc_notes}");
        }

        if (function_exists('activity')) {
            activity('returns')
                ->performedOn($return)
                ->causedBy($actor ?? auth()->user())
                ->log("QC recorded for Return #{$return->return_number}: {$outcome}");
        }

        return $return;
    }

    /**
     * Process finalized financial refund, commission reversal, and inventory restock.
     */
    public function processRefund(OrderReturn $return, ?float $amountOverride = null, ?User $actor = null): OrderReturn
    {
        return DB::transaction(function () use ($return, $amountOverride, $actor) {
            $refundAmount = $amountOverride ?? (float) ($return->refund_amount ?? $return->orderItem->total);
            $item = $return->orderItem;
            $fo = $item?->fulfilmentOrder;
            $order = $fo?->order;

            // 1. Payment Gateway Refund OR Wallet Store Credit
            if ($return->refund_mode === 'wallet') {
                $user = $order?->user;
                if ($user) {
                    $newBalance = (float) $user->wallet_balance + $refundAmount;
                    $user->wallet_balance = $newBalance;
                    $user->save();

                    WalletTransaction::create([
                        'user_id' => $user->id,
                        'type' => 'credit',
                        'amount' => $refundAmount,
                        'balance_after' => $newBalance,
                        'reference_type' => OrderReturn::class,
                        'reference_id' => $return->id,
                        'description' => "Store credit for Return #{$return->return_number}",
                    ]);

                    Log::info("Wallet credited ₹{$refundAmount} for User #{$user->id} (Return #{$return->return_number})");
                }
            } else {
                // PaymentGateway refund
                $payment = $order?->payments()
                    ->where('status', PaymentStatus::PAID)
                    ->latest('id')
                    ->first() ?? $order?->payment;

                if ($payment && $payment->gateway !== 'cod') {
                    $refund = $this->paymentService->processRefund(
                        $payment,
                        $refundAmount,
                        "Return #{$return->return_number}: {$item?->title}",
                        $fo?->id
                    );
                    $return->refund_id = $refund->id;
                } else {
                    // COD or direct record refund ledger
                    $refund = Refund::create([
                        'payment_id' => $payment?->id ?? 1,
                        'fulfilment_order_id' => $fo?->id,
                        'amount' => $refundAmount,
                        'reason' => "Return #{$return->return_number} (COD/Cash Refund)",
                        'status' => \App\Enums\RefundStatus::PROCESSED,
                        'processed_at' => now(),
                    ]);
                    $return->refund_id = $refund->id;
                }
            }

            // 2. Reverse Platform Commission
            if ($fo) {
                try {
                    $this->commissionService->recordReversal(
                        $fo,
                        $refundAmount,
                        "Return #{$return->return_number}: {$item?->title}"
                    );
                } catch (\Throwable $e) {
                    Log::warning("Commission reversal failed for Return #{$return->return_number}: " . $e->getMessage());
                }
            }

            // 3. Restore Stock if resellable
            if ($return->is_resellable && $item && $item->variant_id) {
                try {
                    $locationId = $fo?->inventory_location_id ?? $fo?->store?->location?->id ?? 1;
                    $inventory = Inventory::where('variant_id', $item->variant_id)
                        ->where('location_id', $locationId)
                        ->first() 
                        ?? Inventory::where('variant_id', $item->variant_id)->first();

                    if ($inventory) {
                        $this->inventoryService->restock(
                            $inventory,
                            (int) $item->qty,
                            "Restock from Return #{$return->return_number}",
                            $return,
                            $actor?->id
                        );
                    }
                } catch (\Throwable $e) {
                    Log::warning("Inventory restock failed for Return #{$return->return_number}: " . $e->getMessage());
                }
            }

            // Update return status
            $return->status = ReturnStatus::REFUNDED;
            $return->refund_amount = $refundAmount;
            $return->refunded_at = now();
            $return->save();

            $this->notifyParties($return, "Refund of ₹{$refundAmount} has been issued successfully via {$return->refund_mode}.");

            if (function_exists('activity')) {
                activity('returns')
                    ->performedOn($return)
                    ->causedBy($actor ?? auth()->user())
                    ->log("Return #{$return->return_number} refunded. Amount: ₹{$refundAmount}");
            }

            return $return;
        });
    }

    /**
     * Admin Dispute Override & Force Actions.
     */
    public function adminOverride(OrderReturn $return, string $action, array $params = [], ?User $admin = null): OrderReturn
    {
        $admin = $admin ?? auth()->user();
        $notes = $params['notes'] ?? 'Administrative intervention and dispute override';
        $return->admin_notes = $notes;
        $return->save();

        if ($action === 'force_refund') {
            $return->is_resellable = (bool) ($params['is_resellable'] ?? false);
            return $this->processRefund($return, $params['amount'] ?? null, $admin);
        }

        if ($action === 'approve') {
            $return->status = ReturnStatus::REQUESTED; // Reset for approval transition
            return $this->approve($return, "Admin approved: {$notes}", $admin);
        }

        if ($action === 'reject') {
            return $this->reject($return, "Admin rejected: {$notes}", $admin);
        }

        throw new \InvalidArgumentException("Unknown admin override action: {$action}");
    }

    /**
     * Dispatch notification to customer & seller.
     */
    protected function notifyParties(OrderReturn $return, string $message): void
    {
        try {
            $customerUser = $return->order?->user;
            if ($customerUser) {
                $customerUser->notify(new ReturnStatusUpdatedNotification($return, $message));
            }

            $sellerUser = $return->seller?->user;
            if ($sellerUser && $sellerUser->id !== $customerUser?->id) {
                $sellerUser->notify(new ReturnStatusUpdatedNotification($return, $message));
            }
        } catch (\Throwable $e) {
            Log::info("Return notification dispatch skipped: " . $e->getMessage());
        }
    }
}
