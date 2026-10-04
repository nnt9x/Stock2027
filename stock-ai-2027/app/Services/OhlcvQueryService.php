<?php

namespace App\Services;

use App\Models\Ohlcv;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class OhlcvQueryService
{
    /**
     * Lấy nến gần nhất theo ticker chính xác, trả thứ tự thời gian tăng dần cho các consumer.
     * Chỉ đọc các cột giá/khối lượng; giới hạn 5.000 nến để tránh tải lịch sử không giới hạn.
     *
     * @return Collection<int, Ohlcv>
     */
    public function candles(string $ticker, string $resolution, int $limit = 5000): Collection
    {
        if (! in_array($resolution, ['1D', '1H'], true)) {
            throw new InvalidArgumentException('Khung thời gian không hợp lệ.');
        }

        return Ohlcv::where('ticker', strtoupper(trim($ticker)))->where('resolution', $resolution)
            ->select(['timestamp', 'trading_date', 'open', 'high', 'low', 'close', 'volume'])
            ->orderByDesc('timestamp')->limit(max(1, min($limit, 5000)))->get()->reverse()->values();
    }
}
