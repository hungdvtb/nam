#!/usr/bin/env python
import argparse
import json
import os
import re
import sys
from datetime import datetime, timezone


TIMEFRAMES = {
    "M1": "TIMEFRAME_M1",
    "M5": "TIMEFRAME_M5",
    "M15": "TIMEFRAME_M15",
    "M30": "TIMEFRAME_M30",
    "H1": "TIMEFRAME_H1",
    "H4": "TIMEFRAME_H4",
    "D1": "TIMEFRAME_D1",
}


def emit(payload, code=0):
    # Keep stdout ASCII-safe so Windows console code pages cannot fail on Vietnamese.
    print(json.dumps(payload, ensure_ascii=True, separators=(",", ":")))
    sys.exit(code)


def write_progress(path, payload):
    if not path:
        return
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        tmp_path = f"{path}.tmp"
        with open(tmp_path, "w", encoding="utf-8") as handle:
            json.dump(payload, handle, ensure_ascii=False, separators=(",", ":"))
            handle.write("\n")
        os.replace(tmp_path, path)
    except Exception:
        pass


def import_mt5(progress_file=""):
    try:
        import MetaTrader5 as mt5
        return mt5
    except Exception as exc:
        write_progress(progress_file, {"status": "failed", "message": "Không import được package MetaTrader5.", "error": str(exc)})
        emit(
            {
                "ok": False,
                "error": "Không import được package MetaTrader5.",
                "detail": str(exc),
                "install_hint": "Cài Python 64-bit, sau đó chạy: pip install MetaTrader5",
            },
            2,
        )


def as_dict(value):
    if value is None:
        return None
    if hasattr(value, "_asdict"):
        return value._asdict()
    return dict(value)


def normalize_record(record):
    item = {}
    for name in record.dtype.names:
        value = record[name].item() if hasattr(record[name], "item") else record[name]
        if name == "time":
            item["time"] = int(value)
            item["time_iso"] = datetime.fromtimestamp(int(value), tz=timezone.utc).isoformat()
        else:
            item[name] = float(value) if isinstance(value, float) else int(value)
    return item


