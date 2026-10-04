<?php

namespace Tests\Feature;

use App\Jobs\SyncOhlcvJob;
use App\Models\Company;
use App\Models\Ohlcv;
use App\Models\OhlcvSyncState;
use App\Services\OhlcvSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class OhlcvSyncTest extends TestCase
{
    use RefreshDatabase;

    /** Đồng bộ mới từ mốc đầu và lưu ngày Việt Nam, không tạo nến trùng khi tải lại. */
    public function test_initial_sync_and_idempotent_overlap(): void
    {
        $this->travelTo(CarbonImmutable::parse('2024-01-03 17:00', 'Asia/Ho_Chi_Minh'));
        Http::preventStrayRequests();
        Http::fake(['api.dnse.com.vn/*' => Http::response($this->payload([$this->ts('2024-01-02 09:00')]))]);
        $service = app(OhlcvSyncService::class);
        $this->assertFalse($service->syncStep('NAB', '1D', now()->timestamp));
        $this->assertFalse($service->syncStep('NAB', '1D', now()->timestamp));
        $this->assertDatabaseCount('ohlcvs', 1);
        $this->assertDatabaseHas('ohlcvs', ['ticker' => 'NAB', 'trading_date' => '2024-01-02', 'volume' => 100]);
        Http::assertSent(fn (Request $request) => $request['from'] === $this->ts('2024-01-01'));
    }

    /** Overlap lấy 5 phiên có dữ liệu, không nhầm cuối tuần thành phiên giao dịch. */
    public function test_overlap_five_sessions_detects_adjustment_and_reloads_both_resolutions(): void
    {
        $this->travelTo(CarbonImmutable::parse('2024-01-10 17:00', 'Asia/Ho_Chi_Minh'));
        foreach (['2024-01-02', '2024-01-03', '2024-01-04', '2024-01-05', '2024-01-08', '2024-01-09'] as $date) {
            Ohlcv::factory()->create(['trading_date' => $date, 'timestamp' => $this->ts($date.' 09:00')]);
        }
        OhlcvSyncState::create(['ticker' => 'NAB', 'resolution' => '1D', 'synced_through_timestamp' => $this->ts('2024-01-09 17:00')]);
        Http::preventStrayRequests();
        Http::fake(['api.dnse.com.vn/*' => Http::response($this->payload([$this->ts('2024-01-03 09:00')], 5))]);
        $service = app(OhlcvSyncService::class);
        $this->assertFalse($service->syncStep('NAB', '1D', now()->timestamp));
        Http::assertSent(fn (Request $request) => $request['from'] === $this->ts('2024-01-03'));
        foreach (['1D', '1H'] as $resolution) {
            $this->assertDatabaseHas('ohlcv_sync_states', ['ticker' => 'NAB', 'resolution' => $resolution, 'reload_version' => 1]);
        }
        Http::assertSent(fn (Request $request) => $request['from'] === $this->ts('2024-01-01'));
        Http::assertSentCount(2);
        $this->assertDatabaseHas('ohlcv_sync_states', ['ticker' => 'NAB', 'resolution' => '1D', 'completed_reload_version' => 1]);
        $this->assertSame('5.000000', Ohlcv::where('timestamp', $this->ts('2024-01-03 09:00'))->firstOrFail()->close);
    }

    /** Backfill tải một request đến hiện tại; payload lỗi không được tăng tiến độ. */
    public function test_full_range_sync_and_invalid_payload_does_not_advance(): void
    {
        $this->travelTo(CarbonImmutable::parse('2024-04-01', 'Asia/Ho_Chi_Minh'));
        Http::preventStrayRequests();
        Http::fake(['api.dnse.com.vn/*' => Http::sequence()->push($this->payload([]))->push(['t' => [1], 'o' => []])->push($this->payload([]))]);
        $service = app(OhlcvSyncService::class);
        $this->assertFalse($service->syncStep('NAB', '1H', now()->timestamp));
        $cursor = now()->timestamp;
        $this->assertDatabaseHas('ohlcv_sync_states', ['synced_through_timestamp' => $cursor]);
        Http::assertSent(fn (Request $request) => $request['from'] === $this->ts('2024-01-01') && $request['to'] === $cursor - 1);
        $this->travel(1)->days();
        try {
            $service->syncStep('NAB', '1H', now()->timestamp);
            $this->fail('Phải từ chối payload thiếu dữ liệu.');
        } catch (RuntimeException $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }
        $state = OhlcvSyncState::firstOrFail();
        $this->assertSame($cursor, $state->synced_through_timestamp);
        $this->assertNotNull($state->last_error);
        $service->syncStep('NAB', '1H', now()->timestamp);
        $this->assertNull($state->fresh()->last_error);
        Http::assertSentCount(3);
    }

    /** Nến đang giao dịch và thay đổi volume không gây tải lại toàn bộ lịch sử. */
    public function test_live_candle_changes_do_not_trigger_full_reload(): void
    {
        $this->travelTo(CarbonImmutable::parse('2024-01-02 11:00', 'Asia/Ho_Chi_Minh'));
        Ohlcv::factory()->create();
        Http::preventStrayRequests();
        Http::fake(['api.dnse.com.vn/*' => Http::response($this->payload([$this->ts('2024-01-02 09:00')], 5))]);
        $this->assertFalse(app(OhlcvSyncService::class)->syncStep('NAB', '1D', now()->timestamp));
        $this->assertSame(0, OhlcvSyncState::firstOrFail()->reload_version);
        $this->assertSame('5.000000', Ohlcv::firstOrFail()->close);
    }

    /** Yêu cầu full đến khi HTTP đang chạy không bị kết quả cũ ghi đè hoặc xác nhận hoàn tất. */
    public function test_new_reload_request_survives_in_flight_response(): void
    {
        $this->travelTo(CarbonImmutable::parse('2024-01-03', 'Asia/Ho_Chi_Minh'));
        $service = app(OhlcvSyncService::class);
        $service->requestFullReload('NAB');
        Http::preventStrayRequests();
        Http::fake(['api.dnse.com.vn/*' => function () use ($service) {
            $service->requestFullReload('NAB');

            return Http::response($this->payload([]));
        }]);
        $this->assertTrue($service->syncStep('NAB', '1D', now()->timestamp));
        $this->assertDatabaseHas('ohlcv_sync_states', ['ticker' => 'NAB', 'resolution' => '1D', 'reload_version' => 2,
            'completed_reload_version' => 0, 'reload_through_timestamp' => null]);
        Http::assertSentCount(1);
    }

    /** Command full đánh dấu hai chuỗi và dispatch các job với cùng đích thời gian. */
    public function test_command_dispatches_both_resolutions(): void
    {
        Company::factory()->create(['ticker' => 'NAB']);
        Queue::fake([SyncOhlcvJob::class]);
        $this->artisan('ohlcv:sync --ticker=NAB --full')->assertSuccessful();
        Queue::assertPushed(SyncOhlcvJob::class, 2);
        Queue::assertPushed(SyncOhlcvJob::class, fn ($job) => $job->ticker === 'NAB' && $job->resolution === '1H' && $job->queue === 'ohlcv');
        $this->assertDatabaseHas('ohlcv_sync_states', ['ticker' => 'NAB', 'resolution' => '1H', 'reload_version' => 1]);
    }

    /** Mã ngừng giao dịch vẫn tiến về hiện tại, không kẹt ở 5 phiên cũ đã kiểm tra. */
    public function test_empty_period_after_old_candles_keeps_advancing(): void
    {
        $this->travelTo(CarbonImmutable::parse('2024-04-01', 'Asia/Ho_Chi_Minh'));
        Ohlcv::factory()->create();
        OhlcvSyncState::create(['ticker' => 'NAB', 'resolution' => '1D', 'synced_through_timestamp' => $this->ts('2024-03-01')]);
        Http::preventStrayRequests();
        Http::fake(['api.dnse.com.vn/*' => Http::response($this->payload([]))]);
        $service = app(OhlcvSyncService::class);
        $this->assertFalse($service->syncStep('NAB', '1D', now()->timestamp));
        $this->assertDatabaseHas('ohlcv_sync_states', ['synced_through_timestamp' => now()->timestamp]);
        Http::assertSent(fn (Request $request) => $request['from'] === $this->ts('2024-01-02'));
    }

    /** Volume lịch sử thay đổi chỉ cập nhật nến, không coi là điều chỉnh giá. */
    public function test_volume_change_does_not_trigger_full_reload(): void
    {
        $this->travelTo(CarbonImmutable::parse('2024-01-03', 'Asia/Ho_Chi_Minh'));
        Ohlcv::factory()->create(['volume' => 50]);
        Http::preventStrayRequests();
        Http::fake(['api.dnse.com.vn/*' => Http::response($this->payload([$this->ts('2024-01-02 09:00')]))]);
        app(OhlcvSyncService::class)->syncStep('NAB', '1D', now()->timestamp);
        $this->assertSame(0, OhlcvSyncState::firstOrFail()->reload_version);
        $this->assertSame(100, Ohlcv::firstOrFail()->volume);
        Http::assertSentCount(1);
    }

    /** Ngày giao dịch đổi ở 17:00 UTC tức 00:00 Việt Nam, độc lập timezone ứng dụng. */
    public function test_trading_date_uses_vietnam_timezone_at_utc_day_boundary(): void
    {
        config(['app.timezone' => 'UTC']);
        $this->travelTo(CarbonImmutable::parse('2024-01-03 12:00', 'UTC'));
        $beforeMidnight = CarbonImmutable::parse('2024-01-01 16:59:59', 'UTC')->timestamp;
        $midnight = CarbonImmutable::parse('2024-01-01 17:00:00', 'UTC')->timestamp;
        Http::preventStrayRequests();
        Http::fake(['api.dnse.com.vn/*' => Http::response($this->payload([$beforeMidnight, $midnight]))]);

        app(OhlcvSyncService::class)->syncStep('NAB', '1H', now()->timestamp);

        $this->assertDatabaseHas('ohlcvs', ['timestamp' => $beforeMidnight, 'trading_date' => '2024-01-01']);
        $this->assertDatabaseHas('ohlcvs', ['timestamp' => $midnight, 'trading_date' => '2024-01-02']);
        Http::assertSentCount(1);
    }

    /** Chỉ số dùng endpoint index và lưu được giá dù không có bản ghi công ty. */
    public function test_market_indices_use_index_endpoint(): void
    {
        $this->travelTo(CarbonImmutable::parse('2024-01-03', 'Asia/Ho_Chi_Minh'));
        Http::preventStrayRequests();
        Http::fake(['api.dnse.com.vn/chart-api/v2/ohlcs/index*' => Http::response($this->payload([$this->ts('2024-01-02 09:15')], 1100))]);
        foreach (['VNINDEX', 'VN30'] as $ticker) {
            app(OhlcvSyncService::class)->syncStep($ticker, '1D', now()->timestamp);
            $this->assertDatabaseHas('ohlcvs', ['ticker' => $ticker, 'trading_date' => '2024-01-02']);
        }
        Http::assertSentCount(2);
        $this->assertDatabaseCount('companies', 0);
    }

    /** Giữ nguyên giá nguồn khi close nằm ngoài low/high, không chặn đồng bộ cả chuỗi. */
    public function test_source_ohlc_is_saved_even_when_close_is_outside_high_low(): void
    {
        $this->travelTo(CarbonImmutable::parse('2024-01-03', 'Asia/Ho_Chi_Minh'));
        $timestamp = $this->ts('2024-01-02 09:00');
        Http::preventStrayRequests();
        Http::fake(['api.dnse.com.vn/chart-api/v2/ohlcs/index*' => Http::response([
            't' => [$timestamp], 'o' => [1710.48], 'h' => [1711.03],
            'l' => [1705.74], 'c' => [1702.93], 'v' => [175808448],
        ])]);
        $this->assertFalse(app(OhlcvSyncService::class)->syncStep('VNINDEX', '1H', now()->timestamp));
        $candle = Ohlcv::where('ticker', 'VNINDEX')->firstOrFail();
        $this->assertSame('1702.930000', $candle->close);
        $this->assertSame('1705.740000', $candle->low);
        $this->assertSame('1711.030000', $candle->high);
        Http::assertSentCount(1);
    }

    /** Chuẩn hóa thời điểm mẫu theo múi giờ giao dịch. */
    private function ts(string $date): int
    {
        return CarbonImmutable::parse($date, 'Asia/Ho_Chi_Minh')->timestamp;
    }

    /** Payload mẫu đúng hợp đồng mảng song song của DNSE. */
    private function payload(array $timestamps, int $price = 10): array
    {
        $count = count($timestamps);

        return ['t' => $timestamps, 'o' => array_fill(0, $count, $price), 'h' => array_fill(0, $count, $price + 1),
            'l' => array_fill(0, $count, $price - 1), 'c' => array_fill(0, $count, $price), 'v' => array_fill(0, $count, 100)];
    }
}
