# Worker tính chỉ báo

Python 3.12 dùng TA-Lib để tính chỉ báo và lưu vào MySQL chung với Laravel. FastAPI nhận yêu cầu nội bộ; Granian chạy HTTP server. Laravel quản lý job, retry và batch qua queue `indicators`. Python chỉ trả thành công sau khi lưu dữ liệu xong.

## Cài đặt

Chạy trong thư mục `worker-indicator`:

```bash
uv python install 3.12
uv sync --locked
```

`uv sync` cài các dependency, gồm Granian và TA-Lib, vào `.venv` của thư mục này.

## Cấu hình

Worker tự đọc `.env` Laravel ở thư mục cha. Laravel cần cấu hình:

```dotenv
INDICATOR_API_URL=http://127.0.0.1:8001
INDICATOR_API_TOKEN=<token-noi-bo>
INDICATOR_BENCHMARK=VNINDEX
```

Worker dùng các biến `DB_*` và `INDICATOR_API_TOKEN` từ file đó. Nếu cần cấu hình riêng, tạo `worker-indicator/.env` theo `.env.example`; giá trị trong file riêng ghi đè cấu hình đọc từ Laravel. Token của hai bên phải giống nhau. Không commit `.env`.

## Chạy FastAPI bằng Granian

Từ thư mục `worker-indicator`:

```bash
uv run granian --interface asgi --host 127.0.0.1 --port 8001 indicator_worker.api:app
```

Giữ terminal này mở; nhấn `Ctrl+C` để dừng. Sau khi sửa code hoặc cấu hình, dừng và chạy lại lệnh.

Kiểm tra server:

```bash
curl --fail http://127.0.0.1:8001/health
```

Kết quả mong đợi: `{"status":"ok"}`. Healthcheck kiểm tra server đang chạy, chưa kiểm tra kết nối MySQL. Tài liệu API tại <http://127.0.0.1:8001/docs>; endpoint tính toán yêu cầu Bearer token.

## Chạy tính chỉ báo từ Laravel

Mở terminal khác, từ thư mục gốc dự án Laravel:

```bash
# Tạo batch toàn thị trường từ giá đã đồng bộ thành công.
php artisan indicators:calculate

# Hoặc chỉ tính một mã, các khung đã có giá hợp lệ.
php artisan indicators:calculate --ticker=ACB

# Chạy worker xử lý queue; cần chạy cùng lúc với server Granian.
php artisan queue:work database --queue=indicators --timeout=90
```

Lệnh tạo batch chỉ đưa job vào queue. Granian và Laravel queue worker đều phải chạy để xử lý. Có thể mở nhiều terminal chạy queue worker để xử lý song song.

Theo dõi batch bằng ID được in khi tạo:

```bash
php artisan ohlcv:batch <batch-id>
```

Batch đồng bộ giá mới tự tạo batch chỉ báo sau khi kết thúc, cho các chuỗi giá đồng bộ thành công.

## Quy ước tính toán

- Chỉ dùng nến có `volume > 0`, áp dụng cả `1D` và `1H`; không lấp nến giả vào phiên thiếu.
- Chu kỳ SMA, RSI, CCI và ROC tính theo số nến có giao dịch. ROC là phần trăm thay đổi giá đóng cửa so với N nến trước.
- Ngày giao dịch tính theo múi giờ `Asia/Ho_Chi_Minh` (UTC+7).
- RSLine = giá đóng cửa / giá benchmark × 100; ghép theo ngày hoặc ngày/giờ Việt Nam. Thiếu benchmark thì RSLine để `NULL`.
- Thiếu lịch sử cho một chỉ báo thì để `NULL`.
- Tính lại dùng upsert, không tạo dòng trùng; chỉ báo cũ ứng với nến volume 0 được xóa trong cùng transaction. Dữ liệu OHLCV nguồn vẫn được giữ.

## Kiểm thử

Từ thư mục `worker-indicator`:

```bash
uv run pytest -q tests

# Kiểm thử MySQL bằng database tạm; tài khoản DB cần quyền tạo/xóa database.
INDICATOR_MYSQL_TESTS=1 uv run pytest -q tests
```
