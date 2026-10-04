"""Tính toàn bộ chỉ báo bằng TA-Lib, giữ quy ước nhất quán khi backfill và chạy ngày."""

from datetime import datetime
from zoneinfo import ZoneInfo

import numpy as np
import talib

PERIODS = (5, 10, 20, 50, 100, 150, 200)
VIETNAM = ZoneInfo("Asia/Ho_Chi_Minh")
FIELDS = (
    *(f"sma_{p}" for p in PERIODS),
    "rsi_14", "rsi_50", "rsi_50_ma_10", "cci_20", "cci_20_ma10",
    *(f"roc_{p}" for p in PERIODS),
    "obv", "obv_ma10", "volume_sma_20", "rs_line", "rs_line_sma_10",
)


def candle_key(timestamp: int, resolution: str) -> tuple:
    """Ghép benchmark theo ngày/giờ Việt Nam vì timestamp nguồn có thể lệch 15 phút."""
    local = datetime.fromtimestamp(timestamp, VIETNAM)
    return (local.date(), local.hour) if resolution == "1H" else (local.date(),)


def sma_valid_segments(values: np.ndarray, period: int) -> np.ndarray:
    """Tính SMA trên từng đoạn liên tục hợp lệ; không lấp dữ liệu thiếu hoặc NaN bằng 0."""
    result = np.full(values.size, np.nan)
    edges = np.diff(np.r_[False, np.isfinite(values), False].astype(int))
    for start, end in zip(np.flatnonzero(edges == 1), np.flatnonzero(edges == -1)):
        result[start:end] = talib.SMA(values[start:end], timeperiod=period)
    return result


def calculate(candles: list[dict], benchmark: list[dict], resolution: str) -> tuple[list[dict], int]:
    """Chỉ dùng nến volume > 0; chu kỳ tính theo nến có giao dịch, không lấp phiên trống.

    SMA/ROC tính trên close; OBV dùng khởi tạo TA-Lib; RSLine = tỷ lệ giá × 100.
    """
    candles = [row for row in candles if float(row["volume"]) > 0]
    if not candles:
        return [], 0
    candles = sorted(candles, key=lambda row: row["timestamp"])
    close, high, low, volume = (
        np.array([float(row[field]) for row in candles], dtype=np.float64)
        for field in ("close", "high", "low", "volume")
    )
    values = {f"sma_{p}": talib.SMA(close, timeperiod=p) for p in PERIODS}
    values.update({f"roc_{p}": talib.ROC(close, timeperiod=p) for p in PERIODS})
    values["rsi_14"] = talib.RSI(close, timeperiod=14)
    values["rsi_50"] = talib.RSI(close, timeperiod=50)
    values["rsi_50_ma_10"] = sma_valid_segments(values["rsi_50"], 10)
    values["cci_20"] = talib.CCI(high, low, close, timeperiod=20)
    values["cci_20_ma10"] = sma_valid_segments(values["cci_20"], 10)
    values["obv"] = talib.OBV(close, volume)
    values["obv_ma10"] = talib.SMA(values["obv"], timeperiod=10)
    values["volume_sma_20"] = talib.SMA(volume, timeperiod=20)
    benchmark_by_key = {candle_key(row["timestamp"], resolution): float(row["close"])
                        for row in sorted(benchmark, key=lambda row: row["timestamp"])}
    rs = np.full(close.size, np.nan)
    for i, row in enumerate(candles):
        denominator = benchmark_by_key.get(candle_key(row["timestamp"], resolution))
        if denominator is not None and denominator > 0:
            rs[i] = close[i] / denominator * 100
    values["rs_line"] = rs
    values["rs_line_sma_10"] = sma_valid_segments(rs, 10)
    rows = []
    for i, candle in enumerate(candles):
        row = {"timestamp": candle["timestamp"],
               "trading_date": datetime.fromtimestamp(candle["timestamp"], VIETNAM).date()}
        for field in FIELDS:
            value = values[field][i]
            row[field] = None if not np.isfinite(value) else (
                int(round(value)) if field == "obv" else f"{value:.6f}"
            )
        rows.append(row)
    return rows, int(np.count_nonzero(~np.isfinite(rs)))
