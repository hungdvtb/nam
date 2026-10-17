# Trình dạy AI Trade

Bản đầu chạy local để rà soát mẫu lệnh forex H4/M15 trước khi gắn vào MT5 chạy thật.

## Đã có

- Hồ sơ tài khoản với cấu hình risk mặc định cho tài khoản quỹ.
- Hồ sơ chiến lược cho xu hướng H4, vùng supply/demand H4 và cấu trúc M15.
- Màn hình dạy AI bằng replay tình huống, biểu đồ canvas, chấm điểm, duyệt/bỏ/sửa phản hồi.
- Xuất/nhập bộ nhớ huấn luyện dạng JSON.
- Bộ chấm điểm bằng rule offline và API đánh giá lại dataset.
- Cầu nối MT5 bằng Python để lấy dữ liệu nến H4/M15 từ tài khoản MT5 đang mở.

## Chạy app

Với XAMPP Apache:

```text
http://localhost/ai%20trade/
```

Hoặc dùng PHP built-in server:

```powershell
php -S 127.0.0.1:8010
```

Trên Windows có thể chạy nhanh bằng file:

```text
start-server.bat
```

Sau đó mở:

```text
http://127.0.0.1:8010/
```

## Dữ liệu

Dữ liệu runtime được lưu tại:

```text
data/
```

Các file quan trọng:

```text
data/accounts.json
data/strategy.json
data/candidate_cases.json
data/cases/reviewed_cases.jsonl
data/market_data/*.json
```

File memory JSON export là nguồn dữ liệu gốc để rebuild hoặc kiểm tra một model AI mới sau này.

### Đồng bộ dữ liệu Dạy Price Action

Sau khi dạy ở local, chạy `scripts/sync_pa_training_to_namtrade.ps1` để gộp bài chấm vùng/setup, bài học, vùng tự vẽ và lịch sử AI Coach lên Namtrade qua SSH. Dữ liệu đang có trên Namtrade được giữ lại; bản ghi trùng ID không bị nhân đôi và bản sửa mới hơn được giữ. Có thể chạy lại an toàn. Mỗi lần có thay đổi sẽ sao lưu dữ liệu production bên ngoài thư mục web tại `/var/backups/namtrade-pa-training/`. Lệnh này chỉ đồng bộ dữ liệu PA, không deploy code hay nến MT5.

## Kết nối MT5

1. Mở MT5 và đăng nhập tài khoản demo Exness.
2. Bật các cặp cần lấy dữ liệu trong Market Watch.
3. Vào tab `Kết nối MT5` trong app.
4. Bấm `Kiểm tra kết nối`.
5. Bấm `Lấy dữ liệu từ MT5`.

Mặc định app xin tối đa 1,000,000 nến M15 mục tiêu cho mỗi symbol và tự quy đổi timeframe lớn hơn theo cùng khoảng thời gian, ví dụ H4 = M15 / 16. MT5 có thể trả ít hơn nếu terminal chưa có đủ history hoặc `Max. bars in chart` đang thấp. Dữ liệu được lưu vào `data/market_data/*.json`.
Lần mở app sau sẽ đọc lại các file đã lưu này, không cần mở MT5 nữa. Chỉ mở MT5 khi muốn bấm `Lấy dữ liệu từ MT5` để cập nhật/lấy lại dữ liệu.

Nếu app báo thiếu Python hoặc thiếu package `MetaTrader5`, cài Python 64-bit rồi chạy:

```powershell
pip install MetaTrader5
```

## Thao tác biểu đồ

- Lăn chuột giữa trên biểu đồ để phóng to/thu nhỏ theo chiều ngang.
- Giữ chuột trái và kéo trong vùng biểu đồ để di chuyển linh hoạt theo cả chiều ngang và chiều dọc.
- Kéo biểu đồ sang trái nếu muốn tạo khoảng trống bên phải và đưa nến cuối ra giữa chart.
- Kéo chuột trên trục giá bên phải để co giãn biểu đồ theo phương dọc.
- Bấm `Phóng to` để mở biểu đồ dạng toàn màn hình trong app.
- Công cụ vẽ bên trái gồm con trỏ, quét chọn đối tượng, Long/Short position, trendline, ray, đường ngang/dọc, vùng chữ nhật, Fibonacci, thước đo, text note, cảnh báo giá và xóa drawing.
- Chọn kiểu chart bằng `Nến`, `Bar`, `Đường` hoặc `Vùng`; bật/tắt nam châm OHLC để snap điểm vẽ vào nến gần nhất.
- Drawing được lưu trong trình duyệt theo từng symbol/timeframe, có Undo/Redo, khóa/ẩn drawing, nhân đôi, đổi ghi chú, xuất JSON và nhập lại JSON.
- Bấm `L` hoặc `S`, sau đó click vào giá entry trên chart để tạo vùng Long/Short. App tự lấy RR mặc định trong chiến lược, hiện là 1:3.
- Phím nhanh: `T` trendline, `R` ray, `H` ngang, `V` dọc, `F` Fibonacci, `M` thước đo, `N` note, `A` cảnh báo, `E` xóa gần chuột, `Ctrl+Z` undo và `Ctrl+Y` redo.
- Mặc định tool vẽ là one-shot: vẽ xong tự quay về cursor. Bật `Vẽ liên tục` nếu muốn giữ tool để vẽ nhiều object liên tiếp. `Esc` hoặc chuột phải sẽ hủy tool hiện tại.
- Magnet có 3 chế độ: tắt, yếu và mạnh. Giữ `Ctrl/Cmd` khi vẽ để tạm đảo trạng thái magnet.
- Khi chọn drawing, floating toolbar cho đổi màu, độ dày, line style, opacity, text size, lock/hide, clone, delete và lưu style mặc định.
- Favorites toolbar hiện trên chart, object tree bên phải hỗ trợ chọn nhiều bằng `Ctrl/Shift`, hide/lock/duplicate/rename/group/delete từng object.
- Menu chuột phải hỗ trợ copy/paste, clone, bring to front/send to back, add alert, add favorite, remove selected/all.
- Thanh dưới có range nhanh, go-to-date, timezone, auto/regular/log/percent scale và paper trade panel.
- Bấm `□` để chỉnh Long/Short: kéo dòng TP/SL/Entry để đổi giá, hoặc kéo phần thân để di chuyển cả cụm lệnh.
- Watchlist bên phải hiển thị các cặp tiền trong chiến lược; bấm vào cặp để mở dữ liệu theo timeframe đang chọn.
