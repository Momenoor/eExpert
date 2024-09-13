<?php

namespace App\Models;

use App\Contracts\MatterPartyContract;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class MatterParty extends Pivot
{
    //
    use LogsActivity;
    protected $fillable = [
        'matter_id',
        'party_id',
        'parent_id',
        'role',
    ];
    protected $logAttributes = [
        'matter_id',
        'party_id',
        'parent_id',
        'role_id',
    ];

    public $timestamps = true;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }
    public function subParties(){

        return $this->belongsTo(Party::class,'parent_id');
    }

}
