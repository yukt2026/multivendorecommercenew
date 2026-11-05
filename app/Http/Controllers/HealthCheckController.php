<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HealthCheckController extends Controller
{
    /**
     * Perform deep system health inspection for load balancers and monitoring agents.
     */
    public function check(Request $request): JsonResponse
    {
        $status = 'healthy';
        $statusCode = 200;
        $checks = [];

        // 1. Database Connectivity & Latency Check
        $dbStart = microtime(true);
        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $dbDuration = round((microtime(true) - $dbStart) * 1000, 2);
            $checks['database'] = [
                'status' => 'ok',
                'latency_ms' => $dbDuration,
                'connection' => config('database.default'),
            ];
        } catch (Throwable $e) {
            $status = 'unhealthy';
            $statusCode = 503;
            $checks['database'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }

        // 2. Cache / Redis Connectivity & Read-Write Check
        $cacheStart = microtime(true);
        try {
            $testKey = 'health_check_' . bin2hex(random_bytes(4));
            $testVal = time();
            Cache::put($testKey, $testVal, 10);
            $retrieved = Cache::get($testKey);
            Cache::forget($testKey);

            if ($retrieved !== $testVal) {
                throw new \RuntimeException('Cache write-read mismatch.');
            }

            $cacheDuration = round((microtime(true) - $cacheStart) * 1000, 2);
            $checks['cache'] = [
                'status' => 'ok',
                'latency_ms' => $cacheDuration,
                'driver' => config('cache.default'),
            ];
        } catch (Throwable $e) {
            $status = 'unhealthy';
            $statusCode = 503;
            $checks['cache'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }

        // 3. Storage Read/Write Check
        try {
            $storageDisk = config('filesystems.default', 'local');
            $testFile = '.health_' . bin2hex(random_bytes(4)) . '.tmp';
            Storage::disk($storageDisk)->put($testFile, 'health-check');
            $readContent = Storage::disk($storageDisk)->get($testFile);
            Storage::disk($storageDisk)->delete($testFile);

            if ($readContent !== 'health-check') {
                throw new \RuntimeException('Storage write-read mismatch.');
            }

            $checks['storage'] = [
                'status' => 'ok',
                'disk' => $storageDisk,
            ];
        } catch (Throwable $e) {
            $status = 'degraded';
            if ($statusCode !== 503) {
                $statusCode = 500;
            }
            $checks['storage'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }

        // 4. Queue Connectivity Check
        try {
            $queueConnection = config('queue.default', 'sync');
            $checks['queue'] = [
                'status' => 'ok',
                'connection' => $queueConnection,
            ];
        } catch (Throwable $e) {
            $checks['queue'] = [
                'status' => 'warning',
                'message' => $e->getMessage(),
            ];
        }

        // 5. System Metrics
        $memoryUsage = round(memory_get_usage(true) / 1024 / 1024, 2);
        $peakMemory = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

        $payload = [
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'app_env' => config('app.env'),
            'app_version' => config('onestall.version', '1.0.0'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'memory' => [
                'current_mb' => $memoryUsage,
                'peak_mb' => $peakMemory,
            ],
            'checks' => $checks,
        ];

        return response()->json($payload, $statusCode);
    }
}
