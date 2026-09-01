<?php

namespace Database\Factories;

use App\Enums\FeeType;
use App\Models\Fee;
use App\Models\Matter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fee>
 */
class FeeFactory extends Factory
{
    protected $model = Fee::class;

    public function definition(): array
    {
        return [
            'matter_id' => Matter::factory(),
            'type' => FeeType::EXPERT_FEE,
            'amount' => fake()->numberBetween(1000, 20000),
            'date' => now()->subDays(5),
            'status' => 'unpaid',
        ];
    }

    /**
     * A deduction-type fee. Fee::saving() stores these negative, and they are
     * netted against the matter's revenue rather than becoming their own
     * incentive line.
     */
    public function officeShare(float $amount): static
    {
        return $this->state(fn () => ['type' => FeeType::OFFICE_SHARE, 'amount' => $amount]);
    }

    public function vat(float $amount): static
    {
        return $this->state(fn () => ['type' => FeeType::VAT, 'amount' => $amount]);
    }
}
