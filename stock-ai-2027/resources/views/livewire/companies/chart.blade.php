<div class="flex flex-col gap-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0">
            <h1 class="text-xl font-bold">{{ $ticker }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $companyName }}</p>
        </div>
        <div class="grid grid-cols-[minmax(0,1fr)_120px] items-start gap-3 lg:w-[440px] lg:shrink-0">
            <x-select.styled label="Mã cổ phiếu" placeholder="Tìm và chọn mã" wire:model.live="selectedTicker"
                :options="$tickerOptions" select="label:label|value:value" searchable required :lazy="20" />
            <x-select.styled label="Khung" wire:model.live="resolution" required
                :options="[['label' => '1D · Ngày', 'value' => '1D'], ['label' => '1H · Giờ', 'value' => '1H']]"
                select="label:label|value:value" />
        </div>
    </div>
    <x-card header="ROC · Phiên giao dịch">
        <div class="flex flex-col gap-3">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
                @foreach ($rocPeriods as $period)
                    @php($value = $roc?->{'roc_'.$period})
                    <div class="flex flex-col gap-1">
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $period }} phiên</span>
                        <strong @class(['tabular-nums', 'text-gray-400' => $value === null, 'text-emerald-500' => $value !== null && $value > 0, 'text-red-500' => $value !== null && $value < 0])>{{ $value === null ? '—' : ($value > 0 ? '+' : '').number_format((float) $value, 2).'%' }}</strong>
                    </div>
                @endforeach
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">Khung ngày · {{ $roc?->trading_date->format('d/m/Y') ?? 'Chưa có dữ liệu chỉ báo' }} · ROC = (close / close N phiên trước − 1) × 100%. Chưa đủ phiên hiển thị —.</p>
        </div>
    </x-card>
    @include('livewire.partials.fibonacci-pivot', ['pivot' => $pivot])
    <x-card>
        @if ($candles === [])
            <p class="py-16 text-center text-gray-500 dark:text-gray-400">Chưa có dữ liệu giá cho mã và khung này. Cần đồng bộ giá trước.</p>
        @else
            <div wire:key="chart-{{ $ticker }}-{{ $resolution }}" x-data="stockPriceChart(@js($candles), @js($resolution))" class="flex flex-col gap-3">
                <div class="flex flex-wrap gap-2">
                    @foreach ([5, 10, 20, 50, 100, 150, 200] as $period)
                        <x-button xs outline x-on:click="toggleAverage('sma_{{ $period }}')" x-bind:aria-pressed="enabled.sma_{{ $period }}">
                            <span x-bind:class="{ 'opacity-40': !enabled.sma_{{ $period }} }">SMA{{ $period }}</span>
                        </x-button>
                    @endforeach
                </div>
                <div class="flex flex-wrap gap-3 text-sm tabular-nums" aria-live="polite">
                    <span x-text="legend.date"></span>
                    <span>O <strong x-text="legend.open"></strong></span>
                    <span>H <strong x-text="legend.high"></strong></span>
                    <span>L <strong x-text="legend.low"></strong></span>
                    <span>C <strong x-text="legend.close"></strong></span>
                </div>
                <div class="relative">
                    <div wire:ignore x-ref="canvas" class="h-[1100px] w-full" role="img" aria-label="Biểu đồ giá, khối lượng và chỉ báo {{ $ticker }}"></div>
                    <div class="pointer-events-none absolute inset-0 z-20 overflow-hidden">
                        <template x-for="panel in panels" :key="panel.title">
                            <div class="absolute left-2 right-20 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs tabular-nums" :style="{ top: panel.top + 'px' }">
                                <strong x-show="panel.title" class="rounded bg-white/90 px-1 text-gray-900 dark:bg-gray-950/90 dark:text-gray-100" x-text="panel.title"></strong>
                                <template x-for="item in panel.items" :key="item.label">
                                    <span x-show="item.visible" :style="{ color: item.color }"><span x-show="item.showLabel" x-text="item.label"></span> <strong x-text="item.value"></strong></span>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        @endif
    </x-card>
</div>
