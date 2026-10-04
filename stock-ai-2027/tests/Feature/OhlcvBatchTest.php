<?php

namespace Tests\Feature;

use App\Jobs\SyncOhlcvJob;
use App\Models\Company;
use App\Models\Ohlcv;
use App\Models\OhlcvSyncState;
use App\Services\OhlcvBatchService;
use App\Services\OhlcvSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class OhlcvBatchTest extends TestCase
{
    use RefreshDatabase;

    /** Lượt toàn thị trường tạo batch thật và hai job cho mỗi công ty đang hoạt động. */
    public function test_market_run_creates_batch_and_rejects_overlapping_run(): void
    {
        Queue::fake([SyncOhlcvJob::class]);
        Company::factory()->create(['ticker' => 'NAB']);
        Company::factory()->create(['ticker' => 'ACB']);
        Company::factory()->create(['ticker' => 'OLD'])->delete();
        $service = app(OhlcvBatchService::class);
        $batch = $service->dispatch();
        $this->assertSame(8, $batch->totalJobs);
        $this->assertTrue($batch->allowsFailures());
        Queue::assertPushed(SyncOhlcvJob::class, 8);
        Queue::assertPushed(SyncOhlcvJob::class, fn ($job) => $job->batchId === $batch->id && $job->queue === 'ohlcv');
        try {
            $service->dispatch('NAB', true);
            $this->fail('Phải chặn lượt chạy chồng nhau.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($batch->id, $exception->getMessage());
        }
        $this->assertDatabaseCount('job_batches', 1);
        $this->assertDatabaseCount('ohlcv_sync_states', 0);
        $this->artisan('ohlcv:batch', ['id' => $batch->id, '--cancel' => true])->assertSuccessful();
        $this->assertTrue(Bus::findBatch($batch->id)->cancelled());
        $next = $service->dispatch('NAB');
        $this->assertSame(2, $next->totalJobs);
    }

    /** Chỉ số không cần công ty tương ứng và full reload vẫn đánh dấu cả ngày lẫn giờ. */
    public function test_market_indices_can_sync_without_company_records(): void
    {
        Queue::fake([SyncOhlcvJob::class]);
        foreach (['VNINDEX', 'VN30'] as $ticker) {
            $batch = app(OhlcvBatchService::class)->dispatch($ticker, true);
            $this->assertSame(2, $batch->totalJobs);
            Queue::assertPushed(SyncOhlcvJob::class, fn ($job) => $job->ticker === $ticker && $job->resolution === '1D');
            Queue::assertPushed(SyncOhlcvJob::class, fn ($job) => $job->ticker === $ticker && $job->resolution === '1H');
            $this->assertDatabaseHas('ohlcv_sync_states', ['ticker' => $ticker, 'resolution' => '1D', 'reload_version' => 1]);
            $batch->cancel();
        }
        $this->assertDatabaseCount('companies', 0);
    }

    /** Batch cập nhật tiến độ sau mỗi chuỗi và hoàn tất khi cả hai resolution đã tải xong. */
    public function test_batch_finishes_only_after_backfill_completes(): void
    {
        config(['queue.default' => 'database']);
        $this->travelTo(CarbonImmutable::parse('2024-02-02', 'Asia/Ho_Chi_Minh'));
        Company::factory()->create(['ticker' => 'NAB']);
        Http::preventStrayRequests();
        Http::fake(['api.dnse.com.vn/*' => Http::response(['t' => [], 'o' => [], 'h' => [], 'l' => [], 'c' => [], 'v' => []])]);
        $batch = app(OhlcvBatchService::class)->dispatch('NAB');
        $this->artisan('queue:work database --queue=ohlcv --once --sleep=0')->assertSuccessful();
        $this->assertSame(1, $batch->fresh()->pendingJobs);
        $this->assertFalse($batch->fresh()->finished());
        $this->artisan('queue:work database --queue=ohlcv --once --sleep=0')->assertSuccessful();
        $this->assertSame(0, $batch->fresh()->pendingJobs);
        $this->assertSame(100, $batch->fresh()->progress());
        $this->assertTrue($batch->fresh()->finished());
        Http::assertSentCount(2);
    }

    /** Batch đã hủy không gọi nguồn hay thay đổi dữ liệu khi worker nhận job. */
    public function test_cancelled_batch_skips_external_calls(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.dnse.com.vn/*' => Http::response([])]);
        [$job] = (new SyncOhlcvJob('NAB', '1D', now()->timestamp))->withFakeBatch(cancelledAt: CarbonImmutable::now());
        $job->handle(app(OhlcvSyncService::class));
        Http::assertNothingSent();
        $this->assertDatabaseCount('ohlcv_sync_states', 0);
    }

    /** Resolution kia tải lại trong job hiện có, không tăng tổng job của batch. */
    public function test_partner_reload_runs_without_adding_jobs(): void
    {
        $until = now()->timestamp;
        OhlcvSyncState::create(['ticker' => 'NAB', 'resolution' => '1D', 'synced_through_timestamp' => $until]);
        OhlcvSyncState::create(['ticker' => 'NAB', 'resolution' => '1H', 'reload_version' => 1, 'synced_through_timestamp' => $until]);
        $service = $this->mock(OhlcvSyncService::class);
        $service->shouldReceive('syncStep')->once()->with('NAB', '1H', $until)->andReturn(false);
        [$job, $batch] = (new SyncOhlcvJob('NAB', '1D', $until))->withFakeBatch();
        $job->handle($service);
        $this->assertCount(0, $batch->added);
    }

    /** Job phát hiện chia giá cập nhật cả ngày/giờ ngay, kể cả job giờ đã hoàn tất trước đó. */
    public function test_adjustment_reloads_both_resolutions_in_same_job_without_release(): void
    {
        $this->travelTo(CarbonImmutable::parse('2024-01-10 17:00', 'Asia/Ho_Chi_Minh'));
        $until = now()->timestamp;
        $timestamp = CarbonImmutable::parse('2024-01-09 09:00', 'Asia/Ho_Chi_Minh')->timestamp;
        foreach (['1D', '1H'] as $resolution) {
            Ohlcv::factory()->create(['resolution' => $resolution, 'timestamp' => $timestamp, 'trading_date' => '2024-01-09']);
            OhlcvSyncState::create(['ticker' => 'NAB', 'resolution' => $resolution,
                'synced_through_timestamp' => $resolution === '1H' ? $until : $timestamp]);
        }
        Http::preventStrayRequests();
        Http::fake(['api.dnse.com.vn/chart-api/v2/ohlcs/stock*' => Http::response([
            't' => [$timestamp], 'o' => [5], 'h' => [6], 'l' => [4], 'c' => [5], 'v' => [100],
        ])]);
        [$job, $batch] = (new SyncOhlcvJob('NAB', '1D', $until))->withFakeBatch(totalJobs: 2, pendingJobs: 1);
        $job->handle(app(OhlcvSyncService::class));
        foreach (['1D', '1H'] as $resolution) {
            $this->assertDatabaseHas('ohlcv_sync_states', ['ticker' => 'NAB', 'resolution' => $resolution,
                'reload_version' => 1, 'completed_reload_version' => 1, 'synced_through_timestamp' => $until]);
            $this->assertSame('5.000000', Ohlcv::where('resolution', $resolution)->firstOrFail()->close);
        }
        Http::assertSentCount(3);
        $this->assertCount(0, $batch->added);
        $this->assertSame(2, $batch->totalJobs);
    }
}
