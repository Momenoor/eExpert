<?php

namespace App\Filament\Pms\Resources\ConditionTemplates\RelationManagers;

use App\Enums\PMS\ConditionSection;
use App\Models\ConditionTemplateItem;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Every item here is a Special Condition clause — the only section this
 * app's print views ever pull from a template — so `section` is set
 * automatically rather than exposed as a field.
 */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('sort_order')
                ->label(__('Order'))
                ->numeric()
                ->minValue(0)
                ->default(fn (): int => ConditionTemplateItem::where('condition_template_id', $this->getOwnerRecord()->getKey())->max('sort_order') + 1)
                ->required(),
            Textarea::make('text_en')
                ->label(__('English Text'))
                ->required()
                ->rows(3)
                ->columnSpanFull(),
            Textarea::make('text_ar')
                ->label(__('Arabic Text'))
                ->required()
                ->rows(3)
                ->extraInputAttributes(['dir' => 'rtl'])
                ->columnSpanFull(),
        ])->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('text_en')
            ->columns([
                TextColumn::make('sort_order')
                    ->label(__('Order'))
                    ->sortable(),
                TextColumn::make('text_en')
                    ->label(__('English Text'))
                    ->limit(80)
                    ->wrap(),
                TextColumn::make('text_ar')
                    ->label(__('Arabic Text'))
                    ->limit(80)
                    ->wrap(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        $data['section'] = ConditionSection::SPECIAL->value;

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading(__('No clauses yet'));
    }
}
