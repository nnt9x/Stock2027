<?php

namespace Tests\Feature;

use App\Livewire\Queues\Index;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class QueueMonitorTest extends TestCase
{
    use RefreshDatabase;

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
