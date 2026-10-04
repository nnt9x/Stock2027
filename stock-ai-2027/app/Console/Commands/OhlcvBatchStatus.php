<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class OhlcvBatchStatus extends Command
{
    protected $signature = 'ohlcv:batch {id : ID batch cần xem} {--cancel : Hủy các job chưa bắt đầu của batch}';

    protected $description = 'Xem tiến độ hoặc hủy batch đồng bộ OHLCV';

    /** Hiển thị thống kê native của Laravel; job đang gọi HTTP sẽ dừng ở lượt xử lý kế tiếp. */
    public function handle(): int
    {
        $batch = Bus::findBatch((string) $this->argument('id'));
        if (! $batch || ! str_starts_with($batch->name, 'OHLCV ')) {
            $this->error('Không tìm thấy batch OHLCV.');

            return self::FAILURE;
        }
        if ($this->option('cancel')) {
            $batch->cancel();
            $batch = $batch->fresh();
        }
        $status = $batch->cancelled() ? 'Đã hủy' : ($batch->finished() ? 'Hoàn tất' : 'Đang chạy');
        if (! $batch->cancelled() && $batch->failedJobs > 0 && $batch->pendingJobs === $batch->failedJobs) {
            $status = 'Kết thúc có lỗi';
        }
        $this->table(['ID', 'Tên', 'Tổng', 'Thành công', 'Đang chờ', 'Thất bại', 'Tiến độ', 'Trạng thái'], [[
            $batch->id, $batch->name, $batch->totalJobs, $batch->processedJobs(), $batch->pendingJobs - $batch->failedJobs, $batch->failedJobs,
            $batch->progress().'%', $status,
        ]]);

        return self::SUCCESS;
    }
}
