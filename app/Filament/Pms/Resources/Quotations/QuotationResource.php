<?php

namespace App\Filament\Pms\Resources\Quotations;

use App\Filament\Pms\Resources\Quotations\Pages\CreateQuotation;
use App\Filament\Pms\Resources\Quotations\Pages\ListQuotations;
use App\Filament\Pms\Resources\Quotations\Pages\ViewQuotation;
use App\Filament\Pms\Resources\Quotations\Schemas\QuotationForm;
use App\Filament\Pms\Resources\Quotations\Schemas\QuotationInfolist;
use App\Filament\Pms\Resources\Quotations\Tables\QuotationsTable;
use App\Models\Quotation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class QuotationResource extends Resource
{
    protected static ?string $model = Quotation::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static string|UnitEnum|null $navigationGroup = 'Leasing';

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('Quotation');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Quotations');
    }

    public static function getNavigationLabel(): string
    {
        return __('Quotations');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Leasing');
    }

    public static function form(Schema $schema): Schema
    {
        return QuotationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return QuotationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuotationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuotations::route('/'),
            'create' => CreateQuotation::route('/create'),
            'view' => ViewQuotation::route('/{record}'),
        ];
    }
}
