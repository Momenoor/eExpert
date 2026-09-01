<?php

namespace Database\Factories;

use App\Models\Type;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Type>
 */
class TypeFactory extends Factory
{
    protected $model = Type::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'active' => true,
            'incentive_trigger_type' => 'final_report_date',
        ];
    }

    /**
     * A type whose matters are imported while still in progress.
     */
    public function currentStatusImport(): static
    {
        return $this->state(fn () => [
            'allow_current_status_import' => true,
            'incentive_trigger_type' => 'fees_registered_date',
        ]);
    }
}
