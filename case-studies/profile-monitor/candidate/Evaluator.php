<?php

declare(strict_types=1);

namespace SAEF\ProfileMonitor;

use InvalidArgumentException;

/** Internal case-study evaluator; no Symcon calls, storage or notification effects. */
final class Evaluator
{
    public const VALUE_PRESENTATION = '{3319437D-7CDE-699D-750A-3C6A3841FA75}';
    public const BLINK_MODULE = '{7D2B8EFA-23D0-D29C-DBEE-E81F1FC2DBDC}';
    public const ZIGBEE_MODULE = '{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}';

    private $profiles = [];
    private $ignored = [];
    private $rules;
    private $skipNeverUpdated;

    public function __construct(array $profiles, array $ignored, array $rules, bool $skipNeverUpdated = true)
    {
        foreach ($profiles as $row) {
            if (!is_array($row) || !is_string($row['ProfileName'] ?? null) || $row['ProfileName'] === '' || !array_key_exists('ProfileValue', $row)) {
                throw new InvalidArgumentException('Invalid profile rule');
            }
            if (!is_scalar($row['ProfileValue'])) {
                throw new InvalidArgumentException('Invalid profile threshold');
            }
            // Preserve the original last-row-wins and PHP comparison semantics.
            $this->profiles[$row['ProfileName']] = $row['ProfileValue'];
        }
        foreach ($ignored as $row) {
            $id = is_array($row) ? ($row['ID2Ignore'] ?? null) : $row;
            if (!is_int($id) || $id <= 0) {
                throw new InvalidArgumentException('Invalid ignored variable ID');
            }
            $this->ignored[$id] = true;
        }
        $names = [];
        foreach ($rules as $rule) {
            self::validateRule($rule);
            if (isset($names[$rule['name']])) {
                throw new InvalidArgumentException('Duplicate rule name');
            }
            $names[$rule['name']] = true;
        }
        $this->rules = $rules;
        $this->skipNeverUpdated = $skipNeverUpdated;
    }

    public static function presets(bool $zigbee, bool $blink, float $percentThreshold, int $blinkThreshold): array
    {
        if (!is_finite($percentThreshold) || $percentThreshold < 0 || $percentThreshold > 100 || $blinkThreshold < 1 || $blinkThreshold > 2) {
            throw new InvalidArgumentException('Invalid preset threshold');
        }
        $rules = [];
        if ($zigbee) {
            $rules[] = ['name' => 'zigbee-battery', 'moduleId' => self::ZIGBEE_MODULE, 'ident' => 'battery',
                'type' => 1, 'presentationId' => self::VALUE_PRESENTATION, 'operator' => 'le',
                'threshold' => $percentThreshold, 'unit' => 'percent', 'range' => [0, 100]];
        }
        if ($blink) {
            $rules[] = ['name' => 'blink-battery', 'moduleId' => self::BLINK_MODULE, 'ident' => 'battery',
                'type' => 1, 'presentationId' => self::VALUE_PRESENTATION, 'operator' => 'le',
                'threshold' => $blinkThreshold, 'unit' => 'raw', 'unknownValues' => [0], 'range' => [0, 3]];
        }
        return $rules;
    }

    private static function validateRule($rule): void
    {
        $allowed = ['name', 'variableId', 'moduleId', 'ident', 'type', 'presentationId', 'operator', 'threshold', 'unit', 'unknownValues', 'range', 'overrideLegacy'];
        if (!is_array($rule) || array_diff(array_keys($rule), $allowed) !== [] || !is_string($rule['name'] ?? null) || $rule['name'] === '') {
            throw new InvalidArgumentException('Invalid presentation rule');
        }
        $byId = array_key_exists('variableId', $rule);
        if ($byId) {
            if (!is_int($rule['variableId']) || $rule['variableId'] <= 0 || isset($rule['moduleId']) || isset($rule['ident'])) {
                throw new InvalidArgumentException('Explicit variable rule requires one positive ID');
            }
        } elseif (!is_string($rule['moduleId'] ?? null) || !preg_match('/^\{[A-Fa-f0-9-]{36}\}$/D', $rule['moduleId']) || !is_string($rule['ident'] ?? null) || $rule['ident'] === '') {
            throw new InvalidArgumentException('Provider rule requires module ID and exact ident');
        }
        if (!in_array($rule['type'] ?? null, [0, 1, 2], true)) {
            throw new InvalidArgumentException('Only Boolean, Integer and Float rules are admitted');
        }
        if (!in_array($rule['unit'] ?? null, ['raw', 'percent'], true) || !in_array($rule['operator'] ?? null, ['eq', 'le'], true)) {
            throw new InvalidArgumentException('Invalid comparison or unit');
        }
        if (isset($rule['presentationId']) && (!is_string($rule['presentationId']) || $rule['presentationId'] === '')) {
            throw new InvalidArgumentException('Invalid presentation ID');
        }
        if (isset($rule['overrideLegacy']) && (!is_bool($rule['overrideLegacy']) || ($rule['overrideLegacy'] && !$byId))) {
            throw new InvalidArgumentException('Legacy override requires an explicit variable rule');
        }
        if ($rule['type'] === 0) {
            if (!is_bool($rule['threshold'] ?? null) || $rule['operator'] !== 'eq' || $rule['unit'] !== 'raw' || isset($rule['range'])) {
                throw new InvalidArgumentException('Boolean rules require equality to a Boolean');
            }
        } elseif (!self::isNumber($rule['threshold'] ?? null)) {
            throw new InvalidArgumentException('Numeric thresholds must be finite JSON numbers, not text');
        }
        if ($rule['unit'] === 'percent' && ($rule['threshold'] < 0 || $rule['threshold'] > 100)) {
            throw new InvalidArgumentException('Percent threshold outside 0..100');
        }
        if (isset($rule['range']) && (!is_array($rule['range']) || count($rule['range']) !== 2 || !self::isNumber($rule['range'][0] ?? null) || !self::isNumber($rule['range'][1] ?? null) || $rule['range'][0] >= $rule['range'][1])) {
            throw new InvalidArgumentException('Invalid numeric range');
        }
        if (isset($rule['unknownValues'])) {
            if (!is_array($rule['unknownValues'])) {
                throw new InvalidArgumentException('unknownValues must be an array');
            }
            foreach ($rule['unknownValues'] as $value) {
                if (($rule['type'] === 0 && !is_bool($value)) || ($rule['type'] !== 0 && !self::isNumber($value))) {
                    throw new InvalidArgumentException('Invalid unknown value');
                }
            }
        }
    }

