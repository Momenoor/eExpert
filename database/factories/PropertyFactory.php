<?php

namespace Database\Factories;

use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    protected $model = Property::class;

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
