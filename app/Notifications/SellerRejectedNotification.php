<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Seller;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SellerRejectedNotification extends Notification implements ShouldQueue
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
            ->subject('Important Update Regarding Your OneStall Merchant Application')
            ->greeting("Hello {$this->seller->business_name},")
            ->line('Our verification team has reviewed your KYC documents and merchant application.')
            ->line('Unfortunately, your application could not be approved due to the following reason:')
            ->line("> **{$this->reason}**")
            ->line('You can review your documents and resubmit corrected proofs through your onboarding dashboard.')
            ->action('Access Onboarding Portal', route('seller.onboarding'))
            ->line('If you have any questions, please contact seller support at support@onestall.in.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'seller_rejected',
            'seller_id' => $this->seller->id,
            'business_name' => $this->seller->business_name,
            'reason' => $this->reason,
            'message' => 'Your merchant application was rejected: ' . $this->reason,
        ];
    }
}
