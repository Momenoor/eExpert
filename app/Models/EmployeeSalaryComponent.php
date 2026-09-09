<?php

namespace App\Models;

use App\Enums\SalaryComponent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One effective-dated piece of an employee's monthly salary.
 */
class EmployeeSalaryComponent extends Model
{
    use LogsActivity;

    protected $fillable = [
        'party_id',
        'component',
        'amount',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'component' => SalaryComponent::class,
        'amount' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
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
     * Components in force on a given date.
     *
     * The window is closed-open at the end: a row ending on the 31st is still in
     * force on the 31st, so a raise effective the 1st of the next month does not
     * overlap the row it replaces.
     *
     * @param  Builder<EmployeeSalaryComponent>  $query
     */
    public function scopeEffectiveOn(Builder $query, string $date): void
    {
        $query->whereDate('effective_from', '<=', $date)
            ->where(function (Builder $query) use ($date): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date);
            });
    }
}
