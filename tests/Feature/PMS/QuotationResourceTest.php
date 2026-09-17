<?php

namespace Tests\Feature\PMS;

use App\Enums\PMS\QuotationStatus;
use App\Filament\Resources\Quotations\Pages\CreateQuotation;
use App\Filament\Resources\Quotations\Pages\ViewQuotation;
use App\Models\Party;
use App\Models\Quotation;
use App\Models\Unit;
use App\Models\User;
use App\Services\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QuotationResourceTest extends TestCase
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

    public function test_creating_a_quotation_through_the_form_computes_totals_via_the_service(): void
    {
        $tenant = Party::factory()->tenant()->create();
        $unit = Unit::factory()->commercial()->create();

        Livewire::test(CreateQuotation::class)
            ->fillForm([
                'party_id' => $tenant->id,
                'validity_date' => now()->addDays(10)->toDateString(),
                'security_deposit' => 5000,
                'number_of_installments' => 2,
                'units' => [
                    ['unit_id' => $unit->id, 'offered_rent' => 100000],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $quotation = Quotation::where('party_id', $tenant->id)->sole();
        $this->assertSame('100000.00', $quotation->base_rent);
        $this->assertSame('5000.00', $quotation->vat_amount);
        $this->assertSame(QuotationStatus::DRAFT, $quotation->status);
    }

    public function test_the_view_page_walks_a_quotation_from_draft_to_accepted(): void
    {
        $tenant = Party::factory()->tenant()->create();
        $unit = Unit::factory()->residential()->create();

        $quotation = app(QuotationService::class)->generate([
            'party_id' => $tenant->id,
            'units' => [['unit_id' => $unit->id, 'offered_rent' => 60000]],
            'validity_date' => now()->addDays(10)->toDateString(),
        ]);

        $component = Livewire::test(ViewQuotation::class, ['record' => $quotation->getKey()]);

        $component->callAction('send');
        $this->assertSame(QuotationStatus::SENT, $quotation->fresh()->status);

        $component->callAction('accept');
        $this->assertSame(QuotationStatus::ACCEPTED, $quotation->fresh()->status);
    }
}
