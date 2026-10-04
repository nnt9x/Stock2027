"""Kiểm thử MySQL thật trong database tạm; không thay đổi dữ liệu ứng dụng."""

import os
import uuid
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime, timedelta
from zoneinfo import ZoneInfo

import pytest
from fastapi import HTTPException

from indicator_worker import storage

pytestmark = pytest.mark.skipif(os.getenv("INDICATOR_MYSQL_TESTS") != "1", reason="Bật INDICATOR_MYSQL_TESTS=1 để kiểm tra MySQL trong DB tạm")


@pytest.fixture
def mysql(monkeypatch):
    """Sao chép schema vào database riêng và xóa database đó sau mỗi test."""
    admin = storage.connect()
    name = "indicator_test_" + uuid.uuid4().hex
    try:
        with admin.cursor() as cursor:
            cursor.execute(f"CREATE DATABASE `{name}` CHARACTER SET utf8mb4")
            for table in ("ohlcv_sync_states", "ohlcvs", "technical_indicators"):
                cursor.execute(f"SHOW CREATE TABLE `{table}`")
                ddl = cursor.fetchone()["Create Table"]
                cursor.execute(ddl.replace(f"CREATE TABLE `{table}`", f"CREATE TABLE `{name}`.`{table}`", 1))
        monkeypatch.setenv("DB_DATABASE", name)
        connection = storage.connect()
        with connection.cursor() as cursor:
            cursor.execute("INSERT INTO ohlcv_sync_states (ticker,resolution,synced_through_timestamp,data_version) VALUES ('ACB','1D',1791103422,1)")
            start = datetime(2024, 1, 1, 9, tzinfo=ZoneInfo("Asia/Ho_Chi_Minh"))
            rows = [(int((start + timedelta(days=i)).timestamp()), (start + timedelta(days=i)).date(), i + 1) for i in range(501)]
            cursor.executemany("INSERT INTO ohlcvs (ticker,resolution,timestamp,trading_date,open,high,low,close,volume) VALUES ('ACB','1D',%s,%s,%s,1000,0,10,100)", rows)
        connection.commit()
        yield connection
        connection.close()
    finally:
        with admin.cursor() as cursor:
            cursor.execute(f"DROP DATABASE IF EXISTS `{name}`")
        admin.close()


def request():
    return {"request_id": "test-request", "ticker": "ACB", "resolution": "1D", "until": 1791103422,
            "benchmark": "VNINDEX", "source_version": 1, "benchmark_version": None}


def test_retry_upserts_without_duplicate_rows(mysql):
    first = storage.execute(request())
    assert first["rows_processed"] == 501
    assert storage.execute(request())["rows_processed"] == 1
    with mysql.cursor() as cursor:
        cursor.execute("SELECT COUNT(*) AS n FROM technical_indicators")
        assert cursor.fetchone()["n"] == 501


def test_source_revision_change_prevents_stale_write(mysql):
    with pytest.raises(HTTPException) as error:
        storage.execute({**request(), "source_version": 0})
    assert error.value.status_code == 409
    with mysql.cursor() as cursor:
        cursor.execute("SELECT COUNT(*) AS n FROM technical_indicators")
        assert cursor.fetchone()["n"] == 0


def test_recalculation_removes_old_zero_volume_indicator(mysql):
    """Tính lại xóa kết quả cũ của nến volume 0 nhưng vẫn giữ nến giá nguồn."""
    storage.execute(request())
    with mysql.cursor() as cursor:
        cursor.execute("UPDATE ohlcvs SET volume=0 ORDER BY timestamp LIMIT 1")
        cursor.execute("UPDATE ohlcv_sync_states SET completed_reload_version=1,reload_version=1")
    mysql.commit()
    assert storage.execute(request())["rows_processed"] == 500
    mysql.commit()  # Mở snapshot mới để đọc kết quả worker đã commit.
    with mysql.cursor() as cursor:
        cursor.execute("SELECT COUNT(*) AS n FROM technical_indicators")
        assert cursor.fetchone()["n"] == 500
        cursor.execute("SELECT COUNT(*) AS n FROM ohlcvs")
        assert cursor.fetchone()["n"] == 501


