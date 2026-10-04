<?php

namespace App\Services;

use App\Jobs\CalculateIndicatorsJob;
use App\Models\OhlcvSyncState;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class IndicatorBatchService
{
    /**
     * Sau batch giá, tính các chuỗi trong phạm vi đã lưu ở options và đủ mốc thời gian.
     * Dùng options của job_batches để liên kết hai giai đoạn, không tạo bảng metadata riêng.
     * Khóa và transaction ngăn callback gọi lại tạo batch chỉ báo trùng.
     */
    public function dispatchForPriceBatch(string $priceBatchId): ?Batch
    {
        $price = Bus::findBatch($priceBatchId);
        if (! $price || $price->cancelled() || $price->pendingJobs > $price->failedJobs) {
            return null;
        }

        return Cache::lock('indicators:dispatch:'.$priceBatchId, 120)->block(5, function () use ($priceBatchId): ?Batch {
            return DB::transaction(function () use ($priceBatchId): ?Batch {
                $table = DB::connection(config('queue.batching.database'))->table(config('queue.batching.table'));
                $table->where('id', $priceBatchId)->lockForUpdate()->first();
                $price = Bus::findBatch($priceBatchId);
                if ($child = $price->options['indicator_batch_id'] ?? null) {
                    return Bus::findBatch($child);
                }
                $until = $price->options['until'] ?? null;
                $tickers = $price->options['tickers'] ?? [];
                if (! $until || $tickers === []) {
                    return null;
                }
                $states = OhlcvSyncState::whereIn('ticker', $tickers)->whereNull('last_error')
                    ->where('synced_through_timestamp', '>=', $until)
                    ->whereColumn('reload_version', '<=', 'completed_reload_version')->get();
                $jobs = $states->map(fn ($state) => new CalculateIndicatorsJob($state->ticker, $state->resolution,
                    (int) $until, $priceBatchId, $priceBatchId))->all();
                if ($jobs === []) {
                    return null;
                }
                $batch = $this->createBatch($jobs, 'giá '.$priceBatchId);
                $options = $price->options;
                $options['indicator_batch_id'] = $batch->id;
                // DatabaseBatchRepository lưu options bằng serialize trên MySQL/SQLite của dự án.
                $table->where('id', $priceBatchId)->update(['options' => serialize($options)]);

                return $batch;
            });
        });
    }

    /** Tính lại từ giá đã lưu, bỏ chuỗi lỗi hoặc chưa hoàn tất tải lại lịch sử. */
    public function dispatchStored(?string $ticker = null): Batch
    {
        $runId = (string) Str::uuid();
        $states = OhlcvSyncState::whereNotNull('synced_through_timestamp')->whereNull('last_error')
            ->whereColumn('reload_version', '<=', 'completed_reload_version')
            ->when($ticker !== null, fn ($query) => $query->where('ticker', strtoupper(trim($ticker))))->get();
        $jobs = $states->map(fn ($state) => new CalculateIndicatorsJob($state->ticker, $state->resolution,
            $state->synced_through_timestamp, $runId))->all();
        if ($jobs === []) {
            throw new RuntimeException('Không có chuỗi giá đã đồng bộ thành công để tính chỉ báo.');
        }

        return $this->createBatch($jobs, $ticker ?? 'toàn thị trường từ giá đã lưu');
    }

    /**
     * Một mã lỗi không hủy cả batch; Laravel tiếp tục quản lý retry và thống kê native.
     *
     * @param  list<CalculateIndicatorsJob>  $jobs
     */
    private function createBatch(array $jobs, string $name): Batch
    {
        return Bus::batch($jobs)->name('Indicators '.$name.' '.now()->toIso8601String())
            ->withOption('run_id', $jobs[0]->runId)->withOption('price_batch_id', $jobs[0]->priceBatchId)
            ->onQueue('indicators')->allowFailures()->dispatch();
    }
}
