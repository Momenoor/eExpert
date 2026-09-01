<?php

namespace Database\Factories;

use App\Enums\MatterCommissiong;
use App\Enums\MatterDifficulty;
use App\Models\Court;
use App\Models\Matter;
use App\Models\Type;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Matter>
 */
class MatterFactory extends Factory
{
    protected $model = Matter::class;

    public function definition(): array
    {
        return [
            'number' => (string) fake()->unique()->numberBetween(1, 9999),
            'year' => (string) now()->year,
            'court_id' => Court::factory(),
            'type_id' => Type::factory(),
            'difficulty' => MatterDifficulty::MEDIUM,
            'commissioning' => MatterCommissiong::INDIVIDUAL,
            'received_at' => now()->subDays(30),
            'distributed_at' => now()->subDays(28),
        ];
    }

    public function initialReported(): static
    {
        return $this->state(fn () => ['initial_report_at' => now()->subDays(10)]);
    }

    public function finalReported(): static
    {
        return $this->state(fn () => [
            'initial_report_at' => now()->subDays(10),
            'final_report_at' => now()->subDays(2),
        ]);
    }
}
