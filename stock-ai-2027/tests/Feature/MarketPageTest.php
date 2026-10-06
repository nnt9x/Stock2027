<?php

namespace Tests\Feature;

use App\Livewire\Market\Index;
use App\Models\Ohlcv;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MarketPageTest extends TestCase
{
    use RefreshDatabase;

    /** Chuyển ngày/giờ đọc đúng chuỗi và định dạng trục; từ chối khung tùy ý. */
    public function test_market_resolution_switch(): void
    {
        Ohlcv::factory()->create(['ticker' => 'VNINDEX', 'resolution' => '1D', 'trading_date' => '2024-01-02', 'close' => 1200]);
        Ohlcv::factory()->create(['ticker' => 'VNINDEX', 'resolution' => '1H', 'timestamp' => 1704160800, 'close' => 1210]);
        Livewire::test(Index::class)->assertSet('resolution', '1D')
            ->assertViewHas('candles', fn ($rows) => count($rows) === 1 && $rows[0]['time'] === '2024-01-02' && $rows[0]['close'] === 1200.0)
            ->set('resolution', '1H')
            ->assertViewHas('candles', fn ($rows) => count($rows) === 1 && $rows[0]['time'] === 1704160800 && $rows[0]['close'] === 1210.0)
            ->set('resolution', 'invalid')->assertHasErrors('resolution');
    }

    /** Trang dùng đúng VNINDEX dù không có Company, không lấy pivot của mã khác. */
    public function test_market_page_shows_vnindex_pivots_and_forward(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-05 12:00', 'Asia/Ho_Chi_Minh'));
        foreach (['VNINDEX' => 1300, 'ACB' => 25] as $ticker => $close) {
            Ohlcv::factory()->create([
                'ticker' => $ticker, 'resolution' => '1D', 'trading_date' => '2026-09-30',
                'timestamp' => CarbonImmutable::parse('2026-09-30', 'Asia/Ho_Chi_Minh')->timestamp,
                'high' => $close + 10, 'low' => $close - 10, 'close' => $close, 'volume' => 100,
            ]);
        }
        Ohlcv::factory()->create([
            'ticker' => 'VNINDEX', 'resolution' => '1D', 'trading_date' => '2026-10-02',
            'timestamp' => CarbonImmutable::parse('2026-10-02', 'Asia/Ho_Chi_Minh')->timestamp,
            'high' => 1330, 'low' => 1310, 'close' => 1320, 'volume' => 100,
        ]);

        $this->get(route('market.index'))->assertOk()->assertSee('VNINDEX')
            ->assertSee('Pivot · Fibonacci')->assertSee('1,300.00')->assertSee('1,320.00')
            ->assertSee('(F) 11-2026')->assertSee('R4')->assertSee('S4')
            ->assertSee('stockPriceChart(')->assertSee('Biểu đồ giá và khối lượng VNINDEX')
            ->assertDontSee('Mã cổ phiếu');
    }

    /** Chưa có dữ liệu vẫn mở trang được và giữ các mức thiếu dưới dạng dấu gạch. */
    public function test_market_page_without_prices(): void
    {
        $this->get(route('market.index'))->assertOk()->assertSee('Pivot · Fibonacci')->assertSee('—')
            ->assertSee('Chưa có dữ liệu giá VNINDEX');
        $this->assertDatabaseCount('ohlcvs', 0);
    }
}
