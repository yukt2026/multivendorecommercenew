<?php

declare(strict_types=1);

namespace App\Jobs\Cargo;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Services\Cargo\Contracts\LogisticsProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TrackingSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public ?int $shipmentId = null
    ) {}

    public function handle(LogisticsProvider $provider): void
    {
        Log::info("TrackingSyncJob initiated");

        $query = Shipment::query();

        if ($this->shipmentId) {
            $query->where('id', $this->shipmentId);
        } else {
            $query->whereIn('status', [
                ShipmentStatus::MANIFEST_CREATED,
                ShipmentStatus::PICKED_UP,
                ShipmentStatus::IN_TRANSIT,
                ShipmentStatus::OUT_FOR_DELIVERY,
                ShipmentStatus::NDR_RAISED,
                ShipmentStatus::RTO_INITIATED,
            ])->whereNotNull('awb');
        }

        $shipments = $query->limit(100)->get();

        foreach ($shipments as $shipment) {
            if (!$shipment->awb) {
                continue;
            }

            try {
                $provider->track($shipment->awb);
            } catch (\Throwable $e) {
                Log::warning("TrackingSyncJob error for AWB {$shipment->awb}: " . $e->getMessage());
            }
        }
    }
}
