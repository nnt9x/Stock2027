<?php

namespace App\Services;

use App\Integrations\Dnse\DnseOhlcvClient;
use App\Models\Ohlcv;
use App\Models\OhlcvSyncState;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class OhlcvSyncService
{
    /**
     * Tách giao tiếp DNSE khỏi nghiệp vụ tiến độ, phát hiện điều chỉnh và lưu nến.
     */
    public function __construct(private DnseOhlcvClient $client) {}

    /**
     * Đánh dấu tải lại cả hai resolution; tăng phiên bản để giữ yêu cầu đến trong lúc job chạy.
     */
    public function requestFullReload(string $ticker): void
    {
        DB::transaction(function () use ($ticker): void {
            foreach (['1D', '1H'] as $resolution) {
                $state = $this->state($ticker, $resolution);
                OhlcvSyncState::whereKey($state->id)->update([
                    'reload_version' => DB::raw('reload_version + 1'), 'reload_through_timestamp' => null,
                ]);
            }
        });
    }

    /**
     * Tải toàn bộ khoảng cần đồng bộ trong một request; full luôn bắt đầu từ 2024-01-01.
     * Trả true khi cần thử lại do khóa hoặc có yêu cầu full reload mới.
     * Overlap đúng 5 ngày có nến đã lưu, so sánh OHLC của phiên đã đóng trước khi upsert.
     * Khi phát hiện điều chỉnh, tải full ngay trong lần gọi này, không nhả về queue.
     * Giữ dữ liệu cũ khi nguồn trả rỗng; chỉ tăng tiến độ sau transaction thành công.
     */
    public function syncStep(string $ticker, string $resolution, int $until): bool
    {
        $ticker = strtoupper(trim($ticker));
        if (! in_array($resolution, ['1D', '1H'], true) || $ticker === '') {
            throw new InvalidArgumentException('Ticker hoặc resolution không hợp lệ.');
        }

        $lock = Cache::lock('ohlcv:sync:'.$ticker.':'.$resolution, 150);
        if (! $lock->get()) {
            return true;
        }

        try {
            $state = $this->state($ticker, $resolution);
            $today = CarbonImmutable::now('Asia/Ho_Chi_Minh')->startOfDay()->timestamp;
            $start = CarbonImmutable::parse('2024-01-01', 'Asia/Ho_Chi_Minh')->timestamp;
            $reload = $state->reload_version > $state->completed_reload_version;
            $cursor = $reload ? null : $state->synced_through_timestamp;
            $from = $cursor ?? $start;
            $overlapFrom = null;
            $overlapTo = null;
            if (! $reload) {
                if ($cursor === null) {
                    $latest = Ohlcv::where('ticker', $ticker)->where('resolution', $resolution)
                        ->where('timestamp', '<', $until)->max('timestamp');
                    $from = $latest === null ? $start : max($start, (int) $latest);
                }
                $days = Ohlcv::where('ticker', $ticker)->where('resolution', $resolution)
                    ->where('timestamp', '<', min($until, $today))->distinct()->orderByDesc('trading_date')->limit(5)->pluck('trading_date');
                if ($days->isNotEmpty()) {
                    $overlapFrom = CarbonImmutable::parse((string) $days->last(), 'Asia/Ho_Chi_Minh')->timestamp;
                    $overlapTo = min($until, CarbonImmutable::parse((string) $days->first(), 'Asia/Ho_Chi_Minh')->addDay()->timestamp);
                    if ($overlapFrom >= $from - 25 * 86400) {
                        $from = min($from, $overlapFrom);
                        $overlapFrom = null;
                    }
                }
            }
            $from = max($from, $start);
            if ($from >= $until) {
                return false;
            }
            $to = $until;
            $rows = $this->client->candles($ticker, $resolution, $from, $to);
            if ($overlapFrom !== null) {
                $overlap = $this->client->candles($ticker, $resolution, $overlapFrom, $overlapTo);
                $rows = array_values(array_column(array_merge($rows, $overlap), null, 'timestamp'));
            }
            $existing = Ohlcv::where('ticker', $ticker)->where('resolution', $resolution)
                ->whereIn('timestamp', array_column($rows, 'timestamp'))->get()->keyBy('timestamp');
            $adjusted = false;
            foreach ($rows as $row) {
                $old = $existing->get($row['timestamp']);
                if (! $reload && $old && $row['timestamp'] < $today) {
                    foreach (['open', 'high', 'low', 'close'] as $field) {
                        if (number_format((float) $old->$field, 6, '.', '') !== $row[$field]) {
                            $adjusted = true;
                            break 2;
                        }
                    }
                }
            }
            if ($adjusted) {
                $this->requestFullReload($ticker);
                $state->refresh();
                $reload = true;
                $rows = $this->client->candles($ticker, $resolution, $start, $until);
            }
            foreach ($rows as &$row) {
                $row += ['ticker' => $ticker, 'resolution' => $resolution,
                    'trading_date' => CarbonImmutable::createFromTimestamp($row['timestamp'], 'Asia/Ho_Chi_Minh')->toDateString()];
            }
            unset($row);

            return DB::transaction(function () use ($state, $rows, $reload, $to, $until): bool {
                $current = OhlcvSyncState::whereKey($state->id)->lockForUpdate()->firstOrFail();
                if ($current->reload_version !== $state->reload_version) {
                    return true;
                }
                foreach (array_chunk($rows, 500) as $batch) {
                    Ohlcv::upsert($batch, ['ticker', 'resolution', 'timestamp'], ['open', 'high', 'low', 'close', 'volume', 'trading_date', 'updated_at']);
                }
                if ($reload) {
                    $current->reload_through_timestamp = $to;
                    if ($to >= $until) {
                        $current->completed_reload_version = $state->reload_version;
                        $current->synced_through_timestamp = $to;
                    }
                } else {
                    $current->synced_through_timestamp = max($current->synced_through_timestamp ?? 0, $to);
                }
                $current->data_version++;
                $current->last_success_at = now();
                $current->last_error = null;
                $current->save();

                return $to < $until;
            });
        } catch (Throwable $exception) {
            if (isset($state)) {
                $state->update(['last_error' => $exception->getMessage()]);
            }
            throw $exception;
        } finally {
            $lock->release();
        }
    }

    /**
     * Tạo trạng thái nếu chưa có; unique key bảo vệ khi nhiều tiến trình khởi tạo.
     */
    private function state(string $ticker, string $resolution): OhlcvSyncState
    {
        return OhlcvSyncState::firstOrCreate(['ticker' => strtoupper(trim($ticker)), 'resolution' => $resolution])->refresh();
    }
}
