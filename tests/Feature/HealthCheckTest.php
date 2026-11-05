<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_healthz_endpoint_returns_successful_telemetry(): void
    {
        $response = $this->getJson('/healthz');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'app_env',
            'app_version',
            'php_version',
            'laravel_version',
            'memory' => [
                'current_mb',
                'peak_mb',
            ],
            'checks' => [
                'database' => [
                    'status',
                    'latency_ms',
                ],
                'cache' => [
                    'status',
                    'latency_ms',
                ],
                'storage' => [
                    'status',
                    'disk',
                ],
                'queue' => [
                    'status',
                ],
            ],
        ]);

        $this->assertEquals('healthy', $response->json('status'));
        $this->assertEquals('ok', $response->json('checks.database.status'));
        $this->assertEquals('ok', $response->json('checks.cache.status'));
        $this->assertEquals('ok', $response->json('checks.storage.status'));
    }

    public function test_api_v1_health_endpoint(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200);
        $this->assertEquals('healthy', $response->json('status'));
    }
}
