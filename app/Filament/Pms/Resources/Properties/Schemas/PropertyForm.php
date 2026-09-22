<?php

namespace App\Filament\Pms\Resources\Properties\Schemas;

use App\Enums\PMS\Emirate;
use App\Enums\PMS\PropertyType;
use App\Models\OwnerGroup;
use App\Models\OwnerGroupBankAccount;
use App\Models\Party;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PropertyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Property'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Property Name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('address')
                            ->label(__('Address'))
                            ->maxLength(255),
                        TextEntry::make('total_units')
                            ->label(__('Total Units'))
                            ->numeric(),
                        TextInput::make('year_built')
                            ->label(__('Year Built'))
                            ->numeric()
                            ->minValue(1900)
                            ->maxValue((int) now()->format('Y')),
                        Select::make('emirate')
                            ->label(__('Emirate'))
                            ->options(Emirate::class)
                            ->helperText(__('Determines which government tenancy contract format applies (Ejari, Sharjawi, …).')),
                    ])->columns(3),

                Section::make(__('Owner Group & Bank Account'))
                    ->description(__('Optional. Put the property under an owner group and choose which of the group\'s bank accounts its rent is paid into.'))
                    ->schema([
                        Select::make('owner_group_id')
                            ->label(__('Owner Group'))
                            ->options(fn (): array => OwnerGroup::orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?int $state): void {
                                $set('owner_group_bank_account_id', $state === null ? null : self::defaultAccountId($state));
                            }),
                        Select::make('owner_group_bank_account_id')
                            ->label(__('Bank Account'))
                            ->options(fn (Get $get): array => self::accountOptions($get('owner_group_id')))
                            ->visible(fn (Get $get): bool => filled($get('owner_group_id')))
                            ->required(fn (Get $get): bool => filled($get('owner_group_id')) && self::accountOptions($get('owner_group_id')) !== [])
                            ->helperText(__('Only this group\'s own accounts are offered.')),
                    ])->columns(2),

                Section::make(__('Government Property Details'))
                    ->description(__('Fields required by the emirate\'s own tenancy contract / attestation system.'))
                    ->schema([
                        TextInput::make('municipality')
                            ->label(__('Municipality'))
                            ->maxLength(255),
                        TextInput::make('suburb')
                            ->label(__('Suburb'))
                            ->maxLength(255),
                        TextInput::make('area')
                            ->label(__('Area'))
                            ->maxLength(255),
                        TextInput::make('title_deed_number')
                            ->label(__('Title Deed No.'))
                            ->maxLength(255),
                        DatePicker::make('title_deed_date')
                            ->label(__('Title Deed Date')),
                        TextInput::make('plot_number')
                            ->label(__('Government No.'))
                            ->maxLength(255),
                        Select::make('property_type')
                            ->label(__('Property Type'))
                            ->options(PropertyType::class),
                        TextInput::make('property_number')
                            ->label(__('Property No.'))
                            ->maxLength(255),
                    ])->columns(3),

                Section::make(__('Owners'))
                    ->description(__('Ownership percentages across all owners must add up to 100.'))
                    ->schema([
                        // Deliberately NOT ->relationship(): Filament's
                        // Repeater-to-pivot sync only ever attaches the
                        // related key, silently dropping the extra
                        // `ownership_percentage` pivot column. The page
                        // classes (Create/EditProperty) sync the pivot
                        // explicitly instead — a plain array field here,
                        // pre-filled from the existing pivot on edit.
                        Repeater::make('owners')
                            ->label(__('Owners'))
                            ->schema([
                                Select::make('party_id')
                                    ->label(__('Owner'))
                                    ->options(fn (): array => Party::withRole('owner')->orderBy('name')->pluck('name', 'id')->all())
                                    ->searchable()
                                    ->required()
                                    ->distinct()
                                    ->live(),
                                TextInput::make('ownership_percentage')
                                    ->label(__('Ownership %'))
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->maxValue(100)
                                    ->step(0.01)
                                    ->required()
                                    ->live(onBlur: true),
                            ])
                            ->columns(2)
                            ->minItems(1)
                            ->live()
                            // Validated as one array field, not per-row: a
                            // per-item rule can't see its siblings, and a
                            // property whose owners sum to 60% (or 140%) is
                            // wrong regardless of how any single row looks.
                            ->rule(function () {
                                return function (string $attribute, mixed $value, \Closure $fail): void {
                                    $sum = collect($value)
                                        ->sum(fn (array $row): float => (float) ($row['ownership_percentage'] ?? 0));

                                    if (abs($sum - 100.0) > 0.01) {
                                        $fail(__('Ownership percentages must add up to 100 (currently :sum).', [
                                            'sum' => rtrim(rtrim(number_format($sum, 2), '0'), '.'),
                                        ]));
                                    }
                                };
                            })
                            ->addActionLabel(__('Add Owner'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function accountOptions(mixed $groupId): array
    {
        if (blank($groupId)) {
            return [];
        }

        return OwnerGroupBankAccount::where('owner_group_id', $groupId)
            ->orderByDesc('is_default')
            ->get()
            ->mapWithKeys(fn (OwnerGroupBankAccount $account): array => [$account->getKey() => $account->label()])
            ->all();
    }

    private static function defaultAccountId(int $groupId): ?int
    {
        $id = OwnerGroupBankAccount::where('owner_group_id', $groupId)->orderByDesc('is_default')->orderBy('id')->value('id');

        return $id !== null ? (int) $id : null;
    }
}
