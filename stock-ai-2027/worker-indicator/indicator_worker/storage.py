"""Đọc nến và lưu chỉ báo trong MySQL; khóa/idempotency bảo vệ HTTP retry và chạy đồng thời."""

import json
import os

import pymysql
from fastapi import HTTPException

from .calculator import FIELDS, calculate

START = 1704042000  # 2024-01-01 00:00 tại Việt Nam.


def connect():
    """Dùng cấu hình DB_* chung với Laravel hoặc cấu hình riêng của worker."""
    return pymysql.connect(
        host=os.getenv("DB_HOST", "127.0.0.1"), port=int(os.getenv("DB_PORT", "3306")),
        user=os.getenv("DB_USERNAME", "root"), password=os.getenv("DB_PASSWORD", ""),
        database=os.environ["DB_DATABASE"], unix_socket=os.getenv("DB_SOCKET") or None,
        charset="utf8mb4", cursorclass=pymysql.cursors.DictCursor,
        connect_timeout=5, read_timeout=60, write_timeout=60, autocommit=False,
    )


def execute(request: dict) -> dict:
    """Trả completed sau commit; retry có thể tính lại nhưng upsert không tạo dòng trùng."""
    connection = connect()
    lock_name = "indicator:" + request["ticker"] + ":" + request["resolution"]
    locked = False
    try:
        with connection.cursor() as cursor:
            cursor.execute("SELECT GET_LOCK(%s, 0) AS acquired", (lock_name,))
            locked = cursor.fetchone()["acquired"] == 1
            if not locked:
                raise HTTPException(409, "Chuỗi chỉ báo đang được tính bởi worker khác.")
            # Khóa nguồn theo ticker cố định để tránh deadlock giữa benchmark và cổ phiếu.
            states = {}
            for ticker in sorted({request["ticker"], request["benchmark"]}):
                cursor.execute("SELECT * FROM ohlcv_sync_states WHERE ticker=%s AND resolution=%s FOR UPDATE",
                               (ticker, request["resolution"]))
                states[ticker] = cursor.fetchone()
            source = states[request["ticker"]]
            if not source or source["data_version"] != request["source_version"]:
                raise HTTPException(409, "Phiên bản giá nguồn đã thay đổi; cần tạo lại yêu cầu.")
            if (source["synced_through_timestamp"] or 0) < request["until"] or source["reload_version"] > source["completed_reload_version"]:
                raise HTTPException(409, "Giá nguồn chưa đồng bộ xong đến mốc yêu cầu.")
            reference = states[request["benchmark"]]
            benchmark_version = request["benchmark_version"]
            if benchmark_version is not None and (
                not reference or reference["data_version"] != benchmark_version
                or reference["synced_through_timestamp"] is None
                or reference["reload_version"] > reference["completed_reload_version"]
            ):
                raise HTTPException(409, "Benchmark chưa sẵn sàng hoặc đã đổi phiên bản.")
            cursor.execute("SELECT timestamp, high, low, close, volume FROM ohlcvs WHERE ticker=%s AND resolution=%s AND timestamp >= %s AND timestamp < %s ORDER BY timestamp",
                           (request["ticker"], request["resolution"], START, request["until"]))
            candles = cursor.fetchall()
            benchmark = []
            if benchmark_version is not None:
                cursor.execute("SELECT timestamp, close FROM ohlcvs WHERE ticker=%s AND resolution=%s AND timestamp >= %s AND timestamp < %s ORDER BY timestamp",
                               (request["benchmark"], request["resolution"], START, request["until"]))
                benchmark = cursor.fetchall()
            rows, missing = calculate(candles, benchmark, request["resolution"])
            # Xóa chỉ báo cũ của nến không có giao dịch trong cùng transaction tính lại.
            # Giữ nguyên OHLCV nguồn để có thể đối chiếu; không xóa công ty ít thanh khoản.
            cursor.execute("DELETE i FROM technical_indicators i INNER JOIN ohlcvs o ON o.ticker=i.ticker AND o.resolution=i.resolution AND o.timestamp=i.timestamp WHERE i.ticker=%s AND i.resolution=%s AND i.timestamp >= %s AND i.timestamp < %s AND o.volume <= 0",
                           (request["ticker"], request["resolution"], START, request["until"]))
            fields = ("ticker", "resolution", "timestamp", "trading_date", *FIELDS)
            columns = ",".join(f"`{field}`" for field in fields)
            placeholders = ",".join(["%s"] * len(fields))
            updates = ",".join(f"`{field}`=VALUES(`{field}`)" for field in ("trading_date", *FIELDS))
            sql = f"INSERT INTO technical_indicators ({columns},created_at,updated_at) VALUES ({placeholders},UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE {updates},updated_at=UTC_TIMESTAMP()"
            for offset in range(0, len(rows), 500):
                batch = rows[offset:offset + 500]
                cursor.executemany(sql, [tuple({"ticker": request["ticker"], "resolution": request["resolution"], **row}[field] for field in fields) for row in batch])
            result = {"status": "completed", "request_id": request["request_id"], "ticker": request["ticker"],
                      "resolution": request["resolution"], "until": request["until"], "rows_processed": len(rows),
                      "rs_missing_rows": missing, "benchmark": request["benchmark"]}
            connection.commit()
            return result
    except Exception:
        connection.rollback()
        raise
    finally:
        if locked:
            with connection.cursor() as cursor:
                cursor.execute("SELECT RELEASE_LOCK(%s)", (lock_name,))
        connection.close()
