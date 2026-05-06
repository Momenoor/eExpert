<?php

use App\Models\BulkMailCampaign;
use App\Models\BulkMailRecipient;
use App\Enums\BulkMailCampaignStatus;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use League\Csv\Writer;
use App\Filament\Resources\BulkMailCampaignResource\Actions\ImportRecipientsAction;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// 1. Setup Data
$user = User::first();
if (!$user) {
    echo "No user found to create campaign.\n";
    exit(1);
}

$campaign = BulkMailCampaign::create([
    'name' => 'Test Campaign',
    'subject' => 'Hello {{name}}',
    'body' => 'Welcome Ahmed! Your matter is {{matter_number}}.',
    'from_sender_key' => 'main',
    'placeholders' => ['name', 'matter_number'],
    'status' => BulkMailCampaignStatus::Draft,
    'created_by' => $user->id,
]);

echo "Created campaign ID: {$campaign->id}\n";

// 2. Create CSV
$csvData = [
    ['email', 'name', 'matter_number', 'cc_emails'],
    ['test1@example.com', 'Ahmed', 'MAT-001', 'cc1@example.com'],
    ['test2@example.com', 'Sara', 'MAT-002', ''],
    ['invalid-email', 'John', 'MAT-003', ''],
    ['test1@example.com', 'Duplicate', 'MAT-004', ''], // Duplicate email
];

$csvPath = 'temp_test_import.csv';
$writer = Writer::createFromPath(Storage::disk('local')->path($csvPath), 'w+');
$writer->insertAll($csvData);

echo "Created test CSV at: " . Storage::disk('local')->path($csvPath) . "\n";

// 3. Mock the Filament Action call (internal logic)
try {
    $data = ['file' => $csvPath];

    // We need to simulate the action's logic
    $path = Storage::disk('local')->path($data['file']);
    $csv = League\Csv\Reader::createFromPath($path, 'r');
    $csv->setHeaderOffset(0);

    $records = $csv->getRecords();
    $imported = 0;
    $skipped = 0;

    foreach ($records as $row) {
        if (!isset($row['email']) || empty($row['email']) || !filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            echo "Skipping invalid email: " . ($row['email'] ?? 'EMPTY') . "\n";
            $skipped++;
            continue;
        }

        if (BulkMailRecipient::where('campaign_id', $campaign->id)->where('email', $row['email'])->exists()) {
            echo "Skipping duplicate email: {$row['email']}\n";
            $skipped++;
            continue;
        }

        $placeholders = [];
        foreach ($campaign->placeholders ?? [] as $key) {
            if (isset($row[$key])) {
                $placeholders[$key] = $row[$key];
            }
        }

        BulkMailRecipient::create([
            'campaign_id' => $campaign->id,
            'email' => $row['email'],
            'name' => $row['name'] ?? null,
            'placeholders' => $placeholders,
            'cc_emails' => !empty($row['cc_emails']) ? explode(',', $row['cc_emails']) : null,
            'status' => \App\Enums\BulkMailRecipientStatus::Pending,
        ]);

        $imported++;
    }

    $campaign->increment('total_recipients', $imported);

    echo "Import Result: {$imported} imported, {$skipped} skipped.\n";

    // 4. Verify
    $count = BulkMailRecipient::where('campaign_id', $campaign->id)->count();
    echo "Total recipients in DB for campaign: {$count}\n";

    $r1 = BulkMailRecipient::where('email', 'test1@example.com')->first();
    if ($r1 && $r1->placeholders['matter_number'] === 'MAT-001' && $r1->cc_emails[0] === 'cc1@example.com') {
        echo "Verification passed for test1@example.com\n";
    } else {
        echo "Verification FAILED for test1@example.com\n";
    }

} catch (\Exception $e) {
    echo "Error during import: " . $e->getMessage() . "\n";
} finally {
    Storage::disk('local')->delete($csvPath);
    // Cleanup campaign if you want, but better keep it for proof
}
