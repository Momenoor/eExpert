<?php

namespace App\Console\Commands;

use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\MatterRequest;
use Illuminate\Console\Command;

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
     * Execute the console command.
     */
    public function handle(): void
    {
        app()->setLocale('ar');
        MatterRequest::where('status', RequestStatus::PENDING->value)
            ->where('type', RequestType::CHANGE_DISTRIBUTED_DATE->value)
            ->whereDate('created_at', '<=', now()->subDays(1))
            ->update([
                'status' => RequestStatus::APPROVED->value,
                'approved_at' => now(),
                'approved_comment' => __('Auto-generated: Matter received date confirmed.'),
            ]);
        $this->info('Database records updated successfully.');
    }
}
