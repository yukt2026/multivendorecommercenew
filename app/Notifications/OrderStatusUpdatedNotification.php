<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Order $order,
        public readonly string $previousStatus,
        public readonly string $newStatus,
        public readonly ?string $note = null
    ) {}

    public function via(object $notifiable): array
    {
        // Email now; SMS/WhatsApp/Push can be added via notification channels
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $statusLabel = ucwords(str_replace('_', ' ', $this->newStatus));

        $mail = (new MailMessage)
            ->subject("Order Update: #{$this->order->order_number} is now {$statusLabel}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your order #{$this->order->order_number} has an updated status: **{$statusLabel}**.");

        if ($this->note) {
            $mail->line("Update Note: {$this->note}");
        }

        $mail->action('Track Your Order', route('orders.show', $this->order->order_number))
            ->line('Thank you for shopping with ' . setting('name', 'OneStall') . '!');

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'previous_status' => $this->previousStatus,
            'new_status' => $this->newStatus,
            'note' => $this->note,
        ];
    }
}
