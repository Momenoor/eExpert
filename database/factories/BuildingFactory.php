<?php

namespace Database\Factories;

use App\Models\Building;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Building>
 */
class BuildingFactory extends Factory
{
    protected $model = Building::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company().' Tower',
            'address' => fake()->streetAddress(),
            'city' => fake()->randomElement(['Dubai', 'Abu Dhabi', 'Sharjah', 'Ajman']),
            'total_units' => fake()->numberBetween(10, 200),
            'year_built' => fake()->numberBetween(1990, 2025),
        ];
    }
}
