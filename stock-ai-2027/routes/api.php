<?php

use App\Http\Controllers\Api\V1\CompanyController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('companies', [CompanyController::class, 'index'])->name('companies.index');
    Route::post('companies/sync', [CompanyController::class, 'sync'])
        ->middleware('throttle:2,1')->name('companies.sync');
    Route::get('companies/{ticker}', [CompanyController::class, 'show'])->name('companies.show');
});
