<?php

declare(strict_types=1);

namespace App\Services\Cargo;

use App\Enums\CargoStatus;
use App\Models\OrderReturn;
use App\Models\Setting;
use App\Services\Cargo\Contracts\LogisticsProvider;
use Illuminate\Support\Str;

class CargoService
{
    public function __construct(
        protected ?LogisticsProvider $provider = null
    ) {
        $this->provider = $provider ?? app(LogisticsProvider::class);
    }

    /**
     * Generate a unique tracking waybill number with configured prefix.
     */
    public function generateWaybill(): string
    {
        $prefix = (string) Setting::get('cargo_tracking_prefix', config('onestall.cargo_tracking_prefix', 'OSC-'));

        return $prefix . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
    }

    /**
     * Book a new Cargo logistics consignment for heavy/B2B/inter-city transport.
     *
     * @param array{origin_hub_id: int, dest_hub_id: int, weight_kg: float, parcels_count: int} $data
     * @return array{tracking_number: string, status: string}
     */
    public function bookConsignment(array $data): array
    {
        return [
            'tracking_number' => $this->generateWaybill(),
            'status' => CargoStatus::BOOKED->value,
        ];
    }

    /**
     * Schedule reverse pickup for an approved return consignment.
     *
     * @param OrderReturn $orderReturn
     * @return array{tracking_number: string, waybill: string, courier: string, status: string, estimated_pickup_at: string}
     */
    public function scheduleReversePickup(OrderReturn $orderReturn): array
    {
        if ($this->provider) {
            $result = $this->provider->createReversePickup($orderReturn);
            return [
                'tracking_number' => $result['tracking_number'] ?? $orderReturn->pickup_waybill,
                'waybill' => $result['waybill'] ?? $orderReturn->pickup_waybill,
                'courier' => $result['courier'] ?? 'OneStall Cargo Logistics',
                'status' => $result['status'] ?? 'reverse_pickup_scheduled',
                'estimated_pickup_at' => now()->addHours(24)->toIso8601String(),
            ];
        }

        $waybill = 'REV-' . $this->generateWaybill();
        $orderReturn->pickup_waybill = $waybill;
        $orderReturn->save();

        return [
            'tracking_number' => $waybill,
            'waybill' => $waybill,
            'courier' => 'OneStall Cargo Logistics',
            'status' => 'reverse_pickup_scheduled',
            'estimated_pickup_at' => now()->addHours(24)->toIso8601String(),
        ];
    }
}
