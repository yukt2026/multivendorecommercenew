<?php

declare(strict_types=1);

namespace App\Services\Cargo;

use App\Models\Setting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CargoClient
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $apiSecret;
    protected string $webhookSecret;
    protected bool $fake;
    protected int $timeout;
    protected int $retryTimes;
    protected int $retrySleep;

    public function __construct()
    {
        $this->baseUrl = (string) (Setting::get('cargo_base_url') ?: config('services.cargo.base_url', 'https://api.onestallcargo.internal/v1'));
        $this->apiKey = (string) (Setting::get('cargo_api_key') ?: config('services.cargo.api_key', 'osc_test_api_key_sec123'));
        $this->apiSecret = (string) (Setting::get('cargo_api_secret') ?: config('services.cargo.api_secret', 'osc_test_secret_abc456'));
        $this->webhookSecret = (string) (Setting::get('cargo_webhook_secret') ?: config('services.cargo.webhook_secret', 'osc_webhook_secret_xyz789'));
        
        $settingFake = Setting::get('cargo_fake');
        $this->fake = $settingFake !== null ? (bool) $settingFake : (bool) config('services.cargo.fake', true);

        $this->timeout = (int) (Setting::get('cargo_timeout') ?: config('services.cargo.timeout', 15));
        $this->retryTimes = (int) (Setting::get('cargo_retry_times') ?: config('services.cargo.retry_times', 3));
        $this->retrySleep = (int) (Setting::get('cargo_retry_sleep_ms') ?: config('services.cargo.retry_sleep', 100));
    }

    /**
     * Check if fake test mode is active.
     */
    public function isFakeMode(): bool
    {
        return $this->fake;
    }

    /**
     * Override fake mode for testing.
     */
    public function setFakeMode(bool $fake): self
    {
        $this->fake = $fake;
        return $this;
    }

    /**
     * Get webhook secret key.
     */
    public function getWebhookSecret(): string
    {
        return $this->webhookSecret;
    }

    /**
     * Create shipment consignment in OneStall Cargo.
     */
    public function createShipment(array $payload): array
    {
        if ($this->isFakeMode()) {
            $shipmentNumber = $payload['shipment_number'] ?? 'SHP-' . strtoupper(Str::random(10));
            $awb = 'OSC-' . date('Ymd') . '-' . strtoupper(Str::random(6));

            return [
                'success' => true,
                'shipment_id' => 'CARGO-' . strtoupper(uniqid()),
                'shipment_number' => $shipmentNumber,
                'awb' => $awb,
                'courier' => $payload['courier'] ?? 'OneStall Express Surface',
                'service' => $payload['service'] ?? 'surface',
                'status' => 'manifest_created',
                'label_url' => "https://cargo.onestall.internal/labels/{$awb}.pdf",
                'charges' => (float) ($payload['estimated_charges'] ?? 145.00),
                'created_at' => now()->toIso8601String(),
            ];
        }

        return $this->sendRequest('post', '/shipments', $payload);
    }

    /**
     * Generate or re-generate AWB & Shipping Label for an existing shipment.
     */
    public function generateAwb(string $shipmentNumber, array $payload = []): array
    {
        if ($this->isFakeMode()) {
            $awb = 'OSC-' . date('Ymd') . '-' . strtoupper(Str::random(6));

            return [
                'success' => true,
                'shipment_number' => $shipmentNumber,
                'awb' => $awb,
                'courier' => $payload['courier'] ?? 'OneStall Express',
                'label_url' => "https://cargo.onestall.internal/labels/{$awb}.pdf",
                'barcode_svg' => '<svg>BARCODE-' . $awb . '</svg>',
                'assigned_at' => now()->toIso8601String(),
            ];
        }

        return $this->sendRequest('post', "/shipments/{$shipmentNumber}/awb", $payload);
    }

    /**
     * Get available courier rates and estimated SLAs.
     */
    public function getRates(array $params): array
    {
        if ($this->isFakeMode()) {
            $weight = (float) ($params['weight_kg'] ?? 1.0);
            $isCod = !empty($params['cod_amount']) && (float) $params['cod_amount'] > 0;

            return [
                [
                    'courier_code' => 'onestall_express',
                    'courier_name' => 'OneStall Express Air',
                    'service_type' => 'air',
                    'rate' => round(120.00 + ($weight * 40.00) + ($isCod ? 35.00 : 0.00), 2),
                    'estimated_days' => 2,
                    'etd' => now()->addDays(2)->format('d M Y'),
                    'is_recommended' => true,
                ],
                [
                    'courier_code' => 'onestall_surface',
                    'courier_name' => 'OneStall Cargo Surface',
                    'service_type' => 'surface',
                    'rate' => round(70.00 + ($weight * 25.00) + ($isCod ? 35.00 : 0.00), 2),
                    'estimated_days' => 4,
                    'etd' => now()->addDays(4)->format('d M Y'),
                    'is_recommended' => false,
                ],
                [
                    'courier_code' => 'delhivery_direct',
                    'courier_name' => 'Delhivery Freight',
                    'service_type' => 'surface',
                    'rate' => round(85.00 + ($weight * 30.00) + ($isCod ? 40.00 : 0.00), 2),
                    'estimated_days' => 3,
                    'etd' => now()->addDays(3)->format('d M Y'),
                    'is_recommended' => false,
                ],
            ];
        }

        return $this->sendRequest('get', '/rates', $params);
    }

    /**
     * Request carrier warehouse pickup.
     */
    public function requestPickup(array $payload): array
    {
        if ($this->isFakeMode()) {
            $token = 'PCK-' . date('Ymd') . '-' . strtoupper(Str::random(5));

            return [
                'success' => true,
                'pickup_token' => $token,
                'status' => 'pickup_scheduled',
                'scheduled_date' => $payload['pickup_date'] ?? now()->addDay()->format('Y-m-d'),
                'time_slot' => $payload['time_slot'] ?? '10:00-14:00',
                'message' => 'Carrier pickup successfully requested and scheduled.',
            ];
        }

        return $this->sendRequest('post', '/pickups', $payload);
    }

    /**
     * Track live events for an AWB.
     */
    public function trackShipment(string $awb): array
    {
        if ($this->isFakeMode()) {
            return [
                'success' => true,
                'awb' => $awb,
                'current_status' => 'in_transit',
                'location' => 'Bengaluru Regional Hub',
                'estimated_delivery' => now()->addDays(2)->toIso8601String(),
                'events' => [
                    [
                        'status' => 'in_transit',
                        'activity' => 'Consignment in transit between sorting hubs',
                        'location' => 'Bengaluru Regional Hub',
                        'timestamp' => now()->subHours(3)->toIso8601String(),
                    ],
                    [
                        'status' => 'picked_up',
                        'activity' => 'Package picked up from seller fulfillment center',
                        'location' => 'Indiranagar Hub, Bengaluru',
                        'timestamp' => now()->subHours(12)->toIso8601String(),
                    ],
                    [
                        'status' => 'manifest_created',
                        'activity' => 'Electronic cargo manifest and waybill registered',
                        'location' => 'System',
                        'timestamp' => now()->subDay()->toIso8601String(),
                    ],
                ],
            ];
        }

        return $this->sendRequest('get', "/track/{$awb}");
    }

    /**
     * Cancel an active shipment.
     */
    public function cancelShipment(string $shipmentNumber, string $reason = ''): array
    {
        if ($this->isFakeMode()) {
            return [
                'success' => true,
                'shipment_number' => $shipmentNumber,
                'status' => 'cancelled',
                'reason' => $reason ?: 'Cancelled by merchant',
                'cancelled_at' => now()->toIso8601String(),
            ];
        }

        return $this->sendRequest('post', "/shipments/{$shipmentNumber}/cancel", ['reason' => $reason]);
    }

    /**
     * Create reverse pickup consignment.
     */
    public function createReversePickup(array $payload): array
    {
        if ($this->isFakeMode()) {
            $waybill = 'REV-' . date('Ymd') . '-' . strtoupper(Str::random(6));

            return [
                'success' => true,
                'waybill' => $waybill,
                'tracking_number' => $waybill,
                'status' => 'reverse_pickup_scheduled',
                'courier' => 'OneStall Cargo Logistics',
                'pickup_date' => now()->addDay()->format('Y-m-d'),
                'pickup_slot' => '10:00 - 14:00',
            ];
        }

        return $this->sendRequest('post', '/reverse-pickups', $payload);
    }

    /**
     * Fetch available NDR action options.
     */
    public function getNdrActions(string $awb): array
    {
        if ($this->isFakeMode()) {
            return [
                'success' => true,
                'awb' => $awb,
                'allowed_actions' => ['reattempt', 'change_address', 'rto'],
                'last_failure_reason' => 'Customer unavailable / Phone not reachable',
                'remaining_attempts' => 2,
            ];
        }

        return $this->sendRequest('get', "/ndr/{$awb}/actions");
    }

    /**
     * Submit an NDR action instruction to the carrier.
     */
    public function submitNdrAction(string $awb, string $action, array $params = []): array
    {
        if ($this->isFakeMode()) {
            return [
                'success' => true,
                'awb' => $awb,
                'action' => $action,
                'status' => $action === 'rto' ? 'rto_scheduled' : 'reattempt_scheduled',
                'remarks' => $params['remarks'] ?? 'Action instruction transmitted to carrier.',
                'action_taken_at' => now()->toIso8601String(),
            ];
        }

        return $this->sendRequest('post', "/ndr/{$awb}/action", array_merge(['action' => $action], $params));
    }

    /**
     * Fetch COD remittance records from the carrier.
     */
    public function getCodRemittanceData(array $filters = []): array
    {
        if ($this->isFakeMode()) {
            return [
                [
                    'remittance_id' => 'REMIT-' . strtoupper(Str::random(8)),
                    'utr_reference' => 'UTR-HDFC-' . date('Ymd') . '-' . rand(100000, 999999),
                    'shipment_awb' => 'OSC-' . date('Ymd') . '-SAMPLE',
                    'collected_amount' => 1250.00,
                    'remitted_amount' => 1215.00,
                    'courier_handling_fee' => 35.00,
                    'status' => 'remitted',
                    'collected_at' => now()->subDays(2)->toIso8601String(),
                    'remitted_at' => now()->toIso8601String(),
                ],
            ];
        }

        return $this->sendRequest('get', '/cod/remittances', $filters);
    }

    /**
     * Validate incoming webhook HMAC SHA-256 signature.
     */
    public function verifyWebhookSignature(string $rawPayload, ?string $signature): bool
    {
        if (empty($signature) || empty($this->webhookSecret)) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawPayload, $this->webhookSecret);

        return hash_equals($expected, $signature);
    }

    /**
     * Execute live HTTP call with headers, timeouts, and retries.
     */
    protected function sendRequest(string $method, string $path, array $data = []): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
        $timestamp = (string) now()->timestamp;
        $bodyString = !empty($data) ? json_encode($data) : '';

        $signature = hash_hmac('sha256', $timestamp . '.' . $bodyString, $this->apiSecret);

        try {
            $client = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'X-Cargo-Timestamp' => $timestamp,
                'X-Cargo-Signature' => $signature,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->timeout($this->timeout)->retry($this->retryTimes, $this->retrySleep);

            /** @var Response $response */
            $response = match (strtolower($method)) {
                'post' => $client->post($url, $data),
                'put' => $client->put($url, $data),
                'delete' => $client->delete($url, $data),
                default => $client->get($url, $data),
            };

            if (!$response->successful()) {
                Log::error("CargoClient API failure: {$url} - Status {$response->status()}", [
                    'body' => $response->body(),
                ]);
                throw new \RuntimeException("Cargo API error [{$response->status()}]: " . $response->body());
            }

            return (array) $response->json();
        } catch (\Throwable $e) {
            Log::error("CargoClient request exception: " . $e->getMessage(), [
                'url' => $url,
                'method' => $method,
            ]);
            throw $e;
        }
    }
}
