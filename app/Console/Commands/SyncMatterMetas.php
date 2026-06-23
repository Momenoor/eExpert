<?php

namespace App\Console\Commands;

use App\Models\Matter;
use Illuminate\Console\Command;

class SyncMatterMetas extends Command
{
    protected $signature = 'app:sync-matter-metas {--force : Force sync even if not dirty}';

    protected $description = 'Sync all matters custom_fields to matter_metas table';

    public function handle()
    {
        $matters = Matter::all();
        $this->info("Syncing " . $matters->count() . " matters...");

        $bar = $this->output->createProgressBar($matters->count());
        $bar->start();

        foreach ($matters as $matter) {
            $customFields = $matter->custom_fields ?? [];

            // Get current meta field names to identify what to remove
            $existingFieldNames = $matter->metas()->pluck('field_name')->toArray();
            $newFieldNames = array_keys($customFields);

            // Remove metas that are no longer in custom_fields
            $fieldsToRemove = array_diff($existingFieldNames, $newFieldNames);
            if (!empty($fieldsToRemove)) {
                $matter->metas()->whereIn('field_name', $fieldsToRemove)->delete();
            }

            // Update or create metas from custom_fields
            foreach ($customFields as $key => $value) {
                $matter->metas()->updateOrCreate(
                    ['field_name' => $key],
                    ['field_value' => is_array($value) ? json_encode($value) : $value]
                );
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Sync completed successfully.');
    }
}
