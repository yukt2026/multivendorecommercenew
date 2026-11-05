<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\OrderReturn;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReturnStatusUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public OrderReturn $return,
        public string $customMessage = ''
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $statusLabel = $this->return->status->label();
        $item = $this->return->orderItem;
        $order = $this->return->order;

        $mail = (new MailMessage)
            ->subject("Update on Return #{$this->return->return_number}: {$statusLabel}")
            ->greeting("Hello {$notifiable->name},")
            ->line("There is an update on your return request for '{$item?->title}' (Order #{$order?->order_number}).")
            ->line("Current Status: **{$statusLabel}**");

        if ($this->customMessage) {
            $mail->line("Note: {$this->customMessage}");
        }

        if ($this->return->pickup_waybill) {
            $mail->line("Reverse Pickup Waybill: **{$this->return->pickup_waybill}**");
        }

        if ($this->return->refund_amount && $this->return->status->value === 'refunded') {
            $mail->line("Refund of ₹{$this->return->refund_amount} has been processed via {$this->return->refund_mode}.");
        }

        $mail->action('Track Return Status', url("/orders/{$order?->order_number}/returns/{$this->return->return_number}"));

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'return_id' => $this->return->id,
            'return_number' => $this->return->return_number,
            'status' => $this->return->status->value,
            'status_label' => $this->return->status->label(),
            'item_title' => $this->return->orderItem?->title,
            'refund_amount' => $this->return->refund_amount,
            'message' => $this->customMessage,
        ];
    }
}
