<?php

declare(strict_types=1);

final class PaRunnerCoordinator
{
    private string $directory;
    private int $leaseSeconds;
    private int $freshSeconds;

    public function __construct(string $directory, int $leaseSeconds = 45, int $freshSeconds = 120)
    {
        $this->directory = rtrim($directory, '/\\');
        $this->leaseSeconds = max(15, $leaseSeconds);
        $this->freshSeconds = max(15, $freshSeconds);
    }

    public function heartbeat(string $host, string $instance, string $account, ?int $now = null): array
    {
        $this->validateIdentity($host, $instance);
        $account = trim($account);
        if ($account === '' || strlen($account) > 128) {
            throw new InvalidArgumentException('Invalid MT5 account identity.');
        }
        $accountHash = hash('sha256', $account);
        $now ??= time();

        return $this->locked(function (array &$state, bool &$dirty) use ($host, $instance, $accountHash, $now): array {
            if ($state['account'] !== null && $state['account'] !== $accountHash) {
                throw new RuntimeException('MT5 account differs from the account bound to this runner.');
            }

            $owner = $state['owner'];
            if ($owner === null || (int)$owner['lease_until'] <= $now) {
                $state['account'] = $accountHash;
                $state['epoch']++;
                $state['owner'] = [
                    'host' => $host,
                    'instance' => $instance,
                    'epoch' => $state['epoch'],
                    'lease_until' => $now + $this->leaseSeconds,
                    'last_seen_at' => $now,
                ];
                $dirty = true;
            } elseif ($owner['host'] === $host && $owner['instance'] === $instance) {
                $state['owner']['lease_until'] = $now + $this->leaseSeconds;
                $state['owner']['last_seen_at'] = $now;
                $dirty = true;
            }

            return $this->ownerResponse($state, $host, $instance, $now);
        });
    }

    public function release(string $host, string $instance, int $epoch, ?int $now = null): array
    {
        $this->validateIdentity($host, $instance);
        $now ??= time();
        return $this->locked(function (array &$state, bool &$dirty) use ($host, $instance, $epoch, $now): array {
            $released = $this->isOwner($state, $host, $instance, $epoch, $now);
            if ($released) {
                $state['owner'] = null;
                $state['epoch']++;
                $dirty = true;
            }
            return ['ok' => true, 'released' => $released];
        });
    }

    public function claimClosedBar(string $host, string $instance, int $epoch, string $symbol, int $barOpen, ?int $now = null): array
    {
        $this->validateIdentity($host, $instance);
        $symbol = strtoupper(trim($symbol));
        if (!preg_match('/^[A-Z0-9._-]{2,32}$/', $symbol)) {
            throw new InvalidArgumentException('Invalid MT5 symbol.');
        }
        if ($barOpen <= 0) {
            throw new InvalidArgumentException('Invalid candle time.');
        }
        $now ??= time();

        return $this->locked(function (array &$state, bool &$dirty) use ($host, $instance, $epoch, $symbol, $barOpen, $now): array {
            if (!$this->isOwner($state, $host, $instance, $epoch, $now)) {
                return ['ok' => true, 'status' => 'not_owner'];
            }
            $barClose = $barOpen + 900;
            if ($barClose > $now) {
                return ['ok' => false, 'status' => 'not_closed'];
            }
            $last = $state['last_bars'][$symbol] ?? null;
            if ($last !== null && $barOpen <= (int)$last) {
                return ['ok' => true, 'status' => 'duplicate'];
            }

            $state['last_bars'][$symbol] = $barOpen;
            $dirty = true;
            $status = $last === null ? 'baseline' : ($now - $barClose > $this->freshSeconds ? 'stale' : 'claimed');
            $state['recent_bars'][] = [
                'symbol' => $symbol,
                'bar_open' => $barOpen,
                'status' => $status,
                'host' => $host,
                'observed_at' => $now,
            ];
            $state['recent_bars'] = array_slice($state['recent_bars'], -100);
            return ['ok' => true, 'status' => $status, 'symbol' => $symbol, 'bar_open' => $barOpen];
        });
    }

    public function status(?int $now = null): array
    {
        $now ??= time();
        return $this->locked(function (array &$state, bool &$dirty) use ($now): array {
            $owner = $state['owner'];
            $active = $owner !== null && (int)$owner['lease_until'] > $now;
            return [
                'ok' => true,
                'active_host' => $active ? $owner['host'] : null,
                'lease_until' => $active ? $owner['lease_until'] : null,
                'account_bound' => $state['account'] !== null,
                'last_bars' => $state['last_bars'],
                'recent_bars' => array_slice($state['recent_bars'], -20),
            ];
        });
    }

    private function ownerResponse(array $state, string $host, string $instance, int $now): array
    {
        $owner = $state['owner'];
        $active = $owner !== null && $owner['host'] === $host && $owner['instance'] === $instance
            && (int)$owner['lease_until'] > $now;
        return [
            'ok' => true,
            'active' => $active,
            'active_host' => $owner['host'] ?? null,
            'lease_until' => $owner['lease_until'] ?? null,
            'epoch' => $active ? (int)$owner['epoch'] : null,
        ];
    }

    private function isOwner(array $state, string $host, string $instance, int $epoch, int $now): bool
    {
        $owner = $state['owner'];
        return $owner !== null && $owner['host'] === $host && $owner['instance'] === $instance
            && (int)$owner['epoch'] === $epoch && (int)$owner['lease_until'] > $now;
    }

    private function validateIdentity(string $host, string $instance): void
    {
        if (!in_array($host, ['local', 'vps'], true) || !preg_match('/^[a-f0-9]{32}$/', $instance)) {
            throw new InvalidArgumentException('Invalid runner identity.');
        }
    }

    private function locked(callable $callback): array
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0770, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Cannot create runner state directory.');
        }
        $lock = fopen($this->directory . '/state.lock', 'c+');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            throw new RuntimeException('Cannot lock runner state.');
        }
        try {
            $path = $this->directory . '/state.json';
            $stored = is_file($path) ? json_decode((string)file_get_contents($path), true) : null;
            if (is_file($path) && !is_array($stored)) {
                throw new RuntimeException('Runner state is unreadable; refusing to acquire lease.');
            }
            $state = is_array($stored) ? $stored : [
                'version' => 1,
                'epoch' => 0,
                'account' => null,
                'owner' => null,
                'last_bars' => [],
                'recent_bars' => [],
            ];
            $dirty = false;
            $result = $callback($state, $dirty);
            if ($dirty) {
                $encoded = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                $temp = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
                if (file_put_contents($temp, $encoded, LOCK_EX) === false || !rename($temp, $path)) {
                    @unlink($temp);
                    throw new RuntimeException('Cannot save runner state.');
                }
            }
            return $result;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
