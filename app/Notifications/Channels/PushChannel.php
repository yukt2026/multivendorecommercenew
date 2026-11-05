<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Models\NotificationLog;
use App\Services\Notification\Drivers\PushGatewayInterface;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class PushChannel
{
    public function __construct(
        protected ?PushGatewayInterface $gateway = null
    ) {
        $this->gateway = $gateway ?? app()->makeWith(PushGatewayInterface::class, []);
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toPush')) {
            return;
        }

        $token = $notifiable->fcm_token ?? (method_exists($notifiable, 'routeNotificationFor') ? $notifiable->routeNotificationFor('push') : null);
        if (empty($token)) {
            // Fallback for user in-app push subscription mock
            $token = 'device-token-' . ($notifiable->id ?? 'guest');
        }

        $pushData = $notification->toPush($notifiable);
        $title = $pushData['title'] ?? 'Notification';
        $body = $pushData['body'] ?? '';
        $data = $pushData['data'] ?? [];
        $eventKey = property_exists($notification, 'eventKey') ? $notification->eventKey : 'push_alert';

        try {
            $result = $this->gateway->send((string) $token, $title, $body, $data);

            NotificationLog::create([
                'notifiable_type' => get_class($notifiable),
                'notifiable_id' => $notifiable->id ?? null,
                'event_key' => $eventKey,
                'channel' => 'push',
                'recipient' => (string) $token,
                'subject' => $title,
                'content' => $body,
                'status' => $result['success'] ? 'sent' : 'failed',
                'error_message' => $result['error'] ?? null,
                'sent_at' => $result['success'] ? now() : null,
            ]);
        } catch (\Throwable $e) {
            Log::error("PushChannel failure: {$e->getMessage()}", ['token' => $token]);

            NotificationLog::create([
                'notifiable_type' => get_class($notifiable),
                'notifiable_id' => $notifiable->id ?? null,
                'event_key' => $eventKey,
                'channel' => 'push',
                'recipient' => (string) $token,
                'subject' => $title,
                'content' => $body,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'retry_count' => 0,
            ]);
        }
    }
}
