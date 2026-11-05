<?php

declare(strict_types=1);

namespace App\Jobs\Cargo;

use App\Models\FulfilmentOrder;
use App\Models\Shipment;
use App\Services\Cargo\Contracts\LogisticsProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateShipmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public FulfilmentOrder $fulfilmentOrder,
        public array $options = []
    ) {}

    public function handle(LogisticsProvider $provider): void
    {
        Log::info("CreateShipmentJob running for FulfilmentOrder #{$this->fulfilmentOrder->fulfilment_order_number}");

        try {
            $result = $provider->createShipment($this->fulfilmentOrder, $this->options);

            if (!empty($result['shipment']) && $result['shipment'] instanceof Shipment) {
                $shipment = $result['shipment'];
                
                // Chain label generation
                GenerateLabelJob::dispatch($shipment);
                
                Log::info("Shipment #{$shipment->shipment_number} created with AWB {$shipment->awb}");
            }
        } catch (\Throwable $e) {
            Log::error("CreateShipmentJob failed for FO #{$this->fulfilmentOrder->id}: " . $e->getMessage());
            throw $e;
        }
    }
}
