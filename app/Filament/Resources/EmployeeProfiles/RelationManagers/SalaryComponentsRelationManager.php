<?php

namespace App\Filament\Resources\EmployeeProfiles\RelationManagers;

use App\Enums\SalaryComponent;
use App\Filament\Concerns\RefreshesPayrollData;
use App\Models\EmployeeProfile;
use App\Models\EmployeeSalaryComponent;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Salary structure, managed as a history rather than a set of editable numbers.
 *
 * A raise opens a new row and closes the one it replaces. Editing the amount in
 * place would change what last month's payslip says it paid and what the
 * gratuity accrual was based on — figures the office has already reconciled
 * against a bank transfer.
 */
class SalaryComponentsRelationManager extends RelationManager
{
    use RefreshesPayrollData;

    protected static string $relationship = 'salaryComponents';

    protected static ?string $relatedResource = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Salary Structure');
    }

    /**
     * The relation hangs off the party, not the profile.
     *
     * Salary is keyed to `party_id` like every other record in this module, so
     * the manager resolves it through the profile's party rather than declaring
     * a second foreign key on the profile itself.
     */
    public function getRelationship(): Relation
    {
        return $this->profile()->party->salaryComponents();
    }

    /**
     * The employee profile this manager is attached to.
     *
     * getOwnerRecord() is declared as a bare Model, so the narrowing has to
     * happen somewhere; doing it once here keeps the rest of the class working
     * with a real type instead of guessing.
     */
    private function profile(): EmployeeProfile
    {
        $record = $this->getOwnerRecord();

        if (!$record instanceof EmployeeProfile) {
            throw new LogicException('This relation manager only attaches to an employee profile.');
        }

        return $record;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('component')
                ->label(__('Component'))
                ->options(SalaryComponent::class)
                ->required(),
            TextInput::make('amount')
                ->label(__('Monthly Amount (AED)'))
                ->numeric()
                ->minValue(0)
                ->required(),
            DatePicker::make('effective_from')
                ->label(__('Effective From'))
                ->default(now()->startOfMonth())
                ->required(),
        ])->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('component')
                    ->label(__('Component'))
                    ->badge(),
                TextColumn::make('amount')
                    ->label(__('Monthly Amount (AED)'))
                    ->numeric(decimalPlaces: 2)
                    ->summarize(Sum::make()->query(fn($query) => $query->where('effective_to', null))->label(__('Total'))),
                TextColumn::make('effective_from')
                    ->label(__('Effective From'))
                    ->date()
                    ->sortable(),
                TextColumn::make('effective_to')
                    ->label(__('Effective To'))
                    ->date()
                    ->placeholder(__('Current')),
            ])
            ->defaultSort('effective_from', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label(__('Add / Revise Component'))
                    ->using(function (array $data): EmployeeSalaryComponent {
                        $component = $this->openRevision($data);

                        // Closing the previous row changes another line in this
                        // very table, which the create action would not redraw.
                        $this->dispatchPayrollDataUpdated();

                        return $component;
                    }),
            ])
            ->recordActions([
                // Deliberately no EditAction. The only sanctioned change to a
                // booked component is closing it and opening a new one, which is
                // what "Add / Revise" does.
                Action::make('close')
                    ->label(__('End'))
                    ->icon('heroicon-o-x-mark')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription(__('This component stops counting from the date you choose. Existing payslips are unaffected.'))
                    ->schema([
                        DatePicker::make('effective_to')
                            ->label(__('Effective To'))
                            ->default(now()->endOfMonth())
                            ->required(),
                    ])
                    ->visible(fn(EmployeeSalaryComponent $record): bool => $record->effective_to === null)
                    ->action(function (EmployeeSalaryComponent $record, array $data): void {
                        $record->forceFill(['effective_to' => $data['effective_to']])->save();

                        $this->dispatchPayrollDataUpdated();
                    }),
                DeleteAction::make()
                    ->iconButton()
                    // Only a row nothing has been paid against may be removed;
                    // anything older is closed, not deleted.
                    ->visible(fn(EmployeeSalaryComponent $record): bool => $record->effective_from->isFuture()),
            ])->filters([
                Filter::make('effective_to')
                    ->label(__('Effective Only'))
                    ->query(fn($query) => $query->where('effective_to', null)),
            ])->emptyStateHeading(__('No salary components yet'));
    }

    /**
     * Close whatever this component was, and open the new figure.
     */
    private function openRevision(array $data): EmployeeSalaryComponent
    {
        $partyId = $this->profile()->party_id;
        $from = Carbon::parse($data['effective_from']);

        return DB::transaction(function () use ($data, $partyId, $from): EmployeeSalaryComponent {
            EmployeeSalaryComponent::query()
                ->where('party_id', $partyId)
                ->where('component', $data['component'])
                ->whereNull('effective_to')
                ->where(fn(Builder $query) => $query->whereDate('effective_from', '<', $from->toDateString()))
                ->update(['effective_to' => $from->copy()->subDay()->toDateString()]);

            return EmployeeSalaryComponent::create([
                'party_id' => $partyId,
                'component' => $data['component'],
                'amount' => $data['amount'],
                'effective_from' => $from->toDateString(),
            ]);
        });
    }
}
