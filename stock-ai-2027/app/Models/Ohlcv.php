<?php

namespace App\Models;

use Database\Factories\OhlcvFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lưu một nến giá và khối lượng của ticker theo resolution tại timestamp nguồn.
 */
#[Fillable(['ticker', 'resolution', 'trading_date', 'timestamp', 'open', 'high', 'low', 'close', 'volume'])]
class Ohlcv extends Model
{
    /** Chỉ số thị trường được đồng bộ độc lập, không tạo công ty tương ứng. */
    public const MARKET_INDICES = ['VNINDEX', 'VN30'];

    /**
     * Tạo nến mẫu phục vụ kiểm thử.
     *
     * @use HasFactory<OhlcvFactory>
     */
    use HasFactory;

    /**
     * Giữ timestamp dạng Unix giây và giá dạng decimal để tránh sai số float.
     * trading_date là ngày giao dịch Việt Nam do tầng đồng bộ xác định.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trading_date' => 'date:Y-m-d',
            'timestamp' => 'integer',
            'open' => 'decimal:6',
            'high' => 'decimal:6',
            'low' => 'decimal:6',
            'close' => 'decimal:6',
            'volume' => 'integer',
        ];
    }

    /**
     * Ánh xạ ohlcvs.ticker → companies.ticker, không tạo khóa ngoại database.
     * Trả về null khi công ty chưa tồn tại hoặc đã xóa mềm.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'ticker', 'ticker');
    }
}
