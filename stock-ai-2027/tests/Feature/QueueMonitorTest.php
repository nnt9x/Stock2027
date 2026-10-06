<?php

namespace Tests\Feature;

use App\Jobs\CalculateIndicatorsJob;
use App\Jobs\SyncOhlcvJob;
use App\Livewire\Queues\Index;
use App\Models\Company;
use App\Models\OhlcvSyncState;
use Illuminate\Bus\BatchRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class QueueMonitorTest extends TestCase
{
    use RefreshDatabase;

    /** Nút giá tạo batch thật có callback và chặn nhấn lặp khi batch chưa hoàn tất. */
    public function test_price_button_dispatches_market_batch_once(): void
    {
        Queue::fake([SyncOhlcvJob::class]);
        Company::factory()->create(['ticker' => 'ACB']);
        Livewire::test(Index::class)->call('syncPrices')->call('syncPrices');
        Queue::assertPushed(SyncOhlcvJob::class, 6);
        $this->assertDatabaseCount('job_batches', 1);
        $batch = app(BatchRepository::class)->get()[0];
        $this->assertTrue($batch->hasFinallyCallbacks());
    }

    /** Nút chỉ báo dùng chuỗi giá đã lưu, không phát sinh job tải giá. */
    public function test_indicator_button_dispatches_stored_prices(): void
    {
        Queue::fake([CalculateIndicatorsJob::class, SyncOhlcvJob::class]);
        OhlcvSyncState::create(['ticker' => 'ACB', 'resolution' => '1D', 'synced_through_timestamp' => 1704272400,
            'reload_version' => 0, 'completed_reload_version' => 0]);
        Livewire::test(Index::class)->call('calculateIndicators');
        Queue::assertPushed(CalculateIndicatorsJob::class, fn ($job) => $job->ticker === 'ACB' && $job->queue === 'indicators');
        Queue::assertNotPushed(SyncOhlcvJob::class);
        $this->assertDatabaseCount('job_batches', 1);
    }

    /** Không tạo thêm batch chỉ báo khi queue còn việc hoặc không có chuỗi đủ điều kiện. */
    public function test_indicator_button_rejects_busy_or_empty_queue(): void
    {
        Livewire::test(Index::class)->call('calculateIndicators');
        $this->assertDatabaseCount('job_batches', 0);
        DB::table('jobs')->insert(['queue' => 'ohlcv', 'payload' => '{}', 'attempts' => 0,
            'reserved_at' => null, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp]);
        Livewire::test(Index::class)->call('calculateIndicators');
        $this->assertDatabaseCount('job_batches', 0);
    }

    /** Phân loại queue theo thời điểm, bỏ queue khác và đọc lại DB khi refresh. */
    public function test_queue_counts_and_live_refresh(): void
    {
        $this->freezeTime();
        $now = now()->timestamp;
        $expired = $now - (int) config('queue.connections.database.retry_after');
        foreach ([['ohlcv', null, $now], ['ohlcv', null, $now + 60],
            ['ohlcv', $now, $now], ['ohlcv', $expired, $now],
            ['indicators', null, $now], ['default', null, $now]] as [$queue, $reserved, $available]) {
            DB::table('jobs')->insert(['queue' => $queue, 'payload' => '{}', 'attempts' => 0,
                'reserved_at' => $reserved, 'available_at' => $available, 'created_at' => $now]);
        }
        DB::table('failed_jobs')->insert(['uuid' => 'failed-indicator', 'connection' => 'database',
            'queue' => 'indicators', 'payload' => '{}', 'exception' => 'Test failure', 'failed_at' => now()]);
        $page = Livewire::test(Index::class)->assertViewHas('queues', [
            ['queue' => 'ohlcv', 'total' => 4, 'ready' => 2, 'delayed' => 1, 'reserved' => 1, 'failed' => 0],
            ['queue' => 'indicators', 'total' => 1, 'ready' => 1, 'delayed' => 0, 'reserved' => 0, 'failed' => 1],
        ]);
        DB::table('jobs')->where('queue', 'indicators')->delete();
        $page->call('$refresh')->assertViewHas('queues', fn ($rows) => $rows[1]['total'] === 0 && $rows[1]['failed'] === 1);
        $this->get(route('queues.index'))->assertOk()->assertSee('Giám sát queue');
    }
}
