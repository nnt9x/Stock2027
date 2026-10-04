<?php

namespace App\Livewire\Queues;

use App\Services\QueueMonitorService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Index extends Component
{
    /** Lấy thống kê mới từ DB mỗi lần render, bao gồm polling và làm mới thủ công. */
    public function render(QueueMonitorService $queues): View
    {
        return view('livewire.queues.index', [
            'queues' => $queues->counts(),
            'updatedAt' => now('Asia/Ho_Chi_Minh')->format('d/m/Y H:i:s'),
        ])->layout('components.layouts.app', ['title' => 'Giám sát queue']);
    }
}
