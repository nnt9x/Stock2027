<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['ticker', 'com_group_code', 'icb_code', 'organ_name', 'organ_short_name'])]
class Company extends Model
{
    /**
     * Hỗ trợ tạo dữ liệu bằng factory và xoá mềm công ty.
     *
     * @use HasFactory<CompanyFactory>
     */
    use HasFactory, SoftDeletes;

    /**
     * Lấy ngành của công ty bằng companies.icb_code → icbs.code.
     * Trả về null khi mã chưa có trong danh mục hoặc ngành đã xoá mềm.
     * Quan hệ Eloquent này không tạo ràng buộc khoá ngoại trong database.
     *
     * @return BelongsTo<Icb, $this>
     */
    public function icb(): BelongsTo
    {
        return $this->belongsTo(Icb::class, 'icb_code', 'code');
    }
}
