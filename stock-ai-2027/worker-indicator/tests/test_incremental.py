"""Đối chiếu nối tiếp với TA-Lib full, gồm chuỗi ngẫu nhiên và warm-up."""

import numpy as np
import pytest

from indicator_worker.calculator import FIELDS, calculate
from indicator_worker.incremental import calculate_incremental
from test_calculator import candles


@pytest.mark.parametrize('split', [1, 14, 49, 199, 220])
def test_incremental_matches_full_talib(split):
    source = candles(300)
    rng = np.random.default_rng(42)
    close = 100 + np.cumsum(rng.normal(size=300))
    for i, row in enumerate(source):
        row.update(close=float(close[i]), high=float(close[i]+2), low=float(close[i]-2), volume=int(rng.integers(1, 1000)))
    reference = [{"timestamp": row["timestamp"], "close": 1000+i} for i,row in enumerate(source)]
    _, _, checkpoint = calculate_incremental(source[:split], reference, '1D')
    remaining = [row for row in source if row['timestamp'] > checkpoint.get('timestamp', 0)]
    actual, _, _ = calculate_incremental(remaining, reference, '1D', checkpoint)
    expected, _ = calculate(source, reference, '1D')
    expected = {row['timestamp']:row for row in expected}
    for row in actual:
        for field in FIELDS:
            value = expected[row['timestamp']][field]
            if value is None:
                assert row[field] is None
            else:
                assert float(row[field]) == pytest.approx(float(value), abs=0.000001, rel=0)
