#!/usr/bin/env python
"""Manual local/VPS MT5 candle observer. No AI decisions or order execution."""

import argparse
import json
import os
import secrets
import sys
import time
import urllib.error
import urllib.parse
import urllib.request


class RejectRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, request, fp, code, msg, headers, newurl):
        raise RuntimeError("Runner API redirects are not allowed")


def request_api(opener, url, token, payload):
    body = json.dumps(payload, separators=(",", ":")).encode("utf-8")
    request = urllib.request.Request(
        url,
        data=body,
        headers={"Authorization": "Bearer " + token, "Content-Type": "application/json"},
        method="POST",
    )
    try:
        with opener.open(request, timeout=8) as response:
            result = json.load(response)
    except urllib.error.HTTPError as error:
        raise RuntimeError("Runner API returned HTTP " + str(error.code)) from error
    if not isinstance(result, dict) or not result.get("ok"):
        raise RuntimeError(str(result.get("error", "Runner API rejected request")))
    return result


def validate_url(url):
    parsed = urllib.parse.urlparse(url)
    if parsed.scheme == "https" and parsed.hostname:
        return
    if parsed.scheme == "http" and parsed.hostname in ("localhost", "127.0.0.1", "::1"):
        return
    raise ValueError("Runner API requires HTTPS, except for localhost tests")


def account_identity(account):
    return str(account.server) + ":" + str(account.login)


def closed_bar_open(mt5, symbol):
    if not mt5.symbol_select(symbol, True):
        raise RuntimeError(symbol + ": symbol_select failed")
    rates = mt5.copy_rates_from_pos(symbol, mt5.TIMEFRAME_M15, 1, 1)
    if rates is None or len(rates) != 1:
        raise RuntimeError(symbol + ": closed M15 candle unavailable")
    return int(rates[0]["time"])


def main():
    parser = argparse.ArgumentParser(description="Manual MT5 local/VPS M15 runner (observe only)")
    parser.add_argument("--host", choices=("local", "vps"), required=True)
    parser.add_argument("--url", default="https://namtrade.click/pa_runner_api.php")
    parser.add_argument("--symbols", required=True, help="Comma-separated broker symbols")
    parser.add_argument("--terminal", default="", help="Optional terminal64.exe path")
    parser.add_argument("--interval", type=int, default=5)
    parser.add_argument("--once", action="store_true", help="Run one observation cycle")
    args = parser.parse_args()
    validate_url(args.url)
    token = os.environ.get("PA_RUNNER_TOKEN", "").strip()
    if len(token) < 32:
        parser.error("Set PA_RUNNER_TOKEN in the process environment first")
    symbols = list(dict.fromkeys(s.strip().upper() for s in args.symbols.split(",") if s.strip()))
    if not symbols:
        parser.error("At least one symbol is required")

    try:
        import MetaTrader5 as mt5
    except ImportError as error:
        raise RuntimeError("Install the MetaTrader5 Python package") from error

    initialized = mt5.initialize(path=args.terminal) if args.terminal else mt5.initialize()
    if not initialized:
        raise RuntimeError("MT5 initialization failed: " + str(mt5.last_error()))

    opener = urllib.request.build_opener(RejectRedirect())
    instance = secrets.token_hex(16)
    epoch = None
    last_state = None
    try:
        account = mt5.account_info()
        if account is None:
            raise RuntimeError("MT5 account is unavailable")
        identity = account_identity(account)
        print("Runner started: " + args.host + ", account " + identity, flush=True)
        while True:
            current = mt5.account_info()
            if current is None or account_identity(current) != identity:
                raise RuntimeError("MT5 disconnected or account changed; restart the runner")
            try:
                lease = request_api(opener, args.url, token, {
                    "action": "heartbeat", "host": args.host, "instance": instance, "account": identity,
                })
                active = bool(lease["active"])
                state = "active" if active else "standby (" + str(lease.get("active_host")) + " owns the lease)"
                if state != last_state:
                    print(state, flush=True)
                    last_state = state
                epoch = int(lease["epoch"]) if active else None
                if active:
                    for symbol in symbols:
                        try:
                            bar_open = closed_bar_open(mt5, symbol)
                            result = request_api(opener, args.url, token, {
                                "action": "claim_closed_bar", "host": args.host, "instance": instance,
                                "epoch": epoch, "symbol": symbol, "bar_open": bar_open,
                            })
                            status = result["status"]
                            if status not in ("duplicate",):
                                print(symbol + " M15 " + str(bar_open) + ": " + status + " (observe only)", flush=True)
                            if status == "not_owner":
                                epoch = None
                                break
                        except (RuntimeError, KeyError, ValueError) as error:
                            print("Observation error: " + str(error), file=sys.stderr, flush=True)
            except (RuntimeError, KeyError, ValueError, urllib.error.URLError) as error:
                print("Runner paused: " + str(error), file=sys.stderr, flush=True)
                last_state = None
                epoch = None
            if args.once:
                break
            time.sleep(max(2, args.interval))
    except KeyboardInterrupt:
        print("Stopping runner", flush=True)
    finally:
        if epoch is not None:
            try:
                request_api(opener, args.url, token, {
                    "action": "release", "host": args.host, "instance": instance, "epoch": epoch,
                })
            except Exception:
                pass
        mt5.shutdown()


if __name__ == "__main__":
    try:
        main()
    except (RuntimeError, ValueError) as error:
        print(str(error), file=sys.stderr)
        sys.exit(1)
