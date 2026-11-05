<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CommissionRuleScope;
use App\Models\CommissionRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CommissionRule>
 */
class CommissionRuleFactory extends Factory
{
    protected $model = CommissionRule::class;

    public function definition(): array
    {
        return [
            'name' => 'Standard Commission Rule',
            'scope' => CommissionRuleScope::GLOBAL,
            'category_id' => null,
            'seller_id' => null,
            'percent' => 10.00,
            'fixed_fee' => 0.00,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
            'priority' => 0,
            'is_active' => true,
        ];
    }
}
