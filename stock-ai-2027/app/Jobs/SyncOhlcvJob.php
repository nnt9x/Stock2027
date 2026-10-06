<?php

namespace App\Jobs;

use App\Models\OhlcvSyncState;
use App\Services\OhlcvSyncService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;

class SyncOhlcvJob implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable;

    public int $timeout = 120;

    public int $tries = 200;

    public int $maxExceptions = 5;

    public int $uniqueFor = 86400;

    public array $backoff = [30, 120, 300];

    /**
     * Cố định đích thời gian cho toàn bộ backfill; chạy trên queue ohlcv riêng.
     */
    public function __construct(public string $ticker, public string $resolution, public int $until)
    {
        $this->onQueue('ohlcv');
    }

    /**
     * Chống trùng khi dispatch riêng; job trong batch dùng khóa của service thay thế.
     */
    public function uniqueId(): string
    {
        return $this->ticker.':'.$this->resolution;
    }

    /** DNSE không nhận mã là lỗi vĩnh viễn: kết thúc job ngay, còn lỗi mạng/server giữ retry. */
    public function handle(OhlcvSyncService $service): void
    {
        try {
            $this->synchronize($service);
        } catch (RequestException $exception) {
            if ($exception->response->status() === 400
                && strtolower(trim((string) $exception->response->json('message'))) === 'invalid symbol') {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }
    }

    /**
     * Tải trọn khoảng dữ liệu trong một lượt, không chia job theo tháng.
     * Xử lý ngay cả resolution kia khi được đánh dấu tải lại, không chờ lượt queue tiếp theo.
     * Không thêm job vì batch đã có cả hai resolution. Job còn sống giữ batch pending
     * đến khi phần tải lại phát sinh hoàn tất, kể cả job của resolution kia đã kết thúc.
     */
    private function synchronize(OhlcvSyncService $service): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }
        $state = OhlcvSyncState::where('ticker', $this->ticker)->where('resolution', $this->resolution)->first();
        $ready = $state && $state->synced_through_timestamp >= $this->until
            && $state->reload_version <= $state->completed_reload_version;
        if (! $ready && $service->syncStep($this->ticker, $this->resolution, $this->until)) {
            $this->release(2);

            return;
        }
        if ($this->batch()?->cancelled()) {
            return;
        }
        $other = $this->resolution === '1D' ? '1H' : '1D';
        $pending = OhlcvSyncState::where('ticker', $this->ticker)->where('resolution', $other)
            ->whereColumn('reload_version', '>', 'completed_reload_version')->exists();
        if ($pending && $service->syncStep($this->ticker, $other, $this->until)) {
            $this->release(2);

            return;
        }
    }
}
