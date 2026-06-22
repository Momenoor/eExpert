<?php

namespace App\Services;

use App\Mail\NewMatterNotificationMail;
use App\Models\Matter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NewMatterNotification
{
    public function sendToAssistants(Matter $matter): void
    {
        // Guard: already has a pending request
        if (\App\Models\MatterRequest::where('matter_id', $matter->id)
            ->where('type', \App\Enums\RequestType::CHANGE_DISTRIBUTED_DATE)
            ->exists()) {
            Log::info("NewMatterNotification: Matter #{$matter->id} already has a pending request, skipping.");
            return;
        }

        $matter->load(['assistantsOnly.party', 'court', 'type']);

        $assistants = $matter->assistantsOnly->filter(fn($mp) => $mp->party?->email);

        if ($assistants->isEmpty()) {
            Log::info("NewMatterNotification: Matter #{$matter->id} has no assistants with email.");
            return;
        }

        foreach ($assistants as $mp) {
            $party = $mp->party;

            try {
                $matterRequest = \App\Models\MatterRequest::create([
                    'matter_id'  => $matter->id,
                    'request_by' => $party->user_id ?? null,
                    'type'       => \App\Enums\RequestType::CHANGE_DISTRIBUTED_DATE->value,
                    'status'     => \App\Enums\RequestStatus::PENDING->value,
                    'comment'    => __('Auto-generated: awaiting assistant confirmation of received date.'),
                    'extra'      => [
                        'party_id'               => $party->id,
                        'party_name'             => $party->name,
                        'current_distributed_at' => $matter->distributed_at,
                    ],
                ]);

                Mail::to($party->email)
                    ->locale('ar')
                    ->queue(new NewMatterNotificationMail($matter, $party, $matterRequest));

                Log::info("NewMatterNotification: Mail queued for party #{$party->id} on Matter #{$matter->id}.");

            } catch (\Throwable $e) {
                Log::error("NewMatterNotification: Failed to process party #{$party->id} on Matter #{$matter->id}.", [
                    'error' => $e->getMessage(),
                    'file'  => $e->getFile(),
                    'line'  => $e->getLine(),
                ]);
                // continues to next assistant instead of crashing the whole loop
            }
        }
    }
}
