<?php

declare(strict_types=1);

namespace App\Jobs\Cargo;

use App\Models\Shipment;
use App\Services\Cargo\Contracts\LogisticsProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateLabelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public Shipment $shipment
    ) {}

    public function handle(LogisticsProvider $provider): void
    {
        Log::info("GenerateLabelJob running for Shipment #{$this->shipment->shipment_number}");

        try {
            $provider->generateAwb($this->shipment);
        } catch (\Throwable $e) {
            Log::error("GenerateLabelJob failed for Shipment #{$this->shipment->id}: " . $e->getMessage());
            throw $e;
        }
    }
}