def copy_rates_with_fallback(mt5, symbol, timeframe, count, chunk_size=10000, on_chunk=None):
    rates = mt5.copy_rates_from_pos(symbol, timeframe, 0, count)
    if rates is not None:
        return list(rates), None

    code, message = mt5.last_error()
    if code != -2 or count <= chunk_size:
        return None, {"code": code, "message": message}

    rows = []
    for start_pos in range(0, count, chunk_size):
        requested = min(chunk_size, count - start_pos)
        if on_chunk:
            on_chunk(start_pos // chunk_size + 1, (count + chunk_size - 1) // chunk_size)
        chunk = mt5.copy_rates_from_pos(symbol, timeframe, start_pos, requested)
        if chunk is None:
            code, message = mt5.last_error()
            if not rows:
                return None, {"code": code, "message": message}
            break
        chunk_rows = list(chunk)
        if not chunk_rows:
            break
        rows.extend(chunk_rows)
        if len(chunk_rows) < requested:
            break

    # copy_rates_from_pos addresses bars from newest to oldest by start_pos;
    # the saved chart dataset is kept in chronological order.
    unique_rows = {int(row["time"]): row for row in rows}
    ordered = [unique_rows[key] for key in sorted(unique_rows)]
    warning = None
    if len(ordered) < count:
        warning = {"code": -2, "message": f"MT5 trả {len(ordered)} / {count} nến sau khi chia lô"}
    return ordered, warning


def safe_name(value):
    return re.sub(r"[^A-Za-z0-9_\-]", "_", str(value).upper())


def parse_bars_by_timeframe(raw):
    result = {}
    if not raw:
        return result
    for part in str(raw).split(","):
        if ":" not in part:
            continue
        timeframe, count = part.split(":", 1)
        timeframe = timeframe.strip().upper()
        try:
            bars = int(float(count.strip()))
        except ValueError:
            continue
        if timeframe and bars > 0:
            result[timeframe] = bars
    return result


def write_market_data(output_dir, symbol, timeframe, fetched_at, bars):
    os.makedirs(output_dir, exist_ok=True)
    filename = f"{safe_name(symbol)}_{safe_name(timeframe)}.json"
    path = os.path.join(output_dir, filename)
    incoming_count = len(bars)
    payload = {
        "symbol": symbol,
        "timeframe": timeframe,
        "source": "MT5",
        "fetched_at": fetched_at,
        "bars": bars,
    }
    tmp_path = f"{path}.tmp"
    with open(tmp_path, "w", encoding="utf-8") as handle:
        json.dump(payload, handle, ensure_ascii=False, separators=(",", ":"))
        handle.write("\n")
    os.replace(tmp_path, path)
    return {
        "symbol": symbol,
        "timeframe": timeframe,
        "bars": incoming_count,
        "fetched_bars": incoming_count,
        "file": f"market_data/{filename}",
        "kept_existing": False,
    }


def initialize(mt5, terminal, progress_file=""):
    if terminal:
        ok = mt5.initialize(path=terminal)
    else:
        ok = mt5.initialize()
    if not ok:
        code, message = mt5.last_error()
        write_progress(progress_file, {"status": "failed", "message": "Không kết nối được MT5.", "mt5_error": {"code": code, "message": message}})
        emit(
            {
                "ok": False,
                "error": "Không kết nối được MT5. Hãy mở MT5, đăng nhập tài khoản Exness demo và thử lại.",
                "mt5_error": {"code": code, "message": message},
            },
            3,
        )


def status(args):
    mt5 = import_mt5()
    initialize(mt5, args.terminal)
    try:
        account = as_dict(mt5.account_info())
        terminal = as_dict(mt5.terminal_info())
        version = mt5.version()
        emit(
            {
                "ok": True,
                "connected": True,
                "version": list(version) if version else None,
                "account": account,
                "terminal": terminal,
                "time": datetime.now(timezone.utc).isoformat(),
            }
        )
    finally:
        mt5.shutdown()


def fetch(args):
    mt5 = import_mt5(args.progress_file)
    write_progress(args.progress_file, {"status": "connecting", "message": "Đang kết nối MT5", "completed": 0, "total": 0})
    initialize(mt5, args.terminal, args.progress_file)
    try:
        symbols = [s.strip().upper() for s in args.symbols.split(",") if s.strip()]
        timeframes = [t.strip().upper() for t in args.timeframes.split(",") if t.strip()]
        bars_by_timeframe = parse_bars_by_timeframe(args.bars_by_timeframe)
        output_dir = args.output_dir.strip()
        fetched_at = datetime.now(timezone.utc).isoformat()
        total = len(symbols) * len(timeframes)
        progress = {
            "status": "running",
            "started_at": fetched_at,
            "completed": 0,
            "total": total,
            "written": [],
            "errors": [],
        }
        write_progress(args.progress_file, progress)
        result = {
            "ok": True,
            "fetched_at": fetched_at,
            "bars_requested": args.bars,
            "bars_by_timeframe": bars_by_timeframe,
            "data": {},
            "written": [],
            "errors": [],
        }

        for symbol in symbols:
            mt5.symbol_select(symbol, True)
            result["data"].setdefault(symbol, {})
            for tf in timeframes:
                progress.update({"status": "running", "current_symbol": symbol, "current_timeframe": tf})
                write_progress(args.progress_file, progress)
                tf_const_name = TIMEFRAMES.get(tf)
                if not tf_const_name:
                    result["errors"].append({"symbol": symbol, "timeframe": tf, "error": "Timeframe không hỗ trợ"})
                    progress["errors"] = result["errors"]
                    progress["completed"] += 1
                    write_progress(args.progress_file, progress)
                    continue
                tf_const = getattr(mt5, tf_const_name)
                request_bars = int(bars_by_timeframe.get(tf, args.bars))
                def report_chunk(chunk_number, chunk_total):
                    progress.update({"current_chunk": chunk_number, "chunk_total": chunk_total})
                    write_progress(args.progress_file, progress)

                rates, fetch_warning = copy_rates_with_fallback(mt5, symbol, tf_const, request_bars, on_chunk=report_chunk)
                progress.pop("current_chunk", None)
                progress.pop("chunk_total", None)
                if rates is None:
                    result["errors"].append(
                        {
                            "symbol": symbol,
                            "timeframe": tf,
                            "error": "Không lấy được dữ liệu nến",
                            "mt5_error": fetch_warning,
                        }
                    )
                    progress["errors"] = result["errors"]
                    progress["completed"] += 1
                    write_progress(args.progress_file, progress)
                    continue
                bars = [normalize_record(row) for row in rates]
                if fetch_warning:
                    result["errors"].append({"symbol": symbol, "timeframe": tf, "error": "MT5 chỉ trả về một phần số nến yêu cầu", "mt5_error": fetch_warning})
                if not bars:
                    result["errors"].append({"symbol": symbol, "timeframe": tf, "error": "MT5 trả về 0 nến; giữ nguyên file cũ nếu có"})
                    progress["errors"] = result["errors"]
                    progress["completed"] += 1
                    write_progress(args.progress_file, progress)
                    continue
                if output_dir:
                    result["written"].append(write_market_data(output_dir, symbol, tf, fetched_at, bars))
                else:
                    result["data"][symbol][tf] = bars
                progress["written"] = result["written"]
                progress["fetched_bars"] = len(bars)
                progress["completed"] += 1
                write_progress(args.progress_file, progress)

        progress.update({"status": "complete", "finished_at": datetime.now(timezone.utc).isoformat(), "written": result["written"], "errors": result["errors"]})
        write_progress(args.progress_file, progress)
        emit(result)
    except Exception as exc:
        failed = dict(locals().get("progress", {}))
        partial_result = locals().get("result", {})
        failed.update({
            "status": "failed",
            "message": "Tiến trình MT5 bị lỗi trong khi lấy dữ liệu.",
            "error": str(exc),
            "written": partial_result.get("written", failed.get("written", [])),
            "errors": partial_result.get("errors", failed.get("errors", [])),
        })
        write_progress(args.progress_file, failed)
        emit({"ok": False, "error": "Tiến trình MT5 bị lỗi trong khi lấy dữ liệu.", "detail": str(exc)}, 4)
    finally:
        mt5.shutdown()


def main():
    parser = argparse.ArgumentParser(description="AI Trade MT5 bridge")
    subparsers = parser.add_subparsers(dest="command", required=True)

    status_parser = subparsers.add_parser("status")
    status_parser.add_argument("--terminal", default="", help="Đường dẫn terminal64.exe nếu cần")

    fetch_parser = subparsers.add_parser("fetch")
    fetch_parser.add_argument("--terminal", default="", help="Đường dẫn terminal64.exe nếu cần")
    fetch_parser.add_argument("--symbols", required=True, help="Danh sách symbol, ví dụ EURUSD,GBPUSD")
    fetch_parser.add_argument("--timeframes", default="H4,M15", help="Ví dụ H4,M15")
    fetch_parser.add_argument("--bars", type=int, default=100000, help="Số nến M15 chuẩn; các khung khác có thể truyền qua --bars-by-timeframe")

    fetch_parser.add_argument("--bars-by-timeframe", default="", help="Example: H4:62500,M15:1000000")
    fetch_parser.add_argument("--output-dir", default="", help="Write market data files to disk")
    fetch_parser.add_argument("--progress-file", default="", help="Write progress status to a JSON file")

    args = parser.parse_args()
    if args.command == "status":
        status(args)
    if args.command == "fetch":
        fetch(args)


if __name__ == "__main__":
    main()
