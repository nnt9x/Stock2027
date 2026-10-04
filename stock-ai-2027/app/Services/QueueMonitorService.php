<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class QueueMonitorService
{
    /**
     * Đếm job theo queue database; reservation còn hạn chỉ cho biết worker đang giữ job.
     * Reservation hết hạn được tính là sẵn sàng retry, không khẳng định worker còn chạy.
     *
     * @return list<array{queue: string, total: int, ready: int, delayed: int, reserved: int, failed: int}>
     */
    public function counts(): array
    {
        $queues = ['ohlcv', 'indicators'];
        $now = now()->timestamp;
        $expired = $now - (int) config('queue.connections.database.retry_after');
        $jobs = DB::connection(config('queue.connections.database.connection'))
            ->table(config('queue.connections.database.table'))
            ->whereIn('queue', $queues)
            ->select('queue')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN (reserved_at IS NULL AND available_at <= ?) OR reserved_at <= ? THEN 1 ELSE 0 END) as ready', [$now, $expired])
            ->selectRaw('SUM(CASE WHEN reserved_at IS NULL AND available_at > ? THEN 1 ELSE 0 END) as delayed_count', [$now])
            ->selectRaw('SUM(CASE WHEN reserved_at > ? THEN 1 ELSE 0 END) as reserved', [$expired])
            ->groupBy('queue')->get()->keyBy('queue');
        $failed = DB::connection(config('queue.failed.database'))
            ->table(config('queue.failed.table'))->where('connection', 'database')
            ->whereIn('queue', $queues)->select('queue')->selectRaw('COUNT(*) as total')
            ->groupBy('queue')->pluck('total', 'queue');

        return array_map(function (string $queue) use ($jobs, $failed): array {
            $row = $jobs->get($queue);

            return ['queue' => $queue, 'total' => (int) ($row->total ?? 0),
                'ready' => (int) ($row->ready ?? 0), 'delayed' => (int) ($row->delayed_count ?? 0),
                'reserved' => (int) ($row->reserved ?? 0), 'failed' => (int) $failed->get($queue, 0)];
        }, $queues);
    }
}
