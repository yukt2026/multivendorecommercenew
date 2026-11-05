<?php

declare(strict_types=1);

namespace App\Services\AI\Support;

use App\Enums\OrderStatus;
use App\Models\Refund;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Services\AI\AiManager;
use App\Services\AI\Contracts\SupportAiReplyInterface;

class SupportAiReplyService implements SupportAiReplyInterface
{
    public function __construct(
        protected AiManager $aiManager
    ) {}

    public function generateReply(SupportTicket $ticket, ?string $customerMessage = null, bool $autoSendIfConfident = true): array
    {
        $start = microtime(true);

        if (!$this->aiManager->isFeatureEnabled('support_replies')) {
            return [
                'intent' => 'disabled',
                'confidence' => 0.0,
                'reply_text' => 'AI automated support replies are currently disabled.',
                'auto_posted' => false,
                'message' => null,
            ];
        }

        $ticket->loadMissing(['order.fulfilmentOrders.shipments.tracking', 'order.payments', 'user']);
        $order = $ticket->order;

        $textToAnalyze = strtolower(trim($customerMessage ?: "{$ticket->subject} " . ($ticket->messages()->latest()->first()?->message ?? '')));

        // 1. Classify Customer Intent & Compute Confidence
        $intent = 'general';
        $confidence = 0.60;
        $replyText = "";

        if (preg_match('/\b(track|where is|delivery|delay|status|courier|shipment|arrived|arriving|package)\b/i', $textToAnalyze)) {
            $intent = 'tracking_delivery';
            $confidence = 0.95;

            if ($order) {
                $statusName = ucwords(str_replace('_', ' ', $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status));
                $shipment = $order->fulfilmentOrders->flatMap->shipments->first();
                $tracking = $shipment?->tracking->first();

                if ($tracking && $tracking->tracking_number) {
                    $replyText = "Hello {$ticket->user?->name},\n\nYour order #{$order->order_number} is currently {$statusName}. It is dispatched via {$shipment->courier_name} with tracking number (AWB) {$tracking->tracking_number}.\n\nLatest Milestone: {$tracking->status_description} at {$tracking->location}.\n\nYou can track real-time delivery status here:\n" . route('orders.track', $order->id) . "\n\nPlease let us know if you have any questions!";
                } else {
                    $replyText = "Hello {$ticket->user?->name},\n\nYour order #{$order->order_number} is currently {$statusName}. It is being prepared for dispatch by our fulfillment team. You will receive an SMS and email notification with tracking details as soon as the package is handed over to the courier partner.\n\nThank you for your patience!";
                }
            } else {
                $confidence = 0.70;
                $replyText = "Hello {$ticket->user?->name},\n\nTo help track your shipment right away, could you please share your Order ID (starts with OS-)? You can also view all your active orders on your Orders page:\n" . route('orders.index');
            }
        } elseif (preg_match('/\b(return|replace|exchange|damaged|wrong item|broken|defective|size issue)\b/i', $textToAnalyze)) {
            $intent = 'return_exchange';
            $confidence = 0.90;

            if ($order) {
                $isDelivered = ($order->status === OrderStatus::DELIVERED || (string) $order->status === 'delivered');
                if ($isDelivered) {
                    $replyText = "Hello {$ticket->user?->name},\n\nWe are sorry to hear you're experiencing an issue with Order #{$order->order_number}. Because this order is delivered, you can submit an instant return or replacement request directly from your order detail page within the 7-day return window:\n" . route('orders.show', $order->id) . "\n\nPlease upload clear photos of the issue to ensure speedy approval by our merchant QC team.";
                } else {
                    $replyText = "Hello {$ticket->user?->name},\n\nYour order #{$order->order_number} has not yet been marked delivered. Return and replacement requests can be submitted immediately once the package is received and inspected.";
                }
            } else {
                $replyText = "Hello {$ticket->user?->name},\n\nOneStall offers a 7-day easy return and replacement policy on all eligible marketplace products. Please mention your Order Number so we can immediately guide you through the process.";
            }
        } elseif (preg_match('/\b(refund|money back|account|double charge|debited|deducted|payment failed)\b/i', $textToAnalyze)) {
            $intent = 'refund_payment';
            $confidence = 0.92;

            if ($order) {
                $refund = Refund::where('order_id', $order->id)->latest()->first();
                if ($refund && $refund->status === 'processed') {
                    $refInfo = $refund->gateway_refund_id ?: ($refund->arn ?? 'Standard Gateway Reference');
                    $replyText = "Hello {$ticket->user?->name},\n\nA refund of " . format_currency((float) $refund->amount) . " for Order #{$order->order_number} has already been initiated to your original payment method (Reference: {$refInfo}). It typically reflects in your bank statement within 3 to 5 business days depending on your bank's clearing cycle.";
                } else {
                    $replyText = "Hello {$ticket->user?->name},\n\nWe have received your payment inquiry for Order #{$order->order_number}. If any amount was debited for an unsuccessful transaction, banks automatically reverse the charge within 24 to 48 hours. Our billing team is reviewing this ticket.";
                }
            } else {
                $replyText = "Hello {$ticket->user?->name},\n\nRegarding your refund inquiry, could you please provide your Order Number or payment transaction reference? We will check with our payment gateway immediately.";
            }
        } elseif (preg_match('/\b(cancel|cancellation|stop order|placed by mistake)\b/i', $textToAnalyze)) {
            $intent = 'cancellation';
            $confidence = 0.90;

            if ($order) {
                $canCancel = in_array((string) $order->status, ['placed', 'processing', 'confirmed'], true);
                if ($canCancel) {
                    $replyText = "Hello {$ticket->user?->name},\n\nYour order #{$order->order_number} has not been shipped yet! You can cancel it directly with 1-click on your order details page:\n" . route('orders.show', $order->id) . "\n\nAny online payment made will be automatically refunded back to your source account.";
                } else {
                    $replyText = "Hello {$ticket->user?->name},\n\nOrder #{$order->order_number} is already packed or in transit, so automated cancellation is no longer possible. However, you can safely refuse delivery at your doorstep when the courier attempts delivery, and your refund will process upon package return.";
                }
            } else {
                $replyText = "Hello {$ticket->user?->name},\n\nPlease specify which Order Number you would like to cancel so we can check if it can still be stopped before dispatch.";
            }
        } else {
            $replyText = "Hello {$ticket->user?->name},\n\nThank you for reaching out to OneStall Customer Care! We have received your query regarding '{$ticket->subject}'. Our support specialist will review your ticket and reply shortly.";
        }

        // 2. Automated Posting If Confident
        $threshold = (float) config('ai.support.auto_send_confidence_threshold', 0.80);
        $enableAutoSend = (bool) config('ai.support.enable_auto_send', true);
        $autoPosted = false;
        $createdMessage = null;

        if ($autoSendIfConfident && $enableAutoSend && $confidence >= $threshold) {
            $createdMessage = TicketMessage::create([
                'support_ticket_id' => $ticket->id,
                'user_id' => null, // null denotes AI system assistant
                'message' => $replyText,
                'is_internal' => false,
            ]);
            $autoPosted = true;
        }

        $output = [
            'intent' => $intent,
            'confidence' => $confidence,
            'reply_text' => $replyText,
            'auto_posted' => $autoPosted,
            'message' => $createdMessage,
        ];

        $this->aiManager->log(
            feature: 'support_replies',
            action: 'generate_support_reply',
            userId: $ticket->user_id,
            sellerId: $ticket->seller_id,
            promptSummary: "Support auto-reply for Ticket #{$ticket->ticket_number} (Intent: {$intent})",
            input: [
                'ticket_id' => $ticket->id,
                'subject' => $ticket->subject,
                'order_id' => $ticket->order_id,
                'query_text' => substr($textToAnalyze, 0, 200),
            ],
            output: [
                'intent' => $intent,
                'confidence' => $confidence,
                'auto_posted' => $autoPosted,
            ],
            latencyMs: (microtime(true) - $start) * 1000,
            status: 'success'
        );

        return $output;
    }
}
