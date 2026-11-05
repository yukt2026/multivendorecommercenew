<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AiActionLog;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiManager
{
    /**
     * Check if the global AI engine is enabled.
     */
    public function isEnabled(): bool
    {
        $configEnabled = (bool) config('ai.enabled', true);
        if (!$configEnabled) {
            return false;
        }

        return (bool) Setting::get('ai_enabled', true);
    }

    /**
     * Check if a specific AI feature is enabled via config or database setting.
     */
    public function isFeatureEnabled(string $feature): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $dbSetting = Setting::get("ai_feature_{$feature}");
        if ($dbSetting !== null) {
            return (bool) $dbSetting;
        }

        return (bool) config("ai.features.{$feature}", true);
    }

    /**
     * Get active AI driver / model.
     */
    public function getDriver(): string
    {
        return (string) Setting::get('ai_default_driver', config('ai.default_driver', 'rule_based'));
    }

    /**
     * Log an AI action to the audit trail.
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed> $output
     */
    public function log(
        string $feature,
        string $action,
        ?int $userId = null,
        ?int $sellerId = null,
        ?string $promptSummary = null,
        array $input = [],
        array $output = [],
        float $latencyMs = 0.0,
        string $status = 'success',
        ?string $error = null,
        ?string $model = null,
        int $tokens = 0
    ): ?AiActionLog {
        if (!config('ai.logging.enabled', true)) {
            return null;
        }

        try {
            return AiActionLog::create([
                'feature' => $feature,
                'action' => $action,
                'user_id' => $userId ?? Auth::id(),
                'seller_id' => $sellerId,
                'prompt_summary' => $promptSummary ? substr($promptSummary, 0, 255) : null,
                'input_payload' => config('ai.logging.log_payloads', true) ? $input : null,
                'output_payload' => config('ai.logging.log_payloads', true) ? $output : null,
                'model_or_driver' => $model ?? $this->getDriver(),
                'tokens_used' => $tokens,
                'latency_ms' => round($latencyMs, 2),
                'status' => $status,
                'error_message' => $error,
            ]);
        } catch (Throwable $e) {
            Log::error("Failed to write AI audit action log: {$e->getMessage()}");
            return null;
        }
    }
}
