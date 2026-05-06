<?php

namespace Tests\Unit;

use App\Models\BulkMailCampaign;
use App\Models\BulkMailRecipient;
use App\Models\User;
use App\Filament\Imports\BulkMailRecipientImporter;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilamentImporterTest extends TestCase
{
    // use RefreshDatabase;

    public function test_importer_resolves_record()
    {
        $importer = new BulkMailRecipientImporter();
        $record = $importer->resolveRecord();

        $this->assertInstanceOf(BulkMailRecipient::class, $record);
    }

    public function test_importer_sets_campaign_id()
    {
        $campaign = new BulkMailCampaign();
        $campaign->id = 999;

        $recipient = new BulkMailRecipient();
        $importer = new class($recipient, $campaign->id) extends BulkMailRecipientImporter {
            public ?\Illuminate\Database\Eloquent\Model $record;
            public array $options;
            public function __construct($record, $campaignId) {
                $this->record = $record;
                $this->options = ['campaign_id' => $campaignId];
            }
            public function callBeforeSave() {
                $this->beforeSave();
            }
        };

        $importer->callBeforeSave();

        $this->assertEquals($campaign->id, $recipient->campaign_id);
    }
}
