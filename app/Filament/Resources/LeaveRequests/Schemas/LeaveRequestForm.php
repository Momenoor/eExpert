<?php

namespace App\Filament\Resources\LeaveRequests\Schemas;

use App\Models\LeaveRequest;
use App\Models\Party;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * What an employee fills in: who, when, and why.
 *
 * Deliberately NOT how the absence divides across leave types. An employee
 * asking for five days is making a request, not a determination — whether those
 * days come out of the annual balance, count as casual, or go unpaid is a
 * decision about entitlements the employee cannot see and should not be asked to
 * guess at. The approver makes that call, on the approval form.
 */
class LeaveRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Leave Request'))
                    ->schema([
                        Select::make('party_id')
                            ->label(__('Employee'))
                            ->options(fn () => self::employeeOptions())
                            ->default(fn () => auth()->user()?->party?->id)
                            ->searchable()
                            ->required()
                            // Everyone but management files for themselves. The
                            // field is fixed rather than hidden so the employee
                            // can see whose leave they are booking; the page
                            // enforces it again on save, because a disabled
                            // field is a courtesy, not a barrier.
                            ->disabled(fn (): bool => ! self::managesOthers())
                            ->dehydrated()
                            ->helperText(fn (): ?string => self::managesOthers()
                                ? __('You can file a request on behalf of any employee.')
                                : null),
                        DatePicker::make('start_date')
                            ->label(__('From'))
                            ->required(),
                        DatePicker::make('end_date')
                            ->label(__('To'))
                            ->required()
                            ->afterOrEqual('start_date'),
                        Textarea::make('comment')
                            ->label(__('Reason'))
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(3),
            ]);
    }

    /**
     * The employees this user may file for.
     *
     * @return array<int, string>
     */
    private static function employeeOptions(): array
    {
        if (self::managesOthers()) {
            return Party::withRole('employee')->orderBy('name')->pluck('name', 'id')->all();
        }

        $party = auth()->user()?->party;

        return $party === null ? [] : [$party->getKey() => $party->name];
    }

    private static function managesOthers(): bool
    {
        return auth()->user()?->can('manageOthers', LeaveRequest::class) ?? false;
    }
}
