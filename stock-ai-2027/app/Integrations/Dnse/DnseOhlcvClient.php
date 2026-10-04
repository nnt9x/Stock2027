<?php

namespace App\Integrations\Dnse;

use App\Models\Ohlcv;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DnseOhlcvClient
{
    /**
     * Lấy nến trong khoảng Unix giây [from, to); giữ nguyên giá nguồn, kiểm tra trước khi lưu.
     * VNINDEX/VN30 dùng endpoint index; các mã cổ phiếu dùng endpoint stock.
     * Giữ nguyên OHLC kể cả close nằm ngoài khoảng low/high trong dữ liệu nguồn.
     *
     * @return list<array<string, int|string>>
     */
    public function candles(string $ticker, string $resolution, int $from, int $to): array
    {
        $type = in_array($ticker, Ohlcv::MARKET_INDICES, true) ? 'index' : 'stock';
        $data = Http::baseUrl(config('services.dnse.base_url'))->acceptJson()
            ->withHeaders(['Origin' => 'https://banggia.dnse.com.vn', 'Referer' => 'https://banggia.dnse.com.vn/'])
            ->connectTimeout(5)->timeout(20)
            ->get('/chart-api/v2/ohlcs/'.$type, ['symbol' => $ticker, 'resolution' => $resolution, 'from' => $from, 'to' => $to - 1])
            ->throw()->json();

        foreach (['t', 'o', 'h', 'l', 'c', 'v'] as $key) {
            if (! is_array($data[$key] ?? null) || ! array_is_list($data[$key]) || count($data[$key]) !== count($data['t'])) {
                throw new RuntimeException('DNSE trả về các mảng nến không hợp lệ.');
            }
        }

        if (count($data['t']) !== count(array_unique($data['t']))) {
            throw new RuntimeException('DNSE trả về timestamp trùng.');
        }

        $rows = [];
        foreach ($data['t'] as $i => $timestamp) {
            if (! is_int($timestamp)) {
                throw new RuntimeException('Timestamp DNSE phải là Unix giây.');
            }
            foreach (['o', 'h', 'l', 'c', 'v'] as $key) {
                if (! is_numeric($data[$key][$i]) || ! is_finite((float) $data[$key][$i]) || $data[$key][$i] < 0) {
                    throw new RuntimeException('Giá hoặc khối lượng DNSE không hợp lệ.');
                }
            }
            if (floor((float) $data['v'][$i]) !== (float) $data['v'][$i]) {
                throw new RuntimeException('Khối lượng DNSE phải là số nguyên.');
            }
            if ($timestamp < $from || $timestamp >= $to) {
                continue;
            }
            $rows[] = ['timestamp' => $timestamp, 'open' => number_format((float) $data['o'][$i], 6, '.', ''),
                'high' => number_format((float) $data['h'][$i], 6, '.', ''), 'low' => number_format((float) $data['l'][$i], 6, '.', ''),
                'close' => number_format((float) $data['c'][$i], 6, '.', ''), 'volume' => (int) $data['v'][$i]];
        }

        return $rows;
    }
}
