<?php

namespace Database\Factories;

use App\Models\OwnerGroup;
use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnerGroup>
 */
class OwnerGroupFactory extends Factory
{
    protected $model = OwnerGroup::class;

    public function definition(): array
    {
        $name = 'Legal Heirs of '.fake()->lastName();

        return [
            'party_id' => Party::factory()->state([
                'name' => $name,
                'role' => ['role' => ['owner_group'], 'type' => []],
            ]),
            'name' => $name,
        ];
    }
}
