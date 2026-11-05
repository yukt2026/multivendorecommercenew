<?php

declare(strict_types=1);

namespace App\Services\Cargo;

use App\Enums\PaymentMethod;
use App\Enums\ShipmentStatus;
use App\Models\FulfilmentOrder;
use App\Models\NdrCase;
use App\Models\OrderReturn;
use App\Models\RtoCase;
use App\Models\Shipment;
use App\Models\ShipmentTracking;
use App\Services\Cargo\Contracts\LogisticsProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OneStallCargoProvider implements LogisticsProvider
{
    public function __construct(
        protected CargoClient $client
    ) {}

    /**
     * Get underlying CargoClient instance.
     */
    public function getClient(): CargoClient
    {
        return $this->client;
    }

    /**
     * Create a new shipment consignment for a fulfilment order.
     */
    public function createShipment(FulfilmentOrder $fulfilmentOrder, array $options = []): array
    {
        return DB::transaction(function () use ($fulfilmentOrder, $options) {
            $order = $fulfilmentOrder->order;
            $store = $fulfilmentOrder->store;
            $seller = $fulfilmentOrder->seller;

            // Existing active shipment check
            $existing = Shipment::where('fulfilment_order_id', $fulfilmentOrder->id)
                ->whereNotIn('status', [ShipmentStatus::CANCELLED, ShipmentStatus::FAILED])
                ->first();

            if ($existing && $existing->awb) {
                return [
                    'success' => true,
                    'shipment' => $existing,
                    'awb' => $existing->awb,
                    'status' => $existing->status->value,
                ];
            }

            $shipmentNumber = 'SHP-' . date('Ymd') . '-' . strtoupper(Str::random(6));

            // Origin address (Store or Seller Hub)
            $origin = [
                'name' => $store?->name ?? $seller?->company_name ?? 'OneStall Hub',
                'phone' => $store?->phone ?? $seller?->user?->phone ?? '9876543210',
                'line1' => $store?->address ?? 'Plot 10, Industrial Hub',
                'city' => $store?->city ?? 'Bengaluru',
                'state' => $store?->state ?? 'Karnataka',
                'pincode' => $store?->pincode ?? '560038',
            ];

            // Destination address
            $destAddress = $order?->customerAddress;
            $snapshot = $order?->address_snapshot ?? [];
            $destination = [
                'name' => $destAddress?->name ?? $snapshot['name'] ?? $order?->customer?->user?->name ?? 'Customer',
                'phone' => $destAddress?->phone ?? $snapshot['phone'] ?? '9876543210',
                'line1' => $destAddress?->line1 ?? $snapshot['line1'] ?? 'Delivery Address',
                'city' => $destAddress?->city ?? $snapshot['city'] ?? 'Bengaluru',
                'state' => $destAddress?->state ?? $snapshot['state'] ?? 'Karnataka',
                'pincode' => $destAddress?->pincode ?? $snapshot['pincode'] ?? '560001',
            ];

            // Weight calculation
            $totalWeight = 0.0;
            foreach ($fulfilmentOrder->items as $item) {
                $itemWeight = (float) ($item->variant?->weight_grams ?? 500) / 1000.0;
                $totalWeight += ($itemWeight * (int) $item->qty);
            }
            $totalWeight = max(0.5, round($totalWeight, 2));

            // COD handling
            $isCod = ($order?->payment_method === PaymentMethod::COD)
                || ($order?->payment_method?->value === 'cod');
            $codAmount = $isCod ? (float) ($fulfilmentOrder->total ?? $fulfilmentOrder->total_amount) : 0.00;

            $payload = [
                'shipment_number' => $shipmentNumber,
                'fulfilment_order_number' => $fulfilmentOrder->fulfilment_order_number,
                'origin' => $origin,
                'destination' => $destination,
                'weight_kg' => $totalWeight,
                'is_cod' => $isCod,
                'cod_amount' => $codAmount,
                'courier' => $options['courier'] ?? 'OneStall Express Surface',
                'service' => $options['service'] ?? 'surface',
                'items_count' => $fulfilmentOrder->items->count(),
                'declared_value' => (float) $fulfilmentOrder->subtotal,
            ];

            $apiResult = $this->client->createShipment($payload);

            $shipment = Shipment::create([
                'fulfilment_order_id' => $fulfilmentOrder->id,
                'shipment_number' => $shipmentNumber,
                'provider' => 'onestall_cargo',
                'awb' => $apiResult['awb'] ?? null,
                'courier' => $apiResult['courier'] ?? $payload['courier'],
                'service' => $apiResult['service'] ?? $payload['service'],
                'status' => ShipmentStatus::MANIFEST_CREATED,
                'pickup_status' => 'pending',
                'label_url' => $apiResult['label_url'] ?? null,
                'cod_amount' => $codAmount,
                'weight' => $totalWeight,
                'charges' => (float) ($apiResult['charges'] ?? 120.00),
            ]);

            // Milestone tracking entry
            ShipmentTracking::create([
                'shipment_id' => $shipment->id,
                'status' => 'manifest_created',
                'location' => $origin['city'],
                'event_time' => now(),
                'raw' => $apiResult,
            ]);

            return [
                'success' => true,
                'shipment' => $shipment,
                'awb' => $shipment->awb,
                'status' => $shipment->status->value,
                'label_url' => $shipment->label_url,
            ];
        });
    }

    /**
     * Generate or assign an AWB & shipping label.
     */
    public function generateAwb(Shipment $shipment): array
    {
        $result = $this->client->generateAwb($shipment->shipment_number, [
            'courier' => $shipment->courier,
        ]);

        if (!empty($result['awb'])) {
            $shipment->awb = $result['awb'];
        }
        if (!empty($result['label_url'])) {
            $shipment->label_url = $result['label_url'];
        }
        $shipment->save();

        return $result;
    }

    /**
     * Query courier rates.
     */
    public function selectCourierRates(array $params): array
    {
        return $this->client->getRates($params);
    }

    /**
     * Request carrier pickup.
     */
    public function requestPickup(Shipment $shipment, array $pickupDetails = []): array
    {
        $fo = $shipment->fulfilmentOrder;
        $store = $fo?->store;

        $payload = array_merge([
            'shipment_number' => $shipment->shipment_number,
            'awb' => $shipment->awb,
            'pickup_pincode' => $store?->pincode ?? '560038',
            'pickup_address' => $store?->address ?? 'Warehouse',
            'contact_phone' => $store?->phone ?? '9876543210',
            'packages_count' => 1,
            'total_weight_kg' => (float) $shipment->weight,
        ], $pickupDetails);

        $result = $this->client->requestPickup($payload);

        if (!empty($result['success'])) {
            $shipment->pickup_status = 'scheduled';
            $shipment->save();
        }

        return $result;
    }

    /**
     * Track live events and synchronize tracking timeline.
     */
    public function track(string $awb): array
    {
        $result = $this->client->trackShipment($awb);
        $shipment = Shipment::where('awb', $awb)->first();

        if ($shipment && !empty($result['events'])) {
            foreach ($result['events'] as $event) {
                $status = $event['status'] ?? 'in_transit';
                $location = $event['location'] ?? 'Hub';
                $time = !empty($event['timestamp']) ? \Carbon\Carbon::parse($event['timestamp']) : now();

                // Prevent duplicate tracking events
                $exists = ShipmentTracking::where('shipment_id', $shipment->id)
                    ->where('status', $status)
                    ->where('location', $location)
                    ->exists();

                if (!$exists) {
                    ShipmentTracking::create([
                        'shipment_id' => $shipment->id,
                        'status' => $status,
                        'location' => $location,
                        'event_time' => $time,
                        'raw' => $event,
                    ]);
                }
            }

            // Sync shipment status enum
            $currentStatus = $result['current_status'] ?? null;
            if ($currentStatus) {
                $this->mapCourierStatusToShipment($shipment, $currentStatus);
            }
        }

        return $result;
    }

    /**
     * Cancel consignment.
     */
    public function cancelShipment(Shipment $shipment, string $reason = ''): array
    {
        $result = $this->client->cancelShipment($shipment->shipment_number, $reason);

        $shipment->status = ShipmentStatus::CANCELLED;
        $shipment->save();

        ShipmentTracking::create([
            'shipment_id' => $shipment->id,
            'status' => 'cancelled',
            'location' => 'Origin Hub',
            'event_time' => now(),
            'raw' => ['reason' => $reason],
        ]);

        return $result;
    }

    /**
     * Schedule reverse pickup consignment for an approved return.
     */
    public function createReversePickup(OrderReturn $orderReturn): array
    {
        $order = $orderReturn->order;
        $snapshot = $order?->address_snapshot ?? [];

        $payload = [
            'return_number' => $orderReturn->return_number,
            'pickup_address' => [
                'name' => $order?->customer?->user?->name ?? 'Customer',
                'line1' => $snapshot['line1'] ?? 'Customer Address',
                'city' => $snapshot['city'] ?? 'City',
                'pincode' => $snapshot['pincode'] ?? '560001',
                'phone' => $snapshot['phone'] ?? '9876543210',
            ],
            'item_title' => $orderReturn->orderItem?->title ?? 'Product',
            'quantity' => $orderReturn->orderItem?->qty ?? 1,
        ];

        $result = $this->client->createReversePickup($payload);

        $orderReturn->pickup_waybill = $result['waybill'] ?? ('REV-' . date('Ymd') . '-' . strtoupper(Str::random(6)));
        $orderReturn->status = \App\Enums\ReturnStatus::REVERSE_PICKUP_SCHEDULED;
        $orderReturn->save();

        return $result;
    }

    /**
     * Fetch NDR actions.
     */
    public function fetchNdrActions(Shipment $shipment): array
    {
        return $this->client->getNdrActions((string) $shipment->awb);
    }

    /**
     * Submit customer/seller resolution action for an NDR case.
     */
    public function submitNdrAction(NdrCase $ndrCase, string $action, array $params = []): array
    {
        $shipment = $ndrCase->shipment;
        if (!$shipment) {
            throw new \InvalidArgumentException('NDR Case does not have an associated shipment.');
        }

        $result = $this->client->submitNdrAction((string) $shipment->awb, $action, $params);

        $ndrCase->status = $action === 'rto' ? 'rto_requested' : 'reattempt_scheduled';
        $ndrCase->customer_action = $action;
        $ndrCase->action_notes = $params['remarks'] ?? "Action '{$action}' submitted.";
        $ndrCase->save();

        if ($action === 'rto') {
            $shipment->status = ShipmentStatus::RTO_INITIATED;
            $shipment->save();

            RtoCase::firstOrCreate(
                ['shipment_id' => $shipment->id],
                [
                    'reason' => $params['remarks'] ?? $ndrCase->reason ?? 'RTO initiated via NDR instruction',
                    'status' => 'initiated',
                    'initiated_at' => now(),
                ]
            );
        }

        return $result;
    }

    /**
     * Fetch courier COD remittance reconciliation data.
     */
    public function fetchCodRemittanceData(array $filters = []): array
    {
        return $this->client->getCodRemittanceData($filters);
    }

    /**
     * Helper to map raw courier status string to ShipmentStatus.
     */
    protected function mapCourierStatusToShipment(Shipment $shipment, string $courierStatus): void
    {
        $mapped = match (strtolower($courierStatus)) {
            'manifest_created', 'manifested', 'booked' => ShipmentStatus::MANIFEST_CREATED,
            'picked_up', 'inbound_scanned' => ShipmentStatus::PICKED_UP,
            'in_transit', 'linehaul' => ShipmentStatus::IN_TRANSIT,
            'out_for_delivery' => ShipmentStatus::OUT_FOR_DELIVERY,
            'delivered' => ShipmentStatus::DELIVERED,
            'ndr', 'ndr_raised', 'undelivered' => ShipmentStatus::NDR_RAISED,
            'rto_initiated', 'return_initiated' => ShipmentStatus::RTO_INITIATED,
            'rto_delivered', 'returned_to_origin' => ShipmentStatus::RTO_DELIVERED,
            'cancelled' => ShipmentStatus::CANCELLED,
            default => $shipment->status,
        };

        if ($shipment->status !== $mapped) {
            $shipment->status = $mapped;
            $shipment->save();
        }
    }
}
