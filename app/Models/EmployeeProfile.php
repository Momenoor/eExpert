<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The employment record hanging off a party that holds the `employee` role.
 */
class EmployeeProfile extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'party_id',
        'employee_no',
        'designation',
        'date_of_joining',
        'date_of_leaving',
        'passport_no',
        'passport_expiry',
        'emirates_id_no',
        'emirates_id_expiry',
        'labour_card_no',
        'mohre_personal_no',
        'labour_card_expiry',
        'residency_visa_no',
        'visa_file_no',
        'residency_expiry',
        'sponsor_name',
        'bank_name',
        'bank_account_no',
        'iban',
        'wps_routing_code',
    ];

    protected $casts = [
        'date_of_joining' => 'date',
        'date_of_leaving' => 'date',
        'passport_expiry' => 'date',
        'emirates_id_expiry' => 'date',
        'labour_card_expiry' => 'date',
        'residency_expiry' => 'date',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * Whether the employee is still on the payroll.
     */
    public function isActive(): bool
    {
        $leaving = $this->getAttribute('date_of_leaving');

        return $leaving === null || $leaving->isFuture();
    }

    /**
     * The documents falling due within the given window, as label => date.
     *
     * @return array<string, Carbon>
     */
    public function expiringDocuments(int $withinDays = 60): array
    {
        $limit = now()->addDays($withinDays);

        $documents = [
            'passport_expiry' => $this->getAttribute('passport_expiry'),
            'emirates_id_expiry' => $this->getAttribute('emirates_id_expiry'),
            'labour_card_expiry' => $this->getAttribute('labour_card_expiry'),
            'residency_expiry' => $this->getAttribute('residency_expiry'),
        ];

        return array_filter(
            $documents,
            fn ($date): bool => $date !== null && $date->lessThanOrEqualTo($limit),
        );
    }

    public function salaryComponents()
    {
        return $this->hasMany(EmployeeSalaryComponent::class);
    }
}
