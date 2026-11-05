<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncInventoryJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly int $storeId,
        public readonly int $productId,
        public readonly int $quantity
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Heavy asynchronous inventory sync across physical connect & digital store
    }
}