def test_write_failure_rolls_back_all_chunks(mysql, monkeypatch):
    original = storage.calculate
    def invalid_last_row(*args):
        rows, missing = original(*args)
        rows[-1]["obv"] = 10 ** 30
        return rows, missing
    monkeypatch.setattr(storage, "calculate", invalid_last_row)
    with pytest.raises(Exception):
        storage.execute(request())
    with mysql.cursor() as cursor:
        cursor.execute("SELECT COUNT(*) AS n FROM technical_indicators")
        assert cursor.fetchone()["n"] == 0


def test_other_worker_lock_returns_retryable_conflict(mysql):
    with mysql.cursor() as cursor:
        cursor.execute("SELECT GET_LOCK('indicator:ACB:1D',0)")
    try:
        with pytest.raises(HTTPException) as error:
            storage.execute(request())
        assert error.value.status_code == 409
    finally:
        with mysql.cursor() as cursor:
            cursor.execute("SELECT RELEASE_LOCK('indicator:ACB:1D')")


def test_parallel_tickers_with_zero_volume_cleanup(mysql):
    """Nhiều mã tính/xóa nến volume 0 đồng thời không tranh chấp phạm vi index."""
    tickers = ["AAA", "BBB", "CCC", "DDD"]
    with mysql.cursor() as cursor:
        for ticker in tickers:
            cursor.execute("INSERT INTO ohlcv_sync_states (ticker,resolution,synced_through_timestamp,data_version) VALUES (%s,'1D',1791103422,1)", (ticker,))
            cursor.execute("INSERT INTO ohlcvs (ticker,resolution,timestamp,trading_date,open,high,low,close,volume) SELECT %s,resolution,timestamp,trading_date,open,high,low,close,volume FROM ohlcvs WHERE ticker='ACB'", (ticker,))
    mysql.commit()
    with ThreadPoolExecutor(max_workers=4) as pool:
        assert all(result["rows_processed"] == 501 for result in pool.map(storage.execute, [{**request(), "ticker": ticker} for ticker in tickers]))
    with mysql.cursor() as cursor:
        cursor.execute("UPDATE ohlcvs SET volume=0 WHERE ticker <> 'ACB' AND timestamp=(SELECT first_timestamp FROM (SELECT MIN(timestamp) AS first_timestamp FROM ohlcvs) t)")
    with mysql.cursor() as cursor:
        cursor.execute("UPDATE ohlcv_sync_states SET completed_reload_version=1,reload_version=1")
    mysql.commit()
    with ThreadPoolExecutor(max_workers=4) as pool:
        assert all(result["rows_processed"] == 500 for result in pool.map(storage.execute, [{**request(), "ticker": ticker} for ticker in tickers]))


def test_incremental_only_writes_tail_and_reload_rebuilds(mysql):
    """Chạy lại chỉ ghi nến cuối/mới; tải lại lịch sử buộc tính full và giữ kết quả TA-Lib."""
    assert storage.execute(request())["mode"] == "full"
    with mysql.cursor() as cursor:
        cursor.execute("UPDATE technical_indicators SET updated_at='2000-01-01 00:00:00'")
        cursor.execute("INSERT INTO ohlcvs (ticker,resolution,timestamp,trading_date,open,high,low,close,volume) SELECT ticker,resolution,timestamp+86400,DATE_ADD(trading_date,INTERVAL 1 DAY),open,high,low,close+1,volume FROM ohlcvs ORDER BY timestamp DESC LIMIT 1")
    mysql.commit()
    result = storage.execute(request())
    assert result["mode"] == "incremental"
    assert result["rows_processed"] == 2
    mysql.commit()
    with mysql.cursor() as cursor:
        cursor.execute("SELECT COUNT(*) AS n FROM technical_indicators WHERE updated_at='2000-01-01 00:00:00'")
        assert cursor.fetchone()["n"] == 500
        cursor.execute("UPDATE ohlcv_sync_states SET completed_reload_version=1,reload_version=1")
    mysql.commit()
    result = storage.execute(request())
    assert result["mode"] == "full"
    assert result["rows_processed"] == 502
