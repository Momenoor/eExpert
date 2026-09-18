<?php

namespace Database\Factories;

use App\Enums\PMS\TenantIdentificationType;
use App\Enums\PMS\TenantType;
use App\Models\Party;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'party_id' => Party::factory()->tenant(),
            'tenant_type' => TenantType::PERSON,
            'nationality' => fake()->country(),
            'identification_type' => TenantIdentificationType::EMIRATES_ID,
            'identification_number' => fake()->unique()->numerify('784-####-#######-#'),
            'unified_number' => fake()->numerify('##########'),
        ];
    }

    public function company(): static
    {
        return $this->state(fn (): array => [
            'tenant_type' => TenantType::COMPANY,
            'identification_type' => TenantIdentificationType::TRADE_LICENSE,
            'identification_number' => fake()->unique()->numerify('CN-######'),
            'trn' => fake()->numerify('###############'),
        ]);
    }
}
