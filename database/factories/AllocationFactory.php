<?php

namespace Database\Factories;

use App\Models\Allocation;
use App\Models\Fee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Allocation>
 */
class AllocationFactory extends Factory
{
    protected $model = Allocation::class;

    public function definition(): array
    {
        return [
            'fee_id' => Fee::factory(),
            'amount' => fake()->numberBetween(100, 5000),
            'date' => now()->subDay(),
        ];
    }
}
