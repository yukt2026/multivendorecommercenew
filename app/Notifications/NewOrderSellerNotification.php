<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\FulfilmentOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewOrderSellerNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly FulfilmentOrder $fulfilmentOrder
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $foNumber = $this->fulfilmentOrder->fulfilment_order_number;
        $orderNumber = $this->fulfilmentOrder->order?->order_number ?? 'N/A';
        $type = $this->fulfilmentOrder->fulfilment_type->label();
        $amount = format_currency((float) $this->fulfilmentOrder->total);

        return (new MailMessage)
            ->subject("New {$type} Order Received: #{$foNumber}")
            ->greeting("Hello {$notifiable->name},")
            ->line("You have received a new {$type} order (#{$orderNumber} / Package #{$foNumber}) amounting to **{$amount}**.")
            ->line("Please accept and pack this package promptly according to dispatch SLA.")
            ->action('View & Process Order', route('seller.orders.show', $this->fulfilmentOrder))
            ->line('Thank you for being a valued merchant partner on ' . setting('name', 'OneStall') . '!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'fulfilment_order_id' => $this->fulfilmentOrder->id,
            'fulfilment_order_number' => $this->fulfilmentOrder->fulfilment_order_number,
            'order_number' => $this->fulfilmentOrder->order?->order_number,
            'total' => (float) $this->fulfilmentOrder->total,
        ];
    }
}
