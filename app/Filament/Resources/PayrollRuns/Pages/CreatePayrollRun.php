<?php

namespace App\Filament\Resources\PayrollRuns\Pages;

use App\Enums\PayrollRunStatus;
use App\Filament\Resources\PayrollRuns\PayrollRunResource;
use App\Models\PayrollRun;
use App\Services\PayrollService;
use Filament\Resources\Pages\CreateRecord;

class CreatePayrollRun extends CreateRecord
{
    protected static string $resource = PayrollRunResource::class;

    /**
     * A run always starts as a draft owned by whoever opened it.
     *
     * Neither is a form field: a browser-supplied status would let someone
     * create a run that is already approved.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = PayrollRunStatus::DRAFT;
        $data['created_by'] = auth()->id();

        return $data;
    }

    /**
     * Pull the employees in straight away.
     *
     * A new run used to arrive empty, waiting for someone to press Generate.
     * That reads as a broken screen rather than an unfinished one — "I created a
     * payroll run and it didn't import the employees" is precisely how it was
     * reported. Generation is idempotent and the run is still a draft, so doing
     * it now costs nothing and removes the empty state entirely.
     *
     * Skipped when the user cannot generate: the draft is still created, and
     * whoever does hold the permission can generate it.
     */
    protected function afterCreate(): void
    {
        /** @var PayrollRun $run */
        $run = $this->getRecord();

        if (auth()->user()?->can('generate', $run)) {
            app(PayrollService::class)->generate($run);
        }
    }

    /**
     * Open the new run rather than the list — the payslips are already there.
     */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
