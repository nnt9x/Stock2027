<?php

namespace App\Services;

use App\Models\TechnicalIndicator;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class TechnicalIndicatorQueryService
{
    /** Các mốc ROC theo số phiên có giao dịch, dùng cho bảng tổng quan khung ngày. */
    public const ROC_PERIODS = [5, 10, 20, 50, 100, 150, 200];

    /** Lấy ROC ngày đã tính gần nhất của đúng mã; null biểu thị chưa đủ phiên hoặc chưa tính. */
    public function latestDailyRoc(string $ticker): ?TechnicalIndicator
    {
        return TechnicalIndicator::where('ticker', strtoupper(trim($ticker)))->where('resolution', '1D')
            ->orderByDesc('timestamp')->first(['trading_date', 'timestamp',
                ...array_map(fn (int $period): string => 'roc_'.$period, self::ROC_PERIODS)]);
    }

    /** Các trường đã lưu được hỗ trợ trên biểu đồ nhiều pane. */
    public const CHART_FIELDS = ['sma_5', 'sma_10', 'sma_20', 'sma_50', 'sma_100', 'sma_150', 'sma_200',
        'rsi_14', 'rsi_50', 'rsi_50_ma_10', 'cci_20', 'cci_20_ma10', 'obv', 'obv_ma10',
        'volume_sma_20', 'rs_line', 'rs_line_sma_10'];

    /**
     * Đọc chỉ báo đúng ticker/khung và khoảng nến đang hiển thị; không tính lại trong request UI.
     * Giá trị null giữ nguyên để biểu đồ thể hiện khoảng chưa có kết quả.
     *
     * @return Collection<int, TechnicalIndicator>
     */
    public function between(string $ticker, string $resolution, int $from, int $to): Collection
    {
        if (! in_array($resolution, ['1D', '1H'], true)) {
            throw new InvalidArgumentException('Khung thời gian không hợp lệ.');
        }

        return TechnicalIndicator::where('ticker', strtoupper(trim($ticker)))->where('resolution', $resolution)
            ->whereBetween('timestamp', [$from, $to])->orderBy('timestamp')
            ->get(['timestamp', 'trading_date', ...self::CHART_FIELDS]);
    }
}
