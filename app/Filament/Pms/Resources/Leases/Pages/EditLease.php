<?php

namespace App\Filament\Pms\Resources\Leases\Pages;

use App\Filament\Pms\Resources\Leases\LeaseResource;
use App\Models\Lease;
use App\Services\PMS\LeaseService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Throwable;

/**
 * Only reachable while a lease is still `DRAFT` — `LeaseResource::canEdit()`
 * refuses access (and hides the edit link) for anything past that.
 */
class EditLease extends EditRecord
{
    protected static string $resource = LeaseResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Lease $record */
        try {
            return app(LeaseService::class)->updateDraft(
                $record,
                $data,
                $data['tenants'] ?? [],
                $data['units'] ?? [],
            );
        } catch (Throwable $exception) {
            Notification::make()
                ->danger()
                ->title(__('Could not continue'))
                ->body($exception instanceof RuntimeException ? $exception->getMessage() : __('Something went wrong.'))
                ->send();

            $this->halt();

            return $record;
        }
    }

    protected function getRedirectUrl(): string
    {
        return LeaseResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
