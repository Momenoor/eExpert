<?php

namespace App\Models;

use App\Enums\LetterStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

#[Fillable('matter_id', 'recipient_id', 'email', 'name', 'delivery_status', 'delivered_at')]
class MatterLetterRecipient extends Model
{
    public function casts(): array
    {
        return [
            'delivered_at' => 'datetime',
            'delivery_status' => LetterStatus::class,
        ];
    }

    public function matter(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    public function recipient(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Party::class, 'recipient_id');
    }

    public function status(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->delivery_status,
        );
    }
}
