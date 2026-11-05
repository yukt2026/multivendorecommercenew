<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Inventory\InventoryService;
use Illuminate\Console\Command;

class ExpireInventoryReservationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'inventory:expire-reservations';

    /**
     * The console command description.
     */
    protected $description = 'Release expired inventory reservations back to available stock';

    /**
     * Execute the console command.
     */
    public function handle(InventoryService $inventoryService): int
    {
        $this->info('Scanning for expired active inventory reservations...');

        $releasedCount = $inventoryService->expireReservations();

        $this->info("Successfully released {$releasedCount} expired inventory reservation(s).");

        return Command::SUCCESS;
    }
}
