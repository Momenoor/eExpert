<?php

namespace App\Filament\Resources\LeaveRequests\Tables;

use App\Enums\LeaveType;
use App\Enums\RequestStatus;
use App\Filament\Actions\Leave\ApproveLeaveAction;
use App\Filament\Actions\Leave\RejectLeaveAction;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestPeriod;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LeaveRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['party', 'periods', 'approver']))
            ->columns([
                TextColumn::make('party.name')
                    ->label(__('Employee'))
                    ->searchable()
                    ->sortable()
                    // An employee's own queue is all one name.
                    ->visible(fn (): bool => self::managesOthers()),
                TextColumn::make('start_date')
                    ->label(__('From'))
                    ->date()
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label(__('To'))
                    ->date()
                    ->sortable(),
                TextColumn::make('requested')
                    ->label(__('Days Requested'))
                    ->state(fn (LeaveRequest $record): string => self::days($record->requestedDays())),
                // A pending request has no breakdown yet — classifying it is the
                // approver's job, done on the approval form. Until then this
                // column says so rather than showing a misleading blank.
                TextColumn::make('breakdown')
                    ->label(__('Breakdown'))
                    ->badge()
                    ->color('gray')
                    ->state(fn (LeaveRequest $record): array => $record->periods
                        ->map(fn (LeaveRequestPeriod $period): string => sprintf(
                            '%s %s',
                            self::days((float) $period->day_count),
                            $period->leave_type->getLabel(),
                        ))
                        ->all())
                    ->placeholder(fn (LeaveRequest $record): string => $record->isPending()
                        ? __('Set on approval')
                        : '—'),
                TextColumn::make('unpaid')
                    ->label(__('Unpaid Days'))
                    ->badge()
                    ->color(fn (float $state): string => $state > 0 ? 'danger' : 'success')
                    ->state(fn (LeaveRequest $record): float => $record->unpaidDays())
                    ->formatStateUsing(fn (float $state): string => self::days($state)),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
                TextColumn::make('created_at')
                    ->label(__('Submitted'))
                    ->dateTime()
                    ->since()
                    ->sortable(),
                TextColumn::make('approver.display_name')
                    ->label(__('Handled By'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('approved_comment')
                    ->label(__('Handling Note'))
                    ->wrap()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(RequestStatus::class)
                    ->multiple()
                    // The queue opens on what still needs a decision. Anything
                    // already handled is one filter click away.
                    ->default([RequestStatus::PENDING->value]),
                SelectFilter::make('leave_type')
                    ->label(__('Leave Type'))
                    ->options(LeaveType::class)
                    ->query(fn ($query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereHas('periods', fn ($periods) => $periods->where('leave_type', $data['value']))
                        : $query),
                SelectFilter::make('party_id')
                    ->label(__('Employee'))
                    ->relationship('party', 'name')
                    ->searchable()
                    ->visible(fn (): bool => self::managesOthers()),
            ])
            ->recordActions([
                ApproveLeaveAction::make(),
                RejectLeaveAction::make(),
                EditAction::make()
                    ->iconButton()
                    // Once decided, the record is evidence of a decision. Editing
                    // the dates afterwards would leave the leave ledger saying one
                    // thing and the request another.
                    ->visible(fn (LeaveRequest $record): bool => $record->isPending()),
                DeleteAction::make()
                    ->iconButton()
                    ->visible(fn (LeaveRequest $record): bool => $record->isPending()),
            ])
            ->emptyStateHeading(__('No leave requests'))
            ->emptyStateActions([
                CreateAction::make()->label(__('New Leave Request')),
            ]);
    }

    private static function managesOthers(): bool
    {
        return auth()->user()?->can('manageOthers', LeaveRequest::class) ?? false;
    }

    /**
     * Day counts without a trailing ".0", but keeping ".5" for half days.
     */
    private static function days(float $days): string
    {
        return rtrim(rtrim(number_format($days, 1), '0'), '.');
    }
}
