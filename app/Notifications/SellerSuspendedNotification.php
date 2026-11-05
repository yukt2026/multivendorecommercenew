<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Seller;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SellerSuspendedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Seller $seller,
        public string $reason
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Important Notice: OneStall Merchant Account Suspended')
            ->greeting("Hello {$this->seller->business_name},")
            ->line('Your merchant account and public storefront have been suspended by OneStall platform compliance.')
            ->line('Reason for suspension:')
            ->line("> **{$this->reason}**")
            ->line('Your product catalogue and storefronts have been temporarily hidden from buyers.')
            ->line('Please reach out to merchant compliance at compliance@onestall.in to resolve this matter.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'seller_suspended',
            'seller_id' => $this->seller->id,
            'business_name' => $this->seller->business_name,
            'reason' => $this->reason,
            'message' => 'Your merchant account has been suspended: ' . $this->reason,
        ];
    }
}
