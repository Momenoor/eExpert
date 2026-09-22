<?php

namespace App\Filament\Pms\Resources\LeasePrintTemplates\Schemas;

use App\Enums\PMS\PrintDocumentType;
use App\Models\OwnerGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class LeasePrintTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Print Template'))
                    ->description(__('One background-image page per document page — upload the actual form/letterhead and place each field on top of it.'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(255),
                        Select::make('document_type')
                            ->label(__('Document Type'))
                            ->options(PrintDocumentType::class)
                            ->default(PrintDocumentType::LEASE_CONTRACT->value)
                            ->live()
                            ->required()
                            ->disabledOn('edit'),
                        Select::make('contract_format')
                            ->label(__('Contract Format'))
                            ->options([
                                'sharjah_commercial' => __('Sharjah Commercial'),
                                'sharjah_residential' => __('Sharjah Residential'),
                                'dubai_ejari' => __('Dubai EJARI'),
                            ])
                            ->visible(fn (Get $get): bool => self::documentTypeFrom($get) === PrintDocumentType::LEASE_CONTRACT)
                            ->required(fn (Get $get): bool => self::documentTypeFrom($get) === PrintDocumentType::LEASE_CONTRACT)
                            ->disabledOn('edit'),
                        Select::make('owner_group_id')
                            ->label(__('Owner Group'))
                            ->options(fn (): array => OwnerGroup::orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->visible(fn (Get $get): bool => self::documentTypeFrom($get)?->isOwnerGroupScoped() ?? false)
                            ->required(fn (Get $get): bool => self::documentTypeFrom($get)?->isOwnerGroupScoped() ?? false)
                            ->helperText(__('Every property under this group prints its Tax Invoice / Receivable Receipt on this letterhead.'))
                            ->disabledOn('edit'),
                    ])->columns(2),
            ]);
    }

    /**
     * `document_type`'s form state is the enum instance itself (Filament
     * casts an enum-backed Select's value automatically) — normalized here
     * once since it can also arrive as the raw string while the form is
     * still being filled/validated.
     */
    private static function documentTypeFrom(Get $get): ?PrintDocumentType
    {
        $state = $get('document_type');

        if ($state instanceof PrintDocumentType) {
            return $state;
        }

        return is_string($state) ? PrintDocumentType::tryFrom($state) : null;
    }
}
