<?php

namespace App\Services\PMS;

use App\Enums\PMS\QuotationStatus;
use App\Models\Quotation;
use App\Models\Setting;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Generating a quotation and moving it through its own small lifecycle
 * (draft → sent → accepted/rejected, or expired). Kept out of the Filament
 * layer entirely, the same way `LeaveRequestService` keeps a decision out of
 * its Filament actions — a page collects input, this computes the figures
 * and enforces the transitions.
 */
class QuotationService
{
    /**
     * @param  array{
     *     party_id: int,
     *     units: list<array{unit_id: int, offered_rent: float|string}>,
     *     security_deposit?: float|string,
     *     number_of_installments?: int,
     *     validity_date: string,
     * }  $data
     */
    public function generate(array $data): Quotation
    {
        if ($data['units'] === []) {
            throw new RuntimeException('A quotation must offer at least one unit.');
        }

        $lines = $this->priceLines($data['units']);

        $baseRent = round(array_sum(array_column($lines, 'offered_rent')), 2);
        $vatAmount = round(array_sum(array_column($lines, 'vat_amount')), 2);
        $attestationFee = round((float) Setting::get('pms_attestation_fee_estimate', 0.0), 2);

        return DB::transaction(function () use ($data, $lines, $baseRent, $vatAmount, $attestationFee): Quotation {
            $quotation = Quotation::create([
                'party_id' => $data['party_id'],
                'base_rent' => $baseRent,
                'vat_amount' => $vatAmount,
                'attestation_fee_estimate' => $attestationFee,
                'total_amount' => round($baseRent + $vatAmount + $attestationFee, 2),
                'security_deposit' => (float) ($data['security_deposit'] ?? 0),
                'number_of_installments' => max(1, (int) ($data['number_of_installments'] ?? 1)),
                'validity_date' => $data['validity_date'],
                'status' => QuotationStatus::DRAFT,
            ]);

            foreach ($lines as $line) {
                $quotation->units()->attach($line['unit_id'], [
                    'offered_rent' => $line['offered_rent'],
                    'vat_amount' => $line['vat_amount'],
                ]);
            }

            return $quotation->load('units');
        });
    }

    /**
     * An even split of the total across the quoted number of installments,
     * with any rounding drift absorbed into the last one — informational
     * only at this stage, since real `Installment` rows belong to a Lease,
     * not a Quotation still being negotiated.
     *
     * @return list<float>
     */
    public function paymentSchedule(Quotation $quotation): array
    {
        $count = max(1, (int) $quotation->getAttribute('number_of_installments'));
        $total = (float) $quotation->getAttribute('total_amount');

        $amount = round($total / $count, 2);
        $schedule = array_fill(0, $count, $amount);
        $schedule[$count - 1] = round($total - array_sum(array_slice($schedule, 0, $count - 1)), 2);

        return $schedule;
    }

    public function send(Quotation $quotation): Quotation
    {
        if (! $quotation->isDraft()) {
            throw new RuntimeException('Only a draft quotation can be sent.');
        }

        $quotation->forceFill(['status' => QuotationStatus::SENT])->save();

        return $quotation;
    }

    public function accept(Quotation $quotation): Quotation
    {
        if (! $quotation->isSent()) {
            throw new RuntimeException('Only a sent quotation can be accepted.');
        }

        $quotation->forceFill(['status' => QuotationStatus::ACCEPTED])->save();

        return $quotation;
    }

    public function reject(Quotation $quotation): Quotation
    {
        if (! $quotation->isSent()) {
            throw new RuntimeException('Only a sent quotation can be rejected.');
        }

        $quotation->forceFill(['status' => QuotationStatus::REJECTED])->save();

        return $quotation;
    }

    public function expire(Quotation $quotation): Quotation
    {
        if (! in_array($quotation->getAttribute('status'), [QuotationStatus::DRAFT, QuotationStatus::SENT], true)) {
            throw new RuntimeException('Only a draft or sent quotation can expire.');
        }

        $quotation->forceFill(['status' => QuotationStatus::EXPIRED])->save();

        return $quotation;
    }

    /**
     * @param  list<array{unit_id: int, offered_rent: float|string}>  $units
     * @return list<array{unit_id: int, offered_rent: float, vat_amount: float}>
     */
    private function priceLines(array $units): array
    {
        $unitModels = Unit::whereIn('id', array_column($units, 'unit_id'))->get()->keyBy('id');

        return array_map(function (array $line) use ($unitModels): array {
            $unit = $unitModels->get($line['unit_id']);

            if ($unit === null) {
                throw new RuntimeException("Unit #{$line['unit_id']} does not exist.");
            }

            $rent = round((float) $line['offered_rent'], 2);

            return [
                'unit_id' => $unit->getKey(),
                'offered_rent' => $rent,
                'vat_amount' => round($rent * $unit->vatRate(), 2),
            ];
        }, $units);
    }
}
