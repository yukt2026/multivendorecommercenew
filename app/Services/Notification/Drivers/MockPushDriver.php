<?php

declare(strict_types=1);

namespace App\Services\Notification\Drivers;

use Illuminate\Support\Facades\Log;

class MockPushDriver implements PushGatewayInterface
{
    public function send(string $deviceToken, string $title, string $body, array $data = []): array
    {
        Log::info("MockPushDriver: Dispatched Push Notification to {$deviceToken}", [
            'token' => $deviceToken,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        return [
            'success' => true,
            'message_id' => 'mock-push-' . uniqid(),
            'driver' => 'mock',
        ];
    }
}
