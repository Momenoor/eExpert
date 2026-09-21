<?php

namespace Database\Factories;

use App\Models\OwnerGroup;
use App\Models\OwnerGroupBankAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnerGroupBankAccount>
 */
class OwnerGroupBankAccountFactory extends Factory
{
    protected $model = OwnerGroupBankAccount::class;

    public function definition(): array
    {
        return [
            'owner_group_id' => OwnerGroup::factory(),
            'bank_name' => fake()->randomElement(['Emirates NBD', 'ADCB', 'Mashreq', 'Sharjah Islamic Bank']),
            'account_no' => (string) fake()->numerify('##########'),
            'iban' => 'AE'.fake()->numerify('#####################'),
            'is_default' => false,
        ];
    }
}
