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
    <x-card header="Pivot · Fibonacci">
        <div class="flex flex-col gap-3">
            <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                <span class="text-gray-500 dark:text-gray-400">Giá đóng cửa {{ $pivot['reference_date'] ?? '—' }}: <strong>{{ $pivot['reference_close'] === null ? '—' : number_format($pivot['reference_close'], 2) }}</strong></span>
                <span class="font-medium text-primary-600 dark:text-primary-400">Pivot Forward (F) · Tạm tính từ tháng hiện tại</span>
            </div>
            <x-table :headers="$pivotHeaders" :rows="$pivotRows" striped compact>
                @interact('column_level', $row)
                    <strong @class(['text-red-500' => str_starts_with($row['level'], 'R'), 'text-emerald-500' => str_starts_with($row['level'], 'S'), 'text-primary-600 dark:text-primary-400' => $row['level'] === 'PP'])>{{ $row['level'] }}</strong>
                @endinteract
                @foreach ($pivotHeaders as $column)
                    @if ($column['index'] !== 'level')
                        @php($slotName = 'column_'.$column['index'])
                        @interact($slotName, $row, $column, $pivot)
                            @php($value = $row[$column['index']])
                            @if ($value === null)
                                <span class="text-gray-400">—</span>
                            @elseif ($column['index'] === 'deviation')
                                <span @class(['whitespace-nowrap font-medium tabular-nums', 'text-emerald-500' => $value >= 0, 'text-red-500' => $value < 0])>{{ $value > 0 ? '+' : '' }}{{ number_format($value, 2) }}%</span>
                            @else
                                <span @class(['whitespace-nowrap font-medium tabular-nums', 'text-red-500' => $pivot['reference_close'] !== null && $value > $pivot['reference_close'], 'text-emerald-500' => $pivot['reference_close'] !== null && $value < $pivot['reference_close']])>{{ number_format($value, 2) }}</span>
                                @if ($column['index'] === 'forward' && $row['forward_deviation'] !== null)
                                    <span class="whitespace-nowrap text-xs tabular-nums">({{ $row['forward_deviation'] > 0 ? '+' : '' }}{{ number_format($row['forward_deviation'], 2) }}%)</span>
                                @endif
                            @endif
                        @endinteract
                    @endif
                @endforeach
            </x-table>
            <p class="text-xs text-gray-500 dark:text-gray-400">Độ lệch = (mức pivot / giá đóng cửa mới nhất − 1) × 100%. Cột (F) thay đổi khi có giá mới trong tháng, chưa phải pivot đã chốt.</p>
        </div>
    </x-card>
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
