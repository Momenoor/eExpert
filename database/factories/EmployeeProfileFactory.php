<?php

namespace Database\Factories;

use App\Models\EmployeeProfile;
use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeProfile>
 */
class EmployeeProfileFactory extends Factory
{
    protected $model = EmployeeProfile::class;

    public function definition(): array
    {
        return [
            'party_id' => Party::factory()->employee(),
            'date_of_joining' => fake()->date(),
        ];
    }
}
