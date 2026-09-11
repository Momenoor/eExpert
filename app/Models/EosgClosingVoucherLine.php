<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One employee's share of a saved annual EOSG closing voucher.
 */
class EosgClosingVoucherLine extends Model
{
    protected $fillable = [
        'eosg_closing_voucher_id',
        'party_id',
        'opening_balance',
        'amount',
        'closing_balance',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'amount' => 'decimal:2',
        'closing_balance' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<EosgClosingVoucher, $this>
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(EosgClosingVoucher::class, 'eosg_closing_voucher_id');
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
