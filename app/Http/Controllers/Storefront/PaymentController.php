<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\PaymentGatewayManager;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService,
        protected PaymentGatewayManager $gatewayManager
    ) {}

    /**
     * Show Payment Checkout screen with Razorpay/Gateway JS checkout modal.
     */
    public function pay(Request $request, string $orderNumber): View|RedirectResponse
    {
        $order = Order::where('order_number', $orderNumber)
            ->with(['customer.user', 'fulfilmentOrders.items'])
            ->firstOrFail();

        // If already paid, redirect straight to success
        if ($order->payment_status === PaymentStatus::PAID) {
            return redirect()->route('checkout.success', $order->order_number);
        }

        try {
            $paymentData = $this->paymentService->initiateOnlinePayment($order);
        } catch (\Throwable $e) {
            Log::error('Payment initiation error', ['order' => $orderNumber, 'error' => $e->getMessage()]);
            return redirect()->route('payment.retry', $order->order_number)->with('error', $e->getMessage());
        }

        return view('storefront.payment-checkout', [
            'order' => $order,
            'paymentData' => $paymentData,
        ]);
    }

    /**
     * Customer Browser Return/Callback handler.
     */
    public function callback(Request $request): RedirectResponse
    {
        $payload = $request->all();

        // Check for client-reported gateway cancellation / failure
        if ($request->has('error') || $request->input('status') === 'failed') {
            $reason = $request->input('error.description') ?? $request->input('error_description') ?? 'Payment was cancelled or failed by user.';
            $gatewayOrderId = $request->input('error.metadata.order_id') ?? $request->input('razorpay_order_id') ?? $request->input('order_id');

            $payment = $gatewayOrderId ? Payment::where('gateway_order_id', $gatewayOrderId)->first() : null;
            if ($payment) {
                $this->paymentService->handlePaymentFailure($payment, $reason, $payload);
                return redirect()->route('payment.retry', $payment->order?->order_number ?? '')->with('error', $reason);
            }

            return redirect()->route('cart.index')->with('error', $reason);
        }

        $gatewayOrderId = $request->input('razorpay_order_id') ?? $request->input('order_id');
        $gatewayPaymentId = $request->input('razorpay_payment_id') ?? $request->input('payment_id');

        if (!$gatewayOrderId || !$gatewayPaymentId) {
            return redirect()->route('cart.index')->with('error', 'Incomplete payment response from gateway.');
        }

        $payment = Payment::where('gateway_order_id', $gatewayOrderId)->firstOrFail();
        $order = $payment->order;

        // Idempotency: if already verified and marked paid, proceed directly
        if ($payment->status === PaymentStatus::PAID) {
            return redirect()->route('checkout.success', $order->order_number);
        }

        $gateway = $this->gatewayManager->driver($payment->gateway);

        // Server-side signature verification (Never trust the frontend alone!)
        $isValid = $gateway->verifyPaymentSignature($payload);

        if (!$isValid) {
            Log::warning('Payment callback signature verification failed', [
                'payment_id' => $payment->id,
                'gateway_order_id' => $gatewayOrderId,
            ]);

            $this->paymentService->handlePaymentFailure($payment, 'Signature verification failed (tampered or invalid payload)', $payload);

            return redirect()->route('payment.retry', $order->order_number)
                ->with('error', 'Payment verification failed. Please try again.');
        }

        // Successfully verified payment
        $this->paymentService->handlePaymentSuccess($payment, (string) $gatewayPaymentId, $payload);

        return redirect()->route('checkout.success', $order->order_number)->with('success', 'Payment verified successfully!');
    }

    /**
     * Allow customer to retry payment after failure.
     */
    public function retry(Request $request, string $orderNumber): View
    {
        $order = Order::where('order_number', $orderNumber)
            ->with(['customer.user', 'fulfilmentOrders.items'])
            ->firstOrFail();

        return view('storefront.payment-retry', [
            'order' => $order,
        ]);
    }

    /**
     * Server-to-server webhook endpoint with signature verification, rate-limiting, and idempotency.
     */
    public function webhook(Request $request, string $gateway): JsonResponse
    {
        $rawBody = (string) $request->getContent();
        $gatewayDriver = $this->gatewayManager->driver($gateway);

        // Resolve signature header
        $signature = $request->header('X-Razorpay-Signature')
            ?? $request->header('x-webhook-signature')
            ?? $request->header('x-cashfree-signature')
            ?? '';

        // 1. Signature Verification
        if (!$gatewayDriver->verifyWebhookSignature($rawBody, $signature)) {
            Log::warning("Unauthorized webhook received for gateway [{$gateway}]: invalid signature", [
                'ip' => $request->ip(),
            ]);

            return response()->json(['status' => 'error', 'message' => 'Invalid webhook signature.'], 400);
        }

        $payload = $request->json()->all();

        // 2. Process webhook event
        try {
            $processed = $gatewayDriver->processWebhook($payload, $rawBody);
        } catch (\Throwable $e) {
            Log::error("Webhook payload parsing error [{$gateway}]", ['error' => $e->getMessage()]);
            return response()->json(['status' => 'error', 'message' => 'Malformed webhook payload.'], 400);
        }

        $gatewayOrderId = $processed['gateway_order_id'];
        $gatewayPaymentId = $processed['gateway_payment_id'];

        if ($gatewayOrderId) {
            $payment = Payment::where('gateway_order_id', $gatewayOrderId)->first();

            if ($payment) {
                if ($processed['status'] === 'paid' && $gatewayPaymentId) {
                    $this->paymentService->handlePaymentSuccess($payment, $gatewayPaymentId, $payload);
                } elseif ($processed['status'] === 'failed') {
                    $this->paymentService->handlePaymentFailure($payment, $processed['message'], $payload);
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Webhook processed successfully',
            'event' => $processed['event'],
        ]);
    }
}
