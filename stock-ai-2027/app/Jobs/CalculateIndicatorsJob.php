<?php

namespace App\Jobs;

use App\Integrations\Indicators\PythonIndicatorClient;
use App\Models\OhlcvSyncState;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use RuntimeException;

class CalculateIndicatorsJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $timeout = 90;

    public int $tries = 5;

    public array $backoff = [15, 60, 180];

    /** Một job nối tiếp chỉ báo của một mã/khung; Python tự tính full khi cần khởi tạo hoặc giá điều chỉnh. */
    public function __construct(public string $ticker, public string $resolution, public int $until, public string $runId, public ?string $priceBatchId = null)
    {
        $this->onQueue('indicators');
    }

    /**
     * Đọc phiên bản mới mỗi lần retry; Python kiểm tra lại trong transaction trước khi ghi.
     * Benchmark lỗi trong lượt giá thì để RSLine null, vẫn tính các chỉ báo độc lập.
     */
    public function handle(PythonIndicatorClient $client): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }
        $source = OhlcvSyncState::where('ticker', $this->ticker)->where('resolution', $this->resolution)->first();
        if (! $source || $source->synced_through_timestamp < $this->until
            || $source->reload_version > $source->completed_reload_version) {
            throw new RuntimeException('Giá nguồn chưa sẵn sàng để tính chỉ báo.');
        }
        $benchmark = config('services.indicators.benchmark', 'VNINDEX');
        $reference = OhlcvSyncState::where('ticker', $benchmark)->where('resolution', $this->resolution)->first();
        $price = $this->priceBatchId ? Bus::findBatch($this->priceBatchId) : null;
        $eligible = $this->priceBatchId === null || ($price
            && in_array($benchmark, $price->options['tickers'] ?? [], true)
            && $reference && $reference->synced_through_timestamp >= $this->until);
        $version = $eligible && $reference && $reference->last_error === null
            && $reference->synced_through_timestamp !== null
            && $reference->reload_version <= $reference->completed_reload_version ? (int) $reference->data_version : null;
        $payload = ['ticker' => $this->ticker, 'resolution' => $this->resolution, 'until' => $this->until,
            'benchmark' => $benchmark, 'source_version' => (int) $source->data_version, 'benchmark_version' => $version];
        $payload['request_id'] = hash('sha256', $this->runId.json_encode($payload, JSON_THROW_ON_ERROR));
        $client->calculate($payload);
    }
}
