<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

/**
 * The full-page view of the chat widget (App\Livewire\ChatWidget) — same
 * component, same data, as the floating popup rendered on every other page.
 * This page just gives it its own nav entry and a two-pane layout with room
 * to breathe; all the actual chat logic lives in the Livewire component.
 */
class Chat extends Page
{
    protected string $view = 'filament.pages.chat';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|UnitEnum|null $navigationGroup = 'Communication';

    protected static ?int $navigationSort = 3;

    public function getTitle(): string
    {
        return __('Chat');
    }

    public static function getNavigationLabel(): string
    {
        return __('Chat');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Communication');
    }
}
