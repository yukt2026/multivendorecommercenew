<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\FulfilmentType;
use App\Models\FulfilmentOrder;
use App\Services\Fulfilment\FulfilmentRerouteService;
use Illuminate\Console\Command;

class CheckQuickTimeoutsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'quick:check-timeouts';

    /**
     * The console command description.
     */
    protected $description = 'Scan pending quick commerce orders and auto-reassign any order that exceeded store acceptance timeout.';

    public function handle(FulfilmentRerouteService $rerouteService): int
    {
        $this->info('Scanning pending Quick Commerce orders for store acceptance timeouts...');

        $pendingQuickOrders = FulfilmentOrder::query()
            ->with(['store'])
            ->where('fulfilment_type', FulfilmentType::QUICK)
            ->where('status', 'placed')
            ->get();

        $reroutedCount = 0;
        $rejectedCount = 0;

        foreach ($pendingQuickOrders as $order) {
            $timeoutSeconds = $order->store ? $order->store->effectiveQuickTimeout() : (int) setting('quick_order_timeout_seconds', 120);
            $isExpired = $order->created_at ? $order->created_at->copy()->addSeconds($timeoutSeconds)->isPast() : false;

            if ($isExpired) {
                $elapsedSeconds = $order->created_at ? abs(now()->diffInSeconds($order->created_at)) : $timeoutSeconds;
                $this->warn("Order #{$order->fulfilment_order_number} timed out ({$elapsedSeconds}s > {$timeoutSeconds}s). Re-routing...");

                $result = $rerouteService->attemptAutoReroute(
                    $order,
                    "Store acceptance timed out after {$elapsedSeconds}s (limit: {$timeoutSeconds}s)"
                );

                if ($result['rerouted']) {
                    $reroutedCount++;
                    $this->info(" -> Rerouted to Store #{$result['new_store_id']}");
                } else {
                    $rejectedCount++;
                    $this->error(" -> Could not reroute: {$result['message']}");
                }
            }
        }

        $this->info("Completed scan. Rerouted: {$reroutedCount}, No fallback / rejected: {$rejectedCount}");

        return self::SUCCESS;
    }
}
