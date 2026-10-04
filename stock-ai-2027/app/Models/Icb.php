<?php

namespace App\Models;

use Database\Factories\IcbFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'name'])]
class Icb extends Model
{
    /**
     * Hỗ trợ tạo dữ liệu bằng factory và xoá mềm ngành ICB.
     *
     * @use HasFactory<IcbFactory>
     */
    use HasFactory, SoftDeletes;

    /**
     * Lấy các công ty có companies.icb_code trùng icbs.code của ngành này.
     * Không bao gồm công ty đã xoá mềm; trả về danh sách rỗng nếu chưa có công ty.
     *
     * @return HasMany<Company, $this>
     */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class, 'icb_code', 'code');
    }
}
