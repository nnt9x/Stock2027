"""Checkpoint RSI Wilder/OBV và cửa sổ hữu hạn; không khởi tạo lại RSI từ 200 nến cuối."""

from copy import deepcopy
from datetime import datetime

import numpy as np
import talib

from .calculator import FIELDS, PERIODS, VIETNAM, candle_key


def advance(state: dict, candle: dict, benchmark_close: float | None, resolution: str) -> dict:
    """Tiếp tục một nến có giao dịch từ trạng thái chưa làm tròn của nến trước."""
    close = float(candle["close"])
    volume = float(candle["volume"])
    count = state.get("count", 0)
    previous = state.get("close", close)
    delta = close - previous
    values = {}
    history = state.setdefault("candles", [])
    history.append({key: float(candle[key]) for key in ("close", "high", "low", "volume")})
    history[:] = history[-201:]
    for period in PERIODS:
        values[f"sma_{period}"] = sum(row["close"] for row in history[-period:]) / period if count + 1 >= period else None
        old = history[-period-1]["close"] if count >= period else None
        values[f"roc_{period}"] = ((close / old - 1) * 100 if old else 0.0) if old is not None else None
    for period in (14, 50):
        rsi = state.setdefault(f"rsi_{period}", {"gain": 0.0, "loss": 0.0})
        if count > 0:
            gain, loss = max(delta, 0), max(-delta, 0)
            if count <= period:
                rsi["gain"] += gain / period
                rsi["loss"] += loss / period
            else:
                rsi["gain"] = (rsi["gain"] * (period - 1) + gain) / period
                rsi["loss"] = (rsi["loss"] * (period - 1) + loss) / period
        total = rsi["gain"] + rsi["loss"]
        values[f"rsi_{period}"] = (100 * rsi["gain"] / total if total > 1e-14 else 0.0) if count >= period else None
    if count >= 19:
        recent = history[-20:]
        values["cci_20"] = float(talib.CCI(*[np.array([row[key] for row in recent]) for key in ("high", "low", "close")], timeperiod=20)[-1])
    else:
        values["cci_20"] = None
    values["obv"] = volume if count == 0 else state["obv"] + (volume if delta > 0 else -volume if delta < 0 else 0)
    values["volume_sma_20"] = sum(row["volume"] for row in history[-20:]) / 20 if count >= 19 else None
    values["rs_line"] = close / benchmark_close * 100 if benchmark_close is not None and benchmark_close > 0 else None
    for source, target in (("rsi_50", "rsi_50_ma_10"), ("cci_20", "cci_20_ma10"), ("obv", "obv_ma10"), ("rs_line", "rs_line_sma_10")):
        window = state.setdefault(target, [])
        if values[source] is None:
            window.clear()
        else:
            window.append(values[source])
            window[:] = window[-10:]
        values[target] = sum(window) / 10 if len(window) == 10 else None
    state.update(count=count + 1, close=close, obv=values["obv"], timestamp=int(candle["timestamp"]))
    row = {"timestamp": candle["timestamp"], "trading_date": datetime.fromtimestamp(candle["timestamp"], VIETNAM).date()}
    row.update({field: None if values[field] is None else int(round(values[field])) if field == "obv" else f"{values[field]:.6f}" for field in FIELDS})
    return row


def calculate_incremental(candles: list[dict], benchmark: list[dict], resolution: str, checkpoint: dict | None = None) -> tuple[list[dict], int, dict]:
    """Lưu checkpoint trước nến cuối để lần sau tính lại nến đang mở rồi nối nến mới."""
    state = deepcopy(checkpoint or {})
    references = {candle_key(row["timestamp"], resolution): float(row["close"]) for row in benchmark}
    rows = []
    saved = deepcopy(state)
    valid = sorted((row for row in candles if float(row["volume"]) > 0), key=lambda row: row["timestamp"])
    for index, candle in enumerate(valid):
        if index == len(valid) - 1:
            saved = deepcopy(state)
        rows.append(advance(state, candle, references.get(candle_key(candle["timestamp"], resolution)), resolution))
    return rows, sum(row["rs_line"] is None for row in rows), saved
