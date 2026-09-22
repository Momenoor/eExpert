<?php

namespace Tests\Feature\PMS;

use App\Enums\PMS\ContractCategory;
use App\Enums\PMS\Emirate;
use App\Enums\PMS\LeasePartyRole;
use App\Enums\PMS\PrintDocumentType;
use App\Enums\PMS\PropertyClassification;
use App\Enums\PMS\UnitType;
use App\Models\ConditionTemplate;
use App\Models\ConditionTemplateItem;
use App\Models\Lease;
use App\Models\OwnerGroup;
use App\Models\OwnerProfile;
use App\Models\Party;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Services\PMS\InstallmentGenerator;
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

    private function leaseOnUnit(Unit $unit, ?Party $tenant = null): Lease
    {
        return app(LeaseService::class)->createFromRawInputs([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'total_base_rent' => 40000,
        ], [
            ['party_id' => ($tenant ?? Party::factory()->tenant()->create())->id, 'role' => LeasePartyRole::PRIMARY_TENANT->value],
        ], [$unit->id]);
    }

    public function test_lessor_primary_owner_name_shows_the_flagged_primary_with_no_suffix_for_a_single_owner_group(): void
    {
        $property = Property::factory()->create();
        $unit = Unit::factory()->residential()->create(['property_id' => $property->id]);

        $group = OwnerGroup::factory()->create();
        $property->update(['owner_group_id' => $group->id]);
        OwnerProfile::factory()->create(['owner_group_id' => $group->id, 'is_primary' => true, 'party_id' => Party::factory()->owner()->create(['name' => 'Mahmoud Kalbat'])->id]);

        $lease = $this->leaseOnUnit($unit);

        $this->assertSame('Mahmoud Kalbat', $this->resolver->resolve($lease, 'lessor_primary_owner_name'));
        $this->assertSame($group->name, $this->resolver->resolve($lease, 'lessor_group_name'));
    }

    public function test_lessor_primary_owner_name_is_tagged_heirs_partners_when_the_group_has_several_members(): void
    {
        $property = Property::factory()->create();
        $unit = Unit::factory()->residential()->create(['property_id' => $property->id]);

        $group = OwnerGroup::factory()->create();
        $property->update(['owner_group_id' => $group->id]);
        $primaryParty = Party::factory()->owner()->create(['name' => 'Mahmoud Kalbat']);
        OwnerProfile::factory()->create(['owner_group_id' => $group->id, 'is_primary' => true, 'party_id' => $primaryParty->id]);
        OwnerProfile::factory()->create(['owner_group_id' => $group->id, 'is_primary' => false]);

        $lease = $this->leaseOnUnit($unit);

        app()->setLocale('en');
        $this->assertSame('Mahmoud Kalbat (Heirs/Partners)', $this->resolver->resolve($lease, 'lessor_primary_owner_name'));
    }

    public function test_only_one_owner_profile_per_group_can_be_primary(): void
    {
        $group = OwnerGroup::factory()->create();
        $first = OwnerProfile::factory()->create(['owner_group_id' => $group->id, 'is_primary' => true]);
        $second = OwnerProfile::factory()->create(['owner_group_id' => $group->id, 'is_primary' => true]);

        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue($second->fresh()->is_primary);
        $this->assertSame($second->id, $group->primaryProfile()->id);
    }

    public function test_number_of_lessors_counts_the_owner_groups_members(): void
    {
        $property = Property::factory()->create();
        $unit = Unit::factory()->residential()->create(['property_id' => $property->id]);

        $group = OwnerGroup::factory()->create();
        $property->update(['owner_group_id' => $group->id]);
        OwnerProfile::factory()->count(3)->create(['owner_group_id' => $group->id]);

        $lease = $this->leaseOnUnit($unit);

        $this->assertSame('3', $this->resolver->resolve($lease, 'number_of_lessors'));
    }

    public function test_contract_type_marks_flag_exactly_the_units_own_classification(): void
    {
        $residential = Unit::factory()->residential()->create();
        $commercial = Unit::factory()->commercial()->create();
        $industrial = Unit::factory()->create([
            'unit_type' => UnitType::WAREHOUSE,
            'property_classification' => PropertyClassification::INDUSTRIAL,
        ]);

        $residentialLease = $this->leaseOnUnit($residential);
        $commercialLease = $this->leaseOnUnit($commercial);
        $industrialLease = $this->leaseOnUnit($industrial);

        $this->assertSame('X', $this->resolver->resolve($residentialLease, 'contract_type_mark_residential'));
        $this->assertNull($this->resolver->resolve($residentialLease, 'contract_type_mark_commercial'));
        $this->assertNull($this->resolver->resolve($residentialLease, 'contract_type_mark_industrial'));

        $this->assertSame('X', $this->resolver->resolve($commercialLease, 'contract_type_mark_commercial'));
        $this->assertNull($this->resolver->resolve($commercialLease, 'contract_type_mark_residential'));

        $this->assertSame('X', $this->resolver->resolve($industrialLease, 'contract_type_mark_industrial'));
        $this->assertNull($this->resolver->resolve($industrialLease, 'contract_type_mark_commercial'));
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
        $unit = Unit::factory()->commercial()->create();
        $tenant = Party::factory()->tenant()->create();

        $lease = app(LeaseService::class)->createFromRawInputs([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'total_base_rent' => 60000,
            'contract_type' => 'shop',
            'multiple_rent_amount' => 'yes',
            'allow_multiple_licenses' => true,
        ], [
            ['party_id' => $tenant->id, 'role' => LeasePartyRole::PRIMARY_TENANT->value],
        ], [$unit->id]);

        app()->setLocale('en');

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

    public function test_tax_invoice_fields_resolve_from_the_given_installment_not_the_lease(): void
    {
        $unit = Unit::factory()->commercial()->create(['rental_rate' => 40000]);
        $tenant = Party::factory()->tenant()->create();

        $lease = app(LeaseService::class)->createFromRawInputs([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'total_base_rent' => 40000,
        ], [
            ['party_id' => $tenant->id, 'role' => LeasePartyRole::PRIMARY_TENANT->value],
        ], [$unit->id]);

        app(InstallmentGenerator::class)->generateSchedule($lease->fresh(), 2);
        $lease = $lease->fresh();
        $installments = $lease->installments;

        $first = $installments->first();
        $second = $installments->last();

        $this->assertSame($first->tax_invoice_serial, $this->resolver->resolve($lease, 'invoice_number', null, $first));
        $this->assertSame($second->tax_invoice_serial, $this->resolver->resolve($lease, 'invoice_number', null, $second));
        $this->assertNotSame($first->tax_invoice_serial, $second->tax_invoice_serial);

        $this->assertNull($this->resolver->resolve($lease, 'invoice_number'));
    }

    public function test_receivable_receipt_fields_total_every_installment_on_the_lease(): void
    {
        $unit = Unit::factory()->residential()->create(['rental_rate' => 60000]);
        $tenant = Party::factory()->tenant()->create();

        $lease = app(LeaseService::class)->createFromRawInputs([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'total_base_rent' => 60000,
        ], [
            ['party_id' => $tenant->id, 'role' => LeasePartyRole::PRIMARY_TENANT->value],
        ], [$unit->id]);

        app(InstallmentGenerator::class)->generateSchedule($lease->fresh(), 3);
        $lease = $lease->fresh();

        $this->assertSame('3', $this->resolver->resolve($lease, 'number_of_installments'));
        $this->assertSame('60,000.00 AED', $this->resolver->resolve($lease, 'total_receivable_amount'));
        $this->assertSame('RCP-'.$lease->id, $this->resolver->resolve($lease, 'receipt_number'));
    }

    public function test_available_fields_are_filtered_by_document_type(): void
    {
        $contractFields = LeasePrintFieldResolver::availableFieldsFor(PrintDocumentType::LEASE_CONTRACT);
        $invoiceFields = LeasePrintFieldResolver::availableFieldsFor(PrintDocumentType::TAX_INVOICE);
        $receiptFields = LeasePrintFieldResolver::availableFieldsFor(PrintDocumentType::RECEIVABLE_RECEIPT);

        $this->assertArrayNotHasKey('Tax Invoice', $contractFields);
        $this->assertArrayNotHasKey('Receivable Receipt', $contractFields);

        $this->assertArrayHasKey('Tax Invoice', $invoiceFields);
        $this->assertArrayHasKey('Tenant', $invoiceFields);
        $this->assertArrayNotHasKey('Receivable Receipt', $invoiceFields);
        $this->assertArrayNotHasKey('Special Conditions', $invoiceFields);

        $this->assertArrayHasKey('Receivable Receipt', $receiptFields);
        $this->assertArrayNotHasKey('Tax Invoice', $receiptFields);
    }
}
