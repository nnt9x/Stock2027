<?php

use App\Livewire\Companies\Chart;
use App\Livewire\Companies\Index;
use App\Livewire\Home;
use Illuminate\Support\Facades\Route;

Route::get('/', Home::class)->name('home');

Route::get('/companies', Index::class)->name('companies.index');
Route::get('/analysis', Chart::class)->name('analysis.index');
Route::get('/market', App\Livewire\Market\Index::class)->name('market.index');
Route::get('/companies/{ticker}/chart', Chart::class)->name('companies.chart');
Route::get('/queues', App\Livewire\Queues\Index::class)->name('queues.index');
