<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ChatConversationUser extends Pivot
{
    protected $casts = [
        'last_read_at' => 'datetime',
    ];
}
