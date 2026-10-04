<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Lưu nội dung ghi chú; key unique giữ một tài liệu chung cho trang chủ. */
    public function up(): void
    {
        Schema::create('project_notes', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->comment('Định danh ghi chú, trang chủ dùng project');
            $table->longText('content')->comment('Nội dung Markdown từ editor');
            $table->timestamps();
        });
    }

    /** Xóa bảng ghi chú khi rollback migration. */
    public function down(): void
    {
        Schema::dropIfExists('project_notes');
    }
};
