<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/PaRunnerCoordinator.php';

function testPaRunnerCoordinatorSmokeFile(): void
{
    $directory = sys_get_temp_dir() . '/pa_runner_' . bin2hex(random_bytes(8));
    $coordinator = new PaRunnerCoordinator($directory, 45, 120);
    $local = str_repeat('a', 32);
    $vps = str_repeat('b', 32);
    $account = 'Broker-Demo:123456';
    $start = 1800000000;

    $first = $coordinator->heartbeat('local', $local, $account, $start);
    assertTrue($first['active'], 'local should acquire the initial lease');
    assertFalse($coordinator->heartbeat('vps', $vps, $account, $start + 1)['active'], 'vps must wait while local is active');
    assertSame('baseline', $coordinator->claimClosedBar('local', $local, $first['epoch'], 'EURUSD', $start - 900, $start)['status'], 'first closed bar only establishes baseline');
    assertSame('duplicate', $coordinator->claimClosedBar('local', $local, $first['epoch'], 'EURUSD', $start - 900, $start + 1)['status'], 'same candle must not be processed twice');
    assertSame('not_closed', $coordinator->claimClosedBar('local', $local, $first['epoch'], 'EURUSD', $start, $start + 1)['status'], 'forming candle is not claimable');
    assertFalse($coordinator->release('vps', $vps, $first['epoch'], $start + 2)['released'], 'standby cannot release local lease');

    $renewed = $coordinator->heartbeat('local', $local, $account, $start + 900);
    assertSame('claimed', $coordinator->claimClosedBar('local', $local, $renewed['epoch'], 'EURUSD', $start, $start + 910)['status'], 'fresh next candle must be claimed');
    assertTrue($coordinator->release('local', $local, $renewed['epoch'], $start + 911)['released'], 'local should release before manual handover');
    $next = $coordinator->heartbeat('vps', $vps, $account, $start + 912);
    assertTrue($next['active'], 'vps should acquire after local release');
    assertSame('not_owner', $coordinator->claimClosedBar('local', $local, $renewed['epoch'], 'EURUSD', $start + 900, $start + 1810)['status'], 'old epoch must be fenced out');
    assertSame('duplicate', $coordinator->claimClosedBar('vps', $vps, $next['epoch'], 'EURUSD', $start, $start + 913)['status'], 'handover must preserve last processed candle');

    $expired = $coordinator->heartbeat('local', $local, $account, $start + 1000);
    assertTrue($expired['active'], 'expired vps lease should be recoverable');
    assertSame('local', $coordinator->status($start + 1000)['active_host'], 'status should show active host');
    $late = $coordinator->heartbeat('local', $local, $account, $start + 2500);
    assertSame('stale', $coordinator->claimClosedBar('local', $local, $late['epoch'], 'EURUSD', $start + 900, $start + 2500)['status'], 'old candle must not become a delayed signal');

    $mismatch = false;
    try {
        $coordinator->heartbeat('vps', $vps, 'OtherBroker:999', $start + 1001);
    } catch (RuntimeException $error) {
        $mismatch = true;
    }
    assertTrue($mismatch, 'different MT5 account must not share runner state');
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    function assertTrue(bool $value, string $message): void
    {
        if (!$value) throw new RuntimeException($message);
    }
    function assertFalse(bool $value, string $message): void
    {
        assertTrue(!$value, $message);
    }
    function assertSame(mixed $expected, mixed $actual, string $message): void
    {
        if ($expected !== $actual) throw new RuntimeException($message . ': ' . var_export($actual, true));
    }
    testPaRunnerCoordinatorSmokeFile();
    echo "[PASS] manual local/vps runner coordination\n";
}
