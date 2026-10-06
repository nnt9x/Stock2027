<div class="flex flex-col gap-6" wire:poll.5s.visible>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-gray-500 dark:text-gray-400">Tự cập nhật mỗi 5 giây · Cập nhật lúc {{ $updatedAt }} (giờ Việt Nam)</p>
        <x-button icon="arrow-path" wire:click="$refresh" wire:loading.attr="disabled">Làm mới</x-button>
    </div>
    <x-card header="Đồng bộ toàn thị trường">
        <div class="flex flex-col gap-3">
            <div class="flex flex-wrap gap-3">
                <x-button icon="arrow-path" wire:click="syncPrices" wire:loading.attr="disabled" wire:target="syncPrices,calculateIndicators">Đồng bộ giá + chỉ báo</x-button>
                <x-button outline icon="calculator" wire:click="calculateIndicators" wire:loading.attr="disabled" wire:target="syncPrices,calculateIndicators">Tính chỉ báo từ giá đã lưu</x-button>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400">Toàn bộ công ty đang hoạt động và VNINDEX/VN30 · Khung 1D và 1H. Đồng bộ giá tăng dần; tự tải lại và tính lại khi phát hiện giá điều chỉnh. Batch chỉ báo tự tạo sau khi batch giá hoàn tất.</p>
            <p class="text-sm text-gray-500 dark:text-gray-400">Các nút tạo job; cần worker queue ohlcv/indicators và dịch vụ Python đang chạy để xử lý.</p>
        </div>
    </x-card>
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
