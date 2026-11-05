<?php

declare(strict_types=1);

namespace App\Services\Cargo\Contracts;

use App\Models\FulfilmentOrder;
use App\Models\NdrCase;
use App\Models\OrderReturn;
use App\Models\Shipment;

interface LogisticsProvider
{
    /**
     * Create a new shipment consignment for a fulfilment order package.
     *
     * @param FulfilmentOrder $fulfilmentOrder
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function createShipment(FulfilmentOrder $fulfilmentOrder, array $options = []): array;

    /**
     * Generate or assign an Air Waybill (AWB) number and shipping label for a shipment.
     *
     * @param Shipment $shipment
     * @return array<string, mixed>
     */
    public function generateAwb(Shipment $shipment): array;

    /**
     * Query available courier service rates and transit SLAs for origin/destination pincodes.
     *
     * @param array{origin_pincode: string, dest_pincode: string, weight_kg: float, cod_amount?: float} $params
     * @return array<int, array<string, mixed>>
     */
    public function selectCourierRates(array $params): array;

    /**
     * Request physical carrier pickup from origin warehouse / store hub.
     *
     * @param Shipment $shipment
     * @param array<string, mixed> $pickupDetails
     * @return array<string, mixed>
     */
    public function requestPickup(Shipment $shipment, array $pickupDetails = []): array;

    /**
     * Fetch real-time live milestone tracking events for an AWB.
     *
     * @param string $awb
     * @return array<string, mixed>
     */
    public function track(string $awb): array;

    /**
     * Cancel an active consignment before manifest or dispatch.
     *
     * @param Shipment $shipment
     * @param string $reason
     * @return array<string, mixed>
     */
    public function cancelShipment(Shipment $shipment, string $reason = ''): array;

    /**
     * Create a reverse pickup consignment for an approved customer return.
     *
     * @param OrderReturn $orderReturn
     * @return array<string, mixed>
     */
    public function createReversePickup(OrderReturn $orderReturn): array;

    /**
     * Fetch available NDR (Non-Delivery Report) resolution actions from the carrier.
     *
     * @param Shipment $shipment
     * @return array<string, mixed>
     */
    public function fetchNdrActions(Shipment $shipment): array;

    /**
     * Transmit customer / seller NDR resolution action (reattempt, change address, RTO).
     *
     * @param NdrCase $ndrCase
     * @param string $action 'reattempt', 'change_address', 'rto'
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function submitNdrAction(NdrCase $ndrCase, string $action, array $params = []): array;

    /**
     * Fetch courier COD remittance reconciliation data.
     *
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function fetchCodRemittanceData(array $filters = []): array;
}
