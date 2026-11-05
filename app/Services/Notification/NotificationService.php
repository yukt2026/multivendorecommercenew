<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Models\FulfilmentOrder;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\SellerSettlement;
use App\Models\Shipment;
use App\Models\User;
use App\Notifications\UnifiedNotification;
use App\Services\Notification\Drivers\PushGatewayInterface;
use App\Services\Notification\Drivers\SmsGatewayInterface;
use App\Services\Notification\Drivers\WhatsAppGatewayInterface;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Dispatch unified queued notification across eligible channels.
     *
     * @param User $user
     * @param string $eventKey
     * @param array<string, mixed> $variables
     * @param array<string>|null $overrideChannels
     */
    public function dispatch(
        User $user,
        string $eventKey,
        array $variables = [],
        ?array $overrideChannels = null
    ): void {
        if ($eventKey === 'promotional' && !$user->hasPromotionalConsent()) {
            Log::info("NotificationService: Skipped promotional notification for user #{$user->id} — no stored consent.");
            return;
        }

        $notification = new UnifiedNotification(
            eventKey: $eventKey,
            variables: $variables,
            overrideChannels: $overrideChannels
        );

        $user->notify($notification);

        // Also record database notification in notification_logs if database channel is active
        NotificationLog::create([
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'event_key' => $eventKey,
            'channel' => 'database',
            'recipient' => $user->email,
            'subject' => $variables['subject'] ?? $eventKey,
            'content' => json_encode($variables),
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Send Security OTP.
     */
    public function sendOtp(User $user, string $otp): void
    {
        $this->dispatch($user, 'otp', [
            'otp' => $otp,
            'name' => $user->name,
        ], ['mail', 'sms', 'whatsapp']);
    }

    /**
     * Send Order Confirmation.
     */
    public function sendOrderConfirmation(Order $order): void
    {
        $user = $order->customer?->user;
        if (!$user) return;

        $this->dispatch($user, 'order_confirmed', [
            'order_number' => $order->order_number,
            'amount' => number_format((float) $order->total, 2),
            'name' => $user->name,
            'action_url' => route('orders.show', $order->order_number),
            'action_text' => 'Track Order',
        ]);
    }

    /**
     * Send Payment Confirmation.
     */
    public function sendPaymentConfirmation(Payment $payment): void
    {
        $order = $payment->order;
        $user = $order?->customer?->user;
        if (!$user) return;

        $this->dispatch($user, 'payment_confirmed', [
            'order_number' => $order->order_number,
            'amount' => number_format((float) $payment->amount, 2),
            'gateway' => $payment->gateway,
            'action_url' => route('orders.show', $order->order_number),
        ]);
    }

    /**
     * Send Packing in Progress update.
     */
    public function sendOrderPacking(FulfilmentOrder $fo): void
    {
        $user = $fo->order?->customer?->user;
        if (!$user) return;

        $this->dispatch($user, 'order_packing', [
            'order_number' => $fo->fulfilment_order_number,
            'store_name' => $fo->store?->name ?? 'Store Hub',
            'action_url' => route('orders.show', $fo->order->order_number),
        ]);
    }

    /**
     * Send Shipment / Tracking update.
     */
    public function sendShipmentUpdate(Shipment $shipment, string $status): void
    {
        $user = $shipment->fulfilmentOrder?->order?->customer?->user;
        if (!$user) return;

        $this->dispatch($user, 'shipment_update', [
            'awb_code' => $shipment->awb_number,
            'courier' => $shipment->courier_name,
            'status_label' => ucwords(str_replace('_', ' ', $status)),
            'action_url' => route('orders.track', [
                $shipment->fulfilmentOrder->order->order_number,
                $shipment->fulfilmentOrder->fulfilment_order_number,
            ]),
        ]);
    }

    /**
     * Send Out for Delivery Alert.
     */
    public function sendOutForDelivery(FulfilmentOrder $fo): void
    {
        $user = $fo->order?->customer?->user;
        if (!$user) return;

        $rider = $fo->deliveryAssignment?->deliveryPartner;

        $this->dispatch($user, 'out_for_delivery', [
            'order_number' => $fo->fulfilment_order_number,
            'rider_name' => $rider?->name ?? 'Fleet Rider',
            'eta' => '10-20 mins',
            'action_url' => route('orders.live_tracking', $fo->order_id),
            'action_text' => 'View Live Map & OTP',
        ]);
    }

    /**
     * Send Delivered Confirmation.
     */
    public function sendDelivered(FulfilmentOrder $fo): void
    {
        $user = $fo->order?->customer?->user;
        if (!$user) return;

        $this->dispatch($user, 'delivered', [
            'order_number' => $fo->fulfilment_order_number,
            'action_url' => route('orders.show', $fo->order->order_number),
        ]);
    }

    /**
     * Send Return / Refund status update.
     */
    public function sendReturnRefund(OrderReturn $return, string $status): void
    {
        $user = $return->order?->customer?->user;
        if (!$user) return;

        $this->dispatch($user, 'return_refund', [
            'order_number' => $return->order->order_number,
            'status' => ucwords(str_replace('_', ' ', $status)),
            'amount' => number_format((float) $return->refund_amount, 2),
            'action_url' => route('orders.return.track', [$return->order->order_number, $return->return_number]),
        ]);
    }

    /**
     * Send New Order alert to merchant/seller.
     */
    public function sendSellerNewOrder(FulfilmentOrder $fo): void
    {
        $sellerUser = $fo->seller?->user;
        if (!$sellerUser) return;

        $this->dispatch($sellerUser, 'seller_new_order', [
            'order_number' => $fo->fulfilment_order_number,
            'type' => $fo->fulfilment_type->label(),
            'amount' => number_format((float) $fo->total_amount, 2),
            'action_url' => route('seller.orders.show', $fo->id),
        ]);
    }

    /**
     * Send Low Stock alert to merchant.
     */
    public function sendLowStockAlert(ProductVariant $variant, int $remainingStock): void
    {
        $sellerUser = $variant->product?->seller?->user;
        if (!$sellerUser) return;

        $this->dispatch($sellerUser, 'low_stock', [
            'product_title' => $variant->product->title,
            'sku' => $variant->sku,
            'stock' => $remainingStock,
            'action_url' => route('seller.inventory.index'),
        ]);
    }

    /**
     * Send Settlement Paid alert to merchant.
     */
    public function sendSettlementPaid(SellerSettlement $settlement, string $utr): void
    {
        $sellerUser = $settlement->seller?->user;
        if (!$sellerUser) return;

        $this->dispatch($sellerUser, 'settlement_paid', [
            'amount' => number_format((float) $settlement->net_amount, 2),
            'utr' => $utr,
            'settlement_number' => $settlement->settlement_number,
            'action_url' => route('seller.settlements.show', $settlement->id),
        ]);
    }

    /**
     * Send Promotional notification — ONLY if user has stored consent.
     */
    public function sendPromotional(User $user, string $message, array $vars = []): bool
    {
        if (!$user->hasPromotionalConsent()) {
            return false;
        }

        $this->dispatch($user, 'promotional', array_merge([
            'message' => $message,
            'coupon' => $vars['coupon'] ?? 'SAVE20',
            'discount' => $vars['discount'] ?? '20%',
        ], $vars), ['mail', 'whatsapp', 'database']);

        return true;
    }

    /**
     * Retry a failed notification dispatch.
     */
    public function retryFailedLog(NotificationLog $log): bool
    {
        if (!$log->isFailed()) {
            return false;
        }

        try {
            $log->retry_count += 1;

            $success = true;
            $error = null;

            if ($log->channel === 'sms') {
                $driver = app(SmsGatewayInterface::class);
                $res = $driver->send($log->recipient, $log->content ?? '', ['event_key' => $log->event_key]);
                $success = (bool) ($res['success'] ?? false);
                $error = $res['error'] ?? null;
            } elseif ($log->channel === 'whatsapp') {
                $driver = app(WhatsAppGatewayInterface::class);
                $res = $driver->send($log->recipient, $log->content ?? '', ['event_key' => $log->event_key]);
                $success = (bool) ($res['success'] ?? false);
                $error = $res['error'] ?? null;
            } elseif ($log->channel === 'push') {
                $driver = app(PushGatewayInterface::class);
                $res = $driver->send($log->recipient, $log->event_key, $log->content ?? '', ['event_key' => $log->event_key]);
                $success = (bool) ($res['success'] ?? false);
                $error = $res['error'] ?? null;
            }

            if ($success) {
                $log->status = 'sent';
                $log->sent_at = now();
                $log->error_message = null;
            } else {
                $log->status = 'failed';
                $log->error_message = $error ?? 'Failed on retry';
            }
            $log->save();

            Log::info("NotificationService: Retried notification log #{$log->id} for {$log->recipient} ({$log->channel}) - status: {$log->status}");

            return $success;
        } catch (\Throwable $e) {
            $log->status = 'failed';
            $log->error_message = "Retry error: {$e->getMessage()}";
            $log->save();
            return false;
        }
    }
}
