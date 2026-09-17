<?php

namespace Database\Factories;

use App\Enums\PMS\ContractStatus;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    protected $model = Contract::class;

    public function definition(): array
    {
        return [
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'grace_period_days' => 0,
            'total_base_rent' => fake()->numberBetween(30000, 150000),
            'security_deposit_amount' => 0,
            'status' => ContractStatus::DRAFT,
        ];
    }
}
