<?php

namespace App\Filament\Tables\Columns;

use Filament\Tables\Columns\Column;

class MultiColorBadgeColumn extends Column
{
    protected bool | Closure $isBadge = false;

    protected string $view = 'tables.columns.multi-color-badge-column';

}
