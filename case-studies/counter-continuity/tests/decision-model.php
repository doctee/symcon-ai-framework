<?php

declare(strict_types=1);

namespace CounterContinuityStudy;

/** Educational model only: no storage, locking, archive or device adapter. */
function decide(array $state, array $sample, int $now, int $maxAge): array
{
    $result = static fn(string $status, array $next): array => ['status' => $status, 'state' => $next];
    if ($maxAge <= 0 || $now < 0) {
        throw new \InvalidArgumentException('Invalid clock policy');
    }
    // Ownership/persistent integrity and pending writes precede availability.
    if (($sample['identity'] ?? null) !== 'expected' || ($state['integrity'] ?? null) !== true) {
        return $result('HOLD', $state);
    }
    if (($state['pending'] ?? null) !== false) {
        return $result('RECONCILE', $state);
    }
    if (($sample['readable'] ?? null) !== true || ($sample['stable'] ?? null) !== true) {
        return $result('DEFER', $state);
    }
    foreach (['energy', 'power', 'health'] as $field) {
        $time = $sample[$field]['time'] ?? null;
        if (!is_int($time) || $time < 0 || $time > $now) {
            return $result('HOLD', $state);
        }
        if ($now - $time > $maxAge) {
            return $result('DEFER', $state);
        }
    }
    if (($sample['health']['value'] ?? null) !== true) {
        return $result('HOLD', $state);
    }
    foreach (['energy', 'power'] as $field) {
        $value = $sample[$field]['value'] ?? null;
        if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value) || $value < 0) {
            return $result('HOLD', $state);
        }
        $old = $state[$field];
        if ($sample[$field]['time'] < $old['time']) {
            return $result('DEFER', $state);
        }
        if ($sample[$field]['time'] === $old['time'] && $value !== $old['value']) {
            // Seconds alone cannot order different observations. Require later evidence.
            return $result('DEFER', $state);
        }
    }
    if ($sample['energy']['value'] < $state['energy']['value']) {
        return $result('RESET_REVIEW', $state);
    }
    $next = $state;
    $next['total'] += ($sample['energy']['value'] - $state['energy']['value']) / 1000;
    $next['energy'] = $sample['energy'];
    $next['power'] = $sample['power'];
    return $result('ACCEPT', $next);
}
