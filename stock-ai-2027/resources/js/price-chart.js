import { createChart, CandlestickSeries, HistogramSeries, LineSeries, ColorType, LineStyle, CrosshairMode } from 'lightweight-charts';

const smaDefinitions = [
    [5, '#06b6d4'], [10, '#ec4899'], [20, '#f59e0b'], [50, '#8b5cf6'],
    [100, '#f97316'], [150, '#84cc16'], [200, '#94a3b8'],
];
const paneDefinitions = [
    { title: 'RSI 14', fields: [['rsi_14', 'RSI14', '#a855f7']], levels: [30, 50, 70], fixed: true },
    { title: 'RSI 50', fields: [['rsi_50', 'RSI50', '#a855f7'], ['rsi_50_ma_10', 'MA10', '#f59e0b']], levels: [30, 50, 70], fixed: true },
    { title: 'CCI 20', fields: [['cci_20', 'CCI20', '#f59e0b'], ['cci_20_ma10', 'MA10', '#a855f7']], levels: [-100, 0, 100] },
    { title: 'OBV', fields: [['obv', 'OBV', '#0ea5e9'], ['obv_ma10', 'MA10', '#a855f7']], volume: true },
    { title: 'RSLine', fields: [['rs_line', 'RSLine', '#0ea5e9'], ['rs_line_sma_10', 'SMA10', '#f59e0b']] },
];

