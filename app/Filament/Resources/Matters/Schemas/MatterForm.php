<?php

namespace App\Filament\Resources\Matters\Schemas;

use App\Enums\FeeType;
use App\Enums\MatterDifficulty;
use App\Enums\MatterLevel;
use App\Filament\Resources\Parties\Schemas\PartyForm;
use App\Models\Party;
use App\Models\Type;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\Str;

class MatterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)
                    ->schema([
                        Group::make()
                            ->schema([
                                Section::make(__('Basic Data'))->schema([
                                    TextInput::make('year')
                                        ->label(__('Year'))
                                        ->minValue(now()->subYears(5)->year)
                                        ->maxValue(now()->year)
                                        ->length(4)
                                        ->numeric()
                                        ->rules(['digits:4'])
                                        ->required(),
                                    TextInput::make('number')
                                        ->label(__('Number'))
                                        ->required(),

                                    DatePicker::make('received_at')
                                        ->label(__('Court Assigning Date'))
                                        ->required(),
                                    DatePicker::make('distributed_at')
                                        ->label(__('Assistant Assigning Date'))
                                        ->afterOrEqual('received_at')
                                        ->required(),
                                    DateTimePicker::make('next_session_date')
                                        ->required()
                                        ->format('Y-m-d h:m')
                                        ->seconds(false)
                                        ->afterOrEqual('received_at')
                                        ->label(__('Next Session Date'))
                                        ->live(),
                                    Toggle::make('is_office_work')
                                        ->label(__('Is Office Work'))
                                        ->default(false),
                                    DatePicker::make('initial_report_at')
                                        ->label(__('Initial Report Date'))
                                        ->visible(fn (string $operation, $record) => $operation === 'edit'
                                            && $record->initial_report_at !== null
                                            && auth()->user()->can('UpdateInitialReportDate:Matter')
                                        ),
                                    DatePicker::make('final_report_at')
                                        ->label(__('Final Report Date'))
                                        ->visible(fn (string $operation, $record) => $operation === 'edit'
                                            && $record->final_report_at !== null
                                            && auth()->user()->can('UpdateFinalReportDate:Matter')
                                        ),
                                    DatePicker::make('final_report_memo_date')
                                        ->label(__('Final Report Memo Date'))
                                        ->visible(fn (string $operation, $record) => $operation === 'edit'
                                            && $record->final_report_memo_date !== null
                                            && auth()->user()->can('UpdateFinalReportDate:Matter')
                                        ),
                                    TextInput::make('review_count')
                                        ->label(__('Review Count'))
                                        ->numeric()
                                        ->visible(fn (string $operation, $record) => $operation === 'edit'
                                            && $record->review_count !== null
                                            && auth()->user()->can('Update:Matter')
                                        )
                                        ->default(0),
                                    Toggle::make('has_substantive_changes')
                                        ->label(__('Has Substantive Changes'))
                                        ->visible(fn (string $operation, $record) => $operation === 'edit'
                                            && $record->review_count !== null
                                            && auth()->user()->can('Update:Matter')
                                        ),
                                    Toggle::make('has_court_penalty')
                                        ->label(__('Has Court Penalty'))
                                        ->visible(fn (string $operation, $record) => $operation === 'edit'
                                            && $record->review_count !== null
                                            && auth()->user()->can('Update:Matter')
                                        ),
                                ]),
                                Section::make(__('Court Data'))->schema([
                                    Select::make('court_id')
                                        ->label(__('Court'))
                                        ->relationship('court', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->columnSpanFull(),
                                    Select::make('level')
                                        ->label(__('Level'))
                                        ->options(MatterLevel::class)
                                        ->required(),
                                    Select::make('type_id')
                                        ->label(__('Type'))
                                        ->relationship('type', 'name', modifyQueryUsing: function ($query) {
                                            $query->where('active', true);
                                        })
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->live(),
                                    Group::make()
                                        ->schema(function (Get $get) {
                                            $typeId = $get('type_id');
                                            if (! $typeId) {
                                                return [];
                                            }

                                            $type = Type::find($typeId);
                                            if (! $type) {
                                                return [];
                                            }

                                            $fields = $type->fieldDefinitions;
                                            $components = [];

                                            foreach ($fields as $field) {
                                                $component = match ($field->type) {
                                                    'select_input' => Select::make("custom_fields.{$field->label}")
                                                        ->options($field->options ?? [])
                                                        ->label($field->label),
                                                    'date_input' => DatePicker::make("custom_fields.{$field->label}")
                                                        ->label($field->label),
                                                    'toggle_input' => Toggle::make("custom_fields.{$field->label}")
                                                        ->label($field->label),
                                                    default => TextInput::make("custom_fields.{$field->label}")
                                                        ->label($field->label),
                                                };

                                                if ($field->required) {
                                                    $component->required();
                                                }

                                                $components[] = $component;
                                            }

                                            return $components;
                                        })
                                        ->columns(2)
                                        ->columnSpanFull(),
                                    Select::make('difficulty')
                                        ->label(__('Difficulty'))
                                        ->options(MatterDifficulty::class)
                                        ->required(),
                                    // live() is needed here so expert type options
                                    // re-evaluate when commissioning changes
                                    Toggle::make('is_committee')
                                        ->label(__('Is Committee?'))
                                        ->live()
                                        ->afterStateHydrated(function ($state, Set $set, Get $get) {
                                            $set('is_committee', $get('commissioning') === 'committee');
                                        })
                                        ->afterStateUpdated(function ($state, Set $set) {
                                            $set('commissioning', $state ? 'committee' : 'individual');
                                        })
                                        ->inline(false),
                                    Select::make('commissioning')
                                        ->label(__('Commissioning'))
                                        ->options([
                                            'individual' => __('Individual'),
                                            'committee' => __('Committee'),
                                        ])
                                        ->required()
                                        ->live()
                                        ->afterStateHydrated(function ($state, Set $set) {
                                            $set('is_committee', $state === 'committee');
                                        })
                                        ->afterStateUpdated(function ($state, Set $set) {
                                            $set('is_committee', $state === 'committee');
                                        }),
                                ]),
                            ])
                            ->columns(1)
                            ->columnSpan(1),

                        Group::make()
                            ->schema([
                                Section::make(__('Parties & Experts'))
                                    ->schema([
                                        Repeater::make('expertsOnly')
                                            ->relationship('expertsOnly')
                                            ->label(__('Experts'))
                                            ->columns(3)
                                            ->table([
                                                Repeater\TableColumn::make(__('Type'))->width(250),
                                                Repeater\TableColumn::make(__('Name')),
                                                Repeater\TableColumn::make(__('Commission %')),
                                            ])
                                            ->defaultItems(1)
                                            ->default([['type' => 'certified', 'party_id' => 7358]])
                                            ->compact()
                                            ->schema([
                                                Select::make('type')
                                                    ->label(__('Type'))
                                                    ->options(function (Get $get, $livewire) {
                                                        // Layout components (Grid/Group/Section) are transparent
                                                        // to Filament's data tree — commissioning lives flat on $livewire->data
                                                        $commissioning = $livewire->data['commissioning'] ?? 'individual';
                                                        $isCommittee = $commissioning === 'committee';
                                                        $options = [
                                                            'certified' => __('Certified Expert'),
                                                            'assistant' => __('Assistant Expert'),
                                                        ];
                                                        if ($isCommittee) {
                                                            $options['external'] = __('External Expert');
                                                            $options['external-assistant'] = __('External Assistant');
                                                        }

                                                        return $options;
                                                    })
                                                    ->required()
                                                    ->live(onBlur: true) // onBlur — only re-render when leaving the field
                                                    ->afterStateUpdated(function (Set $set) {
                                                        $set('role', 'expert');
                                                        $set('party_id', null);
                                                    })
                                                    ->columnSpan(1),

                                                // No ->live() — nothing downstream reads party_id changes
                                                Select::make('party_id')
                                                    ->label(__('Expert Name'))
                                                    ->relationship('party', 'name', function ($query, Get $get) {
                                                        $type = $get('type');
                                                        if ($type) {
                                                            $query->whereJsonContains('role', [['role' => 'expert', 'type' => $type]]);
                                                        } else {
                                                            $query->whereJsonContains('role', [['role' => 'expert']]);
                                                        }
                                                    })
                                                    ->createOptionForm(fn (Schema $schema) => PartyForm::configure($schema))
                                                    ->createOptionAction(function (Action $action, Get $get) {
                                                        $type = $get('type') ?? null;

                                                        return $action->fillForm([
                                                            'role' => [
                                                                'role' => ['expert'],
                                                                'type' => [$type],
                                                            ],
                                                        ]);
                                                    })
                                                    ->createOptionUsing(function (array $data) {
                                                        return Party::create($data)->id;
                                                    })
                                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                                    ->columnSpan(2)
                                                    ->searchable()
                                                    ->preload()
                                                    ->required(),

                                                TextInput::make('commission_percentage')
                                                    ->label(__('Commission %'))
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->maxValue(100)
                                                    ->suffix('%')
                                                    ->visible(fn (Get $get) => in_array($get('type'), ['assistant', 'external-assistant'])),

                                                Hidden::make('matter_id'),
                                                Hidden::make('role')->default('expert'),
                                                Hidden::make('id'),
                                            ])
                                            ->addActionLabel(__('Add Expert'))
                                            ->itemLabel(function (array $state): ?string {
                                                $type = $state['type'] ?? '';
                                                $role = $state['role'] ?? '';

                                                return $type ? "{$type} ({$role})" : null;
                                            })
                                            ->addAction(
                                                fn (Action $action) => $action
                                                    ->color('warning')
                                                    ->icon('heroicon-m-plus-circle')
                                            )
                                            ->addActionAlignment(Alignment::Start),

                                        Repeater::make('mainPartiesOnly')
                                            ->relationship('mainPartiesOnly')
                                            ->label(__('Parties'))
                                            ->columns(3)
                                            ->itemNumbers()
                                            ->schema([
                                                Select::make('type')
                                                    ->label(__('Type'))
                                                    ->options([
                                                        'plaintiff' => __('Plaintiff'),
                                                        'defendant' => __('Defendant'),
                                                        'implicate-litigant' => __('Implicate Litigant'),
                                                    ])
                                                    ->required()
                                                    ->live(onBlur: true) // onBlur reduces round-trips
                                                    ->afterStateUpdated(function (Set $set) {
                                                        $set('role', 'party');
                                                        $set('party_id', null);
                                                        $set('representatives', []);
                                                    }),

                                                // No ->live() — nothing downstream reads party_id changes
                                                Select::make('party_id')
                                                    ->label(__('Party Name'))
                                                    ->relationship('party', 'name', function ($query) {
                                                        $query->whereJsonContains('role', [['role' => 'party']]);
                                                    })
                                                    ->createOptionForm(fn (Schema $schema) => PartyForm::configure($schema))
                                                    ->editOptionForm(fn (Schema $schema) => PartyForm::configure($schema))
                                                    ->createOptionAction(function (Action $action) {
                                                        return $action->fillForm([
                                                            'role' => [
                                                                'role' => ['party'],
                                                            ],
                                                        ]);
                                                    })
                                                    ->createOptionUsing(function (array $data) {
                                                        return Party::create($data)->id;
                                                    })
                                                    ->searchable()
                                                    ->preload()
                                                    ->columnSpan(2)
                                                    ->required(),

                                                Hidden::make('matter_id'),
                                                Hidden::make('role')->default('party'),
                                                Hidden::make('id'),

                                                Repeater::make('representatives')
                                                    ->relationship('representatives')
                                                    ->label(__('Representatives / Lawyers'))
                                                    ->table([
                                                        Repeater\TableColumn::make(__('Name'))->width('60%'),
                                                        Repeater\TableColumn::make(__('Email'))->width('25%'),
                                                        Repeater\TableColumn::make(__('Phone'))->width('15%'),
                                                    ])
                                                    ->columns(2)
                                                    // ->compact()
                                                    ->schema([
                                                        Select::make('party_id')
                                                            ->label(__('Representative'))
                                                            ->relationship('party', 'name', function ($query) {
                                                                $query->whereJsonContains('role', [['role' => 'representative']]);
                                                            })
                                                            ->createOptionForm(fn (Schema $schema) => PartyForm::configure($schema))
                                                            ->editOptionForm(fn (Schema $schema) => PartyForm::configure($schema))
                                                            ->createOptionAction(function (Action $action) {
                                                                return $action->fillForm([
                                                                    'role' => [
                                                                        'role' => ['representative'],
                                                                    ],
                                                                ]);
                                                            })
                                                            ->createOptionUsing(function (array $data) {
                                                                return Party::create($data)->id;
                                                            })
                                                            ->afterStateUpdated(function (Get $get, Set $set) {
                                                                $party = Party::find($get('party_id'));
                                                                $set('party_email', $party->email);
                                                                $set('party_phone', $party->phone);
                                                            })
                                                            ->live(onBlur: true)
                                                            ->preload()
                                                            ->required()
                                                            ->searchable()
                                                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                                            ->columnSpanFull(),
                                                        TextInput::make('party_email')->disabled(),
                                                        TextInput::make('party_phone')->disabled(),
                                                        Hidden::make('role')->default('representative'),

                                                        Hidden::make('type')
                                                            ->dehydrateStateUsing(fn (Get $get) => $get('../../type')),

                                                        Hidden::make('parent_id')
                                                            ->dehydrateStateUsing(fn (Get $get) => $get('../../id')),

                                                        Hidden::make('matter_id')
                                                            ->dehydrateStateUsing(fn (Get $get, $state) => $state ?: $get('../../matter_id')),
                                                    ])
                                                    ->addAction(
                                                        fn (Action $action) => $action
                                                            ->color('warning')
                                                            ->icon('heroicon-m-plus-circle')
                                                    )
                                                    ->addActionLabel(__('Add Representative'))
                                                    ->compact()
                                                    ->columnSpanFull(),
                                            ])
                                            ->addActionLabel(__('Add Party'))
                                            ->itemLabel(function (array $state): ?string {
                                                $type = $state['type'] ?? '';
                                                $role = $state['role'] ?? '';

                                                return $type ? __($role).' ('.__($type).')' : null;
                                            })
                                            ->addActionAlignment(Alignment::Start)
                                            ->addAction(
                                                fn (Action $action) => $action
                                                    ->color('warning')
                                                    ->icon('heroicon-m-plus-circle')
                                            ),
                                    ]),

                                Section::make(__('Fees'))
                                    ->visible(fn () => auth()->user()->can('CreateFee:Matter') || auth()->user()->can('UpdateFee:Matter'))
                                    ->schema([
                                        Repeater::make('fees')
                                            ->label(__('Fees'))
                                            ->relationship('fees')
                                            ->table([
                                                Repeater\TableColumn::make(__('Type')),
                                                Repeater\TableColumn::make(__('Amount')),
                                                Repeater\TableColumn::make(__('Including VAT'))
                                                    ->alignment(Alignment::Center),
                                            ])
                                            ->compact()
                                            ->schema([
                                                Select::make('type')
                                                    ->label(__('Type'))
                                                    ->options(FeeType::class)
                                                    ->required(),

                                                TextInput::make('amount')
                                                    ->label(__('Amount'))
                                                    ->numeric()
                                                    ->required()
                                                    ->prefix(fn (Get $get) => $get('type')?->isNegative() ? '-' : '+')
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                        if (! $get('including_vat')) {
                                                            return;
                                                        }

                                                        $amount = (float) $state;
                                                        if ($amount <= 0) {
                                                            return;
                                                        }

                                                        $rowId = $get('row_id');
                                                        $fees = $get('../../fees') ?? [];

                                                        $currentKey = null;
                                                        foreach ($fees as $key => $fee) {
                                                            if (($fee['row_id'] ?? null) === $rowId) {
                                                                $currentKey = $key;
                                                                break;
                                                            }
                                                        }
                                                        if ($currentKey === null) {
                                                            return;
                                                        }

                                                        $keys = array_keys($fees);
                                                        $currentPos = array_search($currentKey, $keys, true);
                                                        $nextKey = $keys[$currentPos + 1] ?? null;

                                                        if (! $nextKey || ($fees[$nextKey]['type'] ?? '') !== 'vat') {
                                                            return;
                                                        }

                                                        $basic = round($amount / 1.05, 2);
                                                        $vat = round($amount - $basic, 2);

                                                        $set('amount', $basic);
                                                        $fees[$currentKey]['amount'] = $basic;
                                                        $fees[$nextKey]['amount'] = $vat;

                                                        $set('../../fees', $fees);
                                                    }),

                                                Toggle::make('including_vat')
                                                    ->label(__('Including VAT'))
                                                    ->live()
                                                    ->inline(false)
                                                    ->extraAttributes(['class' => 'mx-4'])
                                                    ->disabled(fn (Get $get) => $get('type') === 'vat')
                                                    ->afterStateUpdated(function (bool $state, Set $set, Get $get) {
                                                        $amount = (float) $get('amount');
                                                        $rowId = $get('row_id');
                                                        $fees = $get('../../fees') ?? [];

                                                        $currentKey = null;
                                                        foreach ($fees as $key => $fee) {
                                                            if (($fee['row_id'] ?? null) === $rowId) {
                                                                $currentKey = $key;
                                                                break;
                                                            }
                                                        }
                                                        if ($currentKey === null) {
                                                            return;
                                                        }

                                                        $keys = array_keys($fees);
                                                        $currentPos = array_search($currentKey, $keys, true);

                                                        if ($state) {
                                                            if ($amount <= 0) {
                                                                return;
                                                            }

                                                            $basic = round($amount / 1.05, 2);
                                                            $vat = round($amount - $basic, 2);

                                                            $set('amount', $basic);
                                                            $fees[$currentKey]['amount'] = $basic;
                                                            $fees[$currentKey]['including_vat'] = true;

                                                            $vatRow = [
                                                                'row_id' => (string) Str::uuid(),
                                                                'type' => 'vat',
                                                                'amount' => $vat,
                                                                'including_vat' => false,
                                                            ];

                                                            $newFees = [];
                                                            foreach ($fees as $k => $fee) {
                                                                $newFees[$k] = $fee;
                                                                if ($k === $currentKey) {
                                                                    $newFees[(string) Str::uuid()] = $vatRow;
                                                                }
                                                            }
                                                            $set('../../fees', $newFees);

                                                        } else {
                                                            $nextKey = $keys[$currentPos + 1] ?? null;

                                                            if ($nextKey && ($fees[$nextKey]['type'] ?? '') === 'vat') {
                                                                $vatAmount = (float) $fees[$nextKey]['amount'];
                                                                $restored = round($amount + $vatAmount, 2);

                                                                $set('amount', $restored);
                                                                $fees[$currentKey]['amount'] = $restored;
                                                                $fees[$currentKey]['including_vat'] = false;

                                                                unset($fees[$nextKey]);
                                                                $set('../../fees', $fees);
                                                            }
                                                        }
                                                    }),

                                                Hidden::make('row_id')
                                                    ->default(fn () => (string) Str::uuid()),
                                            ])
                                            ->columns(1)
                                            ->addActionLabel(__('Add Fee')),
                                    ]),
                            ])
                            ->columnSpan(2),
                    ])->columnSpanFull(),
            ]);
    }
}
