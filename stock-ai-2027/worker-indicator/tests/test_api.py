"""Kiểm tra xác thực, validation và response chỉ sau khi nghiệp vụ Python trả thành công."""

from fastapi import HTTPException
from fastapi.testclient import TestClient

from indicator_worker.api import app

client = TestClient(app)
PAYLOAD = {"request_id": "test-ACB-1D", "ticker": "ACB", "resolution": "1D",
           "until": 1791103422, "benchmark": "VNINDEX", "source_version": 1, "benchmark_version": 2}


def test_token_is_required(monkeypatch):
    monkeypatch.setenv("INDICATOR_API_TOKEN", "test-token")
    assert client.post("/internal/indicators/calculate", json=PAYLOAD).status_code == 401
    assert client.post("/internal/indicators/calculate", json=PAYLOAD,
                       headers={"Authorization": "Bearer wrong"}).status_code == 401


def test_missing_token_closes_endpoint(monkeypatch):
    monkeypatch.setenv("INDICATOR_API_TOKEN", "")
    assert client.post("/internal/indicators/calculate", json=PAYLOAD).status_code == 503


def test_completed_response_from_storage(monkeypatch):
    monkeypatch.setenv("INDICATOR_API_TOKEN", "test-token")
    calls = []
    def execute(payload):
        calls.append(payload)
        return {"status": "completed", "rows_processed": 683}
    monkeypatch.setattr("indicator_worker.api.storage.execute", execute)
    response = client.post("/internal/indicators/calculate", json=PAYLOAD,
                           headers={"Authorization": "Bearer test-token"})
    assert response.status_code == 200
    assert response.json()["status"] == "completed"
    assert calls == [PAYLOAD]


def test_source_version_conflict_stays_409(monkeypatch):
    monkeypatch.setenv("INDICATOR_API_TOKEN", "test-token")
    def execute(payload):
        raise HTTPException(409, "Phiên bản nguồn thay đổi")
    monkeypatch.setattr("indicator_worker.api.storage.execute", execute)
    response = client.post("/internal/indicators/calculate", json=PAYLOAD,
                           headers={"Authorization": "Bearer test-token"})
    assert response.status_code == 409


def test_unsupported_resolution_is_rejected(monkeypatch):
    monkeypatch.setenv("INDICATOR_API_TOKEN", "test-token")
    response = client.post("/internal/indicators/calculate", json={**PAYLOAD, "resolution": "1W"},
                           headers={"Authorization": "Bearer test-token"})
    assert response.status_code == 422
