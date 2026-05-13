<?php

namespace App\Filament\Resources\LetterTemplates\Schemas;

use App\Enums\LetterTemplateCategories;
use App\Filament\Forms\Components\RichEditor\RichContentCustomBlocks\HeroBlock;
use App\Models\User;
use App\Services\TemplatePlaceholderService;
use Filament\Actions\Action;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Momenoor\FilamentTiptapEditor\TiptapEditor;

class LetterTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->afterStateUpdated(fn($state, Set $set) => $set('slug', str($state)->slug()))
                    ->lazy()
                    ->required(),

                TextInput::make('slug')
                    ->unique(ignoreRecord: true)
                    ->readOnly()
                    ->required(),

                Select::make('locale')
                    ->options(config('app.available_locales'))
                    ->required()
                    ->default('en'),

                Select::make('category')
                    ->options(LetterTemplateCategories::class)
                    ->default('general')
                    ->required(),

                TextInput::make('subject')
                    ->required()
                    ->columnSpanFull()
                    ->hint(__('You can use placeholders like {{matter.reference}}'))
                    ->hintIcon('heroicon-m-information-circle'),
                RichEditor::make('body')
                    ->toolbarButtons([
                        ['h1', 'h2', 'h3', 'bulletList', 'orderedList', 'blockquote', 'horizontalRule'],
                        ['bold', 'italic', 'strike', 'underline', 'superscript', 'subscript', 'lead', 'textColor', 'small', 'highlight', 'alignStart', 'alignCenter', 'alignEnd'],
                        ['link', 'table', 'grid', 'details', 'code', 'codeBlock', 'customBlocks', 'mergeTags',],
                    ]),


                // ── Placeholder panel ──────────────────────────────────────────────────
                Section::make(__('Placeholders'))
                    ->description(__('Variables detected from body. Add descriptions or load from a model.'))
                    ->collapsible()
                    ->columnSpanFull()
                    ->headerActions([
                        // Load from Matter model
                        Action::make('loadFromMatter')
                            ->label(__('Load from Matter'))
                            ->icon('heroicon-o-arrow-down-tray')
                            ->action(function (Set $set, Get $get) {
                                $flat = app(TemplatePlaceholderService::class)
                                    ->flatten(\App\Models\Matter::class, depth: 1);

                                // Merge with existing, don't overwrite user edits
                                $existing = $get('placeholders') ?? [];
                                $set('placeholders', array_merge($flat, $existing));
                            }),

                        // Load from Party model
                        Action::make('loadFromParty')
                            ->label(__('Load from Party'))
                            ->icon('heroicon-o-arrow-down-tray')
                            ->color('gray')
                            ->action(function (Set $set, Get $get) {
                                $flat = app(TemplatePlaceholderService::class)
                                    ->flatten(\App\Models\Party::class, depth: 0, prefix: 'party');

                                $existing = $get('placeholders') ?? [];
                                $set('placeholders', array_merge($flat, $existing));
                            }),

                        // Clear all
                        Action::make('clearPlaceholders')
                            ->label(__('Clear'))
                            ->icon('heroicon-o-trash')
                            ->color('danger')
                            ->requiresConfirmation()
                            ->action(fn(Set $set) => $set('placeholders', [])),
                    ])
                    ->schema([
                        KeyValue::make('placeholders')
                            ->label(false)
                            ->keyLabel(__('Variable (e.g. {{matter.reference}})'))
                            ->valueLabel(__('Description / Label'))
                            ->addActionLabel(__('Add placeholder manually'))
                            ->reorderable()
                            ->columnSpanFull(),
                    ]),

                Toggle::make('is_active')
                    ->default(true)
                    ->required(),

                Toggle::make('is_default')
                    ->required(),
            ]);
    }
}
