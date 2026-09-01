<?php

namespace App\Services;

use App\Models\Allocation;
use App\Models\Fee;
use App\Models\Matter;
use App\Models\MatterParty;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bulk repairs for historical fee/allocation data.
 *
 * Every method comes in a preview/apply pair: the preview is read-only and
 * returns exactly what the apply would change, so an administrator can read the
 * numbers before committing. Nothing here runs automatically — these are driven
 * from the Fee Data Maintenance page.
 *
 * The three repairs INTERACT and must run in this order:
 *
 *   1. removeDuplicateAllocations() — 16 of the 20 duplicates are themselves a
 *      cause of over-collection, so clearing them first shrinks step 2.
 *   2. trimOverCollection()        — brings every fee down to at most its amount.
 *   3. settleNonOwnerMatters()     — tops the remainder up to exactly its amount.
 *
 * Running them out of order is not destructive, just less efficient: each is
 * idempotent and re-checks the live state, so a second run finds nothing to do.
 */
class FeeDataRepairService
{
    /** A cent. Money is decimal(15,2), so anything smaller is noise. */
    private const EPSILON = 0.005;

    // ── The office owner ─────────────────────────────────────────────────────

    /**
     * The certified expert who owns the office.
     *
     * Their matters carry no commission; every other certified expert's do.
     * Configurable via a setting, defaulting to whoever holds the most
     * certified-expert assignments — which is how the owner was identified in
     * the first place, and matches the default expert on the matter form.
     */
    public function officeOwnerPartyId(): ?int
    {
        $configured = Setting::get('office_owner_party_id');

        if ($configured) {
            return (int) $configured;
        }

        return MatterParty::query()
            ->where('role', 'expert')
            ->where('type', 'certified')
            ->selectRaw('party_id, COUNT(DISTINCT matter_id) as total')
            ->groupBy('party_id')
            ->orderByDesc('total')
            ->value('party_id');
    }

    /**
     * Matters whose certified expert is somebody other than the office owner.
     *
     * @return Collection<int, int>
     */
    public function nonOwnerMatterIds(): Collection
    {
        $ownerId = $this->officeOwnerPartyId();

        return MatterParty::query()
            ->where('role', 'expert')
            ->where('type', 'certified')
            ->when($ownerId, fn ($q) => $q->where('party_id', '!=', $ownerId))
            ->distinct()
            ->pluck('matter_id');
    }

    // ── 0. Allocation signs ──────────────────────────────────────────────────

    /**
     * Allocations that run the opposite way to the fee they pay.
     *
     * A deduction fee is stored negative and must be settled negatively. These
     * get out of step because Fee::saving() silently negates a positive
     * deduction fee on ANY save — so merely recalculating statuses flips the
     * fee and leaves its payments pointing the other way. The pair then cancels
     * nothing: the matter's billed total drops by the amount while its received
     * total rises by it, which is what leaves such matters looking
     * over-collected on the aging report.
     *
     * @return Collection<int, object>
     */
    public function misalignedAllocations(): Collection
    {
        return collect(DB::select(
            'SELECT a.id, a.amount, a.fee_id, f.amount AS fee_amount, f.type
             FROM allocations a
             JOIN fees f ON f.id = a.fee_id
             WHERE a.amount <> 0
               AND f.amount <> 0
               AND ((f.amount < 0 AND a.amount > 0) OR (f.amount > 0 AND a.amount < 0))'
        ));
    }

    /**
     * @return array{rows: int, value: float, fees: int}
     */
    public function previewAllocationSignAlignment(): array
    {
        $rows = $this->misalignedAllocations();

        return [
            'rows' => $rows->count(),
            'value' => round($rows->sum(fn ($r) => abs((float) $r->amount)), 2),
            'fees' => $rows->pluck('fee_id')->unique()->count(),
        ];
    }

    /**
     * @return array{rows: int, value: float}
     */
    public function alignAllocationSigns(): array
    {
        $rows = $this->misalignedAllocations();

        if ($rows->isEmpty()) {
            return ['rows' => 0, 'value' => 0.0];
        }

        $feeIds = $rows->pluck('fee_id')->unique();
        $value = 0.0;

        DB::transaction(function () use ($rows, &$value) {
            foreach ($rows as $row) {
                $magnitude = abs((float) $row->amount);
                $sign = ((float) $row->fee_amount) < 0 ? -1 : 1;

                // A builder update, not a model save: statuses are resettled
                // once at the end rather than per row, while the pair is still
                // mid-flip and would read as unpaid.
                Allocation::whereKey($row->id)->update(['amount' => $sign * $magnitude]);
                $value += $magnitude;
            }
        });

        $this->resettle($feeIds);

        return ['rows' => $rows->count(), 'value' => round($value, 2)];
    }

    // ── 1. Duplicate allocations ─────────────────────────────────────────────

