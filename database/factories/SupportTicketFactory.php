<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    protected $model = SupportTicket::class;

    public function definition(): array
    {
        return [
            'ticket_number' => 'TCK-' . strtoupper($this->faker->bothify('??###')),
            'user_id' => User::factory(),
            'seller_id' => null,
            'order_id' => null,
            'subject' => $this->faker->sentence(4),
            'category' => $this->faker->randomElement(['order_inquiry', 'refund_request', 'delivery_delay', 'product_quality']),
            'priority' => $this->faker->randomElement(TicketPriority::cases()),
            'status' => TicketStatus::OPEN,
            'assigned_to' => null,
        ];
    }
}
