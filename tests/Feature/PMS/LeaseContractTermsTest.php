<?php

namespace Tests\Feature\PMS;

use App\Enums\PMS\ContractType;
use App\Enums\PMS\LeasePartyRole;
use App\Enums\PMS\UnitType;
use App\Enums\PMS\YesNo;
use App\Filament\Pms\Resources\Leases\Pages\CreateLease;
use App\Models\Lease;
use App\Models\Party;
use App\Models\Unit;
use App\Models\User;
use App\Services\PMS\LeaseService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The lease's own terms that are worked out rather than typed in: a full
 * one-year end date, annual rent from period + base rent, the contract type
 * from the unit's definition, and the yes/no multiple-rent flag.
 */
class LeaseContractTermsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_full_year_ends_the_day_before_the_anniversary(): void
    {
        $this->assertSame('2026-12-31', Lease::fullYearEnd('2026-01-01')->toDateString());
        $this->assertSame('2027-06-14', Lease::fullYearEnd('2026-06-15')->toDateString());
        $this->assertSame('2029-02-28', Lease::fullYearEnd('2028-02-29')->toDateString());
    }

    public function test_annual_rent_equals_the_base_rent_for_one_full_year(): void
    {
        $this->assertSame(60000.0, Lease::annualRentFor('2026-01-01', '2026-12-31', 60000));
        // A leap year is still one full year.
        $this->assertSame(60000.0, Lease::annualRentFor('2028-01-01', '2028-12-31', 60000));
    }

    public function test_annual_rent_scales_a_shorter_or_longer_period_up_or_down_to_twelve_months(): void
    {
        $this->assertSame(120000.0, Lease::annualRentFor('2026-01-01', '2026-06-30', 60000));
        $this->assertSame(60000.0, Lease::annualRentFor('2026-01-01', '2027-12-31', 120000));
    }

    public function test_annual_rent_for_an_odd_period_is_prorated_by_days(): void
    {
        // 73 days is a fifth of a 365-day year.
        $this->assertSame(50000.0, Lease::annualRentFor('2026-01-01', '2026-03-14', 10000));
    }

    public function test_annual_rent_is_blank_until_dates_and_rent_are_known(): void
    {
        $this->assertNull(Lease::annualRentFor(null, '2026-12-31', 60000));
        $this->assertNull(Lease::annualRentFor('2026-01-01', '2026-12-31', null));
    }

    public function test_a_drafted_lease_calculates_annual_rent_and_takes_its_contract_type_from_the_unit(): void
    {
        $unit = Unit::factory()->residential()->create(['rental_type' => ContractType::BACHELORS]);

        $lease = app(LeaseService::class)->createFromRawInputs([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'total_base_rent' => 48000,
            // Whatever a caller sends for these is ignored — they are derived.
            'annual_rent' => 1,
            'contract_type' => 'shop',
        ], [
            ['party_id' => Party::factory()->tenant()->create()->id, 'role' => LeasePartyRole::PRIMARY_TENANT->value],
        ], [$unit->id]);

        $this->assertSame('48000.00', $lease->annual_rent);
        $this->assertSame(ContractType::BACHELORS, $lease->contract_type);
    }

    public function test_updating_a_draft_recalculates_annual_rent_and_contract_type(): void
    {
        $family = Unit::factory()->residential()->create(['rental_type' => ContractType::FAMILY]);
        $office = Unit::factory()->commercial()->create();

        $service = app(LeaseService::class);
        $lease = $service->createFromRawInputs([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'total_base_rent' => 60000,
        ], [
            ['party_id' => Party::factory()->tenant()->create()->id, 'role' => LeasePartyRole::PRIMARY_TENANT->value],
        ], [$family->id]);

        $updated = $service->updateDraft($lease, ['end_date' => '2026-06-30'], [
            ['party_id' => $lease->primaryTenant()->party_id, 'role' => LeasePartyRole::PRIMARY_TENANT->value],
        ], [$office->id]);

        $this->assertSame('120000.00', $updated->annual_rent);
        $this->assertSame(ContractType::OFFICE, $updated->contract_type);
    }

    public function test_multiple_rent_amount_is_a_yes_no_that_defaults_to_no(): void
    {
        $unit = Unit::factory()->residential()->create();
        $tenants = [['party_id' => Party::factory()->tenant()->create()->id, 'role' => LeasePartyRole::PRIMARY_TENANT->value]];
        $dates = ['start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'total_base_rent' => 60000];

        $plain = app(LeaseService::class)->createFromRawInputs($dates, $tenants, [$unit->id]);
        $multiple = app(LeaseService::class)->createFromRawInputs($dates + ['multiple_rent_amount' => 'yes'], $tenants, [$unit->id]);

        $this->assertSame(YesNo::NO, $plain->fresh()->multiple_rent_amount);
        $this->assertSame(YesNo::YES, $multiple->fresh()->multiple_rent_amount);
    }

    public function test_the_rent_duration_of_a_full_year_reads_as_one_year(): void
    {
        app()->setLocale('en');

        $lease = Lease::factory()->make(['start_date' => '2026-01-01', 'end_date' => '2026-12-31']);

        $this->assertSame('1 Year', $lease->rentDuration());
    }

    public function test_a_unit_type_only_offers_its_own_rental_types(): void
    {
        $this->assertSame(
            [ContractType::FAMILY, ContractType::BACHELORS, ContractType::LABOUR, ContractType::EMPLOYEES],
            ContractType::forUnitType(UnitType::APARTMENT),
        );
        $this->assertContains(ContractType::SHOP, ContractType::forUnitType(UnitType::SHOP));
        $this->assertNotContains(ContractType::FAMILY, ContractType::forUnitType(UnitType::WAREHOUSE));

        $this->assertSame(ContractType::WAREHOUSE, ContractType::defaultForUnitType(UnitType::WAREHOUSE));
        $this->assertNull(ContractType::defaultForUnitType(UnitType::APARTMENT));
    }

    public function test_the_wizard_fills_a_full_year_end_date_and_the_annual_rent(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('pms'));
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $this->actingAs($admin);

        $unit = Unit::factory()->residential()->create(['rental_type' => ContractType::EMPLOYEES]);

        Livewire::test(CreateLease::class)
            ->fillForm(['property_id' => $unit->property_id])
            ->fillForm(['total_base_rent' => 60000])
            ->fillForm(['start_date' => '2026-01-01'])
            ->assertSchemaStateSet([
                'end_date' => '2026-12-31',
                'annual_rent' => '60000.00',
            ]);
    }
}
