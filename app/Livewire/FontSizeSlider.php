<?php

namespace App\Livewire;

use Filament\Forms\Components\Slider;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Livewire\Component;

class FontSizeSlider extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public static function make(): static
    {
        return new static;
    }

    public function mount(): void
    {
        $this->form->fill([
            'font_size' => auth()->user()->font_size,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Slider::make('font_size')
                    ->live()
                    ->label(fn ($state) => __('Font Size').': '.$state.'px')
                    ->step(1)
                    ->fillTrack()
                    ->default(16)
                    ->minValue(12)
                    ->maxValue(24)
                    ->afterStateUpdated(fn ($state) => $this->updateUserFont($state)),
            ])
            ->statePath('data');
    }

    public function updateUserFont($state): void
    {
        auth()->user()->update(['font_size' => $state]);
        Cache::forget('user_font_size_'.auth()->id());
        // Live update while dragging the slider — the same CSS variable the
        // HEAD_END render hook sets server-side on every page load, so the
        // two stay consistent without a separate DOM-ready script.
        $this->js("document.documentElement.style.setProperty('--user-font-size', '{$state}px')");
    }

    public function render(): Factory|\Illuminate\Contracts\View\View|View
    {
        return view('livewire.font-size-slider');
    }
}