    /**
     * Allocation rows that repeat the same fee, amount and date.
     *
     * The oldest row in each group is treated as the real payment; the rest are
     * duplicates. Returns the ids that would be deleted.
     *
     * @return array{rows: int, value: float, ids: list<int>}
     */
    public function previewDuplicateAllocations(): array
    {
        $groups = DB::table('allocations')
            ->selectRaw('fee_id, amount, date, COUNT(*) as total, MIN(id) as keep_id')
            ->groupBy('fee_id', 'amount', 'date')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $ids = [];
        $value = 0.0;

        foreach ($groups as $group) {
            $duplicateIds = DB::table('allocations')
                ->where('fee_id', $group->fee_id)
                ->where('amount', $group->amount)
                ->where('date', $group->date)
                ->where('id', '!=', $group->keep_id)
                ->pluck('id')
                ->all();

            $ids = array_merge($ids, $duplicateIds);
            $value += count($duplicateIds) * (float) $group->amount;
        }

        return ['rows' => count($ids), 'value' => round($value, 2), 'ids' => $ids];
    }

    /**
     * @return array{rows: int, value: float}
     */
    public function removeDuplicateAllocations(): array
    {
        $preview = $this->previewDuplicateAllocations();

        if ($preview['rows'] === 0) {
            return ['rows' => 0, 'value' => 0.0];
        }

        $feeIds = Allocation::whereIn('id', $preview['ids'])->pluck('fee_id')->unique();

        DB::transaction(function () use ($preview) {
            // Query-builder delete: model events would recompute statuses once
            // per row mid-repair. They are resettled together at the end.
            Allocation::whereIn('id', $preview['ids'])->delete();
        });

        $this->resettle($feeIds);

        return ['rows' => $preview['rows'], 'value' => $preview['value']];
    }

    // ── 2. Over-collection ───────────────────────────────────────────────────

    /**
     * Fees whose allocations exceed the fee itself.
     *
     * @return Collection<int, object>
     */
    public function overCollectedFees(): Collection
    {
        // Epsilon inlined for the same reason as in unsettledNonOwnerFees():
        // a bound float becomes a string and SQLite compares it as text.
        return collect(DB::select(
            'SELECT f.id, f.amount, t.allocated
             FROM fees f
             JOIN (SELECT fee_id, SUM(amount) AS allocated FROM allocations GROUP BY fee_id) t
               ON t.fee_id = f.id
             WHERE ABS(t.allocated) > ABS(f.amount) + '.self::EPSILON
        ));
    }

    /**
     * @return array{fees: int, excess: float}
     */
    public function previewOverCollection(): array
    {
        $fees = $this->overCollectedFees();

        return [
            'fees' => $fees->count(),
            'excess' => round($fees->sum(fn ($f) => abs((float) $f->allocated) - abs((float) $f->amount)), 2),
        ];
    }

    /**
     * Reduce allocations so collected never exceeds the fee.
     *
     * Works newest-first: the most recent payments are trimmed or removed until
     * the total fits, which leaves the original recorded payments intact and
     * only touches whatever was added on top.
     *
     * @return array{fees: int, excess: float}
     */
    public function trimOverCollection(): array
    {
        $fees = $this->overCollectedFees();

        if ($fees->isEmpty()) {
            return ['fees' => 0, 'excess' => 0.0];
        }

        $trimmed = 0.0;
        $touched = collect();

        DB::transaction(function () use ($fees, &$trimmed, &$touched) {
            foreach ($fees as $row) {
                $target = abs((float) $row->amount);
                $excess = abs((float) $row->allocated) - $target;

                if ($excess <= self::EPSILON) {
                    continue;
                }

                $trimmed += $excess;
                $touched->push($row->id);
                $remaining = $excess;

                $allocations = Allocation::where('fee_id', $row->id)
                    ->orderByDesc('date')
                    ->orderByDesc('id')
                    ->get();

                foreach ($allocations as $allocation) {
                    if ($remaining <= self::EPSILON) {
                        break;
                    }

                    $magnitude = abs((float) $allocation->amount);

                    if ($magnitude <= $remaining + self::EPSILON) {
                        // Whole payment is surplus — remove it.
                        $remaining -= $magnitude;
                        $allocation->deleteQuietly();

                        continue;
                    }

                    // Partial trim: keep the payment, reduce it, preserving sign.
                    $sign = ((float) $allocation->amount) < 0 ? -1 : 1;
                    $allocation->amount = $sign * ($magnitude - $remaining);
                    $allocation->saveQuietly();
                    $remaining = 0.0;
                }
            }
        });

        $this->resettle($touched);

        return ['fees' => $touched->count(), 'excess' => round($trimmed, 2)];
    }

    // ── 3. Settle non-owner matters in full ──────────────────────────────────

