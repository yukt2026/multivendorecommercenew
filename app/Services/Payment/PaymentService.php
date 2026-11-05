<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\CODRemittanceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\CodRemittance;
use App\Models\FulfilmentOrder;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class PaymentService
{
    public function __construct(
        protected PaymentGatewayManager $gatewayManager,
        protected InventoryService $inventoryService
    ) {}

    /**
     * Initiate online payment for an order and create gateway order.
     *
     * @return array{
     *     payment: Payment,
     *     gateway: string,
     *     gateway_order_id: string,
     *     key_id: string,
     *     amount: float,
     *     currency: string,
     *     order_number: string
     * }
     */
    public function initiateOnlinePayment(Order $order, ?string $gatewayName = null): array
    {
        $gateway = $this->gatewayManager->driver($gatewayName);

        // Find or create active pending payment
        $payment = Payment::firstOrCreate(
            [
                'order_id' => $order->id,
                'status' => PaymentStatus::PENDING,
            ],
            [
                'gateway' => $gateway->getIdentifier(),
                'method' => PaymentMethod::UPI,
                'amount' => $order->total,
                'currency' => 'INR',
            ]
        );

        // Call Gateway createOrder
        $result = $gateway->createOrder($order, $payment);

        $payment->gateway_order_id = $result['gateway_order_id'];
        $payment->raw_response = array_merge($payment->raw_response ?? [], ['order_create' => $result['raw']]);
        $payment->save();

        $keyId = method_exists($gateway, 'getKeyId') ? $gateway->getKeyId() : ($gateway->getAppId() ?? '');

        return [
            'payment' => $payment,
            'gateway' => $gateway->getIdentifier(),
            'gateway_order_id' => $payment->gateway_order_id,
            'key_id' => $keyId,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency ?: 'INR',
            'order_number' => $order->order_number,
        ];
    }

    /**
     * Idempotent payment success handler.
     */
    public function handlePaymentSuccess(Payment $payment, string $gatewayPaymentId, array $rawResponse = []): Payment
    {
        // 1. Idempotency: If already paid, return without duplicating status transitions
        if ($payment->status === PaymentStatus::PAID) {
            return $payment;
        }

        return DB::transaction(function () use ($payment, $gatewayPaymentId, $rawResponse) {
            $payment->status = PaymentStatus::PAID;
            $payment->gateway_payment_id = $gatewayPaymentId;
            $payment->verified_at = now();
            $payment->raw_response = array_merge($payment->raw_response ?? [], ['verification' => $rawResponse]);
            $payment->save();

            $order = $payment->order;
            if ($order) {
                $order->payment_status = PaymentStatus::PAID;
                $order->status = OrderStatus::PROCESSING;
                $order->save();

                // Order history
                OrderStatusHistory::create([
                    'orderable_type' => Order::class,
                    'orderable_id' => $order->id,
                    'from_status' => OrderStatus::PLACED->value,
                    'to_status' => OrderStatus::PROCESSING->value,
                    'note' => "Payment of " . format_currency((float) $payment->amount) . " confirmed via {$payment->gateway} (#{$gatewayPaymentId})",
                    'changed_by' => Auth::id(),
                ]);

                // Update Fulfilment Orders
                foreach ($order->fulfilmentOrders as $fo) {
                    $fo->status = OrderStatus::PROCESSING;
                    $fo->saveQuietly();

                    OrderStatusHistory::create([
                        'orderable_type' => FulfilmentOrder::class,
                        'orderable_id' => $fo->id,
                        'from_status' => OrderStatus::PLACED->value,
                        'to_status' => OrderStatus::PROCESSING->value,
                        'note' => "Payment confirmed. Package queued for picking & packing.",
                        'changed_by' => Auth::id(),
                    ]);
                }
            }

            Log::info("Payment #{$payment->id} successfully marked as PAID for Order #{$payment->order_id}");

            return $payment;
        });
    }

    /**
     * Handle payment failure, release reserved inventory, and allow customer retry.
     */
    public function handlePaymentFailure(Payment $payment, string $reason = 'Payment failed', array $rawResponse = []): Payment
    {
        if ($payment->status === PaymentStatus::PAID) {
            return $payment; // Do not fail an already paid transaction
        }

        return DB::transaction(function () use ($payment, $reason, $rawResponse) {
            $payment->status = PaymentStatus::FAILED;
            $payment->raw_response = array_merge($payment->raw_response ?? [], [
                'failure' => $rawResponse,
                'failure_reason' => $reason,
            ]);
            $payment->save();

            $order = $payment->order;
            if ($order) {
                $order->payment_status = PaymentStatus::FAILED;
                $order->save();

                // Release reserved inventory for this order
                $releasedCount = $this->inventoryService->releaseForOrder(
                    $order->order_number,
                    "Payment failure: {$reason}"
                );

                OrderStatusHistory::create([
                    'orderable_type' => Order::class,
                    'orderable_id' => $order->id,
                    'from_status' => $order->status->value,
                    'to_status' => OrderStatus::PENDING->value,
                    'note' => "Payment failed ({$reason}). {$releasedCount} inventory reservation(s) released.",
                    'changed_by' => Auth::id(),
                ]);
            }

            Log::warning("Payment #{$payment->id} marked as FAILED: {$reason}");

            return $payment;
        });
    }

    /**
     * Process full or partial refund via Gateway API with audit trail.
     */
    public function processRefund(
        Payment $payment,
        float $amount,
        ?string $reason = null,
        ?int $fulfilmentOrderId = null
    ): Refund {
        if ($payment->status !== PaymentStatus::PAID) {
            throw new InvalidArgumentException('Only successfully paid payments can be refunded.');
        }

        $alreadyRefunded = (float) $payment->refunds()
            ->where('status', RefundStatus::PROCESSED)
            ->sum('amount');

        $remainingPaid = (float) $payment->amount - $alreadyRefunded;

        if ($amount <= 0 || $amount > ($remainingPaid + 0.001)) {
            throw new InvalidArgumentException("Invalid refund amount. Maximum refundable is " . format_currency($remainingPaid) . ".");
        }

        $gateway = $this->gatewayManager->driver($payment->gateway);

        // Execute Gateway Refund API
        $result = $gateway->refund($payment, $amount, $reason);

        return DB::transaction(function () use ($payment, $amount, $reason, $fulfilmentOrderId, $result, $alreadyRefunded) {
            $refund = Refund::create([
                'payment_id' => $payment->id,
                'fulfilment_order_id' => $fulfilmentOrderId,
                'amount' => $amount,
                'reason' => $reason ?? 'Customer requested refund',
                'status' => $result['status'] === 'processed' ? RefundStatus::PROCESSED : RefundStatus::PENDING,
                'gateway_refund_id' => $result['gateway_refund_id'],
                'raw_response' => $result['raw'],
                'processed_at' => now(),
            ]);

            $order = $payment->order;
            if ($order) {
                $totalRefundedNow = $alreadyRefunded + $amount;
                if ($totalRefundedNow >= ((float) $payment->amount - 0.01)) {
                    $order->payment_status = PaymentStatus::REFUNDED;
                } else {
                    $order->payment_status = PaymentStatus::PARTIALLY_REFUNDED;
                }
                $order->save();

                OrderStatusHistory::create([
                    'orderable_type' => Order::class,
                    'orderable_id' => $order->id,
                    'from_status' => OrderStatus::PROCESSING->value,
                    'to_status' => $order->status->value,
                    'note' => "Refund of " . format_currency($amount) . " processed via gateway (Refund ID: {$result['gateway_refund_id']})",
                    'changed_by' => Auth::id(),
                ]);
            }

            Log::info("Refund #{$refund->id} processed for Payment #{$payment->id}. Amount: {$amount}");

            return $refund;
        });
    }

    /**
     * Mark COD payment collected on delivery with remittance record.
     */
    public function collectCodPayment(Order $order, ?float $amount = null, ?string $reference = null): Payment
    {
        return DB::transaction(function () use ($order, $amount, $reference) {
            $collectAmount = $amount ?? (float) $order->total;

            $payment = Payment::firstOrCreate(
                ['order_id' => $order->id, 'gateway' => 'cod'],
                [
                    'method' => PaymentMethod::COD,
                    'amount' => $collectAmount,
                    'currency' => 'INR',
                ]
            );

            $payment->status = PaymentStatus::PAID;
            $payment->amount = $collectAmount;
            $payment->verified_at = now();
            $payment->raw_response = ['collected_at' => now()->toIso8601String(), 'reference' => $reference];
            $payment->save();

            $order->payment_status = PaymentStatus::PAID;
            $order->save();

            // Create CodRemittance entry for accounting / seller payout
            foreach ($order->fulfilmentOrders as $fo) {
                CodRemittance::create([
                    'shipment_id' => null,
                    'seller_id' => $fo->seller_id,
                    'amount' => (float) $fo->seller_payable,
                    'status' => CODRemittanceStatus::COLLECTED,
                    'collected_at' => now(),
                    'reference' => $reference ?? "COD-ORD-{$order->id}",
                ]);
            }

            OrderStatusHistory::create([
                'orderable_type' => Order::class,
                'orderable_id' => $order->id,
                'from_status' => OrderStatus::PLACED->value,
                'to_status' => $order->status->value,
                'note' => "Cash on Delivery payment of " . format_currency($collectAmount) . " collected at doorstep.",
                'changed_by' => Auth::id(),
            ]);

            return $payment;
        });
    }
}
