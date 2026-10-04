<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

class CompanyQueryService
{
    /**
     * Tạo truy vấn công ty dùng chung cho API và Livewire; chưa thực thi hay phân trang.
     * Tìm ticker chính xác sau khi bỏ khoảng trắng và chuyển thành chữ hoa.
     * LEFT JOIN lấy tên ngành, vẫn giữ công ty khi thiếu ICB hoặc ngành đã xoá mềm.
     * Điều kiện xoá mềm ICB đặt trong JOIN để không loại công ty khỏi kết quả.
     *
     * @return Builder<Company>
     */
    public function query(string $search = '', string $exchange = '', string $industry = ''): Builder
    {
        $ticker = mb_strtoupper(trim($search));

        $query = Company::query();
        $query->leftJoin('icbs', function (JoinClause $join): void {
            $join->on('companies.icb_code', '=', 'icbs.code')->whereNull('icbs.deleted_at');
        });
        $query->select('companies.*', 'icbs.name as icb_name');

        if ($ticker !== '') {
            $query->where('companies.ticker', $ticker);
        }

        if ($exchange !== '') {
            $query->where('companies.com_group_code', $exchange);
        }

        if ($industry !== '') {
            $query->where('companies.icb_code', $industry);
        }

        $query->orderBy('companies.ticker');

        return $query;
    }

    /**
     * Lấy một công ty đang hoạt động theo ticker chính xác, kèm tên ngành nếu có.
     * Ném ModelNotFoundException khi không tìm thấy để tầng HTTP xử lý trả về 404.
     */
    public function findByTicker(string $ticker): Company
    {
        return $this->query()->where('companies.ticker', mb_strtoupper(trim($ticker)))->firstOrFail();
    }
}
