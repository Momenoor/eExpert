<?php

namespace App\Console\Commands;

use App\Enums\PMS\InstallmentPaymentStatus;
use App\Models\Installment;
use App\Services\MMS\PaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class FlagOverdueInstallments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pms:flag-overdue-installments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Flag rent instalments past their grace period as overdue';

    /**
     * Routed through PaymentService::flagOverdueIfNeeded() rather than a mass
     * update(): that method already carries the one rule ("past its own
     * grace period, not paid, not already flagged") and reusing it here means
     * this command and the office's own manual actions can never disagree
     * about what "overdue" means.
     */
    public function handle(PaymentService $payments): int
    {
        $candidates = Installment::query()
            ->whereNotIn('payment_status', [
                InstallmentPaymentStatus::PAID->value,
                InstallmentPaymentStatus::OVERDUE->value,
            ])
            ->where('grace_period_expiry_date', '<', now()->toDateString())
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No instalments to flag.');

            return self::SUCCESS;
        }

        $flagged = 0;

        foreach ($candidates as $installment) {
            try {
                // Each row is its own transaction: one bad instalment must
                // not roll back the ones already flagged, and this runs
                // unattended.
                DB::transaction(function () use ($installment, $payments): void {
                    $payments->flagOverdueIfNeeded($installment);
                });

                $flagged++;
            } catch (Throwable $e) {
                Log::error("Failed to flag instalment {$installment->id} overdue: ".$e->getMessage());
                $this->error("Instalment #{$installment->id}: {$e->getMessage()}");
            }
        }

        $this->info("Flagged {$flagged} of {$candidates->count()} instalments overdue.");

        return self::SUCCESS;
    }
}
