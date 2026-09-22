<?php

namespace Database\Factories;

use App\Models\License;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<License>
 */
class LicenseFactory extends Factory
{
    protected $model = License::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'MIE-'.strtoupper($this->faker->bothify('?????-?????-?????-?????')),
            'fingerprint' => $this->faker->uuid(),
            'status' => 'active',
            'plan' => 'Standard',
            'expires_at' => null,
            'last_checked_at' => now(),
            'last_valid_at' => now(),
        ];
    }
}
