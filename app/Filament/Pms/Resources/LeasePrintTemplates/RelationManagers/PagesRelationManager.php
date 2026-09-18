<?php

namespace App\Filament\Pms\Resources\LeasePrintTemplates\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class PagesRelationManager extends RelationManager
{
    protected static string $relationship = 'pages';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('page_number')
                ->label(__('Page Number'))
                ->numeric()
                ->minValue(1)
                ->required(),
            FileUpload::make('background_image_path')
                ->label(__('Background Image'))
                ->disk('public')
                ->directory('lease-print-templates')
                ->image()
                ->visibility('public')
                ->required()
                ->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('page_number')
            ->columns([
                TextColumn::make('page_number')
                    ->label(__('Page'))
                    ->sortable(),
                ImageColumn::make('background_image_path')
                    ->label(__('Preview'))
                    ->disk('public')
                    ->height(80),
                TextColumn::make('fields_count')
                    ->label(__('Fields Placed'))
                    ->counts('fields'),
            ])
            ->defaultSort('page_number')
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(fn (array $data): array => $this->withImageDimensions($data)),
            ])
            ->recordActions([
                Action::make('edit_field_positions')
                    ->label(__('Edit Field Positions'))
                    ->icon('heroicon-o-cursor-arrow-rays')
                    ->color('info')
                    ->visible(fn ($record): bool => filled($record->background_image_path))
                    ->modalHeading(__('Place Fields'))
                    ->modalWidth('7xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Close'))
                    ->modalContent(fn ($record) => view('filament.pms.print-template-page-builder-modal', ['pageId' => $record->id])),
                EditAction::make()
                    ->mutateDataUsing(fn (array $data): array => $this->withImageDimensions($data)),
                DeleteAction::make(),
            ])
            ->emptyStateHeading(__('No pages yet'));
    }

    /**
     * Captures the uploaded image's natural pixel dimensions once, at
     * upload time, rather than re-reading the file on every print.
     */
    private function withImageDimensions(array $data): array
    {
        if (blank($data['background_image_path'] ?? null)) {
            return $data;
        }

        $dimensions = @getimagesize(Storage::disk('public')->path($data['background_image_path']));

        if ($dimensions !== false) {
            $data['image_width'] = $dimensions[0];
            $data['image_height'] = $dimensions[1];
        }

        return $data;
    }
}
