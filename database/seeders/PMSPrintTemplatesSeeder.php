<?php

namespace Database\Seeders;

use App\Models\LeasePrintTemplate;
use Illuminate\Database\Seeder;

/**
 * Seeds the three "as-of-launch" print templates — one per contract format
 * — as empty shells with no pages yet. The office uploads each page's
 * background image and places its fields via the admin panel; see
 * `LeasePrintFieldResolver` for what a placed field can show, and
 * `App\Livewire\Pms\PrintTemplatePageBuilder` for the click-to-place tool.
 * Re-running this is safe — each template is upserted by its
 * `contract_format`.
 */
class PMSPrintTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        LeasePrintTemplate::updateOrCreate(
            ['contract_format' => 'sharjah_commercial'],
            ['name' => 'Sharjah Commercial — Standard'],
        );

        LeasePrintTemplate::updateOrCreate(
            ['contract_format' => 'sharjah_residential'],
            ['name' => 'Sharjah Residential — Standard'],
        );

        LeasePrintTemplate::updateOrCreate(
            ['contract_format' => 'dubai_ejari'],
            ['name' => 'Dubai EJARI — Standard'],
        );
    }
}
