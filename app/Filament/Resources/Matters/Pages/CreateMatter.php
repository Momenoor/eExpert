<?php

namespace App\Filament\Resources\Matters\Pages;

use App\Filament\Actions\Calendar\CreateSingleCalendarEventAction;
use App\Filament\Resources\Matters\MatterResource;
use Filament\Resources\Pages\CreateRecord;
use Livewire\Attributes\On;

class CreateMatter extends CreateRecord
{
    protected static string $resource = MatterResource::class;

    public $pendingSessionDate;

    #[On('mount-calendar-event-modal')]
    public function mountCalendarEventModal(array $data): void
    {
        $this->pendingSessionDate = $data['start_datetime'];
        $this->replaceMountedAction('confirmCreateCalendarEvent', [
            'matter_id' => $data['matter_id'],
            'start_datetime' => $data['start_datetime'],
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateSingleCalendarEventAction::make('confirmCreateCalendarEvent')
                ->label(__('Create Calendar Event'))
                ->modalHeading(__('Would you like to create a calendar event for this session date?'))
                ->requiresConfirmation()
                ->color('primary')
                ->fillForm(fn (array $arguments) => [
                    'matter_id' => $arguments['matter_id'] ?? null,
                    'start_datetime' => $arguments['start_datetime'] ?? $this->pendingSessionDate,
                ])
                ->extraAttributes(['class' => 'hidden']),
        ];
    }
}
