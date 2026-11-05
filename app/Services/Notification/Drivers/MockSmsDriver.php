<?php

declare(strict_types=1);

namespace App\Services\Notification\Drivers;

use Illuminate\Support\Facades\Log;

class MockSmsDriver implements SmsGatewayInterface
{
    public function send(string $to, string $message, array $metadata = []): array
    {
        Log::info("MockSmsDriver: Dispatched SMS to {$to}", [
            'to' => $to,
            'message' => $message,
            'metadata' => $metadata,
        ]);

        return [
            'success' => true,
            'message_id' => 'mock-sms-' . uniqid(),
            'driver' => 'mock',
        ];
    }
}
