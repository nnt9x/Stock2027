"""API nội bộ đồng bộ: Python tính/lưu xong mới trả HTTP thành công cho Laravel."""

import hmac
import logging
import os
from pathlib import Path
from typing import Annotated, Literal

from dotenv import load_dotenv
from fastapi import Depends, FastAPI, HTTPException
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer
from pydantic import BaseModel, Field

from . import storage

ROOT = Path(__file__).resolve().parents[2]
load_dotenv(ROOT / ".env")
load_dotenv(ROOT / "worker-indicator" / ".env", override=True)
app = FastAPI(title="Stock Indicator Worker", version="1.0.0")
security = HTTPBearer(auto_error=False)


class CalculationRequest(BaseModel):
    """Giới hạn input; phiên bản giá giúp retry không ghi chỉ báo từ snapshot cũ."""
    request_id: str = Field(min_length=1, max_length=128)
    ticker: str = Field(pattern=r"^[A-Z0-9._-]{1,32}$")
    resolution: Literal["1D", "1H"]
    until: int = Field(gt=1704042000)
    benchmark: Literal["VNINDEX", "VN30"] = "VNINDEX"
    source_version: int = Field(ge=0)
    benchmark_version: int | None = Field(default=None, ge=0)


def authorize(credentials: Annotated[HTTPAuthorizationCredentials | None, Depends(security)]) -> None:
    """Chỉ nhận token cấu hình; không có token thì đóng endpoint tính toán."""
    expected = os.getenv("INDICATOR_API_TOKEN", "")
    if not expected:
        raise HTTPException(503, "Worker chưa cấu hình token nội bộ.")
    if not credentials or not hmac.compare_digest(credentials.credentials, expected):
        raise HTTPException(401, "Token nội bộ không hợp lệ.")


@app.get("/health")
def health() -> dict:
    """Healthcheck không hiển thị thông tin kết nối database hay token."""
    return {"status": "ok"}


@app.post("/internal/indicators/calculate", dependencies=[Depends(authorize)])
def calculate_indicators(request: CalculationRequest) -> dict:
    """Chạy sync trong threadpool FastAPI, tránh chặn event loop khi tính/đọc MySQL."""
    try:
        return storage.execute(request.model_dump())
    except HTTPException:
        raise
    except Exception:
        logging.exception("Tính chỉ báo thất bại cho %s/%s", request.ticker, request.resolution)
        raise HTTPException(500, "Tính chỉ báo thất bại; xem log worker.") from None
