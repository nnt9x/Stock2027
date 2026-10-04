<?php

use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\IcbController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('icbs', [IcbController::class, 'index'])->name('icbs.index');
    Route::get('icbs/{code}', [IcbController::class, 'show'])->name('icbs.show');

    Route::get('companies', [CompanyController::class, 'index'])->name('companies.index');
    Route::post('companies/sync', [CompanyController::class, 'sync'])
        ->middleware('throttle:2,1')->name('companies.sync');
    Route::get('companies/{ticker}', [CompanyController::class, 'show'])->name('companies.show');
});
