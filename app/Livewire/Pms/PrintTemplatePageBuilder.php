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
     * @var list<array{id: int|null, field_key: string, x_percent: float, y_percent: float, width_percent: float|null, height_percent: float|null, font_size: int, text_align: string, rtl: bool, language: string|null}>
     */
    public array $fields = [];

    public ?string $selectedFieldKey = null;

    public ?float $bulkWidthPercent = null;

    public ?float $bulkHeightPercent = null;

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
                'width_percent' => $field->width_percent !== null ? (float) $field->width_percent : null,
                'height_percent' => $field->height_percent !== null ? (float) $field->height_percent : null,
                'font_size' => $field->font_size,
                'text_align' => $field->text_align,
                'rtl' => $field->rtl,
                'language' => $field->language,
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
            'width_percent' => null,
            'height_percent' => null,
            'font_size' => 10,
            'text_align' => 'left',
            'rtl' => false,
            'language' => null,
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
     * Moves the field one step in the given direction — bound to the
     * marker's own arrow-key presses in the Blade view. When the field is
     * part of a multi-selection, the whole selection moves together and
     * keeps its relative layout (the step shrinks so none of them leaves
     * the page).
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

        $indexes = in_array($index, $this->selectedIndexes, true) && count($this->selectedIndexes) > 1
            ? $this->selectedIndexes
            : [$index];

        foreach (['x_percent' => $dx, 'y_percent' => $dy] as $axis => $delta) {
            $values = array_map(fn (int $i): float => $this->fields[$i][$axis], $indexes);
            $delta = $delta < 0 ? max($delta, -min($values)) : min($delta, 100.0 - max($values));

            foreach ($indexes as $i) {
                $this->fields[$i][$axis] = $this->clamp($this->fields[$i][$axis] + $delta);
            }
        }

        $this->selectedIndexes = $indexes;
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

    /**
     * Gives every selected field the same box size in one go. A blank
     * width or height means Auto, exactly as in the per-field inputs.
     */
    public function applyBoxSizeToSelected(): void
    {
        foreach ($this->selectedIndexes as $index) {
            $this->fields[$index]['width_percent'] = $this->bulkWidthPercent !== null ? $this->clamp($this->bulkWidthPercent) : null;
            $this->fields[$index]['height_percent'] = $this->bulkHeightPercent !== null ? $this->clamp($this->bulkHeightPercent) : null;
        }
    }

    /**
     * Spaces the selected fields evenly from top to bottom: the topmost and
     * bottommost stay where they are and the ones between are redistributed
     * at equal intervals, in their current top-to-bottom order. Needs three
     * or more fields — with two there is nothing in between to space.
     */
    public function distributeVertically(): void
    {
        if (count($this->selectedIndexes) < 3) {
            return;
        }

        $ordered = $this->selectedIndexes;
        usort($ordered, fn (int $a, int $b): int => $this->fields[$a]['y_percent'] <=> $this->fields[$b]['y_percent']);

        $top = $this->fields[$ordered[0]]['y_percent'];
        $bottom = $this->fields[$ordered[array_key_last($ordered)]]['y_percent'];
        $gap = ($bottom - $top) / (count($ordered) - 1);

        foreach ($ordered as $position => $index) {
            $this->fields[$index]['y_percent'] = $this->clamp($top + $gap * $position);
        }
    }

    public function updated(string $name): void
    {
        // Choosing a language for a translatable field also flips the
        // text direction to match — Arabic right-to-left, English left-to-right.
        // "Default" leaves the direction as it was.
        if (preg_match('/^fields\.(\d+)\.language$/', $name, $languageMatch)) {
            $index = (int) $languageMatch[1];
            $language = $this->fields[$index]['language'] ?? null;

            if (! in_array($language, ['ar', 'en'], true)) {
                $this->fields[$index]['language'] = null;
            } else {
                $this->fields[$index]['rtl'] = $language === 'ar';
            }

            return;
        }

        // Manual X/Y typed directly into the panel — clamp the same way a
        // drag or keyboard nudge would, so a typo can't place a field
        // off the page.
        if (preg_match('/^fields\.(\d+)\.(x_percent|y_percent|width_percent|height_percent)$/', $name, $matches)) {
            $index = (int) $matches[1];
            $key = $matches[2];

            if (in_array($key, ['width_percent', 'height_percent'], true) && blank($this->fields[$index][$key] ?? null)) {
                $this->fields[$index][$key] = null;
            } elseif (isset($this->fields[$index][$key])) {
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
                'width_percent' => $field['width_percent'] ?? null,
                'height_percent' => $field['height_percent'] ?? null,
                'font_size' => $field['font_size'],
                'text_align' => $field['text_align'],
                'rtl' => $field['rtl'],
                'language' => $field['language'] ?? null,
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
