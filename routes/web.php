<?php

use App\Http\Controllers\BulkMailController;
use App\Http\Controllers\IncentiveCalculationPrintController;
use App\Http\Controllers\MatterReceivedNotificationController;
use App\Models\BulkMailRecipient;
use App\Enums\BulkMailRecipientStatus;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/mail/unsubscribe/{token}', function ($token) {
    $recipient = BulkMailRecipient::where('unsubscribe_token', $token)->firstOrFail();
    $recipient->update(['status' => BulkMailRecipientStatus::Skipped]);
    return __('You have been successfully unsubscribed.');
})->name('bulk-mail.unsubscribe');

Route::get('bulk-mail/preview/{campaign}/{recipient}', [BulkMailController::class,'__invoke'])
    ->name('bulk-mail.preview');

Route::get('attachments/{attachment}/download', function (\App\Models\Attachment $attachment) {
    abort_unless(auth()->user()->can('view', $attachment->matter), 403);

    return response()->download(
        Storage::disk('public')->path($attachment->path),
        $attachment->name  // original filename from DB
    );
})->name('attachment.download')->middleware('auth');

Route::get('incentive/calculations/{calculation}/print', IncentiveCalculationPrintController::class)
    ->name('incentive.calculation.print')
    ->middleware(['auth']);

Route::prefix('admin/matter/{matter}/received-date')->group(function () {

    // Accept link from email (GET — no auth required)
    Route::get('accept/{matterRequest}', [MatterReceivedNotificationController::class, 'accept'])
        ->name('matter.received.accept')
        ->middleware('signed');

    // Dispute link from email — shows form (GET — no auth required)
    Route::get('dispute/{matterRequest}', [MatterReceivedNotificationController::class, 'disputeForm'])
        ->name('matter.received.dispute')
        ->middleware('signed');

    // Dispute form submission (POST — no auth required)
    Route::post('dispute/{matterRequest}', [MatterReceivedNotificationController::class, 'disputeSubmit'])
        ->name('matter.received.dispute.submit');
});
