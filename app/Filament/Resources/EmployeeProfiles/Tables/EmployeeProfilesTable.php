<?php

namespace App\Filament\Resources\EmployeeProfiles\Tables;

use App\Models\EmployeeProfile;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmployeeProfilesTable
{
    /**
     * How far ahead a document counts as "expiring soon".
     */
    private const EXPIRY_WINDOW_DAYS = 60;

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('party.name')
                    ->label(__('Employee'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('employee_no')
                    ->label(__('Employee Number'))
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('designation')
                    ->label(__('Designation'))
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('date_of_joining')
                    ->label(__('Date of Joining'))
                    ->date()
                    ->sortable(),
                // One column for four expiry dates. Four separate columns would
                // push the table past the fold and still leave the reader to work
                // out which of them is the urgent one.
                TextColumn::make('expiring')
                    ->label(__('Expiring Documents'))
                    ->badge()
                    ->color('danger')
                    ->state(fn (EmployeeProfile $record): array => array_map(
                        fn (string $key): string => __(self::documentLabels()[$key]),
                        array_keys($record->expiringDocuments(self::EXPIRY_WINDOW_DAYS)),
                    ))
                    ->placeholder('—'),
                TextColumn::make('date_of_leaving')
                    ->label(__('Date of Leaving'))
                    ->date()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('date_of_joining', 'desc')
            ->filters([
                TernaryFilter::make('employed')
                    ->label(__('Currently Employed'))
                    ->placeholder(__('All'))
                    ->trueLabel(__('Currently Employed'))
                    ->falseLabel(__('Left'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('date_of_leaving'),
                        false: fn (Builder $query) => $query->whereNotNull('date_of_leaving'),
                        blank: fn (Builder $query) => $query,
                    )
                    ->default(true),
                Filter::make('expiring_documents')
                    ->label(__('Has Expiring Documents'))
                    ->query(fn (Builder $query) => $query->where(function (Builder $query): void {
                        foreach (array_keys(self::documentLabels()) as $column) {
                            $query->orWhereBetween($column, [now()->toDateString(), now()->addDays(self::EXPIRY_WINDOW_DAYS)->toDateString()]);
                        }
                    })),
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ])
            ->emptyStateHeading(__('No employee records yet'))
            ->emptyStateActions([
                CreateAction::make()->label(__('Add Employee')),
            ]);
    }

    /**
     * The expiry columns and the label each one shows as a badge.
     *
     * @return array<string, string>
     */
    private static function documentLabels(): array
    {
        return [
            'passport_expiry' => 'Passport',
            'emirates_id_expiry' => 'Emirates ID',
            'labour_card_expiry' => 'Labour Card',
            'residency_expiry' => 'Residency',
        ];
    }
}
