<?php

declare(strict_types=1);

final class StrategyRuleParser
{
    public static function presetNames(): array
    {
        return array_values(array_unique(array_merge(self::builtinPresetNames(), self::calibratedPresetNames())));
    }

    public static function builtinPresetNames(): array
    {
        return ['strict', 'balanced', 'loose-debug'];
    }

    public static function applyBacktestPreset(array $rules, string $preset): array
    {
        $preset = strtolower(trim($preset));
        if ($preset === '' || $preset === 'current') {
            return $rules;
        }
        if (!in_array($preset, self::builtinPresetNames(), true)) {
            $calibrated = self::calibratedPreset($preset);
            if ($calibrated !== null && is_array($calibrated['rules'] ?? null)) {
                $calibrated['rules']['backtestPreset'] = (string)($calibrated['name'] ?? $preset);
                return $calibrated['rules'];
            }
            return $rules;
        }

        $overrides = match ($preset) {
            'strict' => [
                'backtestPreset' => 'strict',
                'h4Rules' => [
                    'minZoneStrength' => 85,
                    'maxZoneTouches' => 1,
                    'requireBosForZone' => true,
                    'freshZoneOnly' => true,
                ],
                'm15Rules' => [
                    'requireStructureBreak' => true,
                    'requireReactionAtZone' => true,
                    'requireQmlOrFakeBreak' => false,
                    'avoidDirtySideway' => true,
                    'sidewayMaxRangeAtr' => 4.0,
                ],
            ],
            'balanced' => [
                'backtestPreset' => 'balanced',
                'h4Rules' => [
                    'minZoneStrength' => 65,
                    'maxZoneTouches' => 3,
                    'requireBosForZone' => true,
                    'freshZoneOnly' => false,
                ],
                'm15Rules' => [
                    'requireStructureBreak' => true,
                    'requireReactionAtZone' => true,
                    'requireQmlOrFakeBreak' => false,
                    'avoidDirtySideway' => false,
                    'sidewayMaxRangeAtr' => 4.5,
                ],
            ],
            'loose-debug' => [
                'backtestPreset' => 'loose-debug',
                'h4Rules' => [
                    'minZoneStrength' => 45,
                    'maxZoneTouches' => 8,
                    'requireBosForZone' => false,
                    'freshZoneOnly' => false,
                    'allowCounterTrendOnlyIfZoneStrong' => false,
                ],
                'm15Rules' => [
                    'requireStructureBreak' => false,
                    'requireReactionAtZone' => true,
                    'requireQmlOrFakeBreak' => false,
                    'avoidDirtySideway' => false,
                    'sidewayMaxRangeAtr' => 0.0,
                ],
            ],
        };

        return array_replace_recursive($rules, $overrides);
    }

