# PROJECT_STATUS

Audit date: 2026-06-10

## Stack

- Backend: PHP app chạy trực tiếp qua XAMPP Apache hoặc PHP built-in server.
- Frontend: `index.php` render HTML, `assets/app.js` là JavaScript vanilla, `assets/styles.css` là CSS vanilla.
- Storage: local JSON/JSONL trong `data/`, qua `lib/JsonStore.php`; chưa có database thật.
- MT5 bridge: Python script `scripts/mt5_bridge.py` dùng package `MetaTrader5` để fetch candle data. Không có order execution trong app hiện tại.
- Agent scaffold phụ: TypeScript dry-run orchestrator trong `scripts/agent-orchestrator.ts` và `agents/`; không chạy code change/live trading mặc định.

## Dữ Liệu Nến MT5

- Candle files lưu tại `data/market_data/*.json`.
- Tên file hiện theo pattern `{SYMBOL}_{TIMEFRAME}.json`, ví dụ `data/market_data/EURUSD_M15.json`.
- Mỗi file là payload:
  - `symbol`
  - `timeframe`
  - `source`
  - `fetched_at`
  - `bars`
- Mỗi candle trong `bars[]` hiện gồm:
  - `time` Unix timestamp
  - `time_iso`
  - `open`
  - `high`
  - `low`
  - `close`
  - `tick_volume`
  - `spread`
  - `real_volume`

## UI Hiện Có

- Trang biểu đồ/MT5: `index.php` tab `mt5`, canvas `#marketChart`; logic chính ở `assets/app.js` (`renderMt5`, `loadMarketData`, `drawMarketChart`).
- Trang chiến lược: `index.php` tab `strategy`; logic ở `assets/app.js` (`renderStrategy`, `readStrategyForm`, `saveStrategy`).
- Trang tài khoản/risk: `index.php` tab `accounts` và dashboard risk preview; logic ở `assets/app.js` (`renderAccounts`, `renderRiskPreview`, `updateRiskOutput`), tính risk ở `lib/RiskCalculator.php`.
- Trang Day AI/dạy AI: `index.php` tab `trainer`; logic ở `assets/app.js` (`renderTrainer`, `scoreActiveCase`, `reviewActiveCase`).
- Trang Bộ nhớ: `index.php` tab `memory`; export/import qua `api.php?action=export_memory/import_memory`.

## Backtest / Strategy / AI Trước Khi Mở Rộng

- Đã có `lib/BacktestEngine.php`: engine deterministic PHP dùng EMA20/EMA50, ATR, spread/slippage/fee cơ bản, trade log và metrics. Trước task này chưa được expose đầy đủ qua API/UI riêng.
- Đã có backtest demo client-side trong `assets/app.js` (`BACKTEST_RUNS`, `simulateBacktestRun`) để overlay chart, mang tính phân tích UI.
- Đã có `lib/StrategyVersionService.php` nhưng trước task này chưa có API/UI đủ cho tree/diff/approve/reject và chưa đủ field validation/final/approval.
- Trade log giả lập có schema trong `lib/BacktestEngine.php` và lưu vào `data/backtests/backtest_trades.json` khi engine PHP được gọi.
- AI service trước task này là mock reviewer `lib/StrategyAiReviewer.php`; chưa có TradeBrain profile/memory/decision log/evolution flow đầy đủ.
- Data quality đã có `lib/DataQualityChecker.php` kiểm tra timestamp, OHLC, volume, gap, duplicate và timeframe spacing.

## Scripts Build/Lint/Test

- Không có `package.json`.
- Không có Composer config.
- Không có script build/lint/test chuẩn trong repo.
- Có thể kiểm tra PHP bằng `php -l` từng file và test runner tự tạo.

## Rủi Ro Kỹ Thuật

- Một số text tiếng Việt trong dữ liệu/config đang bị mojibake do encoding cũ; JSON vẫn đọc được nhưng UI hiển thị không sạch ở vài chỗ.
- Storage local JSON chưa có transaction/locking cấp database; nhiều request ghi đồng thời có thể race.
- Market data là file JSON lớn, đọc nguyên file có thể chậm khi mở rộng lên nhiều symbol/timeframe hơn.
- Backtest engine hiện là proxy deterministic EMA/ATR, chưa phải parser đầy đủ mọi luật discretionary H4 supply/demand/QML của chiến lược gốc.
- News filter chỉ là flag/rule placeholder, chưa có economic calendar feed.
- Data gap do weekend/market close cần phân biệt với missing data thật; hiện data quality báo warning.
- App local chưa có auth/permission layer.
- Không phải git repo trong thư mục hiện tại, nên không có lịch sử commit để rollback bằng git.
- Chưa có database migration; schema mới đang được lưu file JSON để sau này thay bằng DB.

## Safety

- Không có live trading trong task này.
- Không gọi OrderSend hoặc API tương đương.
- Không sửa/xóa API key/secrets.
- Module live chỉ được thiết kế dạng stub fail-closed.
