@php
        $pivotHeaders = [['index' => 'level', 'label' => 'Mức', 'sortable' => false]];
        foreach (array_keys($pivot['months']) as $index => $month) {
            $pivotHeaders[] = ['index' => 'month_'.$index, 'label' => substr($month, 5).'-'.substr($month, 0, 4), 'sortable' => false, 'align' => 'right'];
        }
        $pivotHeaders[] = ['index' => 'deviation', 'label' => 'Độ lệch', 'sortable' => false, 'align' => 'right'];
        $pivotHeaders[] = ['index' => 'forward', 'label' => '(F) '.substr($pivot['forward_month'], 5).'-'.substr($pivot['forward_month'], 0, 4), 'sortable' => false, 'align' => 'right'];
        $pivotRows = [];
        foreach (\App\Services\FibonacciPivotService::LEVELS as $level) {
            $row = ['level' => $level];
            foreach (array_values($pivot['months']) as $index => $levels) {
                $row['month_'.$index] = $levels[$level] ?? null;
            }
            $value = $pivot['months'][$pivot['current_month']][$level] ?? null;
            $row['deviation'] = $value === null || ! $pivot['reference_close'] ? null : ($value / $pivot['reference_close'] - 1) * 100;
            $row['forward'] = $pivot['forward_levels'][$level] ?? null;
            $row['forward_deviation'] = $row['forward'] === null || ! $pivot['reference_close'] ? null : ($row['forward'] / $pivot['reference_close'] - 1) * 100;
            $pivotRows[] = $row;
        }

@endphp
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