    public static function normalize(array $rules): array
    {
        $defaults = [
            'symbols' => [],
            'contextTimeframe' => 'H4',
            'entryTimeframe' => 'M15',
            'entryMode' => 'LIMIT',
            'directionAllowed' => 'BUY_SELL',
            'minRR' => 3,
            'maxRiskPercent' => 3,
            'riskPercent' => 1,
            'maxOpenTrades' => 1,
            'debugLevel' => 1,
            'backtestPreset' => 'current',
            'setupTypes' => ['h4_supply_demand_m15_structure'],
            'zoneLifecycle' => [
                'requireCloseBeyondZoneToBreak' => true,
                'allowWickThrough' => true,
                'wickToleranceAtr' => 0.25,
                'breakBufferAtr' => 0.10,
                'maxTouchesBeforeWeak' => 2,
                'maxTouchesBeforeInvalid' => 4,
                'markTouchedButNotBroken' => true,
            ],
            'h4Rules' => [
                'detectTrend' => true,
                'trendMethod' => 'market_structure',
                'detectSupplyDemandZones' => true,
                'minZoneStrength' => 70,
                'minImpulseSize' => 0,
                'maxZoneTouches' => 0,
                'requireBosForZone' => false,
                'freshZoneOnly' => false,
                'allowCounterTrendOnlyIfZoneStrong' => true,
                'counterTrendZoneStrengthMin' => 85,
                'fractalPeriod' => 2,
                'swingLookback' => 2,
                'structureBreakLookback' => 12,
                'zoneLifecycle' => [],
            ],
            'm15Rules' => [
                'requireStructureBreak' => true,
                'requireQmlOrFakeBreak' => false,
                'requireReactionAtZone' => true,
                    'requirePriceActionPattern' => false,
                    'allowedPriceActionPatterns' => ['rejection_wick', 'engulfing', 'liquidity_sweep', 'choch'],
                    'minRejectionWickRatio' => 0.25,
                    'engulfingRequiresBodyClose' => true,
                'avoidDirtySideway' => true,
                'fractalPeriod' => 2,
                'swingLookback' => 2,
                'sidewayLookback' => 20,
                'sidewayMaxRangeAtr' => 0.0,
            ],
            'entryRules' => [
                'entryType' => 'LIMIT',
                'entryAt' => 'zone_extreme_or_50_percent',
                'minRR' => 3,
                'cancelLimitAfterCandles' => 3,
                'stopLossMode' => 'behind_zone_or_swing',
                'takeProfitMode' => 'rr_or_opposite_zone',
                'minDistanceToTPR' => 3,
                'stopBufferPoints' => 2,
            ],
            'managementRules' => [
                'moveSlToBEAfterR' => 1.8,
                'partialCloseEnabled' => false,
                'cancelLimitAfterCandles' => 3,
            ],
            'filters' => [
                'maxSpread' => null,
                'avoidWeekend' => true,
                'avoidNewsWindow' => false,
            ],
            'exitRules' => [
                'stopLossMode' => 'behind_zone_or_swing',
                'takeProfitMode' => 'fixed_rr',
                'partialCloseRule' => 'none',
                'emergencyExitRule' => 'time_stop',
            ],
        ];

        $rules = self::migrateLegacyRules($rules);
        $rules = array_replace_recursive($defaults, $rules);

        $rules['symbols'] = array_values(array_filter(array_map(
            fn($symbol) => strtoupper(trim((string)$symbol)),
            is_array($rules['symbols'] ?? null) ? $rules['symbols'] : []
        )));
        $rules['contextTimeframe'] = strtoupper((string)$rules['contextTimeframe']);
        $rules['entryTimeframe'] = strtoupper((string)$rules['entryTimeframe']);
        $rules['entryMode'] = strtoupper((string)$rules['entryMode']);
        if ($rules['entryMode'] !== 'LIMIT') {
            $rules['entryMode'] = 'LIMIT';
        }

        $rules['directionAllowed'] = self::normalizeDirectionAllowed((string)$rules['directionAllowed']);
        $rules['setupTypes'] = array_values(array_filter(array_map('strval', is_array($rules['setupTypes'] ?? null) ? $rules['setupTypes'] : [])));
        $rules['zoneLifecycle'] = self::normalizeZoneLifecycle(is_array($rules['zoneLifecycle'] ?? null) ? $rules['zoneLifecycle'] : []);
        $rules['minRR'] = max(0.1, (float)$rules['minRR']);
        $rules['maxRiskPercent'] = max(0.01, (float)$rules['maxRiskPercent']);
        $rules['riskPercent'] = min($rules['maxRiskPercent'], max(0.01, (float)$rules['riskPercent']));
        $rules['maxOpenTrades'] = max(1, (int)$rules['maxOpenTrades']);
        $rules['debugLevel'] = max(0, min(3, (int)$rules['debugLevel']));
        $rules['backtestPreset'] = in_array((string)$rules['backtestPreset'], self::presetNames(), true) ? (string)$rules['backtestPreset'] : 'current';

        $rules['h4Rules']['detectTrend'] = (bool)$rules['h4Rules']['detectTrend'];
        $rules['h4Rules']['detectSupplyDemandZones'] = (bool)$rules['h4Rules']['detectSupplyDemandZones'];
        $rules['h4Rules']['minZoneStrength'] = max(0, min(100, (float)$rules['h4Rules']['minZoneStrength']));
        $rules['h4Rules']['minImpulseSize'] = max(0, (float)$rules['h4Rules']['minImpulseSize']);
        $rules['h4Rules']['maxZoneTouches'] = max(0, (int)$rules['h4Rules']['maxZoneTouches']);
        $rules['h4Rules']['requireBosForZone'] = (bool)$rules['h4Rules']['requireBosForZone'];
        $rules['h4Rules']['freshZoneOnly'] = (bool)$rules['h4Rules']['freshZoneOnly'];
        $rules['h4Rules']['allowCounterTrendOnlyIfZoneStrong'] = (bool)$rules['h4Rules']['allowCounterTrendOnlyIfZoneStrong'];
        $rules['h4Rules']['counterTrendZoneStrengthMin'] = max(0, min(100, (float)$rules['h4Rules']['counterTrendZoneStrengthMin']));
        $rules['h4Rules']['fractalPeriod'] = max(1, (int)$rules['h4Rules']['fractalPeriod']);
        $rules['h4Rules']['swingLookback'] = $rules['h4Rules']['fractalPeriod'];
        $rules['h4Rules']['structureBreakLookback'] = max(3, (int)$rules['h4Rules']['structureBreakLookback']);
        $h4Lifecycle = is_array($rules['h4Rules']['zoneLifecycle'] ?? null) ? $rules['h4Rules']['zoneLifecycle'] : [];
        $rules['h4Rules']['zoneLifecycle'] = self::normalizeZoneLifecycle(array_replace($rules['zoneLifecycle'], $h4Lifecycle));
        $rules['zoneLifecycle'] = $rules['h4Rules']['zoneLifecycle'];

        $rules['m15Rules']['requireStructureBreak'] = (bool)$rules['m15Rules']['requireStructureBreak'];
        $rules['m15Rules']['requireQmlOrFakeBreak'] = (bool)$rules['m15Rules']['requireQmlOrFakeBreak'];
        $rules['m15Rules']['requireReactionAtZone'] = (bool)$rules['m15Rules']['requireReactionAtZone'];
        $rules['m15Rules']['requirePriceActionPattern'] = (bool)$rules['m15Rules']['requirePriceActionPattern'];
        $allowedPaPatterns = ['rejection_wick' => true, 'engulfing' => true, 'liquidity_sweep' => true, 'choch' => true];
        $paPatterns = is_array($rules['m15Rules']['allowedPriceActionPatterns'] ?? null) ? $rules['m15Rules']['allowedPriceActionPatterns'] : [];
        $rules['m15Rules']['allowedPriceActionPatterns'] = array_values(array_unique(array_filter(array_map(
            fn($pattern): string => strtolower(trim((string)$pattern)),
            $paPatterns
        ), fn(string $pattern): bool => isset($allowedPaPatterns[$pattern]))));
        if ($rules['m15Rules']['allowedPriceActionPatterns'] === []) {
            $rules['m15Rules']['allowedPriceActionPatterns'] = array_keys($allowedPaPatterns);
        }
        $rules['m15Rules']['minRejectionWickRatio'] = max(0.05, min(0.9, (float)$rules['m15Rules']['minRejectionWickRatio']));
        $rules['m15Rules']['engulfingRequiresBodyClose'] = (bool)$rules['m15Rules']['engulfingRequiresBodyClose'];
        $rules['m15Rules']['avoidDirtySideway'] = (bool)$rules['m15Rules']['avoidDirtySideway'];
        $rules['m15Rules']['fractalPeriod'] = max(1, (int)$rules['m15Rules']['fractalPeriod']);
        $rules['m15Rules']['swingLookback'] = $rules['m15Rules']['fractalPeriod'];
        $rules['m15Rules']['sidewayLookback'] = max(5, (int)$rules['m15Rules']['sidewayLookback']);
        $rules['m15Rules']['sidewayMaxRangeAtr'] = max(0, (float)$rules['m15Rules']['sidewayMaxRangeAtr']);

        $rules['entryRules']['entryType'] = strtoupper((string)$rules['entryRules']['entryType']);
        if ($rules['entryRules']['entryType'] !== 'LIMIT') {
            $rules['entryRules']['entryType'] = 'LIMIT';
        }
        $entryAt = (string)$rules['entryRules']['entryAt'];
        if (!in_array($entryAt, ['zone_extreme', 'zone_50_percent', 'zone_open', 'zone_extreme_or_50_percent'], true)) {
            $entryAt = 'zone_50_percent';
        }
        $rules['entryRules']['entryAt'] = $entryAt;
        $rules['entryRules']['minRR'] = max(0.1, (float)$rules['entryRules']['minRR']);
        $rules['minRR'] = max($rules['minRR'], $rules['entryRules']['minRR']);
        $rules['entryRules']['minDistanceToTPR'] = max($rules['minRR'], (float)$rules['entryRules']['minDistanceToTPR']);
        $rules['entryRules']['stopBufferPoints'] = max(0, (float)$rules['entryRules']['stopBufferPoints']);
        $rules['entryRules']['cancelLimitAfterCandles'] = max(1, (int)$rules['entryRules']['cancelLimitAfterCandles']);

        $rules['managementRules']['moveSlToBEAfterR'] = max(0, (float)$rules['managementRules']['moveSlToBEAfterR']);
        $rules['managementRules']['partialCloseEnabled'] = (bool)$rules['managementRules']['partialCloseEnabled'];
        $rules['managementRules']['cancelLimitAfterCandles'] = max(1, (int)($rules['managementRules']['cancelLimitAfterCandles'] ?? $rules['entryRules']['cancelLimitAfterCandles']));
        $rules['entryRules']['cancelLimitAfterCandles'] = $rules['managementRules']['cancelLimitAfterCandles'];

        $rules['filters']['avoidWeekend'] = (bool)$rules['filters']['avoidWeekend'];
        $rules['filters']['avoidNewsWindow'] = (bool)$rules['filters']['avoidNewsWindow'];
        if ($rules['filters']['maxSpread'] !== null && $rules['filters']['maxSpread'] !== '') {
            $rules['filters']['maxSpread'] = max(0, (float)$rules['filters']['maxSpread']);
        } else {
            $rules['filters']['maxSpread'] = null;
        }

        $rules['rrMin'] = $rules['minRR'];
        $rules['rrMax'] = max((float)($rules['rrMax'] ?? $rules['minRR']), $rules['minRR']);
        $rules['zoneStrengthMin'] = $rules['h4Rules']['minZoneStrength'];
        $rules['allowPartialClose'] = $rules['managementRules']['partialCloseEnabled'];
        $rules['moveSlAfterR'] = $rules['managementRules']['moveSlToBEAfterR'];

        return $rules;
    }

