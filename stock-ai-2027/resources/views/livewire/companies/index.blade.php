<div class="flex flex-col gap-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <x-button icon="arrow-path" wire:click="sync" wire:loading.attr="disabled" wire:target="sync">
            <span wire:loading.remove wire:target="sync">Đồng bộ từ SSI</span>
            <span wire:loading wire:target="sync">Đang đồng bộ…</span>
        </x-button>
    </div>

    @if ($successMessage)
        <div role="status"><x-alert color="green" :text="$successMessage" /></div>
    @endif
    @if ($errorMessage)
        <div role="alert"><x-alert color="red" :text="$errorMessage" /></div>
    @endif

    <x-card>
        <div class="flex flex-col gap-5">
            <div class="grid gap-4 md:grid-cols-3">
                <x-input label="Mã cổ phiếu" placeholder="Nhập ticker, ví dụ: FPT" icon="magnifying-glass" wire:model.live.debounce.300ms="search" />
                <x-select.native label="Sàn giao dịch" wire:model.live="exchange">
                    <option value="">Tất cả sàn</option>
                    @foreach ($exchanges as $code)
                        <option value="{{ $code }}">{{ ['VNINDEX' => 'HOSE', 'HNXIndex' => 'HNX', 'UpcomIndex' => 'UPCoM'][$code] ?? $code }}</option>
                    @endforeach
                </x-select.native>
                <x-select.native label="Ngành ICB" wire:model.live="industry">
                    <option value="">Tất cả ngành</option>
                    @foreach ($industries as $code)
                        <option value="{{ $code }}">{{ $code }}</option>
                    @endforeach
                </x-select.native>
            </div>

            <p class="text-sm text-gray-500 dark:text-gray-400">{{ number_format($companies->total()) }} kết quả</p>
            <x-table :headers="[
                ['index' => 'ticker', 'label' => 'Ticker', 'sortable' => false],
                ['index' => 'organ_name', 'label' => 'Tên doanh nghiệp', 'sortable' => false],
                ['index' => 'organ_short_name', 'label' => 'Tên viết tắt', 'sortable' => false],
                ['index' => 'com_group_code', 'label' => 'Sàn', 'sortable' => false],
                ['index' => 'icb_code', 'label' => 'ICB', 'sortable' => false],
            ]" :rows="$companies" striped loading paginate>
                @interact('column_com_group_code', $row)
                    {{ ['VNINDEX' => 'HOSE', 'HNXIndex' => 'HNX', 'UpcomIndex' => 'UPCoM'][$row->com_group_code] ?? $row->com_group_code }}
                @endinteract
                <x-slot:empty>
                    {{ $totalCompanies === 0 ? 'Chưa có dữ liệu. Bấm Đồng bộ từ SSI để tải danh sách công ty.' : 'Không tìm thấy công ty phù hợp với bộ lọc.' }}
                </x-slot:empty>
            </x-table>
        </div>
    </x-card>
</div>
