<?php

namespace Tests\Feature;

use App\Livewire\Companies\Chart;
use App\Models\Company;
use App\Models\Ohlcv;
use App\Models\TechnicalIndicator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CompanyChartTest extends TestCase
{
    use RefreshDatabase;

    /** ROC dùng bản ghi ngày mới nhất đúng mã, giữ dấu/zero/null và không đổi sang ROC giờ. */
    public function test_daily_roc_summary_follows_selected_ticker(): void
    {
        Company::factory()->create(['ticker' => 'ACB']);
        Company::factory()->create(['ticker' => 'FPT']);
        TechnicalIndicator::create(['ticker' => 'ACB', 'resolution' => '1D',
            'timestamp' => 1704186000, 'trading_date' => '2024-01-02', 'roc_5' => 99]);
        TechnicalIndicator::create(['ticker' => 'ACB', 'resolution' => '1D',
            'timestamp' => 1704272400, 'trading_date' => '2024-01-03',
            'roc_5' => 2.5, 'roc_10' => -3.25, 'roc_20' => 0]);
        TechnicalIndicator::create(['ticker' => 'ACB', 'resolution' => '1H',
            'timestamp' => 1704358800, 'trading_date' => '2024-01-04', 'roc_5' => 888]);
        TechnicalIndicator::create(['ticker' => 'FPT', 'resolution' => '1D',
            'timestamp' => 1704272400, 'trading_date' => '2024-01-03', 'roc_5' => 7.5]);

        Livewire::test(Chart::class)->assertSee('+2.50%')->assertSee('-3.25%')->assertSee('0.00%')
            ->assertSee('03/01/2024')->assertSee('200 phiên')
            ->assertViewHas('roc', fn ($roc) => $roc->roc_200 === null)
            ->set('resolution', '1H')->assertSee('+2.50%')->assertDontSee('888.00%')
            ->set('selectedTicker', 'FPT')->assertSee('+7.50%')->assertDontSee('+2.50%');
    }

    /** Ghép đúng chỉ báo với nến cùng mã/khung/timestamp, giữ null và số 0 đúng nghĩa. */
    public function test_chart_maps_stored_indicators_and_missing_values(): void
    {
        Company::factory()->create(['ticker' => 'ACB']);
        $candle = Ohlcv::factory()->create(['ticker' => 'ACB', 'resolution' => '1D']);
        Ohlcv::factory()->create(['ticker' => 'ACB', 'resolution' => '1D',
            'timestamp' => $candle->timestamp + 86400, 'trading_date' => '2024-01-03']);
        TechnicalIndicator::create(['ticker' => 'ACB', 'resolution' => '1D',
            'timestamp' => $candle->timestamp, 'trading_date' => $candle->trading_date,
            'sma_20' => 21.25, 'rsi_14' => 0, 'obv' => -100, 'cci_20_ma10' => 42.5]);
        TechnicalIndicator::create(['ticker' => 'FPT', 'resolution' => '1D',
            'timestamp' => $candle->timestamp, 'trading_date' => $candle->trading_date, 'sma_20' => 999]);
        TechnicalIndicator::create(['ticker' => 'ACB', 'resolution' => '1H',
            'timestamp' => $candle->timestamp, 'trading_date' => $candle->trading_date, 'sma_20' => 888]);
        Livewire::test(Chart::class)->assertViewHas('candles', function ($rows): bool {
            return count($rows) === 2 && $rows[0]['indicators']['sma_20'] === 21.25
                && $rows[0]['indicators']['rsi_14'] === 0.0 && $rows[0]['indicators']['obv'] === -100.0
                && $rows[0]['indicators']['cci_20_ma10'] === 42.5 && $rows[0]['indicators']['rs_line'] === null
                && $rows[1]['indicators']['sma_20'] === null;
        });
    }

    /** Trang phân tích mặc định ACB, đổi mã tải đúng nến và từ chối mã đã xóa mềm. */
    public function test_analysis_defaults_to_acb_and_selects_another_ticker(): void
    {
        Company::factory()->create(['ticker' => 'ACB']);
        Company::factory()->create(['ticker' => 'FPT']);
        Company::factory()->create(['ticker' => 'OLD'])->delete();
        Ohlcv::factory()->create(['ticker' => 'FPT', 'resolution' => '1D', 'close' => 100]);
        $this->get(route('analysis.index'))->assertOk()->assertSee('Phân tích');
        Livewire::test(Chart::class)->assertSet('ticker', 'ACB')->assertSet('selectedTicker', 'ACB')
            ->set('selectedTicker', 'FPT')->assertSet('ticker', 'FPT')
            ->assertViewHas('candles', fn ($rows) => count($rows) === 1 && $rows[0]['close'] === 100.0)
            ->set('selectedTicker', 'OLD')->assertHasErrors('selectedTicker')->assertSet('ticker', 'FPT')
            ->set('selectedTicker', 'VNINDEX')->assertHasNoErrors()->assertSet('ticker', 'VNINDEX');
    }

    /** Đọc đúng ticker/khung, sắp tăng thời gian; nến ngày dùng trading_date Việt Nam. */
    public function test_price_volume_and_resolution_switch(): void
    {
        Company::factory()->create(['ticker' => 'ACB']);
        Ohlcv::factory()->create(['ticker' => 'ACB', 'resolution' => '1D', 'timestamp' => 1704186000,
            'trading_date' => '2024-01-02', 'open' => 20, 'high' => 22, 'low' => 19, 'close' => 21, 'volume' => 1234]);
        Ohlcv::factory()->create(['ticker' => 'ACB', 'resolution' => '1H', 'timestamp' => 1704160800]);
        Ohlcv::factory()->create(['ticker' => 'FPT', 'resolution' => '1D']);
        Livewire::test(Chart::class, ['ticker' => 'acb'])
            ->assertViewHas('candles', fn ($rows) => count($rows) === 1 && $rows[0]['time'] === '2024-01-02' && $rows[0]['close'] === 21.0 && $rows[0]['volume'] === 1234)
            ->set('resolution', '1H')->assertViewHas('candles', fn ($rows) => count($rows) === 1 && $rows[0]['time'] === 1704160800)
            ->set('resolution', 'bad')->assertHasErrors(['resolution']);
        $this->get(route('companies.chart', ['ticker' => 'ACB']))->assertOk();
        $this->get(route('companies.chart', ['ticker' => 'UNKNOWN']))->assertNotFound();
    }

    /** Chỉ số mở được dù không có Company; thiếu giá hiển thị trạng thái rỗng rõ ràng. */
    public function test_index_chart_without_company(): void
    {
        Livewire::test(Chart::class, ['ticker' => 'VNINDEX'])->assertSee('Chưa có dữ liệu giá');
    }
}
