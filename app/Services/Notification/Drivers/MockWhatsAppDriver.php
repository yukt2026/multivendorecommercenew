<?php

declare(strict_types=1);

namespace App\Services\Notification\Drivers;

use Illuminate\Support\Facades\Log;

class MockWhatsAppDriver implements WhatsAppGatewayInterface
{
    public function send(string $to, string $message, array $metadata = []): array
    {
        Log::info("MockWhatsAppDriver: Dispatched WhatsApp message to {$to}", [
            'to' => $to,
            'message' => $message,
            'metadata' => $metadata,
        ]);

        return [
            'success' => true,
            'message_id' => 'mock-wa-' . uniqid(),
            'driver' => 'mock',
        ];
    }
}
