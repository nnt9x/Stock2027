<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/** Đồng bộ sau phiên giao dịch theo giờ Việt Nam, bao gồm cả cuối tuần để thử lại lỗi. */
Schedule::command('ohlcv:sync')->dailyAt('16:30')->timezone('Asia/Ho_Chi_Minh')->withoutOverlapping();
