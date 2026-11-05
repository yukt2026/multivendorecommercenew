<?php

declare(strict_types=1);

namespace App\Services\AI\Fraud;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\OrderFraudAssessment;
use App\Models\OrderReturn;
use App\Services\AI\AiManager;
use App\Services\AI\Contracts\OrderFraudScorerInterface;

class OrderFraudScorerService implements OrderFraudScorerInterface
{
    public function __construct(
        protected AiManager $aiManager
    ) {}

    public function assessOrder(Order $order, array $context = []): OrderFraudAssessment
    {
        $start = microtime(true);

        if (!$this->aiManager->isFeatureEnabled('fraud_scoring')) {
            return OrderFraudAssessment::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'risk_score' => 0,
                    'risk_level' => 'low',
                    'reasons' => ['AI fraud scoring disabled by feature flag.'],
                    'signals' => [],
                    'decision' => 'approved',
                ]
            );
        }

        $order->loadMissing(['customer.user', 'fulfilmentOrders']);
        $address = $order->address_snapshot ?? [];
        $total = (float) $order->total;
        $paymentMethod = $order->payment_method instanceof PaymentMethod
            ? $order->payment_method
            : PaymentMethod::tryFrom((string) $order->payment_method) ?? PaymentMethod::UPI;

        $riskScore = 0;
        $reasons = [];
        $signals = [];

        // 1. Velocity Abuse Check (Orders in last 1 hour by same customer)
        $recentOrdersCount = Order::query()
            ->where('customer_id', $order->customer_id)
            ->where('id', '!=', $order->id)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        $signals['recent_orders_1h'] = $recentOrdersCount;
        $maxVelocity = (int) config('ai.fraud.velocity_max_orders_1h', 5);

        if ($recentOrdersCount >= $maxVelocity) {
            $riskScore += 45;
            $reasons[] = "High velocity anomaly: {$recentOrdersCount} orders placed in the past 60 minutes.";
        } elseif ($recentOrdersCount >= 3) {
            $riskScore += 25;
            $reasons[] = "Elevated order frequency: {$recentOrdersCount} orders in the past hour.";
        }

        // 2. Cash On Delivery (COD) Risk Assessment
        $signals['is_cod'] = ($paymentMethod === PaymentMethod::COD);
        if ($paymentMethod === PaymentMethod::COD) {
            $codHighThreshold = (float) config('ai.fraud.high_value_cod_threshold', 3000.00);

            // Total near COD upper ceiling
            if ($total >= $codHighThreshold) {
                $riskScore += 25;
                $reasons[] = "High-value COD order (" . format_currency($total) . ") exceeds standard threshold of " . format_currency($codHighThreshold) . ".";
            }

            // Customer Lifetime Order Count Check
            $priorOrdersCount = Order::where('customer_id', $order->customer_id)
                ->where('id', '!=', $order->id)
                ->where('payment_status', 'paid')
                ->count();

            $signals['prior_delivered_orders'] = $priorOrdersCount;
            if ($priorOrdersCount === 0 && $total >= 2000.00) {
                $riskScore += 20;
                $reasons[] = "First-time customer placing high-value COD order.";
            }

            // Return / RTO History
            $pastReturnsCount = OrderReturn::query()
                ->whereHas('orderItem.fulfilmentOrder.order', fn ($oq) => $oq->where('customer_id', $order->customer_id))
                ->count();

            $signals['past_returns_count'] = $pastReturnsCount;
            if ($pastReturnsCount >= 3) {
                $riskScore += 30;
                $reasons[] = "Customer has an elevated history of returns / RTOs ({$pastReturnsCount} prior returns).";
            }
        }

        // 3. Address Quality & Geo Sanity
        $pincode = trim((string) ($address['pincode'] ?? ''));
        $signals['pincode'] = $pincode;
        if (empty($pincode) || strlen($pincode) !== 6 || !is_numeric($pincode)) {
            $riskScore += 35;
            $reasons[] = "Invalid or malformed shipping pincode: '{$pincode}'.";
        }

        $line1 = strtolower(trim((string) ($address['line1'] ?? '')));
        $signals['address_line1_length'] = strlen($line1);
        if (strlen($line1) < 5 || preg_match('/\b(test|asdf|qwerty|xyz|na|none)\b/i', $line1)) {
            $riskScore += 30;
            $reasons[] = "Suspicious or incomplete shipping address text detected: '{$line1}'.";
        }

        $phone = preg_replace('/\D/', '', (string) ($address['phone'] ?? ''));
        $signals['phone_digits'] = strlen($phone);
        if (strlen($phone) < 10) {
            $riskScore += 30;
            $reasons[] = "Malformed delivery contact number.";
        }

        // 4. Client IP & Header Checks (when provided via context)
        if (!empty($context['client_ip'])) {
            $signals['client_ip'] = $context['client_ip'];
        }

        // 5. Final Score & Decision Determination
        $finalScore = min(100, max(0, $riskScore));
        $highThreshold = (int) config('ai.fraud.high_risk_threshold', 70);
        $mediumThreshold = (int) config('ai.fraud.medium_risk_threshold', 35);

        if ($finalScore >= $highThreshold) {
            $riskLevel = 'high';
            $decision = 'flagged_for_review';
        } elseif ($finalScore >= $mediumThreshold) {
            $riskLevel = 'medium';
            $decision = 'approved';
        } else {
            $riskLevel = 'low';
            $decision = 'approved';
            if (empty($reasons)) {
                $reasons[] = "Normal order velocity, verified address format, and low risk score.";
            }
        }

        $assessment = OrderFraudAssessment::updateOrCreate(
            ['order_id' => $order->id],
            [
                'risk_score' => $finalScore,
                'risk_level' => $riskLevel,
                'reasons' => $reasons,
                'signals' => $signals,
                'decision' => $decision,
            ]
        );

        $this->aiManager->log(
            feature: 'fraud_scoring',
            action: 'score_order',
            userId: $order->customer?->user_id,
            promptSummary: "Fraud assessment for Order #{$order->order_number}: Score {$finalScore} ({$riskLevel})",
            input: ['order_id' => $order->id, 'order_number' => $order->order_number, 'total' => $total],
            output: [
                'risk_score' => $finalScore,
                'risk_level' => $riskLevel,
                'decision' => $decision,
                'reasons' => $reasons,
            ],
            latencyMs: (microtime(true) - $start) * 1000,
            status: 'success'
        );

        return $assessment;
    }
}
