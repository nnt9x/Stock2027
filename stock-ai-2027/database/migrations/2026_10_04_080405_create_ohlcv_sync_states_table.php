<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Theo dõi từng chuỗi nến, kể cả khoảng không có giao dịch và backfill bị gián đoạn.
     */
    public function up(): void
    {
        Schema::create('ohlcv_sync_states', function (Blueprint $table) {
            $table->id();
            $table->string('ticker');
            $table->string('resolution', 10);
            $table->unsignedBigInteger('synced_through_timestamp')->nullable();
            $table->unsignedBigInteger('reload_through_timestamp')->nullable();
            $table->unsignedBigInteger('reload_version')->default(0);
            $table->unsignedBigInteger('completed_reload_version')->default(0);
            $table->timestamp('last_success_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['ticker', 'resolution']);
        });
    }

    /**
     * Xóa trạng thái tiến độ khi rollback, không xóa nến giá.
     */
    public function down(): void
    {
        Schema::dropIfExists('ohlcv_sync_states');
    }
};
