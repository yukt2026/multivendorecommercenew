<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DeliveryAssignmentStatus;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryPartner;
use App\Models\FulfilmentOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeliveryAssignment>
 */
class DeliveryAssignmentFactory extends Factory
{
    protected $model = DeliveryAssignment::class;

    public function definition(): array
    {
        return [
            'fulfilment_order_id' => FulfilmentOrder::factory(),
            'shipment_id' => null,
            'delivery_partner_id' => DeliveryPartner::factory(),
            'status' => DeliveryAssignmentStatus::ASSIGNED,
            'assigned_at' => now(),
            'accepted_at' => null,
            'picked_up_at' => null,
            'delivered_at' => null,
        ];
    }
}
