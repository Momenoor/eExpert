<?php

namespace Database\Factories;

use App\Enums\PMS\PropertyClassification;
use App\Enums\PMS\UnitStatus;
use App\Enums\PMS\UnitType;
use App\Models\Building;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        $type = fake()->randomElement(UnitType::cases());

        return [
            'building_id' => Building::factory(),
            'unit_number' => (string) fake()->unique()->numberBetween(100, 9999),
            'floor' => (string) fake()->numberBetween(1, 40),
            'rental_rate' => fake()->numberBetween(20000, 200000),
            'dewa_premise_number' => fake()->numerify('##########'),
            'property_classification' => $type->defaultClassification(),
            'unit_type' => $type,
            'status' => UnitStatus::VACANT,
        ];
    }

    public function residential(): static
    {
        return $this->state(fn (): array => [
            'unit_type' => UnitType::TWO_BEDROOM,
            'property_classification' => PropertyClassification::RESIDENTIAL,
        ]);
    }

    public function commercial(): static
    {
        return $this->state(fn (): array => [
            'unit_type' => UnitType::COMMERCIAL_OFFICE,
            'property_classification' => PropertyClassification::COMMERCIAL,
        ]);
    }
}
