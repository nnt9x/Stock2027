<?php

namespace App\Services;

use App\Jobs\SyncOhlcvJob;
use App\Models\Company;
use App\Models\Ohlcv;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OhlcvBatchService
{
    /** Nhận nghiệp vụ đánh dấu full reload để command, API và Livewire có thể dùng chung. */
    public function __construct(private OhlcvSyncService $sync) {}

    /**
     * Bao gồm công ty đang hoạt động và VNINDEX/VN30 độc lập với bảng companies.
     * Tạo một batch cho lượt chạy; chặn lượt mới khi batch trước còn job chưa xử lý.
     * Khóa dùng chung cả thị trường và từng mã để tránh chồng phạm vi đồng bộ.
     * Job đã thất bại được theo dõi trong batch, không ngăn chạy lượt mới mãi mãi.
     */
    public function dispatch(?string $ticker = null, bool $full = false): Batch
    {
        $lock = Cache::lock('ohlcv:dispatch', 120);
        if (! $lock->get()) {
            throw new RuntimeException('Đang tạo một batch OHLCV khác.');
        }
        try {
            $active = DB::connection(config('queue.batching.database'))
                ->table(config('queue.batching.table'))->where('name', 'like', 'OHLCV %')
                ->whereNull('cancelled_at')->whereNull('finished_at')
                ->whereColumn('pending_jobs', '>', 'failed_jobs')->first();
            if ($active) {
                throw new RuntimeException('Batch OHLCV '.$active->id.' còn đang chạy.');
            }
            $ticker = $ticker === null ? null : strtoupper(trim($ticker));
            $query = Company::query()->when($ticker !== null, fn ($query) => $query->where('ticker', $ticker));
            $tickers = $query->pluck('ticker')->all();
            foreach (Ohlcv::MARKET_INDICES as $index) {
                if ($ticker === null || $ticker === $index) {
                    $tickers[] = $index;
                }
            }
            $jobs = [];
            $until = now()->timestamp;
            foreach (array_unique($tickers) as $symbol) {
                if ($full) {
                    $this->sync->requestFullReload($symbol);
                }
                foreach (['1D', '1H'] as $resolution) {
                    $jobs[] = new SyncOhlcvJob($symbol, $resolution, $until);
                }
            }
            if ($jobs === []) {
                throw new RuntimeException('Không tìm thấy mã cổ phiếu hoặc chỉ số phù hợp.');
            }

            return Bus::batch($jobs)
                ->name('OHLCV '.($ticker ?? 'toàn thị trường').' '.($full ? 'full' : 'incremental').' '.now()->toIso8601String())
                ->withOption('until', $until)->withOption('tickers', array_values(array_unique($tickers)))
                ->onQueue('ohlcv')->allowFailures()
                ->finally(static function (Batch $batch): void {
                    app(IndicatorBatchService::class)->dispatchForPriceBatch($batch->id);
                })->dispatch();
        } finally {
            $lock->release();
        }
    }
}
