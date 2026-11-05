<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ShipmentStatus;
use App\Models\FulfilmentOrder;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Shipment>
 */
class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        return [
            'fulfilment_order_id' => FulfilmentOrder::factory(),
            'shipment_number' => 'SHP-' . strtoupper(fake()->unique()->bothify('####-????')),
            'provider' => 'onestall_cargo',
            'awb' => 'AWB' . fake()->unique()->numerify('##########'),
            'courier' => 'OneStall Cargo Express',
            'service' => 'Surface',
            'status' => ShipmentStatus::PENDING,
            'pickup_status' => 'pending',
            'label_url' => 'labels/' . fake()->word() . '.pdf',
            'cod_amount' => 0.00,
            'weight' => fake()->randomFloat(2, 0.5, 10.0),
            'charges' => 60.00,
        ];
    }
}