    /**
     * Fees on non-owner matters that are not settled to the cent.
     *
     * Rows are plain objects, not Fee models: each carries an `allocated` total
     * from the joined subquery, which is not a column on the model.
     *
     * @return Collection<int, \stdClass>
     */
    public function unsettledNonOwnerFees(): Collection
    {
        $matterIds = $this->nonOwnerMatterIds();

        if ($matterIds->isEmpty()) {
            return collect();
        }

        return DB::table('fees')
            ->whereIn('matter_id', $matterIds)
            ->leftJoinSub(
                DB::table('allocations')->selectRaw('fee_id, SUM(amount) AS allocated')->groupBy('fee_id'),
                'paid',
                'paid.fee_id',
                '=',
                'fees.id'
            )
            ->select('fees.id', 'fees.amount', 'fees.matter_id')
            ->selectRaw('COALESCE(paid.allocated, 0) AS allocated')
            // Epsilon inlined, not bound. Laravel binds PHP floats as strings,
            // and SQLite's type affinity sorts every number before every text
            // value, so `3000 > '0.005'` is FALSE and this matched nothing at
            // all under the test database while behaving correctly on MySQL.
            // It is a hardcoded constant, so there is nothing to parameterise.
            ->whereRaw('ABS(COALESCE(paid.allocated, 0) - fees.amount) > '.self::EPSILON)
            ->get();
    }

    /**
     * @return array{fees: int, matters: int, shortfall: float, surplus: float}
     */
    public function previewNonOwnerSettlement(): array
    {
        $fees = $this->unsettledNonOwnerFees();

        $shortfall = 0.0;
        $surplus = 0.0;

        foreach ($fees as $fee) {
            // Magnitude decides under vs over; the sign only says which
            // direction the payment runs. A -750 office share settled by
            // nothing is UNDER-settled by 750, not over-collected.
            $missing = abs((float) $fee->amount) - abs((float) $fee->allocated);
            $missing > 0 ? $shortfall += $missing : $surplus += abs($missing);
        }

        return [
            'fees' => $fees->count(),
            'matters' => $fees->pluck('matter_id')->unique()->count(),
            'shortfall' => round($shortfall, 2),
            'surplus' => round($surplus, 2),
        ];
    }

    /**
     * Bring every fee on a non-owner matter to exactly its amount.
     *
     * Under-collected fees receive one balancing allocation; over-collected ones
     * are left for trimOverCollection(), which handles reductions properly
     * rather than writing a negative "payment".
     *
     * @return array{fees: int, added: float, skipped_over: int}
     */
    public function settleNonOwnerMatters(): array
    {
        $fees = $this->unsettledNonOwnerFees();

        if ($fees->isEmpty()) {
            return ['fees' => 0, 'added' => 0.0, 'skipped_over' => 0];
        }

        $settled = 0;
        $added = 0.0;
        $skipped = 0;
        $touched = collect();

        DB::transaction(function () use ($fees, &$settled, &$added, &$skipped, &$touched) {
            foreach ($fees as $row) {
                // How much is still missing, in magnitude — a deduction fee is
                // stored negative and settled negatively, so comparing raw
                // signed values would misread it as over-collected.
                $missing = abs((float) $row->amount) - abs((float) $row->allocated);

                if (abs($missing) <= self::EPSILON) {
                    continue;
                }

                if ($missing < 0) {
                    $skipped++;

                    continue;
                }

                // The balancing payment carries the fee's own direction.
                $gap = (float) $row->amount - (float) $row->allocated;

                Allocation::withoutEvents(function () use ($row, $gap) {
                    Allocation::create([
                        'fee_id' => $row->id,
                        'matter_id' => $row->matter_id,
                        'user_id' => auth()->id(),
                        'amount' => $gap,
                        'date' => now()->toDateString(),
                        'description' => __('Settlement adjustment recorded by Fee Data Maintenance'),
                    ]);
                });

                $settled++;
                $added += abs($gap);
                $touched->push($row->id);
            }
        });

        $this->resettle($touched);

        return ['fees' => $settled, 'added' => round($added, 2), 'skipped_over' => $skipped];
    }

    // ── Shared ───────────────────────────────────────────────────────────────

    /**
     * Recompute fee and matter statuses once, after a bulk repair.
     *
     * @param  Collection<int, int>  $feeIds
     */
    private function resettle(Collection $feeIds): void
    {
        if ($feeIds->isEmpty()) {
            return;
        }

        $matterIds = collect();

        Fee::whereIn('id', $feeIds->unique())->with('allocations')->chunkById(200, function ($fees) use ($matterIds) {
            foreach ($fees as $fee) {
                $fee->syncStatus();

                if ($fee->isDirty('status')) {
                    $fee->saveQuietly();
                }

                $matterIds->push($fee->matter_id);
            }
        });

        Matter::whereIn('id', $matterIds->filter()->unique())->chunkById(200, function ($matters) {
            foreach ($matters as $matter) {
                $matter->updateCollectionStatus();
            }
        });
    }
}
