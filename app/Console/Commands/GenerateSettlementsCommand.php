<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Settlement\SettlementService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateSettlementsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'settlements:generate
                            {--seller= : Generate only for specific seller ID}
                            {--period-end= : End date for settlement cycle (YYYY-MM-DD)}';

    /**
     * The console command description.
     */
    protected $description = 'Generate weekly seller settlement batches for mature delivered orders past the return window';

    /**
     * Execute the console command.
     */
    public function handle(SettlementService $settlementService): int
    {
        $sellerId = $this->option('seller') ? (int) $this->option('seller') : null;
        $periodEnd = $this->option('period-end') ? Carbon::parse($this->option('period-end')) : null;

        $this->info("Starting seller settlement batch generation...");
        if ($sellerId) {
            $this->line("Targeting Seller ID: {$sellerId}");
        }

        $settlements = $settlementService->generateWeeklyBatch($periodEnd, $sellerId);

        if ($settlements->isEmpty()) {
            $this->comment("No delivered orders or remittances eligible for settlement at this time.");
            return Command::SUCCESS;
        }

        $this->info("Successfully generated {$settlements->count()} settlement batch(es):");

        $tableData = $settlements->map(function ($s) {
            return [
                'ID' => $s->id,
                'Settlement #' => $s->settlement_number,
                'Seller' => $s->seller?->store_name ?? "Seller #{$s->seller_id}",
                'Period' => "{$s->period_start->toDateString()} to {$s->period_end->toDateString()}",
                'Gross' => format_currency((float) $s->gross),
                'Commission' => format_currency((float) $s->commission),
                'Tax' => format_currency((float) $s->tax_on_commission),
                'COD Deducted' => format_currency((float) $s->cod_collected),
                'Net Payout' => format_currency((float) $s->net),
                'Status' => $s->status->value,
            ];
        });

        $this->table(
            ['ID', 'Settlement #', 'Seller', 'Period', 'Gross', 'Commission', 'Tax', 'COD Deducted', 'Net Payout', 'Status'],
            $tableData
        );

        return Command::SUCCESS;
    }
}
