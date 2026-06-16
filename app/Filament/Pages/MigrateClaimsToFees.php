<?php

namespace App\Filament\Pages;

use App\Models\Allocation;
use App\Models\Cash;
use App\Models\Claim;
use App\Models\Fee;
use App\Models\Matter;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

class MigrateClaimsToFees extends Page
{
    use HasPageShield;
    protected string $view = 'filament.pages.migrate-claims-to-fees';
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-arrow-path';
    protected static ?string $navigationLabel = 'Migrate Claims to Fees';
    protected static ?string $title = 'Migrate Claims to Fees';

    public static function getNavigationLabel(): string
    {
        return __('Migrate Claims to Fees');
    }

    public function getTitle(): string
    {
        return __('Migrate Claims to Fees');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Current Statistics'))->schema([
                TextEntry::make('total_claims')
                    ->label(__('Total Claims'))
                    ->default(Claim::count()),
                TextEntry::make('total_cashes')
                    ->label(__('Total Cashes'))
                    ->default(Cash::count()),
                TextEntry::make('total_fees')
                    ->label(__('Total Fees'))
                    ->default(Fee::count()),
                TextEntry::make('total_allocations')
                    ->label(__('Total Allocations'))
                    ->default(Allocation::count()),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('migrate')
                ->label(__('Migrate Now'))
                ->color('primary')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalHeading(__('Migrate Claims to Fees'))
                ->modalDescription(__('This will create Fees from existing Claims and Allocations from existing Cashes. Are you sure?'))
                ->action(fn() => $this->runMigration()),
            Action::make('fixStatuses')
                ->label(__('Fix Collection Statuses'))
                ->color('warning')
                ->icon('heroicon-o-wrench-screwdriver')
                ->requiresConfirmation()
                ->modalHeading(__('Fix All Collection Statuses'))
                ->modalDescription(__('This will recalculate the status of ALL Fees and the Collection Status of ALL Matters based on their current allocations. Are you sure?'))
                ->action(fn() => $this->runFixStatuses()),
        ];
    }

    protected function runFixStatuses(): void
    {
        $totalFees = Fee::count();
        $totalMatters = Matter::count();
        $total = $totalFees + $totalMatters;

        if ($total === 0) {
            Notification::make()->title(__('No records to fix'))->info()->send();
            return;
        }

        DB::transaction(function () {
            // Update all Fee statuses
            Fee::chunk(100, function ($fees) {
                foreach ($fees as $fee) {
                    $fee->updateStatus();
                }
            });

            // Update all Matter collection statuses
            Matter::chunk(100, function ($matters) {
                foreach ($matters as $matter) {
                    $matter->updateCollectionStatus();
                }
            });
        });

        Notification::make()
            ->title(__('Status Fix Successful'))
            ->body(__('All fee and matter collection statuses have been recalculated.'))
            ->success()
            ->send();
    }

    protected function runMigration(): void
    {
        $totalClaims = Claim::count();
        $totalMatters = Matter::has('fees')->count();
        $total = $totalClaims + $totalMatters;

        if ($total === 0) {
            Notification::make()->title(__('No claims to migrate'))->info()->send();
            return;
        }

        DB::transaction(function () {
            // Get all claims that haven't been migrated yet (or just all if we're doing a full migration)
            Claim::chunk(100, function ($claims) {
                foreach ($claims as $claim) {
                    // Create Fee
                    $fee = Fee::create([
                        'matter_id' => $claim->matter_id,
                        'user_id' => $claim->user_id,
                        'type' => $claim->type,
                        'amount' => $claim->amount,
                        'date' => $claim->date,
                        'description' => 'Migrated from claim ID: ' . $claim->id,
                    ]);

                    // Create Allocations for this Fee from associated Cashes
                    $cashes = Cash::where('claim_id', $claim->id)->get();
                    foreach ($cashes as $cash) {
                        Allocation::create([
                            'fee_id' => $fee->id,
                            'matter_id' => $cash->matter_id,
                            'user_id' => $cash->user_id,
                            'amount' => $cash->amount,
                            'date' => $cash->datetime,
                            'description' => $cash->description ?: 'Migrated from cash ID: ' . $cash->id,
                        ]);
                    }

                    // Update Fee Status
                    $fee->updateStatus();
                }
            });

            // Update all matters collection status
            Matter::has('fees')->chunk(100, function ($matters) {
                foreach ($matters as $matter) {
                    $matter->updateCollectionStatus();
                }
            });
        });

        Notification::make()
            ->title(__('Migration Successful'))
            ->body(__('All claims and cashes have been migrated to fees and allocations.'))
            ->success()
            ->send();
    }
}