    private static function migrateLegacyRules(array $rules): array
    {
        if (isset($rules['rrMin']) && !isset($rules['minRR'])) {
            $rules['minRR'] = $rules['rrMin'];
        }
        if (isset($rules['riskPercent']) && !isset($rules['maxRiskPercent'])) {
            $rules['maxRiskPercent'] = max((float)$rules['riskPercent'], (float)($rules['maxRiskPercent'] ?? 0), 3.0);
        }
        if (isset($rules['zoneStrengthMin'])) {
            $rules['h4Rules']['minZoneStrength'] = $rules['h4Rules']['minZoneStrength'] ?? $rules['zoneStrengthMin'];
        }
        if (isset($rules['moveSlAfterR'])) {
            $rules['managementRules']['moveSlToBEAfterR'] = $rules['managementRules']['moveSlToBEAfterR'] ?? $rules['moveSlAfterR'];
        }
        if (isset($rules['h4Rules']['swingLookback']) && !isset($rules['h4Rules']['fractalPeriod'])) {
            $rules['h4Rules']['fractalPeriod'] = $rules['h4Rules']['swingLookback'];
        }
        if (isset($rules['m15Rules']['swingLookback']) && !isset($rules['m15Rules']['fractalPeriod'])) {
            $rules['m15Rules']['fractalPeriod'] = $rules['m15Rules']['swingLookback'];
        }
        if (isset($rules['entryRules']['cancelLimitAfterCandles']) && !isset($rules['managementRules']['cancelLimitAfterCandles'])) {
            $rules['managementRules']['cancelLimitAfterCandles'] = $rules['entryRules']['cancelLimitAfterCandles'];
        }
        if (isset($rules['allowPartialClose'])) {
            $rules['managementRules']['partialCloseEnabled'] = $rules['managementRules']['partialCloseEnabled'] ?? $rules['allowPartialClose'];
        }
        if (isset($rules['filters']) && is_array($rules['filters'])) {
            $legacy = $rules['filters'];
            if (array_key_exists('avoidSideway', $legacy)) {
                $rules['m15Rules']['avoidDirtySideway'] = $rules['m15Rules']['avoidDirtySideway'] ?? $legacy['avoidSideway'];
            }
            if (array_key_exists('requireM15StructureBreak', $legacy)) {
                $rules['m15Rules']['requireStructureBreak'] = $rules['m15Rules']['requireStructureBreak'] ?? $legacy['requireM15StructureBreak'];
            }
            if (array_key_exists('maxSpread', $legacy)) {
                $rules['filters']['maxSpread'] = $legacy['maxSpread'];
            }
            if (array_key_exists('avoidWeekend', $legacy)) {
                $rules['filters']['avoidWeekend'] = $legacy['avoidWeekend'];
            }
            if (array_key_exists('avoidNewsWindow', $legacy)) {
                $rules['filters']['avoidNewsWindow'] = $legacy['avoidNewsWindow'];
            }
        }
        if (isset($rules['exitRules']) && is_array($rules['exitRules'])) {
            $rules['entryRules']['stopLossMode'] = $rules['entryRules']['stopLossMode'] ?? ($rules['exitRules']['stopLossMode'] ?? null);
            $rules['entryRules']['takeProfitMode'] = $rules['entryRules']['takeProfitMode'] ?? ($rules['exitRules']['takeProfitMode'] ?? null);
        }
        return $rules;
    }

