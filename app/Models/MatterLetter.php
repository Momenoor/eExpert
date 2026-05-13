<?php

namespace App\Models;

use App\Enums\LetterStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
#[Fillable('letter_template_id','matter_id', 'sent_by', 'subject', 'body','status','sent_at')]
class MatterLetter extends Model
{
    public function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'status' => LetterStatus::class,
        ];
    }
    public function matter(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }
    public function template(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(LetterTemplate::class);
    }
    public function sentBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
    public function recipients(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MatterLetterRecipient::class);
    }

}
