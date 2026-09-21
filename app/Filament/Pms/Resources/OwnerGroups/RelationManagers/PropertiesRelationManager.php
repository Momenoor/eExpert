<?php

namespace App\Filament\Pms\Resources\OwnerGroups\RelationManagers;

use App\Models\OwnerGroup;
use App\Models\OwnerGroupBankAccount;
use App\Models\Property;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * The properties administered by this group, each tied to the one group
 * bank account its rent is paid into.
 */
class PropertiesRelationManager extends RelationManager
{
    protected static string $relationship = 'properties';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Properties');
    }

    private function group(): OwnerGroup
    {
        $record = $this->getOwnerRecord();

        if (! $record instanceof OwnerGroup) {
            throw new LogicException('This relation manager only attaches to an owner group.');
        }

        return $record;
    }

    /**
     * @return array<int, string>
     */
    private function accountOptions(): array
    {
        return $this->group()->bankAccounts()
            ->orderByDesc('is_default')
            ->get()
            ->mapWithKeys(fn (OwnerGroupBankAccount $account): array => [$account->getKey() => $account->label()])
            ->all();
    }

    private function defaultAccountId(): ?int
    {
        $id = $this->group()->bankAccounts()->orderByDesc('is_default')->orderBy('id')->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('Property Name'))
                    ->searchable(),
                TextColumn::make('bankAccount.bank_name')
                    ->label(__('Bank Account'))
                    ->description(fn (Property $record): ?string => $record->bankAccount?->getAttribute('iban') ?? $record->bankAccount?->getAttribute('account_no'))
                    ->placeholder('—'),
            ])
            ->headerActions([
                $this->addPropertyAction(),
            ])
            ->recordActions([
                $this->changeAccountAction(),
                Action::make('remove')
                    ->label(__('Remove from Group'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Property $record) => $record->update([
                        'owner_group_id' => null,
                        'owner_group_bank_account_id' => null,
                    ])),
            ])
            ->emptyStateHeading(__('No properties in this group yet'));
    }

    private function addPropertyAction(): Action
    {
        return Action::make('add_property')
            ->label(__('Add Property'))
            ->icon('heroicon-o-plus')
            ->schema([
                Select::make('property_id')
                    ->label(__('Property'))
                    ->options(fn (): array => Property::whereNull('owner_group_id')->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->required(),
                Select::make('owner_group_bank_account_id')
                    ->label(__('Bank Account'))
                    ->options(fn (): array => $this->accountOptions())
                    ->default(fn (): ?int => $this->defaultAccountId())
                    ->required(fn (): bool => $this->accountOptions() !== [])
                    ->helperText(__('Only this group\'s own accounts are offered.')),
            ])
            ->action(function (array $data): void {
                Property::whereNull('owner_group_id')->findOrFail($data['property_id'])->update([
                    'owner_group_id' => $this->group()->getKey(),
                    'owner_group_bank_account_id' => $data['owner_group_bank_account_id'] ?? null,
                ]);
            });
    }

    private function changeAccountAction(): Action
    {
        return Action::make('change_account')
            ->label(__('Change Bank Account'))
            ->icon('heroicon-o-banknotes')
            ->color('gray')
            ->fillForm(fn (Property $record): array => ['owner_group_bank_account_id' => $record->getAttribute('owner_group_bank_account_id')])
            ->schema([
                Select::make('owner_group_bank_account_id')
                    ->label(__('Bank Account'))
                    ->options(fn (): array => $this->accountOptions())
                    ->required(fn (): bool => $this->accountOptions() !== []),
            ])
            ->action(fn (Property $record, array $data) => $record->update([
                'owner_group_bank_account_id' => $data['owner_group_bank_account_id'] ?? null,
            ]));
    }
}
