<?php

declare(strict_types=1);

require_once __DIR__ . '/StrategyRuleParser.php';

final class CalibratedPresetService
{
    private const FILE = 'calibrated_presets.json';

    public function __construct(private JsonStore $store)
    {
    }

    public function list(): array
    {
        $items = $this->store->readJson(self::FILE, []);
        if (!is_array($items)) {
            return [];
        }
        usort($items, fn($a, $b) => strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? '')));
        return array_values(array_filter($items, 'is_array'));
    }

    public function find(string $name): ?array
    {
        $name = strtolower(trim($name));
        foreach ($this->list() as $preset) {
            if (strtolower((string)($preset['name'] ?? '')) === $name) {
                return $preset;
            }
        }
        return null;
    }

    public function create(array $strategyVersion, string $basePreset, array $recommendations): array
    {
        $basePreset = strtolower(trim($basePreset)) ?: 'balanced';
        $rawRules = is_array($strategyVersion['rulesJson'] ?? null) ? $strategyVersion['rulesJson'] : [];
        $baseRules = StrategyRuleParser::normalize(StrategyRuleParser::applyBacktestPreset($rawRules, $basePreset));
        $tunedRules = $baseRules;
        $applied = [];

        foreach ($recommendations as $recommendation) {
            if (!is_array($recommendation)) {
                continue;
            }
            $field = trim((string)($recommendation['field'] ?? ''));
            if ($field === '') {
                continue;
            }
            $this->setPath($tunedRules, explode('.', $field), $recommendation['suggestedValue'] ?? null);
            $applied[] = [
                'field' => $field,
                'oldValue' => $recommendation['currentValue'] ?? $this->getPath($baseRules, explode('.', $field)),
                'newValue' => $recommendation['suggestedValue'] ?? null,
                'reason' => (string)($recommendation['reason'] ?? ''),
            ];
        }

        $name = $this->nextName($basePreset);
        $tunedRules['backtestPreset'] = $name;
        $tunedRules = StrategyRuleParser::normalize($tunedRules);
        $tunedRules['backtestPreset'] = $name;

        $preset = [
            'id' => 'preset_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4)),
            'name' => $name,
            'basePreset' => $basePreset,
            'baseStrategyVersionId' => (string)($strategyVersion['id'] ?? ''),
            'createdAt' => gmdate('c'),
            'createdBy' => 'calibration_advisor',
            'status' => 'pending_user_approval',
            'requiresUserApproval' => true,
            'rules' => $tunedRules,
            'appliedRecommendations' => $applied,
            'diff' => $this->diffArrays($baseRules, $tunedRules),
        ];

        $items = $this->list();
        array_unshift($items, $preset);
        $this->store->writeJson(self::FILE, $items);
        return $preset;
    }

    private function nextName(string $basePreset): string
    {
        $max = 0;
        $prefix = preg_quote($basePreset, '/');
        foreach ($this->list() as $preset) {
            $name = (string)($preset['name'] ?? '');
            if (preg_match('/^' . $prefix . '-calibrated-v(\d+)$/', $name, $match)) {
                $max = max($max, (int)$match[1]);
            }
        }
        return $basePreset . '-calibrated-v' . ($max + 1);
    }

    private function setPath(array &$target, array $parts, mixed $value): void
    {
        $key = array_shift($parts);
        if ($key === null || $key === '') {
            return;
        }
        if (!$parts) {
            $target[$key] = $value;
            return;
        }
        if (!isset($target[$key]) || !is_array($target[$key])) {
            $target[$key] = [];
        }
        $this->setPath($target[$key], $parts, $value);
    }

    private function getPath(array $target, array $parts): mixed
    {
        $current = $target;
        foreach ($parts as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return null;
            }
            $current = $current[$part];
        }
        return $current;
    }

    private function diffArrays(array $from, array $to, string $prefix = ''): array
    {
        $changes = [];
        $keys = array_unique(array_merge(array_keys($from), array_keys($to)));
        sort($keys);
        foreach ($keys as $key) {
            $path = $prefix === '' ? (string)$key : $prefix . '.' . $key;
            $oldExists = array_key_exists($key, $from);
            $newExists = array_key_exists($key, $to);
            $old = $oldExists ? $from[$key] : null;
            $new = $newExists ? $to[$key] : null;
            if (is_array($old) && is_array($new) && !$this->isList($old) && !$this->isList($new)) {
                $changes = array_merge($changes, $this->diffArrays($old, $new, $path));
                continue;
            }
            if (!$oldExists || !$newExists || json_encode($old) !== json_encode($new)) {
                $changes[] = [
                    'field' => $path,
                    'oldValue' => $oldExists ? $old : null,
                    'newValue' => $newExists ? $new : null,
                ];
            }
        }
        return $changes;
    }

    private function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }
        return array_keys($value) === range(0, count($value) - 1);
    }
}
