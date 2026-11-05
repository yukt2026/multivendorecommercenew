<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\CODRemittanceStatus;
use App\Enums\PaymentMethod;
use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Models\CodRemittance;
use App\Models\FulfilmentOrder;
use App\Models\Inventory;
use App\Models\NdrCase;
use App\Models\RtoCase;
use App\Models\Shipment;
use App\Models\ShipmentTracking;
use App\Services\Cargo\CargoClient;
use App\Services\Inventory\InventoryService;
use App\Services\Order\OrderStateMachine;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CargoWebhookController extends Controller
{
    public function __construct(
        protected CargoClient $cargoClient,
        protected OrderStateMachine $stateMachine,
        protected InventoryService $inventoryService
    ) {}

    /**
     * Handle incoming webhook notifications from OneStall Cargo logistics platform.
     */
    public function handle(Request $request): JsonResponse
    {
        $signature = $request->header('X-Cargo-Signature');
        $rawPayload = $request->getContent();

        // 1. Validate signature in live/production mode
        if (!$this->cargoClient->isFakeMode() || !empty($signature)) {
            if (!$this->cargoClient->verifyWebhookSignature($rawPayload, $signature)) {
                Log::warning('Cargo webhook rejected: invalid signature');
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid webhook signature.',
                ], 401);
            }
        }

        $event = $request->input('event');
        $data = $request->input('data', []);
        $awb = $data['awb'] ?? null;
        $shipmentNumber = $data['shipment_number'] ?? null;

        Log::info("Cargo webhook received event '{$event}' for AWB: {$awb}");

        $shipment = Shipment::query()
            ->when($awb, fn($q) => $q->where('awb', $awb))
            ->when(!$awb && $shipmentNumber, fn($q) => $q->where('shipment_number', $shipmentNumber))
            ->with(['fulfilmentOrder.items.variant', 'fulfilmentOrder.order'])
            ->first();

        if (!$shipment) {
            Log::info("Cargo webhook: no matching shipment found for AWB: {$awb} / Ref: {$shipmentNumber}");
            return response()->json([
                'success' => true,
                'message' => 'Webhook received, but shipment not found locally.',
            ]);
        }

        DB::transaction(function () use ($event, $data, $shipment) {
            $fo = $shipment->fulfilmentOrder;
            $order = $fo?->order;
            $timestamp = !empty($data['timestamp']) ? Carbon::parse($data['timestamp']) : now();
            $location = $data['location'] ?? 'Logistics Transit Hub';
            $activity = $data['activity'] ?? $data['remarks'] ?? "Status updated to {$event}";

            // 1. Append Tracking Milestone
            ShipmentTracking::create([
                'shipment_id' => $shipment->id,
                'status' => $event,
                'location' => $location,
                'event_time' => $timestamp,
                'raw' => $data,
            ]);

            // 2. Handle Event Specific Actions
            switch ($event) {
                case 'shipment.picked_up':
                    $shipment->status = ShipmentStatus::PICKED_UP;
                    $shipment->pickup_status = 'completed';
                    $shipment->save();

                    if ($fo && strtolower($fo->status) !== 'delivered') {
                        $this->advanceFulfilmentOrder($fo, 'picked_up', 'Picked up by Cargo carrier');
                    }
                    break;

                case 'shipment.in_transit':
                    $shipment->status = ShipmentStatus::IN_TRANSIT;
                    $shipment->save();

                    if ($fo && strtolower($fo->status) !== 'delivered') {
                        $this->advanceFulfilmentOrder($fo, 'in_transit', 'In transit to destination city');
                    }
                    break;

                case 'shipment.out_for_delivery':
                    $shipment->status = ShipmentStatus::OUT_FOR_DELIVERY;
                    $shipment->save();

                    if ($fo && strtolower($fo->status) !== 'delivered') {
                        $this->advanceFulfilmentOrder($fo, 'out_for_delivery', 'Out for delivery to customer');
                    }
                    break;

                case 'shipment.delivered':
                    $shipment->status = ShipmentStatus::DELIVERED;
                    $shipment->save();

                    if ($fo && strtolower($fo->status) !== 'delivered') {
                        $this->advanceFulfilmentOrder($fo, 'delivered', 'Delivered successfully by carrier');
                    }

                    // Handle COD Collection
                    $isCod = ($order?->payment_method === PaymentMethod::COD)
                        || ($order?->payment_method?->value === 'cod')
                        || ((string) $order?->payment_method === 'cod')
                        || ($shipment->cod_amount > 0);
                    if ($isCod && $fo) {
                        CodRemittance::firstOrCreate(
                            ['shipment_id' => $shipment->id],
                            [
                                'seller_id' => $fo->seller_id,
                                'amount' => $shipment->cod_amount > 0 ? (float) $shipment->cod_amount : (float) ($fo->total ?? $fo->total_amount),
                                'status' => CODRemittanceStatus::COLLECTED,
                                'collected_at' => $timestamp,
                                'reference' => $data['utr_reference'] ?? null,
                            ]
                        );
                    }
                    break;

                case 'shipment.ndr':
                case 'shipment.undelivered':
                    $shipment->status = ShipmentStatus::NDR_RAISED;
                    $shipment->save();

                    NdrCase::updateOrCreate(
                        ['shipment_id' => $shipment->id],
                        [
                            'reason' => $data['reason'] ?? $data['ndr_reason'] ?? 'Customer not reachable / House locked',
                            'attempts' => (int) ($data['attempt_number'] ?? 1),
                            'status' => 'pending',
                        ]
                    );
                    break;

                case 'shipment.rto_initiated':
                    $shipment->status = ShipmentStatus::RTO_INITIATED;
                    $shipment->save();

                    RtoCase::firstOrCreate(
                        ['shipment_id' => $shipment->id],
                        [
                            'reason' => $data['reason'] ?? 'Failed delivery re-attempts; returned to origin hub',
                            'status' => 'initiated',
                            'initiated_at' => $timestamp,
                        ]
                    );
                    break;

                case 'shipment.rto_delivered':
                    $shipment->status = ShipmentStatus::RTO_DELIVERED;
                    $shipment->save();

                    $rto = RtoCase::where('shipment_id', $shipment->id)->first();
                    if ($rto) {
                        $rto->status = 'delivered_to_origin';
                        $rto->delivered_at = $timestamp;
                        $rto->save();
                    }

                    // Restock returned inventory
                    if ($fo) {
                        foreach ($fo->items as $item) {
                            if ($item->variant_id) {
                                $inv = Inventory::where('variant_id', $item->variant_id)
                                    ->where('location_id', $fo->inventory_location_id ?? 1)
                                    ->first() 
                                    ?? Inventory::where('variant_id', $item->variant_id)->first();

                                if ($inv) {
                                    $this->inventoryService->restock(
                                        $inv,
                                        (int) $item->qty,
                                        "Restocked from RTO Shipment #{$shipment->shipment_number}",
                                        $shipment
                                    );
                                }
                            }
                        }
                    }
                    break;

                case 'cod.remitted':
                    $remittance = CodRemittance::where('shipment_id', $shipment->id)->first();
                    if ($remittance) {
                        $remittance->status = CODRemittanceStatus::REMITTED;
                        $remittance->remitted_at = $timestamp;
                        $remittance->reference = $data['utr_reference'] ?? $remittance->reference ?? 'UTR-REMIT-' . strtoupper(Str::random(8));
                        $remittance->save();
                    }
                    break;
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Cargo webhook processed successfully.',
            'event' => $event,
            'awb' => $awb,
        ]);
    }

    /**
     * Advance fulfilment order through state machine transitions to target status.
     */
    protected function advanceFulfilmentOrder(FulfilmentOrder $fo, string $targetStatus, string $note): void
    {
        $pipeline = ['packed', 'ready_to_ship', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered'];
        $current = strtolower($fo->status ?? 'placed');
        $target = strtolower($targetStatus);

        if ($current === $target) {
            return;
        }

        $currentIdx = array_search($current, $pipeline);
        $targetIdx = array_search($target, $pipeline);

        if ($currentIdx !== false && $targetIdx !== false && $currentIdx < $targetIdx) {
            for ($i = $currentIdx + 1; $i <= $targetIdx; $i++) {
                try {
                    $fo = $this->stateMachine->transitionFulfilmentOrder($fo, $pipeline[$i], $note);
                } catch (\Throwable $e) {
                    Log::warning("Order transition to {$pipeline[$i]} failed: " . $e->getMessage());
                    break;
                }
            }
        } else {
            try {
                $this->stateMachine->transitionFulfilmentOrder($fo, $target, $note);
            } catch (\Throwable $e) {
                Log::warning("Direct order transition to {$target} failed: " . $e->getMessage());
            }
        }
    }
}
