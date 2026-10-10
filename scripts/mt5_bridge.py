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
    print(json.dumps(payload, ensure_ascii=False, separators=(",", ":")))
    sys.exit(code)


def import_mt5():
    try:
        import MetaTrader5 as mt5
        return mt5
    except Exception as exc:
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


def existing_bar_count(path):
    if not os.path.isfile(path):
        return 0
    try:
        count = 0
        needle = b'"time":'
        tail = b""
        with open(path, "rb") as handle:
            while True:
                chunk = handle.read(1024 * 1024)
                if not chunk:
                    break
                scan = tail + chunk
                count += scan.count(needle) - tail.count(needle)
                tail = scan[-(len(needle) - 1) :]
        return count
    except Exception:
        return 0


def write_market_data(output_dir, symbol, timeframe, fetched_at, bars):
    os.makedirs(output_dir, exist_ok=True)
    filename = f"{safe_name(symbol)}_{safe_name(timeframe)}.json"
    path = os.path.join(output_dir, filename)
    incoming_count = len(bars)
    existing_count = existing_bar_count(path)
    if existing_count > incoming_count:
        return {
            "symbol": symbol,
            "timeframe": timeframe,
            "bars": existing_count,
            "fetched_bars": incoming_count,
            "file": f"market_data/{filename}",
            "kept_existing": True,
        }

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


def initialize(mt5, terminal):
    if terminal:
        ok = mt5.initialize(path=terminal)
    else:
        ok = mt5.initialize()
    if not ok:
        code, message = mt5.last_error()
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
    mt5 = import_mt5()
    initialize(mt5, args.terminal)
    try:
        symbols = [s.strip().upper() for s in args.symbols.split(",") if s.strip()]
        timeframes = [t.strip().upper() for t in args.timeframes.split(",") if t.strip()]
        bars_by_timeframe = parse_bars_by_timeframe(args.bars_by_timeframe)
        output_dir = args.output_dir.strip()
        fetched_at = datetime.now(timezone.utc).isoformat()
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
                tf_const_name = TIMEFRAMES.get(tf)
                if not tf_const_name:
                    result["errors"].append({"symbol": symbol, "timeframe": tf, "error": "Timeframe không hỗ trợ"})
                    continue
                tf_const = getattr(mt5, tf_const_name)
                request_bars = int(bars_by_timeframe.get(tf, args.bars))
                rates = mt5.copy_rates_from_pos(symbol, tf_const, 0, request_bars)
                if rates is None:
                    code, message = mt5.last_error()
                    result["errors"].append(
                        {
                            "symbol": symbol,
                            "timeframe": tf,
                            "error": "Không lấy được dữ liệu nến",
                            "mt5_error": {"code": code, "message": message},
                        }
                    )
                    continue
                bars = [normalize_record(row) for row in rates]
                if output_dir:
                    result["written"].append(write_market_data(output_dir, symbol, tf, fetched_at, bars))
                else:
                    result["data"][symbol][tf] = bars

        emit(result)
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
    fetch_parser.add_argument("--bars", type=int, default=10000, help="Số nến cần lấy cho mỗi symbol/timeframe")

    fetch_parser.add_argument("--bars-by-timeframe", default="", help="Example: H4:62500,M15:1000000")
    fetch_parser.add_argument("--output-dir", default="", help="Write market data files to disk")

    args = parser.parse_args()
    if args.command == "status":
        status(args)
    if args.command == "fetch":
        fetch(args)


if __name__ == "__main__":
    main()
