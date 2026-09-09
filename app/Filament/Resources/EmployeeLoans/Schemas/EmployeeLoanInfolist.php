<?php

namespace App\Filament\Resources\EmployeeLoans\Schemas;

use App\Models\EmployeeLoan;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmployeeLoanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Advance'))
                    ->schema([
                        TextEntry::make('party.name')->label(__('Employee')),
                        TextEntry::make('kind')->label(__('Type'))->badge(),
                        TextEntry::make('principal')
                            ->label(__('Amount (AED)'))
                            ->numeric(decimalPlaces: 2),
                        TextEntry::make('months')->label(__('Months')),
                        TextEntry::make('starts_on')->label(__('First Instalment'))->date(),
                        TextEntry::make('status')->label(__('Status'))->badge(),
                    ])->columns(3),

                Section::make(__('Recovery'))
                    ->schema([
                        TextEntry::make('recovered')
                            ->label(__('Recovered (AED)'))
                            ->state(fn (EmployeeLoan $record): string => number_format(
                                (float) $record->installments()->whereNotNull('payslip_id')->sum('amount'),
                                2,
                            )),
                        TextEntry::make('outstanding')
                            ->label(__('Outstanding (AED)'))
                            ->weight('bold')
                            ->state(fn (EmployeeLoan $record): string => number_format($record->outstanding(), 2)),
                        TextEntry::make('editable')
                            ->label(__('Editable'))
                            ->badge()
                            // The commonest question about a locked advance is
                            // why, so the answer is on the record rather than
                            // only in the notification that appears on the way
                            // out of the edit page.
                            ->state(fn (EmployeeLoan $record): string => $record->isEditable()
                                ? __('Yes')
                                : __('No — payroll has started recovering it'))
                            ->color(fn (EmployeeLoan $record): string => $record->isEditable() ? 'success' : 'gray'),
                    ])->columns(3),

                Section::make(__('Approval'))
                    ->schema([
                        TextEntry::make('approver.display_name')
                            ->label(__('Approved By'))
                            ->placeholder('—'),
                        TextEntry::make('approved_at')
                            ->label(__('Approved At'))
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('notes')
                            ->label(__('Notes'))
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }
}
