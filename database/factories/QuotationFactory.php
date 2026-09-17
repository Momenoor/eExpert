<?php

namespace Database\Factories;

use App\Enums\PMS\QuotationStatus;
use App\Models\Party;
use App\Models\Quotation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quotation>
 */
class QuotationFactory extends Factory
{
    protected $model = Quotation::class;

    public function definition(): array
    {
        return [
            'party_id' => Party::factory()->tenant(),
            'base_rent' => 0,
            'vat_amount' => 0,
            'attestation_fee_estimate' => 0,
            'total_amount' => 0,
            'security_deposit' => 0,
            'number_of_installments' => 1,
            'validity_date' => now()->addDays(14)->toDateString(),
            'status' => QuotationStatus::DRAFT,
        ];
    }
}
