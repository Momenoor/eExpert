<?php

namespace App\Filament\Pms\Imports;

use App\Models\OwnerGroup;
use App\Models\OwnerProfile;
use App\Models\Party;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

/**
 * Creates the owner's `Party` and `OwnerProfile` together, the same way
 * `OwnerProfileForm` does — an owner being bulk-imported has, in almost
 * every case, no existing Party record to attach to. Re-importing the same
 * owner (matched by `identification_number`) updates their existing
 * profile/party instead of creating a duplicate.
 */
class OwnerProfileImporter extends Importer
{
    protected static ?string $model = OwnerProfile::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->label(__('Full Name'))
                ->requiredMapping()
                ->example('Mahmoud Kalbat')
                ->rules(['required', 'string']),
            ImportColumn::make('phone')
                ->label(__('Phone'))
                ->multiple(',')
                ->example('0501234567'),
            ImportColumn::make('email')
                ->label(__('Email'))
                ->multiple(',')
                ->example('owner@example.com'),
            ImportColumn::make('identification_number')
                ->label(__('Identification Number (Emirates ID / Passport)'))
                ->requiredMapping()
                ->example('784-1990-1234567-1')
                ->rules(['required', 'string']),
            ImportColumn::make('unified_number')
                ->label(__('Unified No.'))
                ->example('1234567890'),
            ImportColumn::make('nationality')
                ->label(__('Nationality'))
                ->example('UAE'),
            ImportColumn::make('trn')
                ->label(__('TRN'))
                ->example('100123456700003'),
            ImportColumn::make('owner_group_name')
                ->label(__('Owner Group'))
                ->example('Legal Heirs of Mahmoud Kalbat'),
            ImportColumn::make('bank_name')
                ->label(__('Bank Name'))
                ->example('Emirates NBD'),
            ImportColumn::make('bank_account_no')
                ->label(__('Account No'))
                ->example('1234567890123'),
            ImportColumn::make('iban')
                ->label(__('IBAN'))
                ->example('AE070331234567890123456')
                ->rules(['nullable', 'regex:/^AE\d{21}$/']),
        ];
    }

    public function resolveRecord(): OwnerProfile
    {
        $profile = OwnerProfile::firstOrNew(['identification_number' => $this->data['identification_number']]);

        if (! $profile->exists) {
            $party = Party::create([
                'name' => $this->data['name'],
                'phone' => $this->data['phone'] ?? [],
                'email' => $this->data['email'] ?? [],
                'role' => ['role' => ['owner'], 'type' => []],
            ]);

            $profile->party_id = $party->id;
        } else {
            $profile->party?->update([
                'name' => $this->data['name'],
                'phone' => $this->data['phone'] ?? [],
                'email' => $this->data['email'] ?? [],
            ]);
        }

        if (! empty($this->data['owner_group_name'])) {
            $group = OwnerGroup::where('name', $this->data['owner_group_name'])->first();

            if (! $group) {
                $groupParty = Party::create([
                    'name' => $this->data['owner_group_name'],
                    'role' => ['role' => ['owner_group'], 'type' => []],
                ]);

                $group = OwnerGroup::create([
                    'party_id' => $groupParty->id,
                    'name' => $this->data['owner_group_name'],
                ]);
            }

            $profile->owner_group_id = $group->id;
        }

        return $profile;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = __(':count owner(s) imported.', ['count' => Number::format($import->successful_rows)]);

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.__(':count row(s) failed to import.', ['count' => Number::format($failedRowsCount)]);
        }

        return $body;
    }
}
