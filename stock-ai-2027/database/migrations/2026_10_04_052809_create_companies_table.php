<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            // Mã cổ phiếu
            $table->string('ticker')->unique();
            // Sàn giao dịch
            $table->string('com_group_code');
            // Mã ngành ICB
            $table->string('icb_code');
            // Tên doanh nghiệp
            $table->string('organ_name');
            // Tên viết tắt doanh nghiệp
            $table->string('organ_short_name');
            // Thời gian
            $table->timestamps();
            // Cho phép xóa mềm
            $table->softDeletes();

            // Thống kê theo ngành ICB và mã cổ phiếu
            $table->index(['icb_code', 'deleted_at', 'ticker']);
            // Thống kê theo sàn giao dịch và mã cổ phiếu
            $table->index(['com_group_code', 'deleted_at', 'ticker']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
