<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Models\NotificationLog;
use App\Services\Notification\Drivers\WhatsAppGatewayInterface;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class WhatsAppChannel
{
    public function __construct(
        protected ?WhatsAppGatewayInterface $gateway = null
    ) {
        $this->gateway = $gateway ?? app()->makeWith(WhatsAppGatewayInterface::class, []);
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toWhatsApp')) {
            return;
        }

        $phone = $notifiable->phone ?? (method_exists($notifiable, 'routeNotificationFor') ? $notifiable->routeNotificationFor('whatsapp') : null);
        if (empty($phone)) {
            return;
        }

        $waContent = $notification->toWhatsApp($notifiable);
        $message = is_array($waContent) ? ($waContent['message'] ?? '') : (string) $waContent;
        $eventKey = property_exists($notification, 'eventKey') ? $notification->eventKey : 'whatsapp_alert';

        try {
            $result = $this->gateway->send((string) $phone, $message, [
                'event_key' => $eventKey,
                'notifiable_id' => $notifiable->id ?? null,
            ]);

            NotificationLog::create([
                'notifiable_type' => get_class($notifiable),
                'notifiable_id' => $notifiable->id ?? null,
                'event_key' => $eventKey,
                'channel' => 'whatsapp',
                'recipient' => (string) $phone,
                'content' => $message,
                'status' => $result['success'] ? 'sent' : 'failed',
                'error_message' => $result['error'] ?? null,
                'sent_at' => $result['success'] ? now() : null,
            ]);
        } catch (\Throwable $e) {
            Log::error("WhatsAppChannel failure: {$e->getMessage()}", ['phone' => $phone]);

            NotificationLog::create([
                'notifiable_type' => get_class($notifiable),
                'notifiable_id' => $notifiable->id ?? null,
                'event_key' => $eventKey,
                'channel' => 'whatsapp',
                'recipient' => (string) $phone,
                'content' => $message,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'retry_count' => 0,
            ]);
        }
    }
}
