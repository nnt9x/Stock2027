<?php

namespace App\Services;

use App\Models\Ohlcv;
use Carbon\CarbonImmutable;

class FibonacciPivotService
{
    /** Thứ tự mức theo bảng; R4/S4 dùng phần mở rộng Fibonacci 1,618. */
    public const LEVELS = ['R4', 'R3', 'R2', 'R1', 'PP', 'S1', 'S2', 'S3', 'S4'];

    /**
     * Tính pivot từ H/L/C của kỳ nguồn, không làm tròn trước khi tính độ lệch.
     *
     * @return array<string, float>
     */
    public function levels(float $high, float $low, float $close): array
    {
        $pivot = ($high + $low + $close) / 3;
        $range = $high - $low;
        $levels = ['PP' => $pivot];
        foreach ([1 => 0.382, 2 => 0.618, 3 => 1.0, 4 => 1.618] as $number => $ratio) {
            $levels['R'.$number] = $pivot + $ratio * $range;
            $levels['S'.$number] = $pivot - $ratio * $range;
        }

        return $levels;
    }

    /**
     * Pivot 5 tháng gần nhất dùng đúng tháng liền trước; forward dùng tháng hiện tại chưa đóng.
     * Đọc nến 1D có giao dịch theo ngày Việt Nam, độc lập với khung biểu đồ đang chọn.
     * Thiếu tháng nguồn thì trả null, không dùng tháng cũ thay thế và không ghi DB.
     *
     * @return array{months: array<string, array<string, float>|null>, current_month: string, forward_month: string,
     *     forward_levels: array<string, float>|null, provisional: bool, reference_close: float|null, reference_date: string|null}
     */
    public function monthly(string $ticker, ?CarbonImmutable $asOf = null): array
    {
        $asOf = ($asOf ?? CarbonImmutable::now('Asia/Ho_Chi_Minh'))->setTimezone('Asia/Ho_Chi_Minh');
        $current = $asOf->startOfMonth();
        $first = $current->subMonthsNoOverflow(4);
        $candles = Ohlcv::where('ticker', strtoupper(trim($ticker)))->where('resolution', '1D')->where('volume', '>', 0)
            ->where('trading_date', '>=', $first->subMonthNoOverflow()->toDateString())
            ->where('timestamp', '>=', $first->subMonthNoOverflow()->timestamp)
            ->where('trading_date', '<=', $asOf->toDateString())->where('timestamp', '<=', $asOf->timestamp)
            ->orderBy('timestamp')->get(['trading_date', 'timestamp', 'high', 'low', 'close']);
        $groups = $candles->groupBy(fn (Ohlcv $candle): string => $candle->trading_date->format('Y-m'));
        $forMonth = function (string $month) use ($groups): ?array {
            $source = $groups->get($month);
            if ($source === null || $source->isEmpty()) {
                return null;
            }

            return $this->levels((float) $source->max('high'), (float) $source->min('low'), (float) $source->last()->close);
        };
        $months = [];
        for ($index = 0; $index < 5; $index++) {
            $month = $first->addMonthsNoOverflow($index);
            $months[$month->format('Y-m')] = $forMonth($month->subMonthNoOverflow()->format('Y-m'));
        }
        $latest = $candles->last();

        return ['months' => $months, 'current_month' => $current->format('Y-m'),
            'forward_month' => $current->addMonthNoOverflow()->format('Y-m'),
            'forward_levels' => $forMonth($current->format('Y-m')), 'provisional' => true,
            'reference_close' => $latest === null ? null : (float) $latest->close,
            'reference_date' => $latest?->trading_date->toDateString()];
    }
}
