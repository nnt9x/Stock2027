<?php

namespace App\Services;

use App\Models\Icb;
use Illuminate\Database\Eloquent\Builder;

class IcbQueryService
{
    /**
     * Tạo truy vấn danh mục ngành đang hoạt động, sắp xếp theo code và chưa phân trang.
     * Giữ code dạng chuỗi để các mã có số 0 đầu được tra cứu chính xác.
     *
     * @return Builder<Icb>
     */
    public function query(): Builder
    {
        $query = Icb::query();
        $query->orderBy('code');

        return $query;
    }

    /**
     * Tìm ngành theo code chính xác; ném ModelNotFoundException nếu thiếu hoặc đã xoá mềm.
     */
    public function findByCode(string $code): Icb
    {
        return $this->query()->where('code', trim($code))->firstOrFail();
    }
}
