<?php

namespace App\Console\Commands;

use App\Services\OhlcvBatchService;
use Illuminate\Console\Command;
use RuntimeException;

class DispatchOhlcvSync extends Command
{
    protected $signature = 'ohlcv:sync {--ticker= : Mã cổ phiếu hoặc VNINDEX/VN30, bỏ trống để chạy toàn thị trường} {--full : Yêu cầu tải lại từ 2024-01-01}';

    protected $description = 'Tạo batch đồng bộ nến ngày và giờ trên queue ohlcv';

    /** Tạo batch thông qua service và in ID để theo dõi, hủy hoặc retry bằng công cụ Laravel. */
    public function handle(OhlcvBatchService $service): int
    {
        try {
            $ticker = trim((string) $this->option('ticker'));
            $batch = $service->dispatch($ticker === '' ? null : $ticker, (bool) $this->option('full'));
            $this->info('Đã tạo batch '.$batch->id.' gồm '.$batch->totalJobs.' job trên queue ohlcv.');

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
