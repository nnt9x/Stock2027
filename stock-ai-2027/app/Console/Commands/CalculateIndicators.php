<?php

namespace App\Console\Commands;

use App\Services\IndicatorBatchService;
use Illuminate\Console\Command;
use RuntimeException;

class CalculateIndicators extends Command
{
    protected $signature = 'indicators:calculate {--ticker= : Mã cụ thể, bỏ trống để tính toàn thị trường} {--price-batch= : Tính từ kết quả thành công của batch giá}';

    protected $description = 'Tạo batch gọi API Python để tính chỉ báo từ giá đã lưu';

    /** Hỗ trợ tính thủ công và kích hoạt lại callback giá khi cần, in ID để giám sát. */
    public function handle(IndicatorBatchService $service): int
    {
        try {
            $price = $this->option('price-batch');
            $ticker = trim((string) $this->option('ticker'));
            $batch = $price ? $service->dispatchForPriceBatch((string) $price) : $service->dispatchStored($ticker ?: null);
            if (! $batch) {
                $this->error('Batch giá bị hủy, không tồn tại hoặc chưa có kết quả thành công.');

                return self::FAILURE;
            }
            $this->info('Batch chỉ báo '.$batch->id.' gồm '.$batch->totalJobs.' job, queue indicators.');

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
