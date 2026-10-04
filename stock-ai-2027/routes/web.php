<?php

use App\Livewire\Companies\Index;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('index');
});

Route::get('/companies', Index::class)->name('companies.index');
Route::get('/queues', App\Livewire\Queues\Index::class)->name('queues.index');
