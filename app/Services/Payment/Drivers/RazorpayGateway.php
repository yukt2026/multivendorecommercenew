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

class RazorpayGateway implements PaymentGatewayInterface
{
    protected string $baseUrl = 'https://api.razorpay.com/v1';

    public function getIdentifier(): string
    {
        return 'razorpay';
    }

    public function getKeyId(): string
    {
        return (string) Setting::get('razorpay_key_id', config('services.razorpay.key_id', 'rzp_test_onestall_key'));
    }

    public function getKeySecret(): string
    {
        return (string) Setting::get('razorpay_key_secret', config('services.razorpay.key_secret', 'rzp_test_onestall_secret'));
    }

    public function getWebhookSecret(): string
    {
        return (string) Setting::get('razorpay_webhook_secret', config('services.razorpay.webhook_secret', 'rzp_test_webhook_secret'));
    }

    /**
     * Create Razorpay order via API.
     */
    public function createOrder(Order $order, Payment $payment): array
    {
        $amountPaise = (int) round(((float) $payment->amount) * 100);

        $payload = [
            'amount' => $amountPaise,
            'currency' => $payment->currency ?: 'INR',
            'receipt' => $order->order_number,
            'notes' => [
                'order_id' => (string) $order->id,
                'order_number' => $order->order_number,
                'customer_id' => (string) $order->customer_id,
            ],
        ];

        $response = Http::withBasicAuth($this->getKeyId(), $this->getKeySecret())
            ->post("{$this->baseUrl}/orders", $payload);

        if (!$response->successful()) {
            Log::error('Razorpay order creation failed', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'error' => $response->json('error.description') ?? 'Gateway connection error',
            ]);

            throw new RuntimeException('Failed to create Razorpay payment order: ' . ($response->json('error.description') ?? 'Gateway error'));
        }

        $data = $response->json();

        return [
            'gateway_order_id' => (string) $data['id'],
            'amount' => (float) $payment->amount,
            'currency' => (string) ($data['currency'] ?? 'INR'),
            'raw' => $this->sanitizeResponse($data),
        ];
    }

    /**
     * Verify payment signature from checkout callback.
     */
    public function verifyPaymentSignature(array $attributes): bool
    {
        $orderId = $attributes['razorpay_order_id'] ?? $attributes['gateway_order_id'] ?? null;
        $paymentId = $attributes['razorpay_payment_id'] ?? $attributes['gateway_payment_id'] ?? null;
        $signature = $attributes['razorpay_signature'] ?? null;

        if (!$orderId || !$paymentId || !$signature) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $orderId . '|' . $paymentId, $this->getKeySecret());

        return hash_equals($expectedSignature, (string) $signature);
    }

    /**
     * Verify webhook signature.
     */
    public function verifyWebhookSignature(string $rawBody, string $signature, ?string $secret = null): bool
    {
        $webhookSecret = $secret ?: $this->getWebhookSecret();
        if (empty($signature) || empty($webhookSecret)) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $rawBody, $webhookSecret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Process webhook payload.
     */
    public function processWebhook(array $payload, string $rawBody): array
    {
        $event = (string) ($payload['event'] ?? 'unknown');
        $contains = $payload['payload']['payment']['entity'] ?? $payload['payload']['order']['entity'] ?? [];

        $gatewayOrderId = $contains['order_id'] ?? $contains['id'] ?? null;
        $gatewayPaymentId = isset($payload['payload']['payment']['entity']['id'])
            ? (string) $payload['payload']['payment']['entity']['id']
            : null;

        $amountPaise = $contains['amount'] ?? 0;
        $amount = $amountPaise > 0 ? round($amountPaise / 100, 2) : null;

        $status = 'ignored';
        $message = "Unhandled event: {$event}";

        if (in_array($event, ['payment.captured', 'order.paid'], true)) {
            $status = 'paid';
            $message = 'Payment captured successfully';
        } elseif (in_array($event, ['payment.failed'], true)) {
            $status = 'failed';
            $message = $contains['error_description'] ?? 'Payment transaction failed';
        } elseif (in_array($event, ['refund.processed'], true)) {
            $status = 'refunded';
            $message = 'Refund processed';
        }

        return [
            'event' => $event,
            'gateway_order_id' => $gatewayOrderId ? (string) $gatewayOrderId : null,
            'gateway_payment_id' => $gatewayPaymentId,
            'amount' => $amount,
            'status' => $status,
            'message' => $message,
            'raw' => $this->sanitizeResponse($payload),
        ];
    }

    /**
     * Issue full or partial refund.
     */
    public function refund(Payment $payment, float $amount, ?string $reason = null): array
    {
        $gatewayPaymentId = $payment->gateway_payment_id;
        if (!$gatewayPaymentId) {
            throw new RuntimeException('Cannot refund a payment without a gateway_payment_id.');
        }

        $amountPaise = (int) round($amount * 100);

        $payload = [
            'amount' => $amountPaise,
            'notes' => [
                'order_id' => (string) $payment->order_id,
                'reason' => $reason ?? 'Customer return/cancellation',
            ],
        ];

        $response = Http::withBasicAuth($this->getKeyId(), $this->getKeySecret())
            ->post("{$this->baseUrl}/payments/{$gatewayPaymentId}/refund", $payload);

        if (!$response->successful()) {
            Log::error('Razorpay refund failed', [
                'payment_id' => $payment->id,
                'gateway_payment_id' => $gatewayPaymentId,
                'error' => $response->json('error.description') ?? 'Gateway error',
            ]);

            throw new RuntimeException('Razorpay refund failed: ' . ($response->json('error.description') ?? 'Gateway error'));
        }

        $data = $response->json();

        return [
            'gateway_refund_id' => (string) $data['id'],
            'amount' => $amount,
            'status' => ($data['status'] ?? 'processed') === 'processed' ? 'processed' : 'pending',
            'raw' => $this->sanitizeResponse($data),
        ];
    }

    /**
     * Fetch payment details from Razorpay.
     */
    public function fetchPayment(string $gatewayPaymentId): array
    {
        $response = Http::withBasicAuth($this->getKeyId(), $this->getKeySecret())
            ->get("{$this->baseUrl}/payments/{$gatewayPaymentId}");

        if (!$response->successful()) {
            throw new RuntimeException("Failed to fetch Razorpay payment #{$gatewayPaymentId}");
        }

        return $this->sanitizeResponse($response->json());
    }

    /**
     * Fetch refund details from Razorpay.
     */
    public function fetchRefund(string $gatewayRefundId): array
    {
        $response = Http::withBasicAuth($this->getKeyId(), $this->getKeySecret())
            ->get("{$this->baseUrl}/refunds/{$gatewayRefundId}");

        if (!$response->successful()) {
            throw new RuntimeException("Failed to fetch Razorpay refund #{$gatewayRefundId}");
        }

        return $this->sanitizeResponse($response->json());
    }

    /**
     * Sanitize sensitive card or secret fields before saving/logging.
     */
    protected function sanitizeResponse(array $data): array
    {
        if (isset($data['card']['number'])) {
            $data['card']['number'] = '**** **** **** ' . substr((string) $data['card']['number'], -4);
        }
        if (isset($data['card']['cvv'])) {
            unset($data['card']['cvv']);
        }
        return $data;
    }
}
