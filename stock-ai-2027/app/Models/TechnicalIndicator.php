<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Lưu chỉ báo theo từng nến, dùng chung cho cổ phiếu và chỉ số thị trường.
 * SMA giá tính trên close; volume_sma_20 là trung bình volume của 20 nến.
 * ROC là phần trăm thay đổi close theo số kỳ tương ứng.
 * MA của RSI/CCI/OBV dùng trung bình đơn giản; null nghĩa là chưa đủ dữ liệu để tính.
 */
#[Fillable(['ticker', 'resolution', 'trading_date', 'timestamp', 'sma_5', 'sma_10', 'sma_20', 'sma_50', 'sma_100', 'sma_150', 'sma_200', 'rsi_14', 'rsi_50', 'rsi_50_ma_10', 'cci_20', 'cci_20_ma10', 'roc_5', 'roc_10', 'roc_20', 'roc_50', 'roc_100', 'roc_150', 'roc_200', 'obv', 'obv_ma10', 'volume_sma_20', 'rs_line', 'rs_line_sma_10'])]
class TechnicalIndicator extends Model
{
    /**
     * Giữ giá trị chỉ báo dưới dạng decimal, OBV là khối lượng tích lũy có thể âm.
     * trading_date lấy từ nến nguồn, đã xác định theo Asia/Ho_Chi_Minh (UTC+7).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trading_date' => 'date:Y-m-d',
            'timestamp' => 'integer',
            'sma_5' => 'decimal:6',
            'sma_10' => 'decimal:6',
            'sma_20' => 'decimal:6',
            'sma_50' => 'decimal:6',
            'sma_100' => 'decimal:6',
            'sma_150' => 'decimal:6',
            'sma_200' => 'decimal:6',
            'rsi_14' => 'decimal:6',
            'rsi_50' => 'decimal:6',
            'rsi_50_ma_10' => 'decimal:6',
            'cci_20' => 'decimal:6',
            'cci_20_ma10' => 'decimal:6',
            'roc_5' => 'decimal:6',
            'roc_10' => 'decimal:6',
            'roc_20' => 'decimal:6',
            'roc_50' => 'decimal:6',
            'roc_100' => 'decimal:6',
            'roc_150' => 'decimal:6',
            'roc_200' => 'decimal:6',
            'obv' => 'integer',
            'obv_ma10' => 'decimal:6',
            'volume_sma_20' => 'decimal:6',
            'rs_line' => 'decimal:6',
            'rs_line_sma_10' => 'decimal:6',
        ];
    }
}
