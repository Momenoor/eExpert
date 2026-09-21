<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A commercial unit type names its own rental type, so existing units get
     * it directly. Dwellings could be let to a family, bachelors, labour or
     * employees — that is a choice for the office, so they are left blank.
     *
     * @var array<string, string>
     */
    private const MAP = [
        'office' => 'office',
        'shop' => 'shop',
        'warehouse' => 'warehouse',
        'store' => 'store',
        'land' => 'land',
        'parking_bay' => 'parking',
    ];

    public function up(): void
    {
        foreach (self::MAP as $unitType => $rentalType) {
            DB::table('units')->where('unit_type', $unitType)->whereNull('rental_type')->update(['rental_type' => $rentalType]);
        }
    }

    public function down(): void
    {
        // Nothing to undo: the values are derivable from the unit type.
    }
};
