<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Unit types were consolidated: the bedroom-count variants became a plain
     * apartment, and the commercial/retail/industrial ones became office,
     * shop and warehouse. Existing units are moved onto the nearest new type
     * so none is left holding a value the enum no longer knows.
     *
     * @var array<string, string>
     */
    private const MAP = [
        'one_bedroom' => 'apartment',
        'two_bedroom' => 'apartment',
        'three_bedroom' => 'apartment',
        'commercial_office' => 'office',
        'retail_shop' => 'shop',
        'industrial' => 'warehouse',
    ];

    public function up(): void
    {
        foreach (self::MAP as $old => $new) {
            DB::table('units')->where('unit_type', $old)->update(['unit_type' => $new]);
        }
    }

    public function down(): void
    {
        // Lossy by nature (three bedroom counts collapsed into one type) —
        // nothing to restore.
    }
};
