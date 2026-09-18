<?php

namespace Database\Seeders;

use App\Enums\PMS\Emirate;
use App\Models\ConditionTemplate;
use Illuminate\Database\Seeder;

/**
 * Seeds the three "as-of-launch" conditions templates — one per contract
 * format — as empty shells the office populates with its own Special
 * Conditions clauses via the admin panel. General Conditions (the fixed
 * statutory text) live hardcoded in each print view instead, since they
 * never vary per contract and an admin-editable copy risks a typo
 * misstating the law. Re-running this is safe — each template is upserted
 * by its `contract_format`.
 */
class PMSConditionTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        ConditionTemplate::updateOrCreate(
            ['contract_format' => 'sharjah_commercial'],
            ['name' => 'Sharjah Commercial — Standard', 'emirate' => Emirate::SHARJAH->value],
        );

        ConditionTemplate::updateOrCreate(
            ['contract_format' => 'sharjah_residential'],
            ['name' => 'Sharjah Residential — Standard', 'emirate' => Emirate::SHARJAH->value],
        );

        ConditionTemplate::updateOrCreate(
            ['contract_format' => 'dubai_ejari'],
            ['name' => 'Dubai EJARI — Standard', 'emirate' => Emirate::DUBAI->value],
        );
    }
}
