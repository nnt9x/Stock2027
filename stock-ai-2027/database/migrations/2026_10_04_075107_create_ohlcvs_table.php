<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng nến dùng chung cho dữ liệu ngày/giờ và backfill lịch sử.
     * Timestamp giữ nguyên Unix giây; trading_date theo Asia/Ho_Chi_Minh.
     * Các index lát cắt thị trường yêu cầu lọc theo resolution.
     */
    public function up(): void
    {
        Schema::create('ohlcvs', function (Blueprint $table) {
            $table->id();
            $table->string('ticker');
            $table->string('resolution', 10);
            $table->date('trading_date');
            $table->unsignedBigInteger('timestamp');
            $table->decimal('open', 20, 6);
            $table->decimal('high', 20, 6);
            $table->decimal('low', 20, 6);
            $table->decimal('close', 20, 6);
            $table->unsignedBigInteger('volume');
            $table->timestamps();

            /*
             * Mỗi ticker chỉ có một nến cho cùng resolution và timestamp;
             * đồng bộ hằng ngày hoặc backfill có thể upsert mà không tạo bản ghi trùng.
             * Hot path: ticker = NAB, resolution = 1H, timestamp >= đầu và < cuối,
             * ORDER BY timestamp; cũng dùng để lấy nến mới nhất theo timestamp DESC.
             * Khi lọc một mã theo ngày, đổi ngày Việt Nam sang khoảng Unix giây
             * để dùng index này, không cần thêm index ticker + trading_date.
             */
            $table->unique(['ticker', 'resolution', 'timestamp']);

            /*
             * Hot path: lấy tất cả mã tại một ngày giao dịch, ví dụ
             * resolution = 1D AND trading_date = 2024-01-02, ORDER BY ticker.
             * Với 1H, lấy các nến trong ngày và sắp xếp theo ticker, timestamp.
             * Khi hai cột đầu được lọc bằng dấu bằng, hai cột sau hỗ trợ thứ tự này.
             */
            $table->index(['resolution', 'trading_date', 'ticker', 'timestamp']);

            /*
             * Hot path: lấy lát cắt tất cả mã tại đúng một timestamp,
             * resolution = 1H AND timestamp = thời điểm cần xem, ORDER BY ticker.
             * Cũng hỗ trợ khoảng timestamp của toàn thị trường trong một resolution,
             * ORDER BY timestamp, ticker. Unique index phía trên bắt đầu bằng ticker
             * nên không thay thế index này cho truy vấn không lọc một mã cụ thể.
             * Cả hai index lát cắt đều bắt đầu bằng resolution: cần truyền resolution
             * để tận dụng cột đầu index và tránh trộn dữ liệu nến ngày với nến giờ.
             */
            $table->index(['resolution', 'timestamp', 'ticker']);
        });
    }

    /**
     * Xóa bảng nến khi rollback; dữ liệu giá đã lưu sẽ bị xóa cùng bảng.
     */
    public function down(): void
    {
        Schema::dropIfExists('ohlcvs');
    }
};
