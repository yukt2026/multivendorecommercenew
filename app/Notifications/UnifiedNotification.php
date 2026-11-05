<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\NotificationTemplate;
use App\Models\User;
use App\Notifications\Channels\PushChannel;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UnifiedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public string $eventKey;

    /**
     * @param string $eventKey One of the 12 Blueprint events
     * @param array<string, mixed> $variables Dynamic variables for template replacement
     * @param array<string>|null $overrideChannels Optional explicit channel override
     * @param string|null $category Category for user preferences ('orders', 'promotions', 'account', 'security')
     */
    public function __construct(
        string $eventKey,
        public readonly array $variables = [],
        public readonly ?array $overrideChannels = null,
        public readonly ?string $category = null
    ) {
        $this->eventKey = $eventKey;
    }

    public function via(object $notifiable): array
    {
        $requested = $this->overrideChannels ?? ['database', 'mail', 'sms', 'whatsapp', 'push'];

        if ($notifiable instanceof User) {
            $cat = $this->category ?? $this->resolveCategory($this->eventKey);
            $effective = $notifiable->getEffectiveNotificationChannels($cat, $requested);
        } else {
            $effective = $requested;
        }

        $channels = [];
        if (in_array('database', $effective, true)) {
            $channels[] = 'database';
        }
        if (in_array('mail', $effective, true)) {
            $channels[] = 'mail';
        }
        if (in_array('sms', $effective, true)) {
            $channels[] = SmsChannel::class;
        }
        if (in_array('whatsapp', $effective, true)) {
            $channels[] = WhatsAppChannel::class;
        }
        if (in_array('push', $effective, true)) {
            $channels[] = PushChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $template = $this->getTemplate('mail');
        $vars = array_merge(['name' => $notifiable->name ?? 'Customer'], $this->variables);

        if ($template) {
            $rendered = $template->render($vars);
            $subject = $rendered['subject'] ?? "Notification from " . setting('name', 'OneStall');
            $body = $rendered['body'];
        } else {
            $subject = $this->getDefaultSubject();
            $body = $this->getDefaultBody($vars);
        }

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$vars['name']},")
            ->line($body);

        if (!empty($this->variables['action_url'])) {
            $mail->action($this->variables['action_text'] ?? 'View Details', (string) $this->variables['action_url']);
        }

        return $mail->line('Thank you for choosing ' . setting('name', 'OneStall') . '!');
    }

    public function toArray(object $notifiable): array
    {
        $template = $this->getTemplate('database');
        $vars = array_merge(['name' => $notifiable->name ?? 'Customer'], $this->variables);

        if ($template) {
            $rendered = $template->render($vars);
            $title = $rendered['subject'] ?? $this->getDefaultSubject();
            $message = $rendered['body'];
        } else {
            $title = $this->getDefaultSubject();
            $message = $this->getDefaultBody($vars);
        }

        return [
            'event_key' => $this->eventKey,
            'title' => $title,
            'message' => $message,
            'action_url' => $this->variables['action_url'] ?? null,
            'data' => $this->variables,
        ];
    }

    public function toSms(object $notifiable): string
    {
        $template = $this->getTemplate('sms');
        $vars = array_merge(['name' => $notifiable->name ?? 'Customer'], $this->variables);

        if ($template) {
            return $template->render($vars)['body'];
        }

        return $this->getDefaultBody($vars);
    }

    public function toWhatsApp(object $notifiable): string
    {
        $template = $this->getTemplate('whatsapp');
        $vars = array_merge(['name' => $notifiable->name ?? 'Customer'], $this->variables);

        if ($template) {
            return $template->render($vars)['body'];
        }

        return $this->getDefaultBody($vars);
    }

    public function toPush(object $notifiable): array
    {
        $template = $this->getTemplate('push');
        $vars = array_merge(['name' => $notifiable->name ?? 'Customer'], $this->variables);

        if ($template) {
            $rendered = $template->render($vars);
            return [
                'title' => $rendered['subject'] ?? $this->getDefaultSubject(),
                'body' => $rendered['body'],
                'data' => $this->variables,
            ];
        }

        return [
            'title' => $this->getDefaultSubject(),
            'body' => $this->getDefaultBody($vars),
            'data' => $this->variables,
        ];
    }

    protected function getTemplate(string $channel): ?NotificationTemplate
    {
        return NotificationTemplate::query()
            ->where('event_key', $this->eventKey)
            ->where('channel', $channel)
            ->where('is_active', true)
            ->first();
    }

    protected function resolveCategory(string $eventKey): string
    {
        return match ($eventKey) {
            'promotional' => 'promotions',
            'otp' => 'security',
            'low_stock', 'seller_new_order', 'settlement_paid' => 'account',
            default => 'orders',
        };
    }

    protected function getDefaultSubject(): string
    {
        return match ($this->eventKey) {
            'otp' => 'Your OneStall Verification Code',
            'order_confirmed' => 'Order Confirmed - ' . ($this->variables['order_number'] ?? ''),
            'payment_confirmed' => 'Payment Successful - ' . ($this->variables['order_number'] ?? ''),
            'order_packing' => 'Your order is being packed',
            'shipment_update' => 'Shipment Update - ' . ($this->variables['awb_code'] ?? ''),
            'out_for_delivery' => 'Your package is Out for Delivery! 🛵',
            'delivered' => 'Package Delivered Successfully! 🎉',
            'return_refund' => 'Return & Refund Status Update',
            'seller_new_order' => 'New Order Received! ⚡',
            'low_stock' => 'Low Stock Warning: ' . ($this->variables['product_title'] ?? 'Item'),
            'settlement_paid' => 'Seller Payout Dispatched',
            'promotional' => 'Special Deal Just For You!',
            default => 'Notification from OneStall',
        };
    }

    protected function getDefaultBody(array $vars): string
    {
        $otp = $vars['otp'] ?? '000000';
        $orderNum = $vars['order_number'] ?? 'N/A';
        $amount = $vars['amount'] ?? '0.00';
        $awb = $vars['awb_code'] ?? ($vars['tracking_number'] ?? 'N/A');
        $statusLabel = $vars['status_label'] ?? ($vars['status'] ?? 'in progress');
        $rider = $vars['rider_name'] ?? 'Delivery Executive';
        $eta = $vars['eta'] ?? 'soon';
        $product = $vars['product_title'] ?? ($vars['sku'] ?? 'Product');
        $stock = $vars['stock'] ?? 0;
        $utr = $vars['utr'] ?? 'N/A';
        $coupon = $vars['coupon'] ?? 'SAVE10';
        $discount = $vars['discount'] ?? '10%';
        $type = $vars['type'] ?? 'Standard';

        return match ($this->eventKey) {
            'otp' => "Your security OTP is {$otp}. Valid for 10 minutes. Do not share this code.",
            'order_confirmed' => "Your order #{$orderNum} has been confirmed. Total amount: ₹{$amount}.",
            'payment_confirmed' => "Payment of ₹{$amount} received for order #{$orderNum}.",
            'order_packing' => "Store is packing your items for package #{$orderNum}.",
            'shipment_update' => "Consignment tracking #{$awb} updated: {$statusLabel}.",
            'out_for_delivery' => "Rider {$rider} is out for delivery. Estimated delivery: {$eta}.",
            'delivered' => "Order #{$orderNum} has been delivered. Thank you for shopping with us!",
            'return_refund' => "Return status for order #{$orderNum} updated to: {$statusLabel}.",
            'seller_new_order' => "New order received: #{$orderNum} ({$type}). Total: ₹{$amount}.",
            'low_stock', 'low_stock_alert' => "Product '{$product}' is running low (Remaining: {$stock} units).",
            'settlement_paid' => "Payout of ₹{$amount} transferred to your registered bank account. UTR: {$utr}.",
            'promotional' => "Exclusive offer: Use code {$coupon} to get {$discount} off today!",
            default => "You have a new update regarding your account.",
        };
    }
}
