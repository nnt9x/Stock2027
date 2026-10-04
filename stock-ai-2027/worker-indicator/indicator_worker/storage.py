"""Đọc nến và lưu chỉ báo trong MySQL; khóa/idempotency bảo vệ HTTP retry và chạy đồng thời."""

import json
import os
from datetime import datetime, timezone

import pymysql
from fastapi import HTTPException

from .calculator import FIELDS, VIETNAM, calculate
from .incremental import calculate_incremental

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
            # Tránh gap lock giữa các mã; khóa state vẫn bảo vệ phiên bản giá đang đọc.
            cursor.execute("SET TRANSACTION ISOLATION LEVEL READ COMMITTED")
            cursor.execute("SELECT GET_LOCK(%s, 0) AS acquired", (lock_name,))
            locked = cursor.fetchone()["acquired"] == 1
            if not locked:
                raise HTTPException(409, "Chuỗi chỉ báo đang được tính bởi worker khác.")
            # Khóa nguồn theo ticker cố định để tránh deadlock giữa benchmark và cổ phiếu.
            states = {}
            for ticker in sorted({request["ticker"], request["benchmark"]}):
                lock = "FOR UPDATE" if ticker == request["ticker"] else "LOCK IN SHARE MODE"
                cursor.execute(f"SELECT * FROM ohlcv_sync_states WHERE ticker=%s AND resolution=%s {lock}",
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
            signature = [1, source["completed_reload_version"], request["benchmark"],
                         reference["completed_reload_version"] if benchmark_version is not None else None]
            stored = json.loads(source["indicator_state"]) if source.get("indicator_state") else {}
            incremental = stored.get("signature") == signature and request["until"] >= stored.get("until", 0)
            checkpoint = stored.get("checkpoint", {}) if incremental else {}
            lower = max(START, checkpoint.get("timestamp", START - 1) + 1) if incremental else START
            cursor.execute("SELECT timestamp, high, low, close, volume FROM ohlcvs WHERE ticker=%s AND resolution=%s AND timestamp >= %s AND timestamp < %s ORDER BY timestamp",
                           (request["ticker"], request["resolution"], lower, request["until"]))
            candles = cursor.fetchall()
            benchmark = []
            benchmark_from = int(datetime.fromtimestamp(lower, VIETNAM).replace(hour=0, minute=0, second=0, microsecond=0).timestamp())
            if benchmark_version is not None:
                cursor.execute("SELECT timestamp, close FROM ohlcvs WHERE ticker=%s AND resolution=%s AND timestamp >= %s AND timestamp < %s ORDER BY timestamp",
                               (request["benchmark"], request["resolution"], benchmark_from, request["until"]))
                benchmark = cursor.fetchall()
            continuation, missing, saved = calculate_incremental(candles, benchmark, request["resolution"], checkpoint)
            rows = continuation
            if not incremental:
                # TA-Lib là chuẩn full; checkpoint giữ số thực chưa làm tròn cho lần nối tiếp.
                rows, missing = calculate(candles, benchmark, request["resolution"])
            # Xóa chỉ báo cũ của nến không có giao dịch trong cùng transaction tính lại.
            # Giữ nguyên OHLCV nguồn để có thể đối chiếu; không xóa công ty ít thanh khoản.
            # Chỉ xóa theo khóa unique cụ thể; không DELETE JOIN quét/khóa chuỗi khác.
            zero_timestamps = sorted(row["timestamp"] for row in candles if float(row["volume"]) <= 0)
            for offset in range(0, len(zero_timestamps), 500):
                timestamps = zero_timestamps[offset:offset + 500]
                placeholders = ",".join(["%s"] * len(timestamps))
                cursor.execute(f"DELETE FROM technical_indicators WHERE ticker=%s AND resolution=%s AND timestamp IN ({placeholders})",
                               (request["ticker"], request["resolution"], *timestamps))
            fields = ("ticker", "resolution", "timestamp", "trading_date", *FIELDS)
            columns = ",".join(f"`{field}`" for field in fields)
            placeholders = ",".join(["%s"] * len(fields))
            updates = ",".join(f"`{field}`=VALUES(`{field}`)" for field in ("trading_date", *FIELDS))
            sql = f"INSERT INTO technical_indicators ({columns},created_at,updated_at) VALUES ({placeholders},%s,%s) ON DUPLICATE KEY UPDATE {updates},updated_at=VALUES(updated_at)"
            written_at = datetime.now(timezone.utc).replace(tzinfo=None)
            for offset in range(0, len(rows), 500):
                batch = rows[offset:offset + 500]
                cursor.executemany(sql, [(request["ticker"], request["resolution"], row["timestamp"], row["trading_date"], *(row[field] for field in FIELDS), written_at, written_at) for row in batch])
            cursor.execute("UPDATE ohlcv_sync_states SET indicator_state=%s WHERE ticker=%s AND resolution=%s",
                           (json.dumps({"signature": signature, "until": request["until"], "checkpoint": saved}), request["ticker"], request["resolution"]))
            result = {"status": "completed", "request_id": request["request_id"], "ticker": request["ticker"],
                      "resolution": request["resolution"], "until": request["until"], "rows_processed": len(rows),
                      "rs_missing_rows": missing, "benchmark": request["benchmark"],
                      "mode": "incremental" if incremental else "full"}
            connection.commit()
            return result
    except pymysql.err.OperationalError as error:
        connection.rollback()
        if error.args[0] in (1205, 1213):
            # Laravel retry toàn job sau rollback; không tiếp tục transaction bị lỗi.
            raise HTTPException(409, "Database đang tranh chấp khóa; cần thử lại yêu cầu.") from error
        raise
    except Exception:
        connection.rollback()
        raise
    finally:
        if locked:
            with connection.cursor() as cursor:
                cursor.execute("SELECT RELEASE_LOCK(%s)", (lock_name,))
        connection.close()
