<?php

namespace Database\Factories;

use App\Models\Ohlcv;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Tạo nến mẫu với biên giá hợp lệ.
 *
 * @extends Factory<Ohlcv>
 */
class OhlcvFactory extends Factory
{
    /**
     * Timestamp và ngày giao dịch cùng múi giờ Việt Nam; test ghi đè khi cần.
     *
     * @return array<string, int|string>
     */
    public function definition(): array
    {
        $date = CarbonImmutable::parse('2024-01-02 09:00', 'Asia/Ho_Chi_Minh');

        return ['ticker' => 'NAB', 'resolution' => '1D', 'timestamp' => $date->timestamp,
            'trading_date' => $date->toDateString(), 'open' => '10', 'high' => '11',
            'low' => '9', 'close' => '10', 'volume' => 100];
    }
}