    private static function isNumber($value): bool
    {
        return (is_int($value) || is_float($value)) && is_finite((float) $value);
    }

    public function evaluate(array $variables): array
    {
        $out = ['checked' => [], 'warnings' => [], 'unknown' => [], 'ignored' => [], 'details' => []];
        $seen = [];
        foreach ($variables as $v) {
            $id = $v['id'] ?? null;
            if (!is_int($id) || $id <= 0 || isset($seen[$id])) {
                throw new InvalidArgumentException('Invalid or duplicate snapshot ID');
            }
            $seen[$id] = true;
            $profile = $v['profile'] ?? '';
            $legacy = array_key_exists($profile, $this->profiles);
            $matches = [];
            foreach ($this->rules as $rule) {
                $selected = isset($rule['variableId']) ? $id === $rule['variableId']
                    : ($v['moduleId'] ?? '') === $rule['moduleId'] && ($v['ident'] ?? '') === $rule['ident'];
                if ($selected && (!$legacy || ($rule['overrideLegacy'] ?? false))) {
                    $matches[] = $rule;
                }
            }
            if (count($matches) > 1) {
                throw new InvalidArgumentException('Conflicting rules for variable ' . $id);
            }
            if (!$legacy && $matches === []) {
                continue;
            }
            $out['checked'][] = $id;
            $rule = $matches[0] ?? null;
            $reason = $rule === null ? 'profile:' . $profile : 'rule:' . $rule['name'];
            $status = 'ok';
            $warning = false;
            $value = $v['value'] ?? null;
            if (!is_int($v['updated'] ?? null) || $v['updated'] < 0 || ($this->skipNeverUpdated && $v['updated'] === 0)) {
                $status = 'no_data';
            } elseif ($rule === null) {
                $threshold = $this->profiles[$profile];
                $warning = $v['type'] === 0 ? $value == $threshold : $value <= $threshold;
            } else {
                $p = $v['presentation'] ?? [];
                if (
                    !is_array($p) || ($v['type'] ?? null) !== $rule['type'] || ($v['action'] ?? false)
                    || (isset($rule['presentationId']) && ($p['PRESENTATION'] ?? null) !== $rule['presentationId'])
                ) {
                    $status = 'contract_mismatch';
                } elseif (($rule['type'] === 0 && !is_bool($value)) || ($rule['type'] === 1 && !is_int($value)) || ($rule['type'] === 2 && !self::isNumber($value))) {
                    $status = 'invalid_value';
                } elseif (in_array($value, $rule['unknownValues'] ?? [], true)) {
                    $status = 'unknown_value';
                } else {
                    if ($rule['unit'] === 'percent') {
                        $min = $p['MIN'] ?? null;
                        $max = $p['MAX'] ?? null;
                        if ($rule['type'] === 1 && (!is_int($min) || !is_int($max))) {
                            $status = 'invalid_scale_type';
                        } elseif (($p['PERCENTAGE'] ?? null) !== true || !self::isNumber($min) || !self::isNumber($max) || $max <= $min || !is_finite((float) ($max - $min)) || $value < $min || $value > $max) {
                            $status = 'invalid_percent_scale';
                        } else {
                            $value = (($value - $min) / ($max - $min)) * 100.0;
                            if (!is_finite($value)) {
                                $status = 'invalid_percent_scale';
                            }
                        }
                    }
                    if ($status === 'ok' && isset($rule['range']) && ($value < $rule['range'][0] || $value > $rule['range'][1])) {
                        $status = 'out_of_range';
                    }
                    if ($status === 'ok') {
                        $warning = $rule['operator'] === 'eq' ? $value == $rule['threshold'] : $value <= $rule['threshold'];
                    }
                }
            }
            if ($status !== 'ok') {
                $out['unknown'][] = $id;
            }
            $ignored = isset($this->ignored[$id]);
            if ($ignored) {
                $out['ignored'][] = $id;
            } elseif ($warning) {
                $out['warnings'][] = $id;
            }
            $out['details'][] = ['id' => $id, 'reason' => $reason, 'status' => $status, 'ignored' => $ignored, 'warning' => $warning && !$ignored];
        }
        return $out;
    }
}
