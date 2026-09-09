<?php

namespace App\Filament\Resources\LeaveRequests;

use App\Enums\RequestStatus;
use App\Filament\Resources\LeaveRequests\Pages\CreateLeaveRequest;
use App\Filament\Resources\LeaveRequests\Pages\EditLeaveRequest;
use App\Filament\Resources\LeaveRequests\Pages\ListLeaveRequests;
use App\Filament\Resources\LeaveRequests\Schemas\LeaveRequestForm;
use App\Filament\Resources\LeaveRequests\Tables\LeaveRequestsTable;
use App\Models\LeaveRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LeaveRequestResource extends Resource
{
    protected static ?string $model = LeaveRequest::class;

    public static function getModelLabel(): string
    {
        return __('Leave Request');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Leave Requests');
    }

    public static function getNavigationLabel(): string
    {
        return __('Leave Requests');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Human Resources');
    }

    protected static string|null|BackedEnum $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?int $navigationSort = 2;

    /**
     * Employees see their own requests; management sees everyone's.
     *
     * The queue carries reasons for absence — illness, family matters — so an
     * unscoped list would hand every employee a readable history of their
     * colleagues' private circumstances. Scoped here rather than on the page so
     * the restriction holds for the badge, the table and any future widget
     * built on the resource.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (auth()->user()?->can('manageOthers', LeaveRequest::class)) {
            return $query;
        }

        // A user with no linked employee record sees nothing rather than
        // everything: whereKey(null) would match no rows, but stating it as a
        // false condition makes the intent unmistakable.
        $partyId = auth()->user()?->party?->getKey();

        return $partyId === null
            ? $query->whereRaw('1 = 0')
            : $query->where('party_id', $partyId);
    }

    /**
     * How many decisions are waiting.
     *
     * Counted with a single aggregate rather than by hydrating the queue, since
     * this runs on every page load. Only shown to the people who can actually
     * decide — a badge counting requests an employee cannot act on is noise.
     */
    public static function getNavigationBadge(): ?string
    {
        if (! auth()->user()?->can('manageOthers', LeaveRequest::class)) {
            return null;
        }

        $pending = static::getModel()::query()
            ->where('status', RequestStatus::PENDING->value)
            ->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return LeaveRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LeaveRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeaveRequests::route('/'),
            'create' => CreateLeaveRequest::route('/create'),
            'edit' => EditLeaveRequest::route('/{record}/edit'),
        ];
    }
}
