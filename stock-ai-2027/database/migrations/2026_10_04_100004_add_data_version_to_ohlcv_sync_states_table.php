<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Phiên bản giá giúp Python kiểm tra nguồn chưa thay đổi trước khi ghi chỉ báo. */
    public function up(): void
    {
        Schema::table('ohlcv_sync_states', function (Blueprint $table) {
            $table->unsignedBigInteger('data_version')->default(0)->comment('Tăng sau mỗi lần lưu giá thành công');
        });
    }

    /** Xóa cột phiên bản; không ảnh hưởng dữ liệu giá hay chỉ báo. */
    public function down(): void
    {
        Schema::table('ohlcv_sync_states', fn (Blueprint $table) => $table->dropColumn('data_version'));
    }
};
