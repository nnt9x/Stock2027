<?php

namespace App\Integrations\Indicators;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class PythonIndicatorClient
{
    /**
     * Gọi API Python đồng bộ; chỉ công nhận thành công khi response xác nhận đã lưu xong.
     * HTTP retry do queue quản lý, không nhân số lần gọi trong cùng một job.
     *
     * @param  array<string, int|string|null>  $payload
     * @return array<string, mixed>
     */
    public function calculate(array $payload): array
    {
        $token = config('services.indicators.token');
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Chưa cấu hình INDICATOR_API_TOKEN.');
        }
        $result = Http::baseUrl(config('services.indicators.base_url'))->withToken($token)
            ->acceptJson()->connectTimeout(5)->timeout(60)
            ->post('/internal/indicators/calculate', $payload)->throw()->json();
        if (! is_array($result) || ($result['status'] ?? null) !== 'completed'
            || ($result['request_id'] ?? null) !== $payload['request_id']
            || ($result['ticker'] ?? null) !== $payload['ticker']
            || ($result['resolution'] ?? null) !== $payload['resolution']
            || ($result['until'] ?? null) !== $payload['until']
            || ! is_int($result['rows_processed'] ?? null) || $result['rows_processed'] < 0) {
            throw new RuntimeException('Python chưa xác nhận tính và lưu chỉ báo thành công.');
        }

        return $result;
    }
}