// Đối tượng chart nằm trong closure, tránh Alpine proxy các API canvas của thư viện.
window.stockPriceChart = (data, resolution, { showIndicators = true } = {}) => {
    const indicatorPanes = showIndicators ? paneDefinitions : [];
    let chart, price, themeObserver, paneObserver, disposed = false;
    let current, pending, frame = null, layoutDirty = false, lastTime = null;
    const lines = new Map();
    const number = new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 2 });
    const compact = new Intl.NumberFormat('vi-VN', { notation: 'compact', maximumFractionDigits: 2 });
    const date = new Intl.DateTimeFormat('vi-VN', {
        timeZone: 'Asia/Ho_Chi_Minh', day: '2-digit', month: '2-digit', year: 'numeric',
        ...(resolution === '1H' ? { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' } : {}),
    });
    const dayString = (time) => typeof time === 'string' ? time : `${time.year}-${String(time.month).padStart(2, '0')}-${String(time.day).padStart(2, '0')}`;
    const toDate = (time) => typeof time === 'number' ? new Date(time * 1000) : new Date(`${dayString(time)}T00:00:00+07:00`);
    const key = (time) => typeof time === 'number' ? String(time) : dayString(time);
    const byTime = new Map(data.map((row) => [key(row.time), row]));
    const clock = new Intl.DateTimeFormat('vi-VN', { timeZone: 'Asia/Ho_Chi_Minh', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' });
    const dayLabel = new Intl.DateTimeFormat('vi-VN', { timeZone: 'Asia/Ho_Chi_Minh', day: '2-digit', month: '2-digit' });
    const label = (value, isVolume = false) => value == null ? '—' : (isVolume ? compact : number).format(value);
    const latest = data.at(-1);
    return {
        legend: {}, panels: [],
        averages: smaDefinitions.map(([period, color]) => ({ field: `sma_${period}`, label: `SMA${period}`, color })),
        enabled: Object.fromEntries(smaDefinitions.map(([period]) => [`sma_${period}`, [20, 50, 100, 200].includes(period)])),
        show(row, force = false) {
            if (disposed || (!force && lastTime === key(row.time))) return;
            current = row;
            lastTime = key(row.time);
            this.legend = { date: date.format(toDate(row.time)), open: label(row.open), high: label(row.high), low: label(row.low), close: label(row.close), volume: number.format(row.volume) };
            // Giữ cấu trúc legend ổn định; chỉ cập nhật giá trị khi chuyển sang nến khác.
            this.panels.forEach((panel) => panel.items.forEach((item) => {
                const value = item.field === 'volume' ? row.volume : row.indicators?.[item.field];
                const formatted = label(value, item.isVolume);
                if (item.value !== formatted) item.value = formatted;
                if (item.field.startsWith('sma_')) item.visible = this.enabled[item.field];
            }));
        },
        refreshLayout() {
            let top = 8;
            chart.panes().forEach((pane, index) => {
                if (this.panels[index].top !== top) this.panels[index].top = top;
                top += pane.getHeight() + 1;
            });
        },
        schedule(row, resize = false) {
            pending = row;
            layoutDirty ||= resize;
            if (disposed || frame !== null || (!resize && lastTime === key(row.time))) return;
            // Gộp nhiều sự kiện chuột/resize vào một frame, nhường thời gian cho canvas vẽ.
            frame = requestAnimationFrame(() => {
                frame = null;
                if (disposed) return;
                if (layoutDirty) this.refreshLayout();
                layoutDirty = false;
                this.show(pending);
            });
        },
        toggleAverage(field) {
            this.enabled[field] = !this.enabled[field];
            lines.get(field)?.applyOptions({ visible: this.enabled[field] });
            this.show(current, true);
        },
        init() {
            this.$nextTick(() => {
                if (disposed) return;
                chart = createChart(this.$refs.canvas, {
                    autoSize: true, crosshair: { mode: CrosshairMode.Normal },
                    layout: { attributionLogo: true, panes: { enableResize: true } },
                    rightPriceScale: { borderVisible: false, minimumWidth: 76, scaleMargins: { top: 0.12, bottom: 0.25 } },
                    timeScale: { timeVisible: resolution === '1H', secondsVisible: false, rightOffset: 5,
                        tickMarkFormatter: (time, type) => type >= 3 ? clock.format(toDate(time)) : dayLabel.format(toDate(time)) },
                    localization: { locale: 'vi-VN', timeFormatter: (time) => date.format(toDate(time)) },
                });
                price = chart.addSeries(CandlestickSeries, { upColor: '#16a34a', downColor: '#ef4444', borderVisible: false,
                    wickUpColor: '#16a34a', wickDownColor: '#ef4444', priceFormat: { type: 'price', precision: 2, minMove: 0.01 } });
                price.setData(data.map(({ time, open, high, low, close }) => ({ time, open, high, low, close })));
                const volume = chart.addSeries(HistogramSeries, { priceFormat: { type: 'volume' }, priceScaleId: 'volume', lastValueVisible: false, priceLineVisible: false });
                volume.setData(data.map((row) => ({ time: row.time, value: row.volume, color: row.close >= row.open ? '#16a34a60' : '#ef444460' })));
                volume.priceScale().applyOptions({ scaleMargins: { top: 0.8, bottom: 0 } });
                const addLine = (field, color, paneIndex = 0, options = {}) => {
                    const line = chart.addSeries(LineSeries, { color, lineWidth: 1, lastValueVisible: false, priceLineVisible: false, ...options }, paneIndex);
                    // Thiếu chỉ báo tạo whitespace; không biến null thành 0 hay nối qua khoảng thiếu.
                    line.setData(data.map((row) => row.indicators?.[field] == null ? { time: row.time } : { time: row.time, value: row.indicators[field] }));
                    lines.set(field, line);
                    return line;
                };
                if (showIndicators) {
                    smaDefinitions.forEach(([period, color]) => addLine(`sma_${period}`, color, 0, { visible: this.enabled[`sma_${period}`] }));
                    addLine('volume_sma_20', '#0ea5e9', 0, { priceScaleId: 'volume', priceFormat: { type: 'volume' } });
                }
                indicatorPanes.forEach((pane, index) => {
                    pane.fields.forEach(([field, , color], position) => {
                        const line = addLine(field, color, index + 1, {
                            lineWidth: 1,
                            ...(pane.volume ? { priceFormat: { type: 'volume' } } : {}),
                            ...(pane.fixed ? { autoscaleInfoProvider: () => ({ priceRange: { minValue: 0, maxValue: 100 } }) } : {}),
                        });
                        if (position === 0) pane.levels?.forEach((level) => line.createPriceLine({ price: level, color: '#64748b', lineWidth: 1, lineStyle: LineStyle.Dashed, axisLabelVisible: false }));
                    });
                    chart.priceScale('right', index + 1).applyOptions({ scaleMargins: { top: 0.2, bottom: 0.12 } });
                });
                chart.panes().forEach((pane, index) => pane.setStretchFactor(index === 0 ? 3 : 1));
                chart.timeScale().setVisibleLogicalRange({ from: Math.max(0, data.length - 200), to: data.length + 5 });
                const item = (field, name, color, isVolume = false) => ({ field, label: name, color, isVolume, value: '—', visible: true, showLabel: true });
                this.panels = [{ title: '', top: 8, items: [
                    item('volume', 'KL', '#0ea5e9', true),
                    ...(showIndicators ? [item('volume_sma_20', 'KL SMA20', '#0ea5e9', true), ...this.averages.map((average) => item(average.field, average.label, average.color))] : []),
                ] }, ...indicatorPanes.map((pane) => ({ title: pane.title, top: 8,
                    items: pane.fields.map(([field, name, color], index) => ({ ...item(field, name, color, pane.volume), showLabel: index > 0 })) }))];
                this.refreshLayout();
                this.show(latest);
                chart.subscribeCrosshairMove((event) => this.schedule(event.time ? byTime.get(key(event.time)) ?? latest : latest));
                paneObserver = new ResizeObserver(() => this.schedule(current ?? latest, true));
                paneObserver.observe(this.$refs.canvas);
                chart.panes().forEach((pane) => { const element = pane.getHTMLElement(); if (element) paneObserver.observe(element); });
                // Đo lại sau frame vẽ đầu tiên để nhãn nằm đúng góc trái từng pane.
                this.schedule(latest, true);
                const applyTheme = () => {
                    const dark = document.body.classList.contains('dark');
                    chart.applyOptions({ layout: { background: { type: ColorType.Solid, color: dark ? '#030712' : '#ffffff' }, textColor: dark ? '#d1d5db' : '#374151',
                        panes: { separatorColor: dark ? '#334155' : '#e2e8f0', separatorHoverColor: '#64748b' } },
                        grid: { vertLines: { color: dark ? '#111827' : '#f3f4f6' }, horzLines: { color: dark ? '#111827' : '#f3f4f6' } } });
                };
                applyTheme();
                themeObserver = new MutationObserver(applyTheme);
                themeObserver.observe(document.body, { attributes: true, attributeFilter: ['class'] });
            });
        },
        destroy() {
            disposed = true;
            if (frame !== null) cancelAnimationFrame(frame);
            themeObserver?.disconnect();
            paneObserver?.disconnect();
            chart?.remove();
        },
    };
};
