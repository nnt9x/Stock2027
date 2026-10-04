<?php

namespace App\Services;

use App\Exceptions\CompanySyncInProgressException;
use App\Integrations\Ssi\SsiCompanyClient;
use App\Models\Company;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CompanySyncService
{
    /**
     * Nhận client SSI qua dependency injection, tách kết nối bên ngoài khỏi nghiệp vụ đồng bộ.
     */
    public function __construct(private SsiCompanyClient $client) {}

    /**
     * Đồng bộ danh sách SSI theo ticker, thêm mới hoặc cập nhật dữ liệu trong transaction.
     * Gọi API trước khi mở transaction để tránh giữ khoá database trong lúc chờ mạng.
     * Giữ ID, created_at và trạng thái xoá mềm; không xoá công ty vắng trong nguồn.
     * Khoá cache ngăn chạy trùng và luôn được giải phóng khi hoàn tất hoặc phát sinh lỗi.
     * Trả số bản ghi nguồn đã xử lý, không phải số bản ghi mới được thêm.
     *
     * @throws CompanySyncInProgressException Khi có một lần đồng bộ khác đang chạy.
     */
    public function sync(): int
    {
        $lock = Cache::lock('companies:sync', 120);

        if (! $lock->get()) {
            throw new CompanySyncInProgressException('Đang có một lần đồng bộ khác chạy. Vui lòng thử lại sau.');
        }

        try {
            $items = $this->client->organizations();
            $timestamp = now();
            $rows = array_map(fn (array $item): array => [
                'ticker' => mb_strtoupper(trim($item['ticker'])),
                'com_group_code' => $item['comGroupCode'],
                'icb_code' => $item['icbCode'],
                'organ_name' => $item['organName'],
                'organ_short_name' => $item['organShortName'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ], $items);

            DB::transaction(function () use ($rows): void {
                foreach (array_chunk($rows, 500) as $batch) {
                    Company::withTrashed()->upsert($batch, ['ticker'], [
                        'com_group_code', 'icb_code', 'organ_name', 'organ_short_name', 'updated_at',
                    ]);
                }
            });

            return count($rows);
        } finally {
            $lock->release();
        }
    }
}
