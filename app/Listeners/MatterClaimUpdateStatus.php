<?php

namespace App\Listeners;

use App\Events\MatterClaimChanged;
use App\Services\ClaimCollectionStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class MatterClaimUpdateStatus
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(MatterClaimChanged $event)
    {
        $event->matter->refresh();
        $service = ClaimCollectionStatus::make($event->matter);
        $event->matter = $service->updateMatterClaimCollectionStatus();
        return $event->matter;
    }
}
