<?php

use App\Enums\BulkMailRecipientStatus;
use App\Http\Controllers\BulkMailController;
use App\Http\Controllers\IncentiveCalculationAssistantPrintController;
use App\Http\Controllers\IncentiveCalculationPrintController;
use App\Http\Controllers\MatterReceivedNotificationController;
use App\Models\Attachment;
use App\Models\BulkMailRecipient;
use App\Models\Setting;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/mail/unsubscribe/{token}', function ($token) {
    $recipient = BulkMailRecipient::where('unsubscribe_token', $token)->firstOrFail();
    $recipient->update(['status' => BulkMailRecipientStatus::Skipped]);

    return __('You have been successfully unsubscribed.');
})->name('bulk-mail.unsubscribe');

Route::get('admin/system-down', function () {
    $message = session('maintenance_message') ?: Setting::getOfflineMessage();

    return view('errors.maintenance', compact('message'));
})->name('system-down');

Route::get('/login', fn () => redirect()->route('filament.admin.auth.login'))->name('login');

Route::middleware('auth')->group(function () {
    Route::get('bulk-mail/preview/{campaign}/{recipient}', [BulkMailController::class, '__invoke'])
        ->name('bulk-mail.preview');

    Route::get('attachments/{attachment}/download', function (Attachment $attachment) {
        abort_unless(auth()->user()->can('view', $attachment->matter), 403);

        return response()->download(
            Storage::disk('public')->path($attachment->path),
            $attachment->name  // original filename from DB
        );
    })->name('attachment.download')->middleware('auth');

    Route::get('incentive/calculations/{calculation}/print', IncentiveCalculationPrintController::class)
        ->name('incentive.calculation.print')
        ->middleware(['auth']);

    Route::get('incentive/calculations/{calculation}/print/{party}', IncentiveCalculationAssistantPrintController::class)
        ->name('incentive.calculation.print.assistant')
        ->middleware(['auth']);

});

/*
 * Assistant confirms or disputes the assigning date from an email link.
 *
 * These are deliberately OUTSIDE the auth group: the recipient clicks from their
 * inbox and is usually not logged in. They were previously nested inside it,
 * contradicting their own comments, so every emailed link bounced to the login
 * screen. The signature IS the authentication here, which is why the POST is
 * signed too — without it, anyone could flip any request to DISPUTED by ID.
 */
Route::prefix('admin/matter/{matter}/received-date')
    ->middleware('signed')
    ->group(function () {
        Route::get('accept/{matterRequest}', [MatterReceivedNotificationController::class, 'accept'])
            ->name('matter.received.accept');

        Route::get('dispute/{matterRequest}', [MatterReceivedNotificationController::class, 'disputeForm'])
            ->name('matter.received.dispute');

        Route::post('dispute/{matterRequest}', [MatterReceivedNotificationController::class, 'disputeSubmit'])
            ->name('matter.received.dispute.submit');
    });
