<?php

namespace App\Filament\Imports;

use App\Models\EmployeeProfile;
use App\Models\Party;
use Carbon\Carbon;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

/**
 * Employee profiles are matched to an EXISTING party holding the `employee`
 * role by name — this import only ever fills in the employment/HR paperwork
 * for someone already on the party list; it never creates the party itself.
 * Re-importing the same employee (same party name) updates their existing
 * profile rather than creating a duplicate, so a corrected spreadsheet can be
 * re-uploaded safely.
 */
class EmployeeProfileImporter extends Importer
{
    protected static ?string $model = EmployeeProfile::class;

    public static function getColumns(): array
    {
        $columns = [
            ImportColumn::make('party_id')
                ->label(__('ID'))
                ->requiredMapping()
                ->example('7514')
                ->rules(['required', 'string']),

            ImportColumn::make('employee_no')
                ->label(__('Employee Number'))
                ->example('EMP-0142'),

            ImportColumn::make('designation')
                ->label(__('Designation'))
                ->example('مستشار مالي'),

            ImportColumn::make('date_of_joining')
                ->label(__('Date of Joining'))
                ->requiredMapping()
                ->example('2024-01-15')
                ->castStateUsing(fn(?string $state) => self::parseDate($state))
                ->rules(['required']),

            ImportColumn::make('date_of_leaving')
                ->label(__('Date of Leaving'))
                ->example('')
                ->castStateUsing(fn(?string $state) => self::parseDate($state)),

            ImportColumn::make('passport_no')
                ->label(__('Passport No'))
                ->example('N1234567'),

            ImportColumn::make('passport_expiry')
                ->label(__('Passport Expiry'))
                ->example('2030-05-01')
                ->castStateUsing(fn(?string $state) => self::parseDate($state)),

            ImportColumn::make('emirates_id_no')
                ->label(__('Emirates ID No'))
                ->example('784-1990-1234567-1'),

            ImportColumn::make('emirates_id_expiry')
                ->label(__('Emirates ID Expiry'))
                ->example('2028-03-10')
                ->castStateUsing(fn(?string $state) => self::parseDate($state)),

            ImportColumn::make('labour_card_no')
                ->label(__('Labour Card No'))
                ->example('1234567890'),

            ImportColumn::make('mohre_personal_no')
                ->label(__('MOHRE Personal Number'))
                ->example('12345678901234'),

            ImportColumn::make('labour_card_expiry')
                ->label(__('Labour Card Expiry'))
                ->example('2027-11-20')
                ->castStateUsing(fn(?string $state) => self::parseDate($state)),

            ImportColumn::make('residency_visa_no')
                ->label(__('Visa No'))
                ->example('123456789'),

            ImportColumn::make('visa_file_no')
                ->label(__('File No'))
                ->example('201/2024/1/123456'),

            ImportColumn::make('residency_expiry')
                ->label(__('Residency Expiry'))
                ->example('2027-11-20')
                ->castStateUsing(fn(?string $state) => self::parseDate($state)),

            ImportColumn::make('sponsor_name')
                ->label(__('Sponsor'))
                ->example('JPA Auditing & Accounting LLC'),

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

            ImportColumn::make('wps_routing_code')
                ->label(__('Routing Code'))
                ->example('123456789')
                ->rules(['nullable', 'regex:/^\d{9}$/']),
        ];

        // The "Download Example" button headers each column with
        // `getExampleHeader()`, which otherwise falls back to the raw
        // machine name (e.g. "party_name") — make it follow the panel's
        // current language like every other label here.
        foreach ($columns as $column) {
            $column->exampleHeader(fn(ImportColumn $col): string => $col->getLabel() ?? $col->getName());
        }

        return $columns;
    }

    /**
     * Every date column shares this — Excel exports a date either as a plain
     * string in whatever format the spreadsheet was set to, or as its own
     * serial day-number (days since 1899-12-30). openspout already resolves
     * a genuine Excel date cell to a string before this ever runs, so this
     * only has to cope with the everyday variety of string formats a person
     * might type by hand, not the numeric serial itself.
     */
    private static function parseDate(?string $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        $state = trim($state);

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'd.m.Y'] as $format) {
            $date = \DateTime::createFromFormat('!' . $format, $state);

            // createFromFormat silently overflows out-of-range components
            // (e.g. "01/15/2024" as d/m/Y rolls month 15 into next March)
            // instead of failing, so confirm the round-trip matches before
            // trusting the match — otherwise a later, correct format is
            // never tried.
            if ($date instanceof \DateTime && $date->format($format) === $state) {
                return $date->format('Y-m-d');
            }
        }

        try {
            return Carbon::parse($state)->format('Y-m-d');
        } catch (\Throwable) {
            throw new RowImportFailedException(__('":value" is not a recognisable date.', ['value' => $state]));
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = __(':count employee profile(s) imported.', ['count' => Number::format($import->successful_rows)]);

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . __(':count row(s) failed to import.', ['count' => Number::format($failedRowsCount)]);
        }

        return $body;
    }
}
