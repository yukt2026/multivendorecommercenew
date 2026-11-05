<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payment\PaymentGatewayManager;
use App\Services\Payment\PaymentService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcilePaymentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'payments:reconcile 
                            {--gateway= : Filter by specific gateway driver (e.g. razorpay, cashfree)}
                            {--days=7 : Number of days to look back}
                            {--sync : Automatically update local records to match gateway status}';

    /**
     * The console command description.
     */
    protected $description = 'Compare local payment records against payment gateway records and flag or resolve mismatches';

    public function handle(
        PaymentGatewayManager $gatewayManager,
        PaymentService $paymentService
    ): int {
        $days = (int) $this->option('days');
        $gatewayFilter = $this->option('gateway');
        $autoSync = (bool) $this->option('sync');

        $this->info("Starting payment reconciliation for past {$days} day(s)...");

        $query = Payment::query()
            ->where('created_at', '>=', Carbon::now()->subDays($days))
            ->whereNotNull('gateway_payment_id')
            ->where('gateway', '!=', 'cod');

        if ($gatewayFilter) {
            $query->where('gateway', $gatewayFilter);
        }

        $payments = $query->with('order')->get();
        if ($payments->isEmpty()) {
            $this->info('No online payments found in the specified window.');
            return self::SUCCESS;
        }

        $mismatches = [];
        $syncedCount = 0;

        foreach ($payments as $payment) {
            try {
                $driver = $gatewayManager->driver($payment->gateway);
                $remoteData = $driver->fetchPayment($payment->gateway_payment_id);

                $remoteStatus = strtolower((string) ($remoteData['status'] ?? 'unknown'));
                $remoteAmount = isset($remoteData['amount']) ? round(((float) $remoteData['amount']) / 100, 2) : (float) $payment->amount;

                $localStatus = $payment->status->value;
                $isMismatch = false;
                $mismatchReasons = [];

                // 1. Status Check
                $isRemotePaid = in_array($remoteStatus, ['captured', 'paid', 'success'], true);
                if ($isRemotePaid && $payment->status !== PaymentStatus::PAID) {
                    $isMismatch = true;
                    $mismatchReasons[] = "Remote status is '{$remoteStatus}' but local is '{$localStatus}'";
                } elseif (!$isRemotePaid && $payment->status === PaymentStatus::PAID) {
                    $isMismatch = true;
                    $mismatchReasons[] = "Remote status is '{$remoteStatus}' but local is marked 'PAID'";
                }

                // 2. Amount Check
                if (abs((float) $payment->amount - $remoteAmount) > 0.05) {
                    $isMismatch = true;
                    $mismatchReasons[] = "Amount mismatch: Local is " . format_currency((float) $payment->amount) . " vs Gateway " . format_currency($remoteAmount);
                }

                if ($isMismatch) {
                    $mismatches[] = [
                        'order_number' => $payment->order?->order_number ?? 'N/A',
                        'payment_id' => $payment->id,
                        'gateway' => $payment->gateway,
                        'gateway_payment_id' => $payment->gateway_payment_id,
                        'local_status' => $localStatus,
                        'remote_status' => $remoteStatus,
                        'reason' => implode('; ', $mismatchReasons),
                    ];

                    Log::warning("Payment reconciliation mismatch detected for Payment #{$payment->id}", [
                        'order_id' => $payment->order_id,
                        'reasons' => $mismatchReasons,
                    ]);

                    if ($autoSync && $isRemotePaid) {
                        $paymentService->handlePaymentSuccess($payment, $payment->gateway_payment_id, $remoteData);
                        $syncedCount++;
                    }
                }
            } catch (\Throwable $e) {
                $this->warn("Skipping payment #{$payment->id}: " . $e->getMessage());
            }
        }

        if (empty($mismatches)) {
            $this->info("✓ Reconciliation completed. All {$payments->count()} payment records match gateway states perfectly.");
            return self::SUCCESS;
        }

        $this->warn("Found " . count($mismatches) . " payment mismatch(es):");
        $this->table(
            ['Order #', 'Payment ID', 'Gateway', 'Gateway Payment ID', 'Local Status', 'Gateway Status', 'Details'],
            $mismatches
        );

        if ($autoSync) {
            $this->info("Successfully auto-synced {$syncedCount} payment(s) to match gateway records.");
        } else {
            $this->comment("Run with --sync flag to automatically update mismatched local payments.");
        }

        return self::SUCCESS;
    }
}
