<?php

namespace App\Livewire\Companies;

use App\Models\Company;
use App\Models\Ohlcv;
use App\Services\OhlcvQueryService;
use App\Services\TechnicalIndicatorQueryService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Chart extends Component
{
    #[Locked]
    public string $ticker;

    #[Locked]
    public string $companyName;

    public string $selectedTicker = 'ACB';

    #[Validate('required|in:1D,1H')]
    public string $resolution = '1D';

    /** Nhận ticker chính xác; cho phép cả VNINDEX/VN30 dù không có công ty tương ứng. */
    public function mount(string $ticker = 'ACB'): void
    {
        $this->selectTicker($ticker);
        $this->selectedTicker = $this->ticker;
    }

    /** Đổi mã từ ô chọn tìm kiếm; chỉ nhận công ty còn hoạt động hoặc chỉ số hỗ trợ. */
    public function updatedSelectedTicker(): void
    {
        $ticker = strtoupper(trim($this->selectedTicker));
        if (! in_array($ticker, Ohlcv::MARKET_INDICES, true) && ! Company::where('ticker', $ticker)->exists()) {
            $this->addError('selectedTicker', 'Mã không tồn tại trong danh sách công ty.');
            $this->selectedTicker = $this->ticker;

            return;
        }
        $this->resetValidation('selectedTicker');
        $this->selectTicker($ticker);
        $this->selectedTicker = $ticker;
    }

    /** Cập nhật ticker chính xác và tên hiển thị; chỉ số không cần bản ghi Company. */
    private function selectTicker(string $ticker): void
    {
        $this->ticker = strtoupper(trim($ticker));
        if (in_array($this->ticker, Ohlcv::MARKET_INDICES, true)) {
            $this->companyName = $this->ticker;
        } else {
            $this->companyName = Company::where('ticker', $this->ticker)->firstOrFail()->organ_name;
        }
    }

    /** Kiểm tra khung trước khi đọc dữ liệu để không nhận giá trị tùy ý từ trình duyệt. */
    public function updatedResolution(): void
    {
        $this->validateOnly('resolution');
    }

    /** Ghép chỉ báo đã lưu theo timestamp của nến; thiếu kết quả giữ null, không tự tính ở UI. */
    public function render(OhlcvQueryService $prices, TechnicalIndicatorQueryService $indicators): View
    {
        $resolution = in_array($this->resolution, ['1D', '1H'], true) ? $this->resolution : '1D';
        $source = $prices->candles($this->ticker, $resolution);
        $values = $source->isEmpty() ? collect() : $indicators->between($this->ticker, $resolution,
            $source->first()->timestamp, $source->last()->timestamp)->keyBy('timestamp');
        $candles = $source->map(function (Ohlcv $candle) use ($resolution, $values): array {
            $indicator = $values->get($candle->timestamp);
            $fields = [];
            foreach (TechnicalIndicatorQueryService::CHART_FIELDS as $field) {
                $fields[$field] = $indicator?->$field === null ? null : (float) $indicator->$field;
            }

            return [
                'time' => $resolution === '1D' ? $candle->trading_date->format('Y-m-d') : $candle->timestamp,
                'open' => (float) $candle->open, 'high' => (float) $candle->high,
                'low' => (float) $candle->low, 'close' => (float) $candle->close, 'volume' => $candle->volume,
                'indicators' => $fields,
            ];
        })->all();

        $tickerOptions = Company::orderBy('ticker')->get(['ticker', 'organ_short_name'])
            ->map(fn (Company $company): array => ['value' => $company->ticker,
                'label' => $company->ticker.' · '.$company->organ_short_name])
            ->prepend(['value' => 'VN30', 'label' => 'VN30 · Chỉ số thị trường'])
            ->prepend(['value' => 'VNINDEX', 'label' => 'VNINDEX · Chỉ số thị trường'])->all();

        return view('livewire.companies.chart', ['candles' => $candles, 'tickerOptions' => $tickerOptions])
            ->layout('components.layouts.app', ['title' => 'Phân tích']);
    }
}
