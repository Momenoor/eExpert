<?php

namespace App\Services;

use App\Mail\NewMatterNotificationMail;
use App\Models\Matter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NewMatterNotification
{
    public function sendToAssistants($matter): void
    {

        if (!$matter->distributed_at) return;

        // Check if a request of this type already exists for this matter
        if (\App\Models\MatterRequest::where('matter_id', $matter->id)
            ->where('type', \App\Enums\RequestType::CHANGE_DISTRIBUTED_DATE)
            ->exists()) {
            return;
        }

        $matter->load(['assistantsOnly.party', 'court', 'type']);

        foreach ($matter->assistantsOnly->filter(fn($mp) => $mp->party?->email) as $mp) {
            $party = $mp->party;
            // Create pending request — no token needed
            $matterRequest = \App\Models\MatterRequest::create([
                'matter_id' => $matter->id,
                'request_by' => $party->user_id ?? null,
                'type' => \App\Enums\RequestType::CHANGE_DISTRIBUTED_DATE->value,
                'status' => \App\Enums\RequestStatus::PENDING->value,
                'comment' => __('Auto-generated: awaiting assistant confirmation of received date.'),
                'extra' => [
                    'party_id' => $party->id,
                    'party_name' => $party->name,
                    'current_distributed_at' => $matter->distributed_at,
                ],
            ]);
            Mail::to($party->email)
                ->locale('ar')
                ->queue(new NewMatterNotificationMail($matter, $party, $matterRequest));
        }
    }
}
