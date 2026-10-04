"""Kiểm tra công thức và warm-up bằng dữ liệu xác định, không phụ thuộc API giá."""

from datetime import datetime, timedelta
from zoneinfo import ZoneInfo

import numpy as np
import pytest

from indicator_worker.calculator import calculate, sma_valid_segments


def candles(count=220):
    """Chuỗi tăng đều để có kết quả SMA, ROC, RSI và OBV biết trước."""
    start = datetime(2024, 1, 1, 9, tzinfo=ZoneInfo("Asia/Ho_Chi_Minh"))
    return [{"timestamp": int((start + timedelta(days=i)).timestamp()),
             "high": i + 2, "low": i, "close": i + 1, "volume": 10} for i in range(count)]


def test_known_values_and_null_warmup():
    rows, missing = calculate(candles(), [], "1D")
    assert rows[3]["sma_5"] is None
    assert rows[4]["sma_5"] == "3.000000"
    assert rows[199]["sma_200"] == "100.500000"
    assert rows[20]["roc_20"] == "2000.000000"
    assert rows[13]["rsi_14"] is None
    assert rows[14]["rsi_14"] == "100.000000"
    assert rows[58]["rsi_50_ma_10"] is None
    assert rows[59]["rsi_50_ma_10"] == "100.000000"
    assert rows[19]["cci_20"] == "126.666667"
    assert rows[27]["cci_20_ma10"] is None
    assert rows[28]["cci_20_ma10"] == "126.666667"
    assert rows[0]["obv"] == 10
    assert rows[9]["obv"] == 100
    assert rows[9]["obv_ma10"] == "55.000000"
    assert rows[19]["volume_sma_20"] == "10.000000"
    assert missing == 220
    assert rows[-1]["rs_line"] is None


def test_rs_matches_vietnam_hour_with_provider_timestamp_offset():
    source = candles(12)
    benchmark = [{"timestamp": row["timestamp"] + 15 * 60, "close": 1000} for row in source]
    rows, missing = calculate(source, benchmark, "1H")
    assert missing == 0
    assert rows[9]["rs_line"] == "1.000000"
    assert rows[9]["rs_line_sma_10"] == "0.550000"
    assert rows[0]["trading_date"].isoformat() == "2024-01-01"


def test_obv_can_be_negative():
    source = candles(3)
    source[0]["close"], source[1]["close"], source[2]["close"] = 3, 2, 1
    rows, _ = calculate(source, [], "1D")
    assert [row["obv"] for row in rows] == [10, 0, -10]


def test_missing_benchmark_breaks_ma_without_zero_fill():
    values = np.array([1., 2., 3., np.nan, 5., 6., 7.])
    actual = sma_valid_segments(values, 3)
    assert actual[2] == 2
    assert np.isnan(actual[3:6]).all()
    assert actual[6] == 6


def test_vietnam_date_changes_at_17_utc():
    source = candles(1)
    source[0]["timestamp"] = int(datetime(2024, 1, 1, 17, tzinfo=ZoneInfo("UTC")).timestamp())
    rows, _ = calculate(source, [], "1H")
    assert rows[0]["trading_date"].isoformat() == "2024-01-02"


def test_empty_series():
    assert calculate([], [], "1D") == ([], 0)


def test_zero_volume_candles_do_not_affect_any_indicator():
    """Nến không giao dịch không tạo chỉ báo và không chiếm một chu kỳ tính toán."""
    source = candles()
    zero = {**source[10], "timestamp": source[10]["timestamp"] + 60,
            "close": 99999, "high": 99999, "low": 99999, "volume": 0}
    expected = calculate(source, source, "1D")
    assert calculate([*source, zero], source, "1D") == expected
    assert calculate([zero], source, "1D") == ([], 0)
