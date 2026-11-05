<?php

declare(strict_types=1);

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\Payment\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CashfreeGateway implements PaymentGatewayInterface
{
    public function getIdentifier(): string
    {
        return 'cashfree';
    }

    protected function getBaseUrl(): string
    {
        $env = (string) Setting::get('cashfree_environment', config('services.cashfree.environment', 'sandbox'));
        return $env === 'production'
            ? 'https://api.cashfree.com/pg'
            : 'https://sandbox.cashfree.com/pg';
    }

    public function getAppId(): string
    {
        return (string) Setting::get('cashfree_app_id', config('services.cashfree.app_id', 'cf_test_app_id'));
    }

    public function getSecretKey(): string
    {
        return (string) Setting::get('cashfree_secret_key', config('services.cashfree.secret_key', 'cf_test_secret_key'));
    }

    public function getApiVersion(): string
    {
        return (string) Setting::get('cashfree_api_version', config('services.cashfree.api_version', '2023-08-01'));
    }

    /**
     * Create order in Cashfree.
     */
    public function createOrder(Order $order, Payment $payment): array
    {
        $payload = [
            'order_id' => $order->order_number,
            'order_amount' => (float) $payment->amount,
            'order_currency' => $payment->currency ?: 'INR',
            'customer_details' => [
                'customer_id' => (string) $order->customer_id,
                'customer_name' => $order->customer?->user?->name ?? 'Shopper',
                'customer_email' => $order->customer?->user?->email ?? 'shopper@onestall.local',
                'customer_phone' => $order->customer?->user?->phone ?? '9999999999',
            ],
            'order_meta' => [
                'return_url' => route('payment.callback') . '?order_id={order_id}',
                'notify_url' => route('payment.webhook', ['gateway' => 'cashfree']),
            ],
        ];

        $response = Http::withHeaders([
            'x-client-id' => $this->getAppId(),
            'x-client-secret' => $this->getSecretKey(),
            'x-api-version' => $this->getApiVersion(),
        ])->post("{$this->getBaseUrl()}/orders", $payload);

        if (!$response->successful()) {
            Log::error('Cashfree order creation failed', [
                'status' => $response->status(),
                'error' => $response->json('message') ?? 'Connection failed',
            ]);

            throw new RuntimeException('Failed to create Cashfree payment order: ' . ($response->json('message') ?? 'Gateway error'));
        }

        $data = $response->json();

        return [
            'gateway_order_id' => (string) ($data['order_id'] ?? $order->order_number),
            'amount' => (float) $payment->amount,
            'currency' => (string) ($data['order_currency'] ?? 'INR'),
            'raw' => $data,
        ];
    }

    /**
     * Verify payment signature.
     */
    public function verifyPaymentSignature(array $attributes): bool
    {
        $orderId = $attributes['order_id'] ?? null;
        $orderAmount = $attributes['order_amount'] ?? null;
        $signature = $attributes['signature'] ?? null;

        if (!$orderId || !$signature) {
            return false;
        }

        $dataToSign = "{$orderId}{$orderAmount}";
        $expected = base64_encode(hash_hmac('sha256', $dataToSign, $this->getSecretKey(), true));

        return hash_equals($expected, (string) $signature);
    }

    /**
     * Verify webhook signature.
     */
    public function verifyWebhookSignature(string $rawBody, string $signature, ?string $secret = null): bool
    {
        $secretKey = $secret ?: $this->getSecretKey();
        if (empty($signature) || empty($secretKey)) {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $rawBody, $secretKey, true));
        return hash_equals($expected, $signature);
    }

    /**
     * Process webhook payload.
     */
    public function processWebhook(array $payload, string $rawBody): array
    {
        $type = (string) ($payload['type'] ?? 'PAYMENT_SUCCESS_WEBHOOK');
        $data = $payload['data'] ?? [];
        $orderId = $data['order']['order_id'] ?? null;
        $paymentId = $data['payment']['cf_payment_id'] ?? null;
        $amount = isset($data['payment']['payment_amount']) ? (float) $data['payment']['payment_amount'] : null;

        $status = 'ignored';
        $message = "Handled {$type}";

        if ($type === 'PAYMENT_SUCCESS_WEBHOOK' || ($data['payment']['payment_status'] ?? '') === 'SUCCESS') {
            $status = 'paid';
            $message = 'Cashfree payment captured';
        } elseif ($type === 'PAYMENT_FAILED_WEBHOOK' || ($data['payment']['payment_status'] ?? '') === 'FAILED') {
            $status = 'failed';
            $message = $data['payment']['payment_message'] ?? 'Payment failed';
        }

        return [
            'event' => $type,
            'gateway_order_id' => $orderId ? (string) $orderId : null,
            'gateway_payment_id' => $paymentId ? (string) $paymentId : null,
            'amount' => $amount,
            'status' => $status,
            'message' => $message,
            'raw' => $payload,
        ];
    }

    /**
     * Issue refund in Cashfree.
     */
    public function refund(Payment $payment, float $amount, ?string $reason = null): array
    {
        $payload = [
            'refund_amount' => $amount,
            'refund_id' => 'ref_' . uniqid(),
            'refund_note' => $reason ?? 'Order cancelled / item returned',
        ];

        $orderId = $payment->gateway_order_id ?: $payment->order?->order_number;

        $response = Http::withHeaders([
            'x-client-id' => $this->getAppId(),
            'x-client-secret' => $this->getSecretKey(),
            'x-api-version' => $this->getApiVersion(),
        ])->post("{$this->getBaseUrl()}/orders/{$orderId}/refunds", $payload);

        if (!$response->successful()) {
            throw new RuntimeException('Cashfree refund request failed: ' . ($response->json('message') ?? 'Gateway error'));
        }

        $data = $response->json();

        return [
            'gateway_refund_id' => (string) ($data['refund_id'] ?? $payload['refund_id']),
            'amount' => $amount,
            'status' => ($data['refund_status'] ?? '') === 'SUCCESS' ? 'processed' : 'pending',
            'raw' => $data,
        ];
    }

    public function fetchPayment(string $gatewayPaymentId): array
    {
        $response = Http::withHeaders([
            'x-client-id' => $this->getAppId(),
            'x-client-secret' => $this->getSecretKey(),
            'x-api-version' => $this->getApiVersion(),
        ])->get("{$this->getBaseUrl()}/payments/{$gatewayPaymentId}");

        return $response->json() ?? [];
    }

    public function fetchRefund(string $gatewayRefundId): array
    {
        return [
            'refund_id' => $gatewayRefundId,
            'status' => 'SUCCESS',
        ];
    }
}
