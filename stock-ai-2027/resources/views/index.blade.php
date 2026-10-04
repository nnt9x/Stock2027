<x-layouts.app title="Trang chủ">
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-2">
            <p class="text-sm font-medium text-primary-600 dark:text-primary-400">STOCKAI 2027</p>
            <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Chào mừng đến với StockAI</h1>
            <p class="text-gray-500 dark:text-gray-400">Không gian theo dõi thị trường và phân tích cổ phiếu của bạn.</p>
        </div>

        <div class="grid gap-6 md:grid-cols-3">
            <x-card title="Thị trường" icon="chart-bar">
                <p class="text-sm text-gray-500 dark:text-gray-400">Theo dõi diễn biến thị trường và các chỉ số chính.</p>
            </x-card>
            <x-card title="Danh mục" icon="briefcase">
                <p class="text-sm text-gray-500 dark:text-gray-400">Quản lý danh mục và những cổ phiếu bạn quan tâm.</p>
            </x-card>
            <x-card title="Phân tích AI" icon="sparkles">
                <p class="text-sm text-gray-500 dark:text-gray-400">Khám phá thông tin và góc nhìn hỗ trợ nghiên cứu đầu tư.</p>
            </x-card>
        </div>

        <x-card title="Bắt đầu" subtitle="Trang chủ StockAI">
            <div class="flex flex-col gap-3">
                <p class="text-gray-600 dark:text-gray-300">Chưa có dữ liệu thị trường. Các tính năng theo dõi và phân tích sẽ được bổ sung tại đây.</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">Sử dụng nút trên thanh tiêu đề để thu gọn menu hoặc chuyển giao diện sáng/tối.</p>
            </div>
        </x-card>
    </div>
</x-layouts.app>
