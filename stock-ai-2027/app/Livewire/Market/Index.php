<?php

namespace App\Livewire\Market;

use App\Models\Ohlcv;
use App\Services\FibonacciPivotService;
use App\Services\OhlcvQueryService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Index extends Component
{
    #[Validate('required|in:1D,1H')]
    public string $resolution = '1D';

    /** Chỉ nhận khung ngày/giờ hỗ trợ trước khi đọc nến từ DB. */
    public function updatedResolution(): void
    {
        $this->validateOnly('resolution');
    }

    /** Hiển thị nến VNINDEX theo khung chọn; pivot luôn dùng dữ liệu ngày để tính tháng. */
    public function render(FibonacciPivotService $pivots, OhlcvQueryService $prices): View
    {
        $resolution = in_array($this->resolution, ['1D', '1H'], true) ? $this->resolution : '1D';
        $candles = $prices->candles('VNINDEX', $resolution)->map(fn (Ohlcv $candle): array => [
            'time' => $resolution === '1D' ? $candle->trading_date->format('Y-m-d') : $candle->timestamp,
            'open' => (float) $candle->open, 'high' => (float) $candle->high,
            'low' => (float) $candle->low, 'close' => (float) $candle->close, 'volume' => $candle->volume,
        ])->all();

        return view('livewire.market.index', ['pivot' => $pivots->monthly('VNINDEX'), 'candles' => $candles])
            ->layout('components.layouts.app', ['title' => 'VNINDEX']);
    }
}