    private static function normalizeZoneLifecycle(array $lifecycle): array
    {
        $defaults = [
            'requireCloseBeyondZoneToBreak' => true,
            'allowWickThrough' => true,
            'wickToleranceAtr' => 0.25,
            'breakBufferAtr' => 0.10,
            'maxTouchesBeforeWeak' => 2,
            'maxTouchesBeforeInvalid' => 4,
            'markTouchedButNotBroken' => true,
        ];
        $lifecycle = array_replace($defaults, array_intersect_key($lifecycle, $defaults));
        return [
            'requireCloseBeyondZoneToBreak' => (bool)$lifecycle['requireCloseBeyondZoneToBreak'],
            'allowWickThrough' => (bool)$lifecycle['allowWickThrough'],
            'wickToleranceAtr' => max(0, (float)$lifecycle['wickToleranceAtr']),
            'breakBufferAtr' => max(0, (float)$lifecycle['breakBufferAtr']),
            'maxTouchesBeforeWeak' => max(0, (int)$lifecycle['maxTouchesBeforeWeak']),
            'maxTouchesBeforeInvalid' => max(0, (int)$lifecycle['maxTouchesBeforeInvalid']),
            'markTouchedButNotBroken' => (bool)$lifecycle['markTouchedButNotBroken'],
        ];
    }

