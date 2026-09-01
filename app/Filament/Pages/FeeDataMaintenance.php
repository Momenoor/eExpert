<?php

namespace App\Filament\Pages;

use App\Enums\FeeType;
use App\Models\Allocation;
use App\Models\Fee;
use App\Models\Matter;
use App\Models\Party;
use App\Services\FeeDataRepairService;
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
 * All operations are idempotent — running one twice changes nothing the second
 * time — and each is wrapped in a transaction.
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

    private function repairs(): FeeDataRepairService
    {
        return app(FeeDataRepairService::class);
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
    public function wrongSignedFees(): Collection
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
        $repairs = $this->repairs();
        $wrongSigned = $this->wrongSignedFees();
        $duplicates = $repairs->previewDuplicateAllocations();
        $over = $repairs->previewOverCollection();
        $settlement = $repairs->previewNonOwnerSettlement();
        $ownerId = $repairs->officeOwnerPartyId();
        $owner = $ownerId ? Party::find($ownerId) : null;

        return $schema->components([
            Section::make(__('Office Owner'))
                ->description(__('Matters whose certified expert is anyone else are treated as commission matters. Confirm this is correct before running the settlement repair.'))
                ->icon(Heroicon::OutlinedUserCircle)
                ->columns(2)
                ->schema([
                    TextEntry::make('owner_name')
                        ->label(__('Treated as the office owner'))
                        ->state($owner?->name ?? __('Could not be determined'))
                        ->badge()
                        ->color($owner ? 'success' : 'danger'),

                    TextEntry::make('non_owner_matters')
                        ->label(__('Matters with another certified expert'))
                        ->state($repairs->nonOwnerMatterIds()->count()),
                ]),

            Section::make(__('Pending Repairs'))
                ->description(__('Computed live against this database. Read-only — nothing here changes data.'))
                ->icon(Heroicon::OutlinedMagnifyingGlass)
                ->columns(3)
                ->schema([
                    TextEntry::make('misaligned')
                        ->label(__('Payments running against their fee'))
                        ->state(function () {
                            $p = $this->repairs()->previewAllocationSignAlignment();

                            return $p['rows'].'  ('.number_format($p['value'], 2).' AED)';
                        })
                        ->badge()
                        ->color(fn () => $this->repairs()->previewAllocationSignAlignment()['rows'] > 0 ? 'danger' : 'success'),

                    TextEntry::make('duplicates')
                        ->label(__('Duplicate allocation rows'))
                        ->state($duplicates['rows'].'  ('.number_format($duplicates['value'], 2).' AED)')
                        ->badge()
                        ->color($duplicates['rows'] > 0 ? 'warning' : 'success'),

                    TextEntry::make('over_collected')
                        ->label(__('Over-collected fees'))
                        ->state($over['fees'].'  ('.number_format($over['excess'], 2).' AED)')
                        ->badge()
                        ->color($over['fees'] > 0 ? 'warning' : 'success'),

                    TextEntry::make('unsettled')
                        ->label(__('Unsettled fees on non-owner matters'))
                        ->state($settlement['fees'].'  ('.number_format($settlement['shortfall'], 2).' AED short)')
                        ->badge()
                        ->color($settlement['fees'] > 0 ? 'warning' : 'success'),

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
            Action::make('alignAllocationSigns')
                ->label(__('0. Align Allocation Signs'))
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('Align Allocation Signs'))
                ->modalDescription(function () {
                    $p = $this->repairs()->previewAllocationSignAlignment();

                    if ($p['rows'] === 0) {
                        return __('Nothing to align — every payment runs the same way as the fee it pays.');
                    }

                    return __('Flips :rows payment(s) worth :value AED across :fees fee(s) so they run the same way as the fee they pay. A deduction fee settled by a positive payment cancels nothing: the matter is billed less and received more, which is what makes such matters look over-collected. Run this FIRST.', [
                        'rows' => $p['rows'],
                        'value' => number_format($p['value'], 2),
                        'fees' => $p['fees'],
                    ]);
                })
                ->modalSubmitActionLabel(__('Align signs'))
                ->action(function () {
                    $r = $this->repairs()->alignAllocationSigns();

                    Notification::make()
                        ->title($r['rows'] > 0 ? __('Allocation signs aligned') : __('Nothing to align'))
                        ->body(__(':rows payment(s) flipped, worth :value AED.', [
                            'rows' => $r['rows'],
                            'value' => number_format($r['value'], 2),
                        ]))
                        ->success()
                        ->send();
                }),

            Action::make('removeDuplicates')
                ->label(__('1. Remove Duplicate Allocations'))
                ->icon(Heroicon::OutlinedDocumentDuplicate)
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('Remove Duplicate Allocations'))
                ->modalDescription(function () {
                    $p = $this->repairs()->previewDuplicateAllocations();

                    if ($p['rows'] === 0) {
                        return __('Nothing to remove — no allocation repeats the same fee, amount and date.');
                    }

                    return __('Deletes :rows duplicate payment row(s) worth :value AED, keeping the earliest of each set. Run this FIRST: several duplicates are themselves a cause of over-collection, so clearing them shrinks the next step.', [
                        'rows' => $p['rows'],
                        'value' => number_format($p['value'], 2),
                    ]);
                })
                ->modalSubmitActionLabel(__('Remove duplicates'))
                ->action(function () {
                    $r = $this->repairs()->removeDuplicateAllocations();

                    Notification::make()
                        ->title($r['rows'] > 0 ? __('Duplicates removed') : __('Nothing to remove'))
                        ->body(__(':rows row(s) deleted, worth :value AED.', [
                            'rows' => $r['rows'],
                            'value' => number_format($r['value'], 2),
                        ]))
                        ->success()
                        ->send();
                }),

            Action::make('trimOverCollection')
                ->label(__('2. Trim Over-Collection'))
                ->icon(Heroicon::OutlinedScissors)
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('Trim Over-Collection'))
                ->modalDescription(function () {
                    $p = $this->repairs()->previewOverCollection();

                    if ($p['fees'] === 0) {
                        return __('Nothing to trim — no fee has collected more than it billed.');
                    }

                    return __('Reduces collections on :fees fee(s) by :excess AED in total, so no fee is collected beyond its own amount. Newest payments are trimmed or removed first, leaving the original recorded payments intact.', [
                        'fees' => $p['fees'],
                        'excess' => number_format($p['excess'], 2),
                    ]);
                })
                ->modalSubmitActionLabel(__('Trim over-collection'))
                ->action(function () {
                    $r = $this->repairs()->trimOverCollection();

                    Notification::make()
                        ->title($r['fees'] > 0 ? __('Over-collection trimmed') : __('Nothing to trim'))
                        ->body(__(':fees fee(s) adjusted, :excess AED removed.', [
                            'fees' => $r['fees'],
                            'excess' => number_format($r['excess'], 2),
                        ]))
                        ->success()
                        ->send();
                }),

            Action::make('settleNonOwner')
                ->label(__('3. Settle Non-Owner Matters in Full'))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading(__('Settle Non-Owner Matters in Full'))
                ->modalDescription(function () {
                    $repairs = $this->repairs();
                    $p = $repairs->previewNonOwnerSettlement();
                    $owner = $repairs->officeOwnerPartyId() ? Party::find($repairs->officeOwnerPartyId()) : null;

                    if ($p['fees'] === 0) {
                        return __('Nothing to settle — every fee on those matters already matches its collections exactly.');
                    }

                    return __('Adds balancing payments totalling :shortfall AED across :fees fee(s) on :matters matter(s), so every fee is collected in full and closed. Applies to matters whose certified expert is NOT :owner. Over-collected fees are left to step 2 rather than recorded as negative payments.', [
                        'shortfall' => number_format($p['shortfall'], 2),
                        'fees' => $p['fees'],
                        'matters' => $p['matters'],
                        'owner' => $owner?->name ?? __('the office owner'),
                    ]);
                })
                ->modalSubmitActionLabel(__('Settle in full'))
                ->action(function () {
                    $r = $this->repairs()->settleNonOwnerMatters();

                    Notification::make()
                        ->title($r['fees'] > 0 ? __('Matters settled') : __('Nothing to settle'))
                        ->body(__(':fees fee(s) settled with :added AED. :skipped were over-collected and left for step 2.', [
                            'fees' => $r['fees'],
                            'added' => number_format($r['added'], 2),
                            'skipped' => $r['skipped_over'],
                        ]))
                        ->success()
                        ->send();
                }),

            Action::make('correctFeeSigns')
                ->label(__('Correct Deduction Fee Signs'))
                ->icon(Heroicon::OutlinedArrowsUpDown)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading(__('Correct Deduction Fee Signs'))
                ->modalDescription(fn () => $this->describeSignCorrection())
                ->modalSubmitActionLabel(__('Apply correction'))
                ->action(fn () => $this->correctFeeSigns()),

            Action::make('recalculateStatuses')
                ->label(__('Recalculate Fee & Collection Statuses'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
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
