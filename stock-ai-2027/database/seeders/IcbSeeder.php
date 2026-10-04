<?php

namespace Database\Seeders;

use App\Models\Icb;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;

class IcbSeeder extends Seeder
{
    /**
     * Đọc mục icbs trong file export phpMyAdmin và kiểm tra dữ liệu trước khi ghi.
     * Upsert theo code để chạy lại không trùng, giữ số 0 đầu mã và trạng thái xoá mềm.
     * Không lấy ID hay timestamps của bản export và không xoá ngành bổ sung ngoài file.
     */
    public function run(): void
    {
        $export = json_decode(File::get(database_path('icbs.json')), true, 512, JSON_THROW_ON_ERROR);
        $table = collect($export)->first(fn ($entry): bool => is_array($entry)
            && ($entry['type'] ?? null) === 'table' && ($entry['name'] ?? null) === 'icbs');
        $rows = $table['data'] ?? [];

        Validator::make(['rows' => $rows], [
            'rows' => ['required', 'array', 'list', 'min:1'],
            'rows.*.code' => ['required', 'string', 'max:255', 'distinct:strict'],
            'rows.*.name' => ['required', 'string', 'max:255'],
        ])->validate();

        $timestamp = now();
        $records = array_map(fn (array $row): array => [
            'code' => $row['code'],
            'name' => $row['name'],
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ], $rows);

        Icb::withTrashed()->upsert($records, ['code'], ['name', 'updated_at']);
    }
}
