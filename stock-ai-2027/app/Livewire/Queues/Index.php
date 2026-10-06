<?php

namespace App\Livewire\Queues;

use App\Services\IndicatorBatchService;
use App\Services\OhlcvBatchService;
use App\Services\QueueMonitorService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use RuntimeException;
use TallStackUi\Traits\Interactions;
use Throwable;

class Index extends Component
{
    use Interactions;

    /** Tạo lượt incremental toàn thị trường; callback batch giá tự tạo lượt chỉ báo tiếp theo. */
    public function syncPrices(OhlcvBatchService $service): void
    {
        try {
            $batch = $service->dispatch();
            $this->toast()->success('Đã tạo batch giá '.$batch->id.' với '.number_format($batch->totalJobs).' job. Chỉ báo sẽ tự chạy sau khi giá hoàn tất.')->send();
        } catch (RuntimeException $exception) {
            $this->toast()->error($exception->getMessage())->send();
        } catch (Throwable $exception) {
            report($exception);
            $this->toast()->error('Không thể tạo batch đồng bộ giá lúc này.')->send();
        }
    }

    /** Tính từ checkpoint giá đã lưu; khóa thao tác và chặn khi queue giá/chỉ báo còn việc. */
    public function calculateIndicators(IndicatorBatchService $service, QueueMonitorService $queues): void
    {
        try {
            Cache::lock('queues:dispatch-indicators', 120)->block(5, function () use ($service, $queues): void {
                foreach ($queues->counts() as $queue) {
                    if ($queue['total'] > 0) {
                        throw new RuntimeException('Queue giá hoặc chỉ báo còn job. Hãy chờ lượt hiện tại hoàn tất.');
                    }
                }
                $batch = $service->dispatchStored();
                $this->toast()->success('Đã tạo batch chỉ báo '.$batch->id.' với '.number_format($batch->totalJobs).' job từ giá đã lưu.')->send();
            });
        } catch (RuntimeException $exception) {
            $this->toast()->error($exception->getMessage())->send();
        } catch (Throwable $exception) {
            report($exception);
            $this->toast()->error('Không thể tạo batch chỉ báo lúc này.')->send();
        }
    }

    /** Lấy thống kê mới từ DB mỗi lần render, bao gồm polling và làm mới thủ công. */
    public function render(QueueMonitorService $queues): View
    {
        return view('livewire.queues.index', [
            'queues' => $queues->counts(),
            'updatedAt' => now('Asia/Ho_Chi_Minh')->format('d/m/Y H:i:s'),
        ])->layout('components.layouts.app', ['title' => 'Giám sát queue']);
    }
}
