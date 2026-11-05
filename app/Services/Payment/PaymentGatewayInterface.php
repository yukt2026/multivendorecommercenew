<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Payment;

interface PaymentGatewayInterface
{
    /**
     * Unique identifier of this gateway driver (e.g. 'razorpay', 'cashfree').
     */
    public function getIdentifier(): string;

    /**
     * Create an order on the payment gateway.
     *
     * @param Order $order
     * @param Payment $payment
     * @return array{gateway_order_id: string, amount: float, currency: string, raw: array<string, mixed>}
     */
    public function createOrder(Order $order, Payment $payment): array;

    /**
     * Verify payment signature from customer callback.
     *
     * @param array<string, mixed> $attributes [order_id, payment_id, signature]
     * @return bool
     */
    public function verifyPaymentSignature(array $attributes): bool;

    /**
     * Verify server-to-server webhook signature.
     *
     * @param string $rawBody
     * @param string $signature
     * @param string|null $secret
     * @return bool
     */
    public function verifyWebhookSignature(string $rawBody, string $signature, ?string $secret = null): bool;

    /**
     * Process verified webhook payload.
     *
     * @param array<string, mixed> $payload
     * @param string $rawBody
     * @return array{
     *     event: string,
     *     gateway_order_id: ?string,
     *     gateway_payment_id: ?string,
     *     amount: ?float,
     *     status: 'paid'|'failed'|'refunded'|'ignored',
     *     message: string,
     *     raw: array<string, mixed>
     * }
     */
    public function processWebhook(array $payload, string $rawBody): array;

    /**
     * Issue full or partial refund for a payment.
     *
     * @param Payment $payment
     * @param float $amount
     * @param string|null $reason
     * @return array{gateway_refund_id: string, amount: float, status: 'processed'|'pending', raw: array<string, mixed>}
     */
    public function refund(Payment $payment, float $amount, ?string $reason = null): array;

    /**
     * Fetch payment status and details from gateway.
     *
     * @param string $gatewayPaymentId
     * @return array<string, mixed>
     */
    public function fetchPayment(string $gatewayPaymentId): array;

    /**
     * Fetch refund status and details from gateway.
     *
     * @param string $gatewayRefundId
     * @return array<string, mixed>
     */
    public function fetchRefund(string $gatewayRefundId): array;
}
