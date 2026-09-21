<?php

namespace App\Filament\Pms\Resources\OwnerGroups\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * `name` is the group's own field. `phone`/`email` belong to a Party created
 * alongside it purely to supply the estate's own contact details — the page
 * classes (Create/EditOwnerGroup) split these back apart on save.
 */
class OwnerGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Owner Group'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Group Name'))
                            ->placeholder(__('e.g. Legal Heirs of Mahmoud Kalbat'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TagsInput::make('phone')
                            ->label(__('Phone'))
                            ->trim()
                            ->splitKeys(['Tab', ' ', ',', 'Enter']),
                        TagsInput::make('email')
                            ->label(__('Email'))
                            ->trim()
                            ->splitKeys(['Tab', ' ', ',', 'Enter'])
                            ->nestedRecursiveRules(['email']),
                        TextInput::make('trn')
                            ->label(__('TRN'))
                            ->maxLength(255),
                    ])->columns(2),

                Section::make(__('Bank Accounts'))
                    ->description(__('The estate\'s own accounts — where its share of the rent is paid, distinct from any individual heir\'s account. Each property in the group is linked to one of them.'))
                    ->schema([
                        Repeater::make('bankAccounts')
                            ->relationship()
                            ->label(__('Bank Accounts'))
                            ->schema([
                                TextInput::make('bank_name')
                                    ->label(__('Bank Name'))
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('account_no')
                                    ->label(__('Account No'))
                                    ->maxLength(255),
                                TextInput::make('iban')
                                    ->label(__('IBAN'))
                                    ->maxLength(34)
                                    ->rule('regex:/^AE\d{21}$/')
                                    ->validationMessages([
                                        'regex' => __('A UAE IBAN is AE followed by 21 digits.'),
                                    ])
                                    ->placeholder('AE070331234567890123456'),
                                Toggle::make('is_default')
                                    ->label(__('Default')),
                            ])
                            ->table([
                                TableColumn::make(__('Bank Name')),
                                TableColumn::make(__('Account No')),
                                TableColumn::make(__('IBAN')),
                                TableColumn::make(__('Default')),
                            ])
                            ->addActionLabel(__('Add Bank Account'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
