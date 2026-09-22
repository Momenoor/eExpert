<?php

namespace Database\Seeders;

use App\Enums\PMS\Emirate;
use App\Enums\PMS\LeasePartyRole;
use App\Enums\PMS\PropertyType;
use App\Models\Lease;
use App\Models\LeasePrintTemplate;
use App\Models\LeasePrintTemplatePage;
use App\Models\OwnerGroup;
use App\Models\OwnerGroupBankAccount;
use App\Models\OwnerProfile;
use App\Models\Party;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Services\MMS\PaymentService;
use App\Services\PMS\InstallmentGenerator;
use App\Services\PMS\LeaseService;
use App\Services\PMS\QuotationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Realistic, internally-consistent demo data covering every stage of the PMS
 * module — properties across both emirates it currently prints contracts
 * for, owners (both a single owner and an owner-group), tenants, a
 * quotation still in draft, one converted and attested into an active
 * lease with a generated instalment schedule, and one lease with a
 * manually-declared schedule (including a security deposit row) — so the
 * whole module has something to look at without needing real client data.
 *
 * This is NOT part of `ProductionDatabaseSeeder` — like
 * `PMSConditionTemplatesSeeder`'s statutory clause shells, this is example
 * business data, not framework scaffolding a fresh install needs. Run it
 * explicitly: `php artisan db:seed --class=PMSDemoSeeder`.
 */
class PMSDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PMSConditionTemplatesSeeder::class);
        $this->call(PMSPrintTemplatesSeeder::class);

        // Idempotent like every other seeder in this app — re-running this
        // command is a no-op once the demo data already exists, and a
        // failure partway through never leaves orphaned rows behind.
        if (Property::where('name', 'Al Majaz Business Center')->exists()) {
            return;
        }

        DB::transaction(function (): void {
            $sharjahCommercial = $this->sharjahCommercialProperty();
            $sharjahResidential = $this->sharjahResidentialProperty();
            $dubaiProperty = $this->dubaiProperty();

            $activeLease = $this->activeLeaseWithGeneratedSchedule($sharjahCommercial);
            $this->recordSamplePayments($activeLease);
            $this->activeLeaseWithManualSchedule($sharjahResidential);
            $this->draftLease($dubaiProperty);
            $this->terminatedLease($dubaiProperty);
            $this->draftQuotation($dubaiProperty);
            $this->samplePrintTemplateFields();
        });
    }

    private function sharjahCommercialProperty(): Property
    {
        $property = Property::create([
            'name' => 'Al Majaz Business Center',
            'emirate' => Emirate::SHARJAH,
            'address' => 'Al Majaz 3, Sharjah',
            'municipality' => 'Sharjah Municipality',
            'suburb' => 'Al Majaz',
            'area' => 'Al Majaz 3',
            'title_deed_number' => 'TD-SHJ-1001',
            'title_deed_date' => now()->subYears(5)->toDateString(),
            'plot_number' => '525(312-633)',
            'property_type' => PropertyType::BUILDING,
            'property_number' => 'P-1001',
            'total_units' => 12,
            'year_built' => 2015,
        ]);

        Unit::factory()->commercial()->create(['property_id' => $property->id, 'unit_number' => 'C-101', 'rental_rate' => 90000]);
        Unit::factory()->commercial()->create(['property_id' => $property->id, 'unit_number' => 'C-102', 'rental_rate' => 75000]);
        Unit::factory()->create(['property_id' => $property->id, 'unit_number' => 'C-103', 'rental_rate' => 60000]);

        $owner = OwnerProfile::factory()->create([
            'party_id' => Party::factory()->owner()->create(['name' => 'Ahmed Al Falasi'])->id,
        ]);
        $property->owners()->attach($owner->party_id, ['ownership_percentage' => 100]);

        return $property;
    }

    private function sharjahResidentialProperty(): Property
    {
        $property = Property::create([
            'name' => 'Al Khan Residence',
            'emirate' => Emirate::SHARJAH,
            'address' => 'Al Khan, Sharjah',
            'municipality' => 'Sharjah Municipality',
            'suburb' => 'Al Khan',
            'area' => 'Al Khan Corniche',
            'title_deed_number' => 'TD-SHJ-2002',
            'title_deed_date' => now()->subYears(8)->toDateString(),
            'plot_number' => '318(204-410)',
            'property_type' => PropertyType::TOWER,
            'property_number' => 'P-2002',
            'total_units' => 24,
            'year_built' => 2010,
        ]);

        Unit::factory()->residential()->create(['property_id' => $property->id, 'unit_number' => '204', 'rental_rate' => 45000, 'number_of_rooms' => 2]);
        Unit::factory()->residential()->create(['property_id' => $property->id, 'unit_number' => '305', 'rental_rate' => 55000, 'number_of_rooms' => 3]);
        Unit::factory()->residential()->create(['property_id' => $property->id, 'unit_number' => '410', 'rental_rate' => 38000, 'number_of_rooms' => 1]);

        // A shared estate — "Legal Heirs of Mahmoud Kalbat" — administering
        // this property as one group rather than as individual owners.
        $group = OwnerGroup::factory()->create(['name' => 'Legal Heirs of Mahmoud Kalbat']);
        $heirOne = OwnerProfile::factory()->create([
            'party_id' => Party::factory()->owner()->create(['name' => 'Fatima Kalbat'])->id,
            'owner_group_id' => $group->id,
            'is_primary' => true,
        ]);
        $heirTwo = OwnerProfile::factory()->create([
            'party_id' => Party::factory()->owner()->create(['name' => 'Khalid Kalbat'])->id,
            'owner_group_id' => $group->id,
        ]);
        $property->owners()->attach($heirOne->party_id, ['ownership_percentage' => 60]);
        $property->owners()->attach($heirTwo->party_id, ['ownership_percentage' => 40]);

        // The estate's own accounts — one default, one alternate — and the
        // property points at the default one for its rent.
        $defaultAccount = OwnerGroupBankAccount::factory()->create([
            'owner_group_id' => $group->id,
            'bank_name' => 'Emirates NBD',
            'is_default' => true,
        ]);
        OwnerGroupBankAccount::factory()->create([
            'owner_group_id' => $group->id,
            'bank_name' => 'Sharjah Islamic Bank',
        ]);
        $property->update(['owner_group_id' => $group->id, 'owner_group_bank_account_id' => $defaultAccount->id]);

        return $property;
    }

    private function dubaiProperty(): Property
    {
        $property = Property::create([
            'name' => 'Al Souq Al Kabeer Building',
            'emirate' => Emirate::DUBAI,
            'address' => 'Al Souq Al Kabeer, Dubai',
            'area' => 'Al Souq Al Kabeer',
            'plot_number' => '525(312-633)',
            'property_type' => PropertyType::BUILDING,
            'property_number' => 'P-3003',
            'total_units' => 20,
            'year_built' => 2005,
        ]);

        Unit::factory()->residential()->create(['property_id' => $property->id, 'unit_number' => '207', 'rental_rate' => 38000, 'premise_number' => '2874718841']);
        Unit::factory()->residential()->create(['property_id' => $property->id, 'unit_number' => '208', 'rental_rate' => 42000]);
        Unit::factory()->commercial()->create(['property_id' => $property->id, 'unit_number' => 'G-01', 'rental_rate' => 50000]);

        $owner = OwnerProfile::factory()->create([
            'party_id' => Party::factory()->owner()->create(['name' => 'Legal Heirs of the late Sayed Hashim'])->id,
        ]);
        $property->owners()->attach($owner->party_id, ['ownership_percentage' => 100]);

        return $property;
    }

    /**
     * A quotation converted to a lease and attested to ACTIVE, with an
     * evenly-split, auto-generated instalment schedule — the ordinary path
     * most leases in this app actually take.
     */
    private function activeLeaseWithGeneratedSchedule(Property $property): Lease
    {
        $unit = $property->units()->first();
        $tenant = Tenant::factory()->company()->create([
            'party_id' => Party::factory()->tenant()->create(['name' => 'Gulf Trading LLC'])->id,
        ]);

        $quotationService = app(QuotationService::class);
        $quotation = $quotationService->generate([
            'party_id' => $tenant->party_id,
            'units' => [['unit_id' => $unit->id, 'offered_rent' => (float) $unit->rental_rate]],
            'security_deposit' => 5000,
            'validity_date' => now()->addDays(14)->toDateString(),
        ]);
        $quotationService->send($quotation);
        $quotationService->accept($quotation);

        $leaseService = app(LeaseService::class);
        $lease = $leaseService->createFromQuotation($quotation->fresh());
        $leaseService->attest($lease, [
            'attestation_system' => 'sharjawai_sharjah',
            'attestation_serial_number' => 'SHJ-'.fake()->numerify('######'),
        ]);

        app(InstallmentGenerator::class)->generateSchedule($lease->fresh(), 4);

        return $lease->fresh();
    }

    /**
     * One instalment paid in full, another partially — so the payment
     * ledger (`installment_payments`) has more than one row per instalment
     * to show the individual collections behind a running total.
     */
    private function recordSamplePayments(Lease $lease): void
    {
        $installments = $lease->installments()->orderBy('due_date')->get();
        $paymentService = app(PaymentService::class);

        if ($installments->isEmpty()) {
            return;
        }

        $first = $installments->first();
        $paymentService->recordPayment($first, [
            'amount' => (float) $first->total_due_amount,
            'payment_method' => 'bank_transfer',
            'transaction_reference' => 'TRF-DEMO-001',
        ]);

        if ($installments->count() > 1) {
            $second = $installments->get(1);
            $half = round((float) $second->total_due_amount / 2, 2);

            $paymentService->recordPayment($second, [
                'amount' => $half,
                'payment_method' => 'cash',
            ]);
            $paymentService->recordPayment($second->fresh(), [
                'amount' => (float) $second->fresh()->balance_due,
                'payment_method' => 'cash',
            ]);
        }
    }

    /**
     * A lease whose instalments were declared by hand (the wizard's own
     * flow) rather than evenly split — including the security-deposit row
     * that's excluded from VAT.
     */
    private function activeLeaseWithManualSchedule(Property $property): void
    {
        $unit = $property->units()->skip(1)->first();
        $tenant = Tenant::factory()->create([
            'party_id' => Party::factory()->tenant()->create(['name' => 'Layla Al Marzooqi'])->id,
        ]);

        $leaseService = app(LeaseService::class);
        $lease = $leaseService->createFromRawInputs([
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->subDay()->toDateString(),
            'total_base_rent' => (float) $unit->rental_rate,
            'security_deposit_amount' => 5000,
            'government_contract_number' => 'SHJ-'.fake()->numerify('######'),
            'issue_date' => now()->toDateString(),
        ], [
            ['party_id' => $tenant->party_id, 'role' => LeasePartyRole::PRIMARY_TENANT->value],
        ], [$unit->id]);

        $rent = (float) $unit->rental_rate;
        $quarter = round($rent / 4, 2);

        app(InstallmentGenerator::class)->recordManualSchedule($lease, [
            ['payment_method' => 'post_dated_cheque', 'payment_date' => now()->addMonths(3)->toDateString(), 'amount' => $quarter, 'reference_number' => 'CHQ-001'],
            ['payment_method' => 'post_dated_cheque', 'payment_date' => now()->addMonths(6)->toDateString(), 'amount' => $quarter, 'reference_number' => 'CHQ-002'],
            ['payment_method' => 'post_dated_cheque', 'payment_date' => now()->addMonths(9)->toDateString(), 'amount' => $quarter, 'reference_number' => 'CHQ-003'],
            ['payment_method' => 'post_dated_cheque', 'payment_date' => now()->addMonths(12)->toDateString(), 'amount' => round($rent - ($quarter * 3), 2), 'reference_number' => 'CHQ-004'],
            ['payment_method' => 'cash', 'payment_date' => now()->toDateString(), 'amount' => 5000, 'is_security_deposit' => true],
        ]);
    }

    /**
     * A quotation still in draft — the earliest stage of the PMS pipeline,
     * so the module has something to show before attestation/instalments
     * even come into play.
     */
    private function draftQuotation(Property $property): void
    {
        $unit = $property->units()->first();
        $tenant = Tenant::factory()->create([
            'party_id' => Party::factory()->tenant()->create(['name' => 'Madhav Chaturvedi'])->id,
        ]);

        app(QuotationService::class)->generate([
            'party_id' => $tenant->party_id,
            'units' => [['unit_id' => $unit->id, 'offered_rent' => (float) $unit->rental_rate]],
            'security_deposit' => 5000,
            'validity_date' => now()->addDays(14)->toDateString(),
        ]);
    }

    /**
     * A lease still `DRAFT` — never submitted for attestation — so the
     * Edit Lease flow has something to correct in the demo data.
     */
    private function draftLease(Property $property): void
    {
        $unit = $property->units()->skip(1)->first();
        $tenant = Tenant::factory()->create([
            'party_id' => Party::factory()->tenant()->create(['name' => 'Youssef Al Ameri'])->id,
        ]);

        app(LeaseService::class)->createFromRawInputs([
            'start_date' => now()->addMonth()->toDateString(),
            'end_date' => now()->addMonth()->addYear()->subDay()->toDateString(),
            'total_base_rent' => (float) $unit->rental_rate,
        ], [
            ['party_id' => $tenant->party_id, 'role' => LeasePartyRole::PRIMARY_TENANT->value],
        ], [$unit->id]);
    }

    /**
     * A lease that ran its course and was ended — status variety beyond
     * "still active", and its unit released back to vacant.
     */
    private function terminatedLease(Property $property): void
    {
        $unit = $property->units()->skip(2)->first();
        $tenant = Tenant::factory()->create([
            'party_id' => Party::factory()->tenant()->create(['name' => 'Rania Al Suwaidi'])->id,
        ]);

        $leaseService = app(LeaseService::class);
        $lease = $leaseService->createFromRawInputs([
            'start_date' => now()->subYears(2)->toDateString(),
            'end_date' => now()->subYear()->subDay()->toDateString(),
            'total_base_rent' => (float) $unit->rental_rate,
        ], [
            ['party_id' => $tenant->party_id, 'role' => LeasePartyRole::PRIMARY_TENANT->value],
        ], [$unit->id]);

        $leaseService->submitForAttestation($lease);
        $leaseService->attest($lease, [
            'attestation_system' => 'ejari_dubai',
            'attestation_serial_number' => 'DXB-'.fake()->numerify('######'),
        ]);
        $leaseService->terminate($lease->fresh());
    }

    /**
     * Two field placements on the Sharjah Residential print template — so
     * the click-to-place builder has something to load and edit, rather
     * than every seeded template being an empty shell.
     */
    private function samplePrintTemplateFields(): void
    {
        $template = LeasePrintTemplate::where('contract_format', 'sharjah_residential')->first();

        if ($template === null) {
            return;
        }

        $page = LeasePrintTemplatePage::create([
            'lease_print_template_id' => $template->id,
            'page_number' => 1,
        ]);

        $page->fields()->createMany([
            ['field_key' => 'tenant_name', 'x_percent' => 15, 'y_percent' => 20],
            ['field_key' => 'government_contract_number', 'x_percent' => 60, 'y_percent' => 10],
        ]);
    }
}
