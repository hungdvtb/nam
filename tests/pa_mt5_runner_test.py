import importlib.util
import pathlib
import sys
import unittest


sys.dont_write_bytecode = True
SCRIPT = pathlib.Path(__file__).resolve().parents[1] / "scripts" / "pa_mt5_runner.py"
SPEC = importlib.util.spec_from_file_location("pa_mt5_runner", SCRIPT)
runner = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(runner)


class FakeMt5:
    TIMEFRAME_M15 = 15

    def __init__(self):
        self.request = None

    def symbol_select(self, symbol, enabled):
        return True

    def copy_rates_from_pos(self, symbol, timeframe, start, count):
        self.request = (symbol, timeframe, start, count)
        return [{"time": 1800000000}]


class RunnerTest(unittest.TestCase):
    def test_reads_last_closed_candle(self):
        mt5 = FakeMt5()
        self.assertEqual(1800000000, runner.closed_bar_open(mt5, "EURUSD"))
        self.assertEqual(("EURUSD", 15, 1, 1), mt5.request)

    def test_remote_api_requires_https(self):
        runner.validate_url("https://namtrade.click/pa_runner_api.php")
        runner.validate_url("http://127.0.0.1:18088/pa_runner_api.php")
        with self.assertRaises(ValueError):
            runner.validate_url("http://namtrade.click/pa_runner_api.php")


if __name__ == "__main__":
    unittest.main()
