<?php

namespace Tests\Feature\PMS;

use App\Enums\PMS\LeasePartyRole;
use App\Enums\PMS\PrintDocumentType;
use App\Models\Lease;
use App\Models\LeasePrintTemplate;
use App\Models\LeasePrintTemplatePage;
use App\Models\OwnerGroup;
use App\Models\Party;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Services\PMS\InstallmentGenerator;
use App\Services\PMS\LeaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The Tax Invoice and Receivable Receipt handed to the tenant once a lease
 * exists — rendered on the owning property's owner group's own letterhead
 * template, exactly like the lease contract print but scoped by group
 * instead of contract format.
 */
class LeaseFinancialDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $this->actingAs($admin);
    }

    private function leaseWithInstallments(Property $property): Lease
    {
        $unit = Unit::factory()->residential()->create(['property_id' => $property->id, 'rental_rate' => 40000]);
        $tenant = Party::factory()->tenant()->create();

        $lease = app(LeaseService::class)->createFromRawInputs([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'total_base_rent' => 40000,
            'government_contract_number' => 'CN-2026-042',
        ], [
            ['party_id' => $tenant->id, 'role' => LeasePartyRole::PRIMARY_TENANT->value],
        ], [$unit->id]);

        app(InstallmentGenerator::class)->generateSchedule($lease->fresh(), 2);

        return $lease->fresh();
    }

    public function test_tax_invoices_show_a_not_configured_message_when_the_group_has_no_invoice_template(): void
    {
        $group = OwnerGroup::factory()->create();
        $property = Property::factory()->create(['owner_group_id' => $group->id]);
        $lease = $this->leaseWithInstallments($property);

        $response = $this->get(route('pms.leases.tax-invoices', ['lease' => $lease]));

        $response->assertOk();
        $response->assertSee("hasn't been set up yet", false);
    }

    public function test_tax_invoices_render_one_per_installment_on_the_groups_own_template(): void
    {
        $group = OwnerGroup::factory()->create();
        $property = Property::factory()->create(['owner_group_id' => $group->id]);
        $lease = $this->leaseWithInstallments($property);

        $template = LeasePrintTemplate::create([
            'name' => 'Test Invoice',
            'document_type' => PrintDocumentType::TAX_INVOICE->value,
            'owner_group_id' => $group->id,
            'contract_format' => LeasePrintTemplate::syntheticFormatFor($group->id, PrintDocumentType::TAX_INVOICE),
        ]);
        $page = LeasePrintTemplatePage::create([
            'lease_print_template_id' => $template->id,
            'page_number' => 1,
            'background_image_path' => 'fake.png',
        ]);
        $page->fields()->create(['field_key' => 'invoice_number', 'x_percent' => 10, 'y_percent' => 10]);

        $response = $this->get(route('pms.leases.tax-invoices', ['lease' => $lease]));

        $response->assertOk();
        $response->assertDontSee("hasn't been set up yet", false);

        foreach ($lease->installments as $installment) {
            $response->assertSee($installment->tax_invoice_serial);
        }
    }

    public function test_receivable_receipt_lists_every_installment_in_its_schedule_table(): void
    {
        $group = OwnerGroup::factory()->create();
        $property = Property::factory()->create(['owner_group_id' => $group->id]);
        $lease = $this->leaseWithInstallments($property);

        $response = $this->get(route('pms.leases.receivable-receipt', ['lease' => $lease]));

        $response->assertOk();

        foreach ($lease->installments as $installment) {
            $response->assertSee(number_format((float) $installment->total_due_amount, 2));
        }
    }

    public function test_a_placed_installments_table_field_renders_sized_and_replaces_the_fallback_table(): void
    {
        $group = OwnerGroup::factory()->create();
        $property = Property::factory()->create(['owner_group_id' => $group->id]);
        $lease = $this->leaseWithInstallments($property);

        $template = LeasePrintTemplate::create([
            'name' => 'Test Receipt',
            'document_type' => PrintDocumentType::RECEIVABLE_RECEIPT->value,
            'owner_group_id' => $group->id,
            'contract_format' => LeasePrintTemplate::syntheticFormatFor($group->id, PrintDocumentType::RECEIVABLE_RECEIPT),
        ]);
        $page = LeasePrintTemplatePage::create([
            'lease_print_template_id' => $template->id,
            'page_number' => 1,
            'background_image_path' => 'fake.png',
        ]);
        $page->fields()->create([
            'field_key' => 'installments_table',
            'x_percent' => 10,
            'y_percent' => 30,
            'width_percent' => 70,
            'height_percent' => 40,
        ]);

        $response = $this->get(route('pms.leases.receivable-receipt', ['lease' => $lease]));

        $response->assertOk();
        $response->assertSee('class="field-table"', false);
        $response->assertSee('left: 10.000%', false);
        $response->assertSee('width: 70.000%', false);
        $response->assertSee('height: 40.000%', false);

        foreach ($lease->installments as $installment) {
            $response->assertSee(number_format((float) $installment->total_due_amount, 2));
        }

        // The plain fallback table is not also rendered.
        $response->assertDontSee('class="schedule"', false);
    }

    public function test_a_placed_installments_table_fields_column_widths_render_as_a_colgroup(): void
    {
        $group = OwnerGroup::factory()->create();
        $property = Property::factory()->create(['owner_group_id' => $group->id]);
        $lease = $this->leaseWithInstallments($property);

        $template = LeasePrintTemplate::create([
            'name' => 'Test Receipt',
            'document_type' => PrintDocumentType::RECEIVABLE_RECEIPT->value,
            'owner_group_id' => $group->id,
            'contract_format' => LeasePrintTemplate::syntheticFormatFor($group->id, PrintDocumentType::RECEIVABLE_RECEIPT),
        ]);
        $page = LeasePrintTemplatePage::create([
            'lease_print_template_id' => $template->id,
            'page_number' => 1,
            'background_image_path' => 'fake.png',
        ]);
        $page->fields()->create([
            'field_key' => 'installments_table',
            'x_percent' => 10,
            'y_percent' => 30,
            'width_percent' => 70,
            'height_percent' => 40,
            // due_date/net/vat set explicitly; total_due_amount/payment_status
            // left blank to split the leftover 40% evenly (20% each).
            'column_widths' => ['due_date' => 20, 'net_amount' => 20, 'vat_amount' => 20],
        ]);

        $response = $this->get(route('pms.leases.receivable-receipt', ['lease' => $lease]));

        $response->assertOk();
        $response->assertSee('<colgroup>', false);
        $response->assertSeeInOrder([
            'width: 20%;',
            'width: 20%;',
            'width: 20%;',
            'width: 20%;',
            'width: 20%;',
        ], false);
    }

    public function test_an_installments_table_field_placed_in_arabic_prints_arabic_headers_and_drops_hidden_columns(): void
    {
        $group = OwnerGroup::factory()->create();
        $property = Property::factory()->create(['owner_group_id' => $group->id]);
        $lease = $this->leaseWithInstallments($property);

        $template = LeasePrintTemplate::create([
            'name' => 'Test Receipt',
            'document_type' => PrintDocumentType::RECEIVABLE_RECEIPT->value,
            'owner_group_id' => $group->id,
            'contract_format' => LeasePrintTemplate::syntheticFormatFor($group->id, PrintDocumentType::RECEIVABLE_RECEIPT),
        ]);
        $page = LeasePrintTemplatePage::create([
            'lease_print_template_id' => $template->id,
            'page_number' => 1,
            'background_image_path' => 'fake.png',
        ]);
        $page->fields()->create([
            'field_key' => 'installments_table',
            'x_percent' => 10,
            'y_percent' => 30,
            'language' => 'ar',
            'rtl' => true,
            'hidden_columns' => ['payment_status'],
        ]);

        $response = $this->get(route('pms.leases.receivable-receipt', ['lease' => $lease]));

        $response->assertOk();
        $response->assertSee('dir="rtl"', false);
        $response->assertSee(__('Due Date', [], 'ar'));
        $response->assertDontSee(__('Status', [], 'ar'));
        $response->assertDontSee('>Status<', false);
    }

    public function test_payment_description_field_resolves_on_both_documents(): void
    {
        $group = OwnerGroup::factory()->create();
        $property = Property::factory()->create(['owner_group_id' => $group->id, 'name' => 'Marina Tower']);
        $lease = $this->leaseWithInstallments($property);
        $unitNumber = $lease->units->first()->unit_number;

        $expected = __('Rental Payment for unit :unit - Building :building for period from :start until :end', [
            'unit' => $unitNumber,
            'building' => 'Marina Tower',
            'start' => '01/01/2026',
            'end' => '31/12/2026',
        ]);

        $invoiceTemplate = LeasePrintTemplate::create([
            'name' => 'Test Invoice',
            'document_type' => PrintDocumentType::TAX_INVOICE->value,
            'owner_group_id' => $group->id,
            'contract_format' => LeasePrintTemplate::syntheticFormatFor($group->id, PrintDocumentType::TAX_INVOICE),
        ]);
        $invoicePage = LeasePrintTemplatePage::create([
            'lease_print_template_id' => $invoiceTemplate->id,
            'page_number' => 1,
            'background_image_path' => 'fake.png',
        ]);
        $invoicePage->fields()->create(['field_key' => 'payment_description', 'x_percent' => 10, 'y_percent' => 10]);

        $this->get(route('pms.leases.tax-invoices', ['lease' => $lease]))
            ->assertOk()
            ->assertSee($expected);

        $receiptTemplate = LeasePrintTemplate::create([
            'name' => 'Test Receipt',
            'document_type' => PrintDocumentType::RECEIVABLE_RECEIPT->value,
            'owner_group_id' => $group->id,
            'contract_format' => LeasePrintTemplate::syntheticFormatFor($group->id, PrintDocumentType::RECEIVABLE_RECEIPT),
        ]);
        $receiptPage = LeasePrintTemplatePage::create([
            'lease_print_template_id' => $receiptTemplate->id,
            'page_number' => 1,
            'background_image_path' => 'fake.png',
        ]);
        $receiptPage->fields()->create(['field_key' => 'payment_description', 'x_percent' => 10, 'y_percent' => 10]);

        $this->get(route('pms.leases.receivable-receipt', ['lease' => $lease]))
            ->assertOk()
            ->assertSee($expected);
    }

    public function test_the_two_documents_never_pick_up_the_lease_contract_template(): void
    {
        // A lease-contract template for the property's format exists, but
        // has nothing to do with this owner group's invoice/receipt.
        LeasePrintTemplate::create([
            'name' => 'Sharjah Residential',
            'contract_format' => 'sharjah_residential',
        ]);

        $group = OwnerGroup::factory()->create();
        $property = Property::factory()->create(['owner_group_id' => $group->id]);
        $lease = $this->leaseWithInstallments($property);

        $this->get(route('pms.leases.tax-invoices', ['lease' => $lease]))
            ->assertSee("hasn't been set up yet", false);
    }
}
