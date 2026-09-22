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

    public function test_box_size_is_saved_and_clamped_and_blank_means_auto(): void
    {
        $page = $this->page();

        Livewire::test(PrintTemplatePageBuilder::class, ['pageId' => $page->id])
            ->set('selectedFieldKey', 'tenant_name')
            ->call('placeField', 10, 10)
            ->set('fields.0.width_percent', 250)
            ->assertSet('fields.0.width_percent', 100.0)
            ->set('fields.0.width_percent', 30)
            ->set('fields.0.height_percent', 4)
            ->call('save');

        $field = $page->fields()->first();
        $this->assertSame('30.000', $field->width_percent);
        $this->assertSame('4.000', $field->height_percent);

        Livewire::test(PrintTemplatePageBuilder::class, ['pageId' => $page->id])
            ->set('fields.0.width_percent', '')
            ->assertSet('fields.0.width_percent', null);
    }

    public function test_arrow_nudge_moves_every_selected_field_together(): void
    {
        $page = $this->page();

        Livewire::test(PrintTemplatePageBuilder::class, ['pageId' => $page->id])
            ->set('selectedFieldKey', 'government_contract_number')
            ->call('placeField', 10, 10)
            ->set('selectedFieldKey', 'tenant_name')
            ->call('placeField', 30, 20)
            ->call('selectMarker', 0, false)
            ->call('selectMarker', 1, true)
            ->call('nudgeField', 0, 'right', true)
            ->assertSet('fields.0.x_percent', 11.0)
            ->assertSet('fields.1.x_percent', 31.0)
            ->call('nudgeField', 1, 'up', true)
            ->assertSet('fields.0.y_percent', 9.0)
            ->assertSet('fields.1.y_percent', 19.0)
            ->assertSet('selectedIndexes', [0, 1]);
    }

    public function test_group_nudge_stops_at_the_edge_without_distorting_the_layout(): void
    {
        $page = $this->page();

        Livewire::test(PrintTemplatePageBuilder::class, ['pageId' => $page->id])
            ->set('selectedFieldKey', 'government_contract_number')
            ->call('placeField', 0.4, 10)
            ->set('selectedFieldKey', 'tenant_name')
            ->call('placeField', 20, 10)
            ->call('selectMarker', 0, false)
            ->call('selectMarker', 1, true)
            ->call('nudgeField', 0, 'left', true)
            ->assertSet('fields.0.x_percent', 0.0)
            ->assertSet('fields.1.x_percent', 19.6);
    }

    public function test_distribute_vertically_keeps_first_and_last_and_spaces_the_middle_equally(): void
    {
        $page = $this->page();

        $component = Livewire::test(PrintTemplatePageBuilder::class, ['pageId' => $page->id]);

        foreach ([[10, 50], [10, 10], [10, 20], [10, 90]] as [$x, $y]) {
            $component->set('selectedFieldKey', 'tenant_name')->call('placeField', $x, $y);
        }

        $component
            ->call('selectMarker', 0, false)
            ->call('selectMarker', 1, true)
            ->call('selectMarker', 2, true)
            ->call('selectMarker', 3, true)
            ->call('distributeVertically')
            ->assertSet('fields.1.y_percent', 10.0)
            ->assertSet('fields.2.y_percent', 36.667)
            ->assertSet('fields.0.y_percent', 63.333)
            ->assertSet('fields.3.y_percent', 90.0);
    }

    public function test_distribute_vertically_needs_three_selected_fields(): void
    {
        $page = $this->page();

        Livewire::test(PrintTemplatePageBuilder::class, ['pageId' => $page->id])
            ->set('selectedFieldKey', 'tenant_name')
            ->call('placeField', 10, 10)
            ->set('selectedFieldKey', 'tenant_name')
            ->call('placeField', 10, 40)
            ->call('selectMarker', 0, false)
            ->call('selectMarker', 1, true)
            ->call('distributeVertically')
            ->assertSet('fields.1.y_percent', 40.0);
    }

    public function test_apply_box_size_sets_width_and_height_on_every_selected_field_only(): void
    {
        $page = $this->page();

        Livewire::test(PrintTemplatePageBuilder::class, ['pageId' => $page->id])
            ->set('selectedFieldKey', 'tenant_name')->call('placeField', 10, 10)
            ->set('selectedFieldKey', 'tenant_name')->call('placeField', 10, 20)
            ->set('selectedFieldKey', 'tenant_name')->call('placeField', 10, 30)
            ->call('selectMarker', 0, false)
            ->call('selectMarker', 1, true)
            ->set('bulkWidthPercent', 25)
            ->set('bulkHeightPercent', 3.5)
            ->call('applyBoxSizeToSelected')
            ->assertSet('fields.0.width_percent', 25.0)
            ->assertSet('fields.1.height_percent', 3.5)
            ->assertSet('fields.2.width_percent', null)
            ->set('bulkWidthPercent', null)
            ->call('applyBoxSizeToSelected')
            ->assertSet('fields.0.width_percent', null);
    }

    public function test_the_same_field_can_be_placed_twice_once_per_language(): void
    {
        $page = $this->page();

        Livewire::test(PrintTemplatePageBuilder::class, ['pageId' => $page->id])
            ->set('selectedFieldKey', 'contract_type')->call('placeField', 10, 10)
            ->set('selectedFieldKey', 'contract_type')->call('placeField', 60, 10)
            ->set('fields.0.language', 'en')
            ->assertSet('fields.0.rtl', false)
            ->set('fields.1.language', 'ar')
            ->assertSet('fields.1.rtl', true)
            ->call('save');

        $saved = $page->fields()->orderBy('id')->get();
        $this->assertCount(2, $saved);
        $this->assertSame(['en', 'ar'], $saved->pluck('language')->all());
        $this->assertSame('contract_type', $saved[0]->field_key);
        $this->assertSame('contract_type', $saved[1]->field_key);
    }

    public function test_an_unknown_language_falls_back_to_default(): void
    {
        $page = $this->page();

        Livewire::test(PrintTemplatePageBuilder::class, ['pageId' => $page->id])
            ->set('selectedFieldKey', 'contract_type')->call('placeField', 10, 10)
            ->set('fields.0.language', 'fr')
            ->assertSet('fields.0.language', null);
    }

    /**
     * Server-side regression lock for the same bug the missing `wire:key`
     * caused in the browser: editing one placed copy of a field must never
     * touch another copy of the same field placed elsewhere on the page.
     */
    public function test_editing_one_copy_of_a_duplicated_field_never_touches_the_others(): void
    {
        $page = $this->page();

        Livewire::test(PrintTemplatePageBuilder::class, ['pageId' => $page->id])
            ->set('selectedFieldKey', 'contract_type')->call('placeField', 10, 10)
            ->set('selectedFieldKey', 'contract_type')->call('placeField', 30, 10)
            ->set('selectedFieldKey', 'contract_type')->call('placeField', 50, 10)
            ->set('fields.1.language', 'ar')
            ->set('fields.1.font_size', 22)
            ->call('toggleRtl', 1)
            ->assertSet('fields.0.language', null)
            ->assertSet('fields.0.font_size', 10)
            ->assertSet('fields.0.rtl', false)
            ->assertSet('fields.2.language', null)
            ->assertSet('fields.2.font_size', 10)
            ->assertSet('fields.2.rtl', false)
            ->assertSet('fields.1.language', 'ar')
            ->assertSet('fields.1.font_size', 22);
    }
}
