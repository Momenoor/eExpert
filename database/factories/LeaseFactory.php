<?php

namespace Database\Factories;

use App\Enums\PMS\LeaseStatus;
use App\Models\Lease;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lease>
 */
class LeaseFactory extends Factory
{
    protected $model = Lease::class;

    public function definition(): array
    {
        return [
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'grace_period_days' => 0,
            'total_base_rent' => fake()->numberBetween(30000, 150000),
            'security_deposit_amount' => 0,
            'status' => LeaseStatus::DRAFT,
        ];
    }
}
