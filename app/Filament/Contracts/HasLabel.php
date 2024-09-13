<?php

namespace App\Filament\Contracts;

use Illuminate\Support\Str;

trait HasLabel
{
    public function getLabel(): ?string
    {
        return ucwords(Str::of($this->name)->snake(' '));
    }

}
