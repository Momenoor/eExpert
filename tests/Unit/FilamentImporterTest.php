<?php

namespace Tests\Unit;

use App\Filament\Imports\BulkMailRecipientImporter;
use App\Models\BulkMailCampaign;
use App\Models\BulkMailRecipient;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilamentImporterTest extends TestCase
{
    // use RefreshDatabase;

    public function test_importer_resolves_record()
    {
        $import = new Import;
        $importer = new BulkMailRecipientImporter($import, [], []);
        $record = $importer->resolveRecord();

        $this->assertInstanceOf(BulkMailRecipient::class, $record);
    }

    public function test_importer_sets_campaign_id()
    {
        $campaign = new BulkMailCampaign;
        $campaign->id = 999;

        $recipient = new BulkMailRecipient;
        $importer = new class($recipient, $campaign->id) extends BulkMailRecipientImporter
        {
            public ?Model $record;

            public array $options;

            public function __construct($record, $campaignId)
            {
                $this->record = $record;
                $this->options = ['campaign_id' => $campaignId];
            }

            public function callBeforeSave()
            {
                $this->beforeSave();
            }
        };

        $importer->callBeforeSave();

        $this->assertEquals($campaign->id, $recipient->campaign_id);
    }
}
