<?php

namespace Tests\Feature\PMS;

use App\Enums\PMS\PrintDocumentType;
use App\Filament\Pms\Resources\LeasePrintTemplates\Pages\CreateLeasePrintTemplate;
use App\Models\LeasePrintTemplate;
use App\Models\OwnerGroup;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeasePrintTemplateResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('pms'));
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $this->actingAs($admin);
    }

    public function test_creating_a_lease_contract_template_keeps_its_own_contract_format(): void
    {
        Livewire::test(CreateLeasePrintTemplate::class)
            ->fillForm([
                'name' => 'Sharjah Residential',
                'document_type' => PrintDocumentType::LEASE_CONTRACT->value,
                'contract_format' => 'sharjah_residential',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $template = LeasePrintTemplate::sole();
        $this->assertSame('sharjah_residential', $template->contract_format);
        $this->assertNull($template->owner_group_id);
    }

    public function test_creating_a_tax_invoice_template_scopes_it_to_the_chosen_owner_group(): void
    {
        $group = OwnerGroup::factory()->create();

        Livewire::test(CreateLeasePrintTemplate::class)
            ->fillForm([
                'name' => 'Kalbat Estate Invoice',
                'document_type' => PrintDocumentType::TAX_INVOICE->value,
                'owner_group_id' => $group->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $template = LeasePrintTemplate::sole();
        $this->assertSame($group->id, $template->owner_group_id);
        $this->assertSame(PrintDocumentType::TAX_INVOICE, $template->document_type);
        $this->assertSame(
            LeasePrintTemplate::syntheticFormatFor($group->id, PrintDocumentType::TAX_INVOICE),
            $template->contract_format,
        );
    }

    public function test_a_group_cannot_have_two_tax_invoice_templates(): void
    {
        $group = OwnerGroup::factory()->create();
        LeasePrintTemplate::create([
            'name' => 'First',
            'document_type' => PrintDocumentType::TAX_INVOICE->value,
            'owner_group_id' => $group->id,
            'contract_format' => LeasePrintTemplate::syntheticFormatFor($group->id, PrintDocumentType::TAX_INVOICE),
        ]);

        Livewire::test(CreateLeasePrintTemplate::class)
            ->fillForm([
                'name' => 'Second',
                'document_type' => PrintDocumentType::TAX_INVOICE->value,
                'owner_group_id' => $group->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['owner_group_id']);

        $this->assertSame(1, LeasePrintTemplate::count());
    }

    public function test_a_group_can_have_both_a_tax_invoice_and_a_receivable_receipt_template(): void
    {
        $group = OwnerGroup::factory()->create();
        LeasePrintTemplate::create([
            'name' => 'Invoice',
            'document_type' => PrintDocumentType::TAX_INVOICE->value,
            'owner_group_id' => $group->id,
            'contract_format' => LeasePrintTemplate::syntheticFormatFor($group->id, PrintDocumentType::TAX_INVOICE),
        ]);

        Livewire::test(CreateLeasePrintTemplate::class)
            ->fillForm([
                'name' => 'Receipt',
                'document_type' => PrintDocumentType::RECEIVABLE_RECEIPT->value,
                'owner_group_id' => $group->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(2, LeasePrintTemplate::count());
    }
}
