<?php

namespace App\Console\Commands;

use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\MatterRequest;
use App\Services\Requests\RequestServiceFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConfirmMatterReceivingForUnacceptedMail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'matter:confirm-receiving';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Confirm receiving for unaccepted matter mails from assistant on time';

    /**
     * Auto-approve assigning-date requests the assistant never responded to.
     *
     * Routed through ChangeDistributedAtRequestService rather than a mass
     * update(): the previous version flipped the status columns directly, so the
     * proposed date was never applied to the matter and nobody was notified —
     * the auto-approval did strictly less than a manual one. Going through the
     * service is the whole point of the per-type strategy classes.
     */
    public function handle(): int
    {
        app()->setLocale('ar');

        $requests = MatterRequest::query()
            ->where('status', RequestStatus::PENDING->value)
            ->where('type', RequestType::CHANGE_DISTRIBUTED_DATE->value)
            ->whereDate('created_at', '<=', now()->subDay())
            ->get();

        if ($requests->isEmpty()) {
            $this->info('No pending assigning-date requests to confirm.');

            return self::SUCCESS;
        }

        $approved = 0;

        foreach ($requests as $request) {
            try {
                // Each request is its own transaction: one bad row must not roll
                // back the ones already confirmed, and this runs unattended.
                DB::transaction(function () use ($request) {
                    RequestServiceFactory::make($request)->approve([
                        'approved_comment' => __('Auto-generated: Matter received date confirmed.'),
                        'attachments' => [],
                    ]);
                });

                $approved++;
            } catch (\Throwable $e) {
                Log::error("Auto-confirm failed for matter request {$request->id}: ".$e->getMessage());
                $this->error("Request #{$request->id}: {$e->getMessage()}");
            }
        }

        $this->info("Confirmed {$approved} of {$requests->count()} pending assigning-date requests.");

        return self::SUCCESS;
    }
}
