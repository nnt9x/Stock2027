<div class="flex flex-col gap-6" wire:poll.5s.visible>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-gray-500 dark:text-gray-400">Tự cập nhật mỗi 5 giây · Cập nhật lúc {{ $updatedAt }} (giờ Việt Nam)</p>
        <x-button icon="arrow-path" wire:click="$refresh" wire:loading.attr="disabled">Làm mới</x-button>
    </div>
    <x-card title="Job giá và chỉ báo" subtitle="Thống kê từ database queue">
        <div class="flex flex-col gap-4">
            <x-table :headers="[
                ['index' => 'queue', 'label' => 'Queue', 'sortable' => false],
                ['index' => 'total', 'label' => 'Còn trong queue', 'sortable' => false],
                ['index' => 'ready', 'label' => 'Sẵn sàng chạy', 'sortable' => false],
                ['index' => 'delayed', 'label' => 'Chờ đến giờ chạy', 'sortable' => false],
                ['index' => 'reserved', 'label' => 'Worker đang giữ', 'sortable' => false],
                ['index' => 'failed', 'label' => 'Thất bại đã lưu', 'sortable' => false],
            ]" :rows="$queues" striped loading />
            <p class="text-sm text-gray-500 dark:text-gray-400">ohlcv: đồng bộ giá · indicators: tính chỉ báo. Tổng còn trong queue gồm sẵn sàng, chờ đến giờ chạy và worker đang giữ.</p>
            <p class="text-sm text-gray-500 dark:text-gray-400">Worker đang giữ chưa chắc vẫn đang xử lý. Khi reservation hết hạn, job được tính là sẵn sàng retry. Job thất bại được đếm riêng từ failed_jobs, không nằm trong tổng queue.</p>
        </div>
    </x-card>
</div>
