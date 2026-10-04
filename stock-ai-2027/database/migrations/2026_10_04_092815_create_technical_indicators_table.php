<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mỗi dòng là bộ chỉ báo của một nến; ánh xạ nguồn bằng ticker/resolution/timestamp.
     * Không ràng buộc companies vì VNINDEX/VN30 cũng dùng bảng này.
     * Chỉ báo chưa đủ nến để tính giữ null, không dùng 0 thay cho dữ liệu thiếu.
     */
    public function up(): void
    {
        Schema::create('technical_indicators', function (Blueprint $table) {
            $table->id();
            $table->string('ticker');
            $table->string('resolution', 10);
            $table->date('trading_date')->comment('Ngày nến nguồn theo giờ Việt Nam UTC+7');
            $table->unsignedBigInteger('timestamp')->comment('Unix giây giữ nguyên từ nến nguồn');

            foreach ([5, 10, 20, 50, 100, 150, 200] as $period) {
                $table->decimal('sma_'.$period, 20, 6)->nullable()->comment('SMA giá đóng cửa '.$period.' kỳ');
            }

            $table->decimal('rsi_14', 10, 6)->nullable();
            $table->decimal('rsi_50', 10, 6)->nullable();
            $table->decimal('rsi_50_ma_10', 10, 6)->nullable()->comment('SMA10 của RSI50');
            $table->decimal('cci_20', 20, 6)->nullable();
            $table->decimal('cci_20_ma10', 20, 6)->nullable()->comment('SMA10 của CCI20');

            foreach ([5, 10, 20, 50, 100, 150, 200] as $period) {
                $table->decimal('roc_'.$period, 20, 6)->nullable()->comment('ROC close '.$period.' kỳ, đơn vị phần trăm');
            }

            $table->bigInteger('obv')->nullable()->comment('Khối lượng tích lũy có dấu, có thể âm');
            $table->decimal('obv_ma10', 30, 6)->nullable()->comment('SMA10 của OBV, có thể âm và có phần thập phân');
            $table->decimal('volume_sma_20', 30, 6)->nullable()->comment('Trung bình khối lượng giao dịch của 20 nến');
            $table->decimal('rs_line', 20, 6)->nullable()->comment('Đường sức mạnh tương đối so với chỉ số tham chiếu');
            $table->decimal('rs_line_sma_10', 20, 6)->nullable()->comment('SMA10 của đường RSLine');
            $table->timestamps();

            /*
             * Hot path: lịch sử một mã, một khung trong khoảng timestamp; nến mới nhất.
             * Unique cũng phục vụ upsert khi tính lại do giá nguồn bị điều chỉnh.
             */
            $table->unique(['ticker', 'resolution', 'timestamp']);

            /*
             * Hot path: lọc toàn thị trường theo resolution và ngày giao dịch Việt Nam.
             * Sau khi lọc hai cột đầu bằng dấu bằng, sắp xếp theo ticker, timestamp.
             * Điều kiện RSI/CCI/ROC được lọc trong lát cắt ngày, chưa cần index từng chỉ báo.
             */
            $table->index(['resolution', 'trading_date', 'ticker', 'timestamp'], 'indicators_resolution_date_ticker_timestamp_index');

            /*
             * Hot path: lát cắt tất cả mã theo resolution và đúng timestamp,
             * hoặc khoảng timestamp sắp theo timestamp, ticker.
             * Lọc ngành/sàn bằng JOIN companies trên ticker; unique companies.ticker hỗ trợ JOIN.
             * Hai index lát cắt yêu cầu truyền resolution để tận dụng cột đầu.
             */
            $table->index(['resolution', 'timestamp', 'ticker'], 'indicators_resolution_timestamp_ticker_index');
        });
    }

    /** Xóa bảng và dữ liệu chỉ báo khi rollback; bảng giá nguồn không bị ảnh hưởng. */
    public function down(): void
    {
        Schema::dropIfExists('technical_indicators');
    }
};
