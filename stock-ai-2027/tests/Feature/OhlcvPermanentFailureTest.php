<?php

namespace Tests\Feature;

use App\Jobs\SyncOhlcvJob;
use App\Services\IndicatorBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OhlcvPermanentFailureTest extends TestCase
{
    use RefreshDatabase;

    /** Mã nguồn không nhận kết thúc ngay và callback vẫn chạy khi batch có lỗi. */
    public function test_invalid_symbol_fails_immediately_and_finishes_batch(): void
    {
        config(['queue.default' => 'database']);
        Http::fake(['*' => Http::response(['status' => 400, 'code' => 'BAD_REQUEST', 'message' => 'invalid symbol'], 400)]);
        $this->mock(IndicatorBatchService::class)->shouldReceive('dispatchForPriceBatch')->once()->andReturnNull();
        $batch = Bus::batch([new SyncOhlcvJob('CTC', '1D', now()->timestamp)])
            ->onQueue('ohlcv')->allowFailures()->finally(static function ($batch): void {
                app(IndicatorBatchService::class)->dispatchForPriceBatch($batch->id);
            })->dispatch();
        $this->artisan('queue:work', ['--queue' => 'ohlcv', '--once' => true])->assertSuccessful();
        Http::assertSentCount(1);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertSame(1, $batch->fresh()->failedJobs);
        $this->assertFalse($batch->fresh()->cancelled());
        $this->assertStringContainsString('invalid symbol', DB::table('ohlcv_sync_states')->value('last_error'));
    }

    /** Lỗi tạm thời phía DNSE vẫn giữ job để retry, không bị xem là mã không hợp lệ. */
    public function test_server_error_remains_retryable(): void
    {
        config(['queue.default' => 'database']);
        Http::fake(['*' => Http::response(['message' => 'Unavailable'], 503)]);
        SyncOhlcvJob::dispatch('ACB', '1D', now()->timestamp);
        $this->artisan('queue:work', ['--queue' => 'ohlcv', '--once' => true])->assertSuccessful();
        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseCount('failed_jobs', 0);
        $job = DB::table('jobs')->first();
        $this->assertSame(1, $job->attempts);
        $this->assertGreaterThan(now()->timestamp, $job->available_at);
    }
}
