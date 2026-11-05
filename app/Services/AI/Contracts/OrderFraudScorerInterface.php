<?php

declare(strict_types=1);

namespace App\Services\AI\Contracts;

use App\Models\Order;
use App\Models\OrderFraudAssessment;

interface OrderFraudScorerInterface
{
    /**
     * Evaluate incoming or placed orders against multi-dimensional fraud and abuse signals.
     *
     * @param Order $order
     * @param array<string, mixed> $context Additional request telemetry (e.g. client_ip, user_agent)
     * @return OrderFraudAssessment
     */
    public function assessOrder(Order $order, array $context = []): OrderFraudAssessment;
}
