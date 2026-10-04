<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Lưu checkpoint chỉ báo cùng state giá, không tạo bảng trạng thái riêng. */
    public function up(): void
    {
        Schema::table('ohlcv_sync_states', function (Blueprint $table) {
            $table->json('indicator_state')->nullable()->comment('Checkpoint RSI/OBV, cửa sổ và phiên bản lịch sử giá');
        });
    }

    /** Bỏ checkpoint; lần tính sau tự khởi tạo lại từ lịch sử giá. */
    public function down(): void
    {
        Schema::table('ohlcv_sync_states', fn (Blueprint $table) => $table->dropColumn('indicator_state'));
    }
};