    private static function normalizeDirectionAllowed(string $value): string
    {
        $value = strtoupper(trim($value));
        return match ($value) {
            'BOTH', 'BUY_SELL', 'BUYSELL', 'LONG_SHORT' => 'BUY_SELL',
            'BUY', 'LONG' => 'BUY',
            'SELL', 'SHORT' => 'SELL',
            default => 'BUY_SELL',
        };
    }

    private static function calibratedPresetNames(): array
    {
        return array_values(array_map(
            fn($preset) => strtolower((string)($preset['name'] ?? '')),
            array_filter(self::calibratedPresets(), fn($preset) => is_array($preset) && (string)($preset['name'] ?? '') !== '')
        ));
    }

    private static function calibratedPreset(string $name): ?array
    {
        $name = strtolower(trim($name));
        foreach (self::calibratedPresets() as $preset) {
            if (is_array($preset) && strtolower((string)($preset['name'] ?? '')) === $name) {
                return $preset;
            }
        }
        return null;
    }

    private static function calibratedPresets(): array
    {
        $file = __DIR__ . '/../data/calibrated_presets.json';
        if (!is_file($file)) {
            return [];
        }
        $content = file_get_contents($file);
        if ($content === false || trim($content) === '') {
            return [];
        }
        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : [];
    }
}
