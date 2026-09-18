<?php

namespace App\Filament\Pms\Resources\Leases\Pages;

use App\Filament\Pms\Resources\Leases\LeaseResource;
use App\Models\Lease;
use App\Models\LeaseParty;
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

    /**
     * `tenants`/`units` aren't columns on `leases` — `LeaseForm`'s repeater
     * and multi-select for them start blank unless explicitly filled here
     * from the lease's actual `leaseParties`/`units` relationships.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Lease $lease */
        $lease = $this->getRecord();

        $data['tenants'] = $lease->leaseParties->map(fn (LeaseParty $leaseParty): array => [
            'party_id' => $leaseParty->getAttribute('party_id'),
            'role' => $leaseParty->getAttribute('role')->value,
        ])->all();

        $data['units'] = $lease->units->pluck('id')->all();

        return $data;
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
