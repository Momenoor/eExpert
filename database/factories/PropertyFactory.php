<?php

namespace Database\Factories;

use App\Enums\PMS\Emirate;
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
            'emirate' => fake()->randomElement(Emirate::cases()),
            'total_units' => fake()->numberBetween(10, 200),
            'year_built' => fake()->numberBetween(1990, 2025),
        ];
    }
}
