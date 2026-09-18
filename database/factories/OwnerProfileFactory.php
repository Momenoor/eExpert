<?php

namespace Database\Factories;

use App\Models\OwnerProfile;
use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnerProfile>
 */
class OwnerProfileFactory extends Factory
{
    protected $model = OwnerProfile::class;

    public function definition(): array
    {
        return [
            'party_id' => Party::factory()->owner(),
            'identification_number' => fake()->unique()->numerify('784-####-#######-#'),
            'nationality' => fake()->country(),
            'unified_number' => fake()->numerify('##########'),
            'bank_name' => fake()->randomElement(['Emirates NBD', 'ADCB', 'Mashreq Bank', 'FAB']),
            'bank_account_no' => fake()->numerify('#############'),
            'iban' => 'AE'.fake()->numerify('#####################'),
        ];
    }
}
