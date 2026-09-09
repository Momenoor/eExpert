<?php

namespace Database\Factories;

use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Party>
 */
class PartyFactory extends Factory
{
    protected $model = Party::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'role' => ['role' => ['party'], 'type' => ['plaintiff']],
        ];
    }

    /**
     * An assistant expert — the role shape every assistant query filters on.
     */
    public function assistant(): static
    {
        return $this->state(fn () => [
            'role' => ['role' => ['expert'], 'type' => ['assistant']],
        ]);
    }

    /**
     * A party on the payroll — the role the payroll and leave modules filter on.
     */
    public function employee(): static
    {
        return $this->state(fn () => [
            'role' => ['role' => ['employee'], 'type' => []],
        ]);
    }

    public function certifiedExpert(): static
    {
        return $this->state(fn () => [
            'role' => ['role' => ['expert'], 'type' => ['certified']],
        ]);
    }
}
