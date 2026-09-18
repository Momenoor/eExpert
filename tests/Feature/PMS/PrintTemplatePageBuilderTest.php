<?php

namespace Tests\Feature\PMS;

use App\Livewire\Pms\PrintTemplatePageBuilder;
use App\Models\LeasePrintTemplate;
use App\Models\LeasePrintTemplateField;
use App\Models\LeasePrintTemplatePage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PrintTemplatePageBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function page(): LeasePrintTemplatePage
    {
        $template = LeasePrintTemplate::create(['name' => 'Test', 'contract_format' => 'sharjah_residential']);

        return LeasePrintTemplatePage::create([
            'lease_print_template_id' => $template->id,
            'page_number' => 1,
            'background_image_path' => 'lease-print-templates/fake.png',
        ]);
    }

    public function test_placing_a_field_adds_it_at_the_clicked_position(): void
    {
        $page = $this->page();

        Livewire::test(PrintTemplatePageBuilder::class, ['pageId' => $page->id])
            ->set('selectedFieldKey', 'government_contract_number')
            ->call('placeField', 25.5, 10.0)
            ->assertSet('fields.0.field_key', 'government_contract_number')
            ->assertSet('fields.0.x_percent', 25.5)
            ->assertSet('fields.0.y_percent', 10.0)
            // Placing a field clears the picker so the same field isn't
            // accidentally dropped twice on the next click.
            ->assertSet('selectedFieldKey', null);
    }

    public function test_a_click_outside_the_image_bounds_is_clamped_to_0_100(): void
    {
        $page = $this->page();

        Livewire::test(PrintTemplatePageBuilder::class, ['pageId' => $page->id])
            ->set('selectedFieldKey', 'government_contract_number')
            ->call('placeField', -5.0, 150.0)
            ->assertSet('fields.0.x_percent', 0.0)
            ->assertSet('fields.0.y_percent', 100.0);
    }

    public function test_nudging_a_field_moves_it_by_the_small_step_by_default_and_the_large_step_with_shift(): void
    {
        $page = $this->page();

        $component = Livewire::test(PrintTemplatePageBuilder::class, ['pageId' => $page->id])
            ->set('selectedFieldKey', 'government_contract_number')
            ->call('placeField', 50.0, 50.0)
            ->call('nudgeField', 0, 'right')
            ->assertSet('fields.0.x_percent', 50.1)
            ->call('nudgeField', 0, 'up', true)
            ->assertSet('fields.0.y_percent', 49.0);

        $component->assertSet('selectedIndexes', [0]);
    }

    public function test_align_all_sets_every_fields_text_align(): void
    {
        $page = $this->page();

        Livewire::test(PrintTemplatePageBuilder::class, ['pageId' => $page->id])
            ->set('selectedFieldKey', 'government_contract_number')
            ->call('placeField', 10, 10)
            ->set('selectedFieldKey', 'tenant_name')
            ->call('placeField', 20, 20)
            ->call('alignAll', 'right')
            ->assertSet('fields.0.text_align', 'right')
            ->assertSet('fields.1.text_align', 'right');
    }

    public function test_shift_selecting_a_second_marker_adds_to_the_selection(): void
    {
        $page = $this->page();

        Livewire::test(PrintTemplatePageBuilder::class, ['pageId' => $page->id])
            ->set('selectedFieldKey', 'government_contract_number')
            ->call('placeField', 10, 10)
            ->set('selectedFieldKey', 'tenant_name')
            ->call('placeField', 20, 20)
            ->call('selectMarker', 0, false)
            ->assertSet('selectedIndexes', [0])
            ->call('selectMarker', 1, true)
            ->assertSet('selectedIndexes', [0, 1])
            ->call('selectMarker', 1, true)
            ->assertSet('selectedIndexes', [0]);
    }

    public function test_align_selected_left_snaps_the_selection_to_the_leftmost_edge(): void
    {
        $page = $this->page();

        Livewire::test(PrintTemplatePageBuilder::class, ['pageId' => $page->id])
            ->set('selectedFieldKey', 'government_contract_number')
            ->call('placeField', 30, 10)
            ->set('selectedFieldKey', 'tenant_name')
            ->call('placeField', 10, 20)
            ->call('selectMarker', 0, false)
            ->call('selectMarker', 1, true)
            ->call('alignSelected', 'left')
            ->assertSet('fields.0.x_percent', 10.0)
            ->assertSet('fields.1.x_percent', 10.0);
    }

    public function test_align_selected_right_snaps_the_selection_to_the_rightmost_edge(): void
    {
        $page = $this->page();

        Livewire::test(PrintTemplatePageBuilder::class, ['pageId' => $page->id])
            ->set('selectedFieldKey', 'government_contract_number')
            ->call('placeField', 30, 10)
            ->set('selectedFieldKey', 'tenant_name')
            ->call('placeField', 10, 20)
            ->call('selectMarker', 0, false)
            ->call('selectMarker', 1, true)
            ->call('alignSelected', 'right')
            ->assertSet('fields.0.x_percent', 30.0)
            ->assertSet('fields.1.x_percent', 30.0);
    }

    public function test_align_selected_does_nothing_with_fewer_than_two_selected(): void
    {
        $page = $this->page();

        Livewire::test(PrintTemplatePageBuilder::class, ['pageId' => $page->id])
            ->set('selectedFieldKey', 'government_contract_number')
            ->call('placeField', 30, 10)
            ->call('selectMarker', 0, false)
            ->call('alignSelected', 'left')
            ->assertSet('fields.0.x_percent', 30.0);
    }

    public function test_manually_typed_coordinates_are_clamped_on_update(): void
    {
        $page = $this->page();

        Livewire::test(PrintTemplatePageBuilder::class, ['pageId' => $page->id])
            ->set('selectedFieldKey', 'government_contract_number')
            ->call('placeField', 10, 10)
            ->set('fields.0.x_percent', 250)
            ->assertSet('fields.0.x_percent', 100.0);
    }

    public function test_save_persists_the_current_in_memory_field_set(): void
    {
        $page = $this->page();

        Livewire::test(PrintTemplatePageBuilder::class, ['pageId' => $page->id])
            ->set('selectedFieldKey', 'government_contract_number')
            ->call('placeField', 25, 30)
            ->call('save');

        $this->assertSame(1, $page->fields()->count());
        $this->assertSame('government_contract_number', $page->fields()->first()->field_key);
    }

    /**
     * `mount()` loads a page's existing fields so the office is editing
     * (not overwriting) its current layout — removing one before saving
     * is how a field actually gets dropped from the template.
     */
    public function test_removing_an_existing_field_before_saving_drops_it_from_the_template(): void
    {
        $page = $this->page();
        LeasePrintTemplateField::create([
            'lease_print_template_page_id' => $page->id,
            'field_key' => 'stale_field',
            'x_percent' => 1,
            'y_percent' => 1,
        ]);

        Livewire::test(PrintTemplatePageBuilder::class, ['pageId' => $page->id])
            ->call('removeField', 0)
            ->set('selectedFieldKey', 'government_contract_number')
            ->call('placeField', 25, 30)
            ->call('save');

        $this->assertSame(1, $page->fields()->count());
        $this->assertSame('government_contract_number', $page->fields()->first()->field_key);
    }
}
