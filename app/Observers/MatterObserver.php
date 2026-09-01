<?php

namespace App\Observers;

use App\Enums\MatterCollectionStatus;
use App\Models\Matter;
use App\Services\NewMatterNotification;
use Illuminate\Support\Facades\Log;

class MatterObserver
{
    public function creating(Matter $matter): void
    {
        $matter->collection_status ??= MatterCollectionStatus::NO_FEES;
    }

    public function created(Matter $matter): void
    {
        Log::debug('MatterObserver@created fired', [
            'matter_id' => $matter->id,
            'exists' => $matter->exists,
            'dirty' => $matter->getDirty(),
        ]);

        $matterId = $matter->id;

        if (! $matterId) {
            Log::error('MatterObserver@created: matter has no ID at observer time.');

            return;
        }

        dispatch(function () use ($matterId) {
            try {
                $matter = Matter::withTrashed()->find($matterId);

                if (! $matter) {
                    Log::error("NewMatterNotification [created]: Matter #{$matterId} not found in DB.");

                    return;
                }

                if (! $matter->distributed_at) {
                    Log::info("NewMatterNotification [created]: Matter #{$matterId} has no distributed_at, skipping.");

                    return;
                }

                app(NewMatterNotification::class)->sendToAssistants($matter);

            } catch (\Throwable $e) {
                Log::error("NewMatterNotification [created]: Failed for Matter #{$matterId}.", [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        })->afterCommit();
    }

    public function saved(Matter $matter): void
    {
        $matter->updateCollectionStatus();

    }

    public function deleted(Matter $matter): void
    {
        $matter->children()->delete();
    }

    public function restored(Matter $matter): void
    {
        $matter->children()->onlyTrashed()->restore();
    }

    public function forceDeleting(Matter $matter): void
    {
        $matter->children()->withTrashed()->each(
            fn (Matter $child) => $child->forceDelete()
        );
        $matter->matterParties()->delete();
        $matter->fees()->each(function ($fee) {
            $fee->allocations()->delete();
            $fee->delete();
        });
        $matter->allocations()->delete();
        $matter->notes()->delete();

        $matter->attachments->each->delete();
        $matter->requests()->delete();
    }
}
