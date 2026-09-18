<?php

namespace App\Livewire\Pms;

use App\Models\LeasePrintTemplateField;
use App\Models\LeasePrintTemplatePage;
use App\Services\PMS\LeasePrintFieldResolver;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The click-to-place tool: pick a field, click on the background image
 * where it belongs, then fine-tune it by dragging, typing exact X/Y
 * percentages, or nudging it with the keyboard arrows. See
 * `LeasePrintFieldResolver` for what each field resolves to at actual
 * print time.
 */
class PrintTemplatePageBuilder extends Component
{
    public int $pageId;

    /**
     * @var list<array{id: int|null, field_key: string, x_percent: float, y_percent: float, font_size: int, text_align: string, rtl: bool}>
     */
    public array $fields = [];

    public ?string $selectedFieldKey = null;

    /**
     * @var list<int>
     */
    public array $selectedIndexes = [];

    /**
     * How far one arrow-key press moves the selected field, as a
     * percentage of the image's width/height — held Shift moves ten times
     * as far, for quick large adjustments.
     */
    private const NUDGE_STEP = 0.1;

    private const NUDGE_STEP_LARGE = 1.0;

    public function mount(int $pageId): void
    {
        $this->pageId = $pageId;
        $this->fields = LeasePrintTemplatePage::findOrFail($pageId)->fields
            ->map(fn (LeasePrintTemplateField $field): array => [
                'id' => $field->id,
                'field_key' => $field->field_key,
                'x_percent' => (float) $field->x_percent,
                'y_percent' => (float) $field->y_percent,
                'font_size' => $field->font_size,
                'text_align' => $field->text_align,
                'rtl' => $field->rtl,
            ])
            ->all();
    }

    public function placeField(float $xPercent, float $yPercent): void
    {
        if (blank($this->selectedFieldKey)) {
            return;
        }

        $this->fields[] = [
            'id' => null,
            'field_key' => $this->selectedFieldKey,
            'x_percent' => $this->clamp($xPercent),
            'y_percent' => $this->clamp($yPercent),
            'font_size' => 10,
            'text_align' => 'left',
            'rtl' => false,
        ];

        $this->selectedIndexes = [array_key_last($this->fields)];
        $this->selectedFieldKey = null;
    }

    public function moveField(int $index, float $xPercent, float $yPercent): void
    {
        if (! isset($this->fields[$index])) {
            return;
        }

        $this->fields[$index]['x_percent'] = $this->clamp($xPercent);
        $this->fields[$index]['y_percent'] = $this->clamp($yPercent);
    }

    /**
     * Plain click selects only this marker. Shift/ctrl-click ($multi) adds
     * or removes it from the selection, so several fields can be aligned
     * together in one go.
     */
    public function selectMarker(int $index, bool $multi = false): void
    {
        if (! isset($this->fields[$index])) {
            return;
        }

        if (! $multi) {
            $this->selectedIndexes = [$index];

            return;
        }

        if (in_array($index, $this->selectedIndexes, true)) {
            $this->selectedIndexes = array_values(array_diff($this->selectedIndexes, [$index]));
        } else {
            $this->selectedIndexes[] = $index;
        }
    }

    /**
     * Moves the selected field one step in the given direction — bound to
     * the marker's own arrow-key presses in the Blade view.
     */
    public function nudgeField(int $index, string $direction, bool $big = false): void
    {
        if (! isset($this->fields[$index])) {
            return;
        }

        $step = $big ? self::NUDGE_STEP_LARGE : self::NUDGE_STEP;

        [$dx, $dy] = match ($direction) {
            'up' => [0, -$step],
            'down' => [0, $step],
            'left' => [-$step, 0],
            'right' => [$step, 0],
            default => [0, 0],
        };

        $this->fields[$index]['x_percent'] = $this->clamp($this->fields[$index]['x_percent'] + $dx);
        $this->fields[$index]['y_percent'] = $this->clamp($this->fields[$index]['y_percent'] + $dy);
        $this->selectedIndexes = [$index];
    }

    public function removeField(int $index): void
    {
        unset($this->fields[$index]);
        $this->fields = array_values($this->fields);

        $this->selectedIndexes = array_values(array_map(
            fn (int $i): int => $i > $index ? $i - 1 : $i,
            array_filter($this->selectedIndexes, fn (int $i): bool => $i !== $index),
        ));
    }

    public function toggleRtl(int $index): void
    {
        if (isset($this->fields[$index])) {
            $this->fields[$index]['rtl'] = ! $this->fields[$index]['rtl'];
        }
    }

    public function setAlign(int $index, string $align): void
    {
        if (isset($this->fields[$index])) {
            $this->fields[$index]['text_align'] = $align;
        }
    }

    /**
     * A bulk convenience — most fields on a form line up along one edge,
     * so setting them all at once beats clicking through each one.
     */
    public function alignAll(string $align): void
    {
        foreach (array_keys($this->fields) as $index) {
            $this->fields[$index]['text_align'] = $align;
        }
    }

    /**
     * Snaps every selected field to a shared left/right edge — needs at
     * least two fields selected (shift/ctrl-click a marker or its list row
     * to build up the selection first).
     */
    public function alignSelected(string $align): void
    {
        if (count($this->selectedIndexes) < 2) {
            return;
        }

        $xValues = array_map(fn (int $i): float => $this->fields[$i]['x_percent'], $this->selectedIndexes);
        $target = $align === 'right' ? max($xValues) : min($xValues);

        foreach ($this->selectedIndexes as $index) {
            $this->fields[$index]['x_percent'] = $target;
        }
    }

    public function updated(string $name): void
    {
        // Manual X/Y typed directly into the panel — clamp the same way a
        // drag or keyboard nudge would, so a typo can't place a field
        // off the page.
        if (preg_match('/^fields\.(\d+)\.(x_percent|y_percent)$/', $name, $matches)) {
            $index = (int) $matches[1];
            $key = $matches[2];

            if (isset($this->fields[$index][$key])) {
                $this->fields[$index][$key] = $this->clamp((float) $this->fields[$index][$key]);
            }
        }
    }

    public function save(): void
    {
        $page = LeasePrintTemplatePage::findOrFail($this->pageId);

        $page->fields()->delete();

        foreach ($this->fields as $field) {
            LeasePrintTemplateField::create([
                'lease_print_template_page_id' => $page->id,
                'field_key' => $field['field_key'],
                'x_percent' => $field['x_percent'],
                'y_percent' => $field['y_percent'],
                'font_size' => $field['font_size'],
                'text_align' => $field['text_align'],
                'rtl' => $field['rtl'],
            ]);
        }

        Notification::make()->success()->title(__('Field positions saved.'))->send();
    }

    private function clamp(float $value): float
    {
        return round(min(100.0, max(0.0, $value)), 3);
    }

    public function render(): View
    {
        $page = LeasePrintTemplatePage::find($this->pageId);

        return view('livewire.pms.print-template-page-builder', [
            'imageUrl' => $page?->imageUrl(),
            'availableFields' => LeasePrintFieldResolver::availableFields(),
        ]);
    }
}
