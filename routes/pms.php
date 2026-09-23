<?php

use App\Http\Controllers\LeaseFinancialDocumentsController;
use App\Http\Controllers\LeasePrintController;
use App\Http\Controllers\QuotationPrintController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('pms/quotations/{quotation}/print', QuotationPrintController::class)
        ->name('pms.quotations.print')
        ->middleware(['auth']);

    Route::get('pms/leases/{lease}/print/{format}', LeasePrintController::class)
        ->name('pms.leases.print')
        ->middleware(['auth']);

    Route::get('pms/leases/{lease}/tax-invoices', [LeaseFinancialDocumentsController::class, 'taxInvoices'])
        ->name('pms.leases.tax-invoices')
        ->middleware(['auth']);

    Route::get('pms/leases/{lease}/receivable-receipt', [LeaseFinancialDocumentsController::class, 'receivableReceipt'])
        ->name('pms.leases.receivable-receipt')
        ->middleware(['auth']);
});
