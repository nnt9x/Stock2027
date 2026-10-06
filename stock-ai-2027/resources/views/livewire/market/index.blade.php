<div class="flex flex-col gap-6">
    <div class="flex items-end justify-between gap-3">
        <h1 class="text-xl font-bold">VNINDEX</h1>
        <div class="w-36">
            <x-select.styled label="Khung" wire:model.live="resolution" required
                :options="[['label' => '1D · Ngày', 'value' => '1D'], ['label' => '1H · Giờ', 'value' => '1H']]"
                select="label:label|value:value" />
        </div>
    </div>

    @include('livewire.partials.fibonacci-pivot', ['pivot' => $pivot])

    <x-card :header="'VNINDEX · '.$resolution">
        @if ($candles === [])
            <p class="py-16 text-center text-gray-500 dark:text-gray-400">Chưa có dữ liệu giá VNINDEX. Cần đồng bộ giá trước.</p>
        @else
            <div wire:key="market-chart-{{ $resolution }}" x-data="stockPriceChart(@js($candles), @js($resolution), { showIndicators: false })" class="flex flex-col gap-3">
                <div class="flex flex-wrap gap-3 text-sm tabular-nums" aria-live="polite">
                    <span x-text="legend.date"></span>
                    <span>O <strong x-text="legend.open"></strong></span>
                    <span>H <strong x-text="legend.high"></strong></span>
                    <span>L <strong x-text="legend.low"></strong></span>
                    <span>C <strong x-text="legend.close"></strong></span>
                    <span>KL <strong x-text="legend.volume"></strong></span>
                </div>
                <div wire:ignore x-ref="canvas" class="h-[520px] w-full" role="img" aria-label="Biểu đồ giá và khối lượng VNINDEX"></div>
            </div>
        @endif
    </x-card>
</div>
