<?php

namespace Tests\Feature\PMS;

use App\Enums\PMS\ContractCategory;
use App\Enums\PMS\ContractType;
use App\Enums\PMS\Emirate;
use App\Enums\PMS\LeasePartyRole;
use App\Models\ConditionTemplate;
use App\Models\ConditionTemplateItem;
use App\Models\OwnerGroup;
use App\Models\OwnerProfile;
use App\Models\Party;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Services\PMS\LeasePrintFieldResolver;
use App\Services\PMS\LeaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The single place print-field values are resolved from a lease — this is
 * where regression coverage for "does the right value show up" now lives,
 * since the visual layout itself (an uploaded image) isn't something a
 * backend test can meaningfully assert on.
 */
class LeasePrintFieldResolverTest extends TestCase
{
    use RefreshDatabase;

    private LeasePrintFieldResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new LeasePrintFieldResolver;
    }

    public function test_it_resolves_contract_fields(): void
    {
        $unit = Unit::factory()->commercial()->create(['rental_rate' => 60000]);
        $tenant = Party::factory()->tenant()->create();

        $lease = app(LeaseService::class)->createFromRawInputs([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'total_base_rent' => 60000,
            'grace_period_days' => 15,
            'government_contract_number' => 'CN-2026-001',
            'issue_date' => '2026-01-01',
            'annual_rent' => 60000,
        ], [
            ['party_id' => $tenant->id, 'role' => LeasePartyRole::PRIMARY_TENANT->value],
        ], [$unit->id]);

        $this->assertSame('CN-2026-001', $this->resolver->resolve($lease, 'government_contract_number'));
        $this->assertSame('01/01/2026', $this->resolver->resolve($lease, 'issue_date'));
        $this->assertSame('01/01/2026', $this->resolver->resolve($lease, 'start_date'));
        $this->assertSame('31/12/2026', $this->resolver->resolve($lease, 'end_date'));
        $this->assertSame(ContractCategory::NEW->getLabel(), $this->resolver->resolve($lease, 'contract_category'));
        $this->assertSame('60,000.00 AED', $this->resolver->resolve($lease, 'total_base_rent'));
        $this->assertSame('60,000.00 AED', $this->resolver->resolve($lease, 'annual_rent'));
        $this->assertNull($this->resolver->resolve($lease, 'unknown_field_key'));
    }

    public function test_it_resolves_lessor_and_tenant_fields(): void
    {
        $property = Property::factory()->create();
        $unit = Unit::factory()->residential()->create(['property_id' => $property->id]);

        $ownerParty = Party::factory()->owner()->create(['name' => 'Ahmed Al Falasi', 'phone' => ['0501234567'], 'email' => ['owner@example.com']]);
        $ownerProfile = OwnerProfile::factory()->create(['party_id' => $ownerParty->id, 'identification_number' => '784-1111']);
        $property->owners()->attach($ownerParty->id, ['ownership_percentage' => 100]);

        $tenantParty = Party::factory()->tenant()->create(['name' => 'Madhav Chaturvedi', 'phone' => ['0507654321'], 'email' => ['tenant@example.com']]);
        Tenant::factory()->create(['party_id' => $tenantParty->id, 'identification_number' => '784-2222']);

        $lease = app(LeaseService::class)->createFromRawInputs([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'total_base_rent' => 40000,
        ], [
            ['party_id' => $tenantParty->id, 'role' => LeasePartyRole::PRIMARY_TENANT->value],
        ], [$unit->id]);

        $this->assertSame('Ahmed Al Falasi', $this->resolver->resolve($lease, 'owner_name'));
        $this->assertSame('Ahmed Al Falasi', $this->resolver->resolve($lease, 'landlord_name'));
        $this->assertSame('784-1111', $this->resolver->resolve($lease, 'lessor_identification_number'));
        $this->assertSame('0501234567', $this->resolver->resolve($lease, 'lessor_mobile'));
        $this->assertSame('owner@example.com', $this->resolver->resolve($lease, 'lessor_email'));
        $this->assertSame('1', $this->resolver->resolve($lease, 'number_of_lessors'));

        $this->assertSame('Madhav Chaturvedi', $this->resolver->resolve($lease, 'tenant_name'));
        $this->assertSame('784-2222', $this->resolver->resolve($lease, 'tenant_identification_number'));
        $this->assertSame('0507654321', $this->resolver->resolve($lease, 'tenant_mobile'));
        $this->assertSame('tenant@example.com', $this->resolver->resolve($lease, 'tenant_email'));
    }

    public function test_landlord_name_prefers_the_poa_signer_over_the_owner(): void
    {
        $property = Property::factory()->create();
        $unit = Unit::factory()->residential()->create(['property_id' => $property->id]);
        $ownerParty = Party::factory()->owner()->create(['name' => 'Ahmed Al Falasi']);
        $property->owners()->attach($ownerParty->id, ['ownership_percentage' => 100]);
        $tenant = Party::factory()->tenant()->create();

        $lease = app(LeaseService::class)->createFromRawInputs([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'total_base_rent' => 40000,
            'poa_name' => 'Khalid the Agent',
        ], [
            ['party_id' => $tenant->id, 'role' => LeasePartyRole::PRIMARY_TENANT->value],
        ], [$unit->id]);

        $this->assertSame('Ahmed Al Falasi', $this->resolver->resolve($lease, 'owner_name'));
        $this->assertSame('Khalid the Agent', $this->resolver->resolve($lease, 'landlord_name'));
    }

    public function test_owner_group_name_is_used_when_every_owner_shares_one(): void
    {
        $property = Property::factory()->create();
        $unit = Unit::factory()->residential()->create(['property_id' => $property->id]);

        $group = OwnerGroup::factory()->create(['name' => 'Legal Heirs of Mahmoud Kalbat']);
        $heir = OwnerProfile::factory()->create([
            'party_id' => Party::factory()->owner()->create()->id,
            'owner_group_id' => $group->id,
        ]);
        $property->owners()->attach($heir->party_id, ['ownership_percentage' => 100]);

        $tenant = Party::factory()->tenant()->create();
        $lease = app(LeaseService::class)->createFromRawInputs([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'total_base_rent' => 40000,
        ], [
            ['party_id' => $tenant->id, 'role' => LeasePartyRole::PRIMARY_TENANT->value],
        ], [$unit->id]);

        $this->assertSame('Legal Heirs of Mahmoud Kalbat', $this->resolver->resolve($lease, 'owner_name'));
    }

    public function test_it_resolves_property_and_unit_fields(): void
    {
        $property = Property::factory()->create([
            'name' => 'Al Majaz Business Center',
            'emirate' => Emirate::SHARJAH,
            'municipality' => 'Sharjah Municipality',
            'plot_number' => '525(312-633)',
        ]);
        $unit = Unit::factory()->commercial()->create([
            'property_id' => $property->id,
            'unit_number' => 'C-101',
            'area_sqm' => 85.5,
            'premise_number' => '123456',
        ]);
        $tenant = Party::factory()->tenant()->create();

        $lease = app(LeaseService::class)->createFromRawInputs([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'total_base_rent' => 40000,
        ], [
            ['party_id' => $tenant->id, 'role' => LeasePartyRole::PRIMARY_TENANT->value],
        ], [$unit->id]);

        $this->assertSame('Al Majaz Business Center', $this->resolver->resolve($lease, 'property_name'));
        $this->assertSame('Sharjah Municipality', $this->resolver->resolve($lease, 'property_municipality'));
        $this->assertSame(Emirate::SHARJAH->getLabel(), $this->resolver->resolve($lease, 'property_emirate'));
        $this->assertSame('525(312-633)', $this->resolver->resolve($lease, 'property_plot_number'));

        $this->assertSame('C-101', $this->resolver->resolve($lease, 'unit_number'));
        $this->assertSame('85.50', $this->resolver->resolve($lease, 'unit_area_sqm'));
        $this->assertSame('123456', $this->resolver->resolve($lease, 'unit_premise_number'));
    }

    public function test_special_conditions_resolve_from_the_leases_condition_template(): void
    {
        $unit = Unit::factory()->residential()->create();
        $tenant = Party::factory()->tenant()->create();

        $template = ConditionTemplate::create(['name' => 'Test Template', 'contract_format' => 'sharjah_residential_test']);
        ConditionTemplateItem::create([
            'condition_template_id' => $template->id,
            'section' => 'special',
            'sort_order' => 0,
            'text_en' => 'No pets allowed.',
            'text_ar' => 'لا يسمح بالحيوانات الأليفة.',
        ]);

        $lease = app(LeaseService::class)->createFromRawInputs([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'total_base_rent' => 40000,
            'condition_template_id' => $template->id,
        ], [
            ['party_id' => $tenant->id, 'role' => LeasePartyRole::PRIMARY_TENANT->value],
        ], [$unit->id]);

        $this->assertSame('No pets allowed.', $this->resolver->resolve($lease, 'special_conditions_en'));
        $this->assertSame('لا يسمح بالحيوانات الأليفة.', $this->resolver->resolve($lease, 'special_conditions_ar'));
    }

    public function test_enum_fields_print_in_the_language_chosen_for_the_field(): void
    {
        $unit = Unit::factory()->commercial()->create(['rental_type' => ContractType::SHOP]);
        $tenant = Party::factory()->tenant()->create();

        $lease = app(LeaseService::class)->createFromRawInputs([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'total_base_rent' => 60000,
            'multiple_rent_amount' => 'yes',
            'allow_multiple_licenses' => true,
        ], [
            ['party_id' => $tenant->id, 'role' => LeasePartyRole::PRIMARY_TENANT->value],
        ], [$unit->id]);

        app()->setLocale('en');

        // The contract type is not typed in — it comes from the unit.
        $this->assertSame('Shop', $this->resolver->resolve($lease, 'contract_type', 'en'));
        $this->assertSame('محل', $this->resolver->resolve($lease, 'contract_type', 'ar'));
        $this->assertSame('Yes', $this->resolver->resolve($lease, 'multiple_rent_amount', 'en'));
        $this->assertSame('نعم', $this->resolver->resolve($lease, 'multiple_rent_amount', 'ar'));
        $this->assertSame('Yes', $this->resolver->resolve($lease, 'allow_multiple_licenses', 'en'));
        $this->assertSame('نعم', $this->resolver->resolve($lease, 'allow_multiple_licenses', 'ar'));
        $this->assertSame('سنة واحدة', $this->resolver->resolve($lease, 'rent_duration', 'ar'));
        $this->assertSame('1 Year', $this->resolver->resolve($lease, 'rent_duration', 'en'));

        // No language set keeps the app's current one, and the app locale
        // is restored after a per-field override.
        $this->assertSame('Shop', $this->resolver->resolve($lease, 'contract_type'));
        $this->assertSame('en', app()->getLocale());
    }

    public function test_a_language_never_changes_a_value_that_is_not_translatable(): void
    {
        $unit = Unit::factory()->residential()->create();
        $tenant = Party::factory()->tenant()->create();

        $lease = app(LeaseService::class)->createFromRawInputs([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'total_base_rent' => 60000,
            'government_contract_number' => 'CN-7',
        ], [
            ['party_id' => $tenant->id, 'role' => LeasePartyRole::PRIMARY_TENANT->value],
        ], [$unit->id]);

        $this->assertSame('CN-7', $this->resolver->resolve($lease, 'government_contract_number', 'ar'));
        $this->assertSame('01/01/2026', $this->resolver->resolve($lease, 'start_date', 'ar'));
    }
}
