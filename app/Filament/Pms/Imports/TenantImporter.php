<?php

namespace App\Filament\Pms\Imports;

use App\Enums\PMS\TenantIdentificationType;
use App\Enums\PMS\TenantType;
use App\Models\Party;
use App\Models\Tenant;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

/**
 * Creates the tenant's `Party` and `Tenant` together, the same way
 * `TenantForm` does — a tenant being bulk-imported has, in almost every
 * case, no existing Party record to attach to. Re-importing the same
 * tenant (matched by `identification_number`) updates their existing
 * profile/party instead of creating a duplicate.
 */
class TenantImporter extends Importer
{
    protected static ?string $model = Tenant::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->label(__('Full Name'))
                ->requiredMapping()
                ->example('Madhav Chaturvedi')
                ->rules(['required', 'string']),
            ImportColumn::make('phone')
                ->label(__('Phone'))
                ->multiple(',')
                ->example('0501234567'),
            ImportColumn::make('email')
                ->label(__('Email'))
                ->multiple(',')
                ->example('tenant@example.com'),
            ImportColumn::make('tenant_type')
                ->label(__('Tenant Type'))
                ->example('person')
                ->castStateUsing(fn (?string $state) => $state ? TenantType::tryFrom(strtolower(trim($state)))?->value : TenantType::PERSON->value),
            ImportColumn::make('nationality')
                ->label(__('Nationality'))
                ->example('India'),
            ImportColumn::make('identification_type')
                ->label(__('Identification Type'))
                ->requiredMapping()
                ->example('emirates_id')
                ->castStateUsing(fn (?string $state) => $state ? TenantIdentificationType::tryFrom(strtolower(trim($state)))?->value : null)
                ->rules(['required']),
            ImportColumn::make('identification_number')
                ->label(__('Identification Number'))
                ->requiredMapping()
                ->example('784-1990-1234567-1')
                ->rules(['required', 'string']),
            ImportColumn::make('unified_number')
                ->label(__('Unified No.'))
                ->example('1234567890'),
            ImportColumn::make('trn')
                ->label(__('TRN'))
                ->example('100123456700003'),
            ImportColumn::make('emergency_contact_name')
                ->label(__('Emergency Contact Name'))
                ->example('Jane Doe'),
            ImportColumn::make('emergency_contact_phone')
                ->label(__('Emergency Contact Phone'))
                ->example('0507654321'),
        ];
    }

    public function resolveRecord(): Tenant
    {
        $tenant = Tenant::firstOrNew(['identification_number' => $this->data['identification_number']]);

        if (! $tenant->exists) {
            $party = Party::create([
                'name' => $this->data['name'],
                'phone' => $this->data['phone'] ?? [],
                'email' => $this->data['email'] ?? [],
                'role' => ['role' => ['tenant'], 'type' => []],
            ]);

            $tenant->party_id = $party->id;
        } else {
            $tenant->party?->update([
                'name' => $this->data['name'],
                'phone' => $this->data['phone'] ?? [],
                'email' => $this->data['email'] ?? [],
            ]);
        }

        return $tenant;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = __(':count tenant(s) imported.', ['count' => Number::format($import->successful_rows)]);

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.__(':count row(s) failed to import.', ['count' => Number::format($failedRowsCount)]);
        }

        return $body;
    }
}
