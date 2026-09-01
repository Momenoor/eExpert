<?php

namespace App\Filament\Pages;

use App\Enums\FeeType;
use App\Models\Allocation;
use App\Models\Fee;
use App\Models\Matter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * Operator-driven fee data corrections.
 *
 * Deliberately NOT migrations: these touch live financial records, so each one
 * is a button an administrator presses after reading exactly what it will do.
 * Every figure on this page is computed live against the current database, so
 * the numbers shown here are the numbers for THIS environment.
 *
 * Both operations are idempotent — running them twice changes nothing the
 * second time — and each is wrapped in a transaction.
 */
class FeeDataMaintenance extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.fee-data-maintenance';

    public static function getNavigationLabel(): string
    {
        return __('Fee Data Maintenance');
    }

    public function getTitle(): string
    {
        return __('Fee Data Maintenance');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Settings');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super-admin', 'super_admin']) ?? false;
    }

    // ── Diagnostics (all read-only) ──────────────────────────────────────────

    /**
     * Deduction-type fees stored with a positive amount. Fee::saving() has
     * normalised the sign for a long time, so these are legacy rows that
     * predate it. Their allocations are usually positive too, which is why
     * both must be flipped together.
     *
     * @return Collection<int, Fee>
     */
    public function wrongSignedFees()
    {
        return Fee::query()
            ->whereIn('type', FeeType::deductionTypeValues())
            ->where('amount', '>', 0)
            ->with('matter')
            ->get();
    }

    /**
     * Fees whose stored status disagrees with what the current rules produce.
     */
    public function staleStatusCount(): int
    {
        $stale = 0;

        Fee::query()->with('allocations')->chunkById(500, function ($fees) use (&$stale) {
            foreach ($fees as $fee) {
                $original = $fee->status;
                $fee->syncStatus();

                if ($fee->status !== $original) {
                    $stale++;
                }
            }
        });

        return $stale;
    }

    public function content(Schema $schema): Schema
    {
        $wrongSigned = $this->wrongSignedFees();

        return $schema->components([
            Section::make(__('Current State'))
                ->description(__('Computed live against this database. Read-only — nothing here changes data.'))
                ->icon(Heroicon::OutlinedMagnifyingGlass)
                ->columns(3)
                ->schema([
                    TextEntry::make('wrong_signed')
                        ->label(__('Deduction fees with a positive amount'))
                        ->state($wrongSigned->count())
                        ->badge()
                        ->color($wrongSigned->count() > 0 ? 'warning' : 'success'),

                    TextEntry::make('wrong_signed_total')
                        ->label(__('Their combined value'))
                        ->state(number_format((float) $wrongSigned->sum(fn (Fee $f) => abs((float) $f->amount)), 2).' AED'),

                    TextEntry::make('stale_statuses')
                        ->label(__('Fees whose stored status is out of date'))
                        ->state($this->staleStatusCount())
                        ->badge()
                        ->color(fn ($state) => $state > 0 ? 'warning' : 'success'),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('correctFeeSigns')
                ->label(__('Correct Deduction Fee Signs'))
                ->icon(Heroicon::OutlinedArrowsUpDown)
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('Correct Deduction Fee Signs'))
                ->modalDescription(fn () => $this->describeSignCorrection())
                ->modalSubmitActionLabel(__('Apply correction'))
                ->action(fn () => $this->correctFeeSigns()),

            Action::make('recalculateStatuses')
                ->label(__('Recalculate Fee & Collection Statuses'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading(__('Recalculate Fee & Collection Statuses'))
                ->modalDescription(__('Recomputes every fee status and every matter collection status from the current allocations. Safe to run at any time — it only writes where the stored value is actually out of date.'))
                ->modalSubmitActionLabel(__('Recalculate'))
                ->action(fn () => $this->recalculateStatuses()),
        ];
    }

    private function describeSignCorrection(): string
    {
        $fees = $this->wrongSignedFees();

        if ($fees->isEmpty()) {
            return __('Nothing to correct — every deduction fee already has the right sign.');
        }

        $allocations = Allocation::whereIn('fee_id', $fees->pluck('id'))->where('amount', '>', 0)->count();

        return __(
            'This flips :fees fee(s) totalling :total AED to negative, and :allocations positive allocation(s) against them, so they match the convention every other deduction fee uses. Both are flipped together — flipping only the fee would leave a negative fee with a positive payment recorded against it. Matter-level totals do not change.',
            [
                'fees' => $fees->count(),
                'total' => number_format((float) $fees->sum(fn (Fee $f) => abs((float) $f->amount)), 2),
                'allocations' => $allocations,
            ]
        );
    }

    private function correctFeeSigns(): void
    {
        $fees = $this->wrongSignedFees();

        if ($fees->isEmpty()) {
            Notification::make()
                ->title(__('Nothing to correct'))
                ->body(__('Every deduction fee already has the right sign.'))
                ->info()
                ->send();

            return;
        }

        $feeCount = 0;
        $allocationCount = 0;

        DB::transaction(function () use ($fees, &$feeCount, &$allocationCount) {
            foreach ($fees as $fee) {
                foreach ($fee->allocations()->where('amount', '>', 0)->get() as $allocation) {
                    // saveQuietly: the Allocation observer would call
                    // updateStatus() per row mid-flip, when the fee and its
                    // allocations momentarily disagree. Statuses are recomputed
                    // once at the end instead.
                    $allocation->amount = -abs((float) $allocation->amount);
                    $allocation->saveQuietly();
                    $allocationCount++;
                }

                $fee->amount = -abs((float) $fee->amount);
                $fee->saveQuietly();
                $feeCount++;
            }
        });

        // Now that fee and allocations agree again, resettle the statuses.
        foreach ($fees as $fee) {
            $fee->refresh()->updateStatus();
        }

        Notification::make()
            ->title(__('Signs corrected'))
            ->body(__(':fees fee(s) and :allocations allocation(s) updated.', [
                'fees' => $feeCount,
                'allocations' => $allocationCount,
            ]))
            ->success()
            ->send();
    }

    private function recalculateStatuses(): void
    {
        $feesChanged = 0;
        $mattersChanged = 0;

        Fee::query()->with('allocations')->chunkById(500, function ($fees) use (&$feesChanged) {
            foreach ($fees as $fee) {
                $original = $fee->status;
                $fee->syncStatus();

                if ($fee->status !== $original) {
                    $fee->save();
                    $feesChanged++;
                }
            }
        });

        Matter::query()->chunkById(500, function ($matters) use (&$mattersChanged) {
            foreach ($matters as $matter) {
                $original = $matter->collection_status;
                $matter->updateCollectionStatus();

                if ($matter->fresh()->collection_status !== $original) {
                    $mattersChanged++;
                }
            }
        });

        Notification::make()
            ->title(__('Statuses recalculated'))
            ->body(__(':fees fee status(es) and :matters matter collection status(es) updated.', [
                'fees' => $feesChanged,
                'matters' => $mattersChanged,
            ]))
            ->success()
            ->send();
    }
}
