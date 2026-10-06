<?php

namespace Tests\Feature;

use App\Livewire\Companies\Chart;
use App\Models\Company;
use App\Models\Ohlcv;
use App\Services\FibonacciPivotService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FibonacciPivotTest extends TestCase
{
    use RefreshDatabase;

    /** Dùng tháng trước để chốt pivot; forward dùng tháng đang mở, không trộn nến tương lai. */
    public function test_monthly_and_provisional_forward(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-04 12:00', 'Asia/Ho_Chi_Minh'));
        Company::factory()->create(['ticker' => 'ACB']);
        foreach ([['2026-09-01', 30, 20, 24], ['2026-09-30', 28, 21, 25],
            ['2026-10-02', 27, 23, 26], ['2026-10-05', 999, 1, 999]] as [$date, $high, $low, $close]) {
            Ohlcv::factory()->create(['ticker' => 'ACB', 'resolution' => '1D', 'trading_date' => $date,
                'timestamp' => CarbonImmutable::parse($date, 'Asia/Ho_Chi_Minh')->timestamp,
                'high' => $high, 'low' => $low, 'close' => $close, 'volume' => 100]);
        }
        Ohlcv::factory()->create(['ticker' => 'ACB', 'resolution' => '1H', 'trading_date' => '2026-10-02', 'high' => 999]);
        $result = app(FibonacciPivotService::class)->monthly('acb');
        $this->assertEquals(25, $result['months']['2026-10']['PP']);
        $this->assertEqualsWithDelta(28.82, $result['months']['2026-10']['R1'], 0.000001);
        $this->assertEqualsWithDelta(8.82, $result['months']['2026-10']['S4'], 0.000001);
        $this->assertNull($result['months']['2026-09']);
        $this->assertSame('2026-11', $result['forward_month']);
        $this->assertTrue($result['provisional']);
        $this->assertEqualsWithDelta(76 / 3, $result['forward_levels']['PP'], 0.000001);
        $this->assertSame(26.0, $result['reference_close']);
        $this->assertSame('2026-10-02', $result['reference_date']);
        Livewire::test(Chart::class)->assertSee('Pivot · Fibonacci')->assertSee('(F) 11-2026')
            ->assertSee('R4')->assertSee('PP')->assertSee('S4')
            ->set('resolution', '1H')->assertViewHas('pivot', fn ($pivot) => $pivot['months']['2026-10']['PP'] === 25.0);
    }

    /** Không có tháng nguồn không tạo pivot giả hoặc ghi thêm bất kỳ dữ liệu nào. */
    public function test_missing_source_month(): void
    {
        $result = app(FibonacciPivotService::class)->monthly('UNKNOWN');
        $this->assertNull($result['forward_levels']);
        $this->assertNull($result['reference_close']);
        $this->assertCount(5, $result['months']);
        $this->assertDatabaseCount('ohlcvs', 0);
    }
}
