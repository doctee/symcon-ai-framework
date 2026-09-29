<?php

declare(strict_types=1);

namespace SAEF\StorageHeater;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/** Starts only. No assumed burn duration, heat output, or inferred sensor evidence. */
final class FireplaceLog
{
    public static function localStart(string $json): int
    {
        $parts = Forecast::decode($json);
        foreach (['year', 'month', 'day', 'hour', 'minute', 'second'] as $key) {
            if (!isset($parts[$key]) || !is_int($parts[$key])) {
                throw new RuntimeException('Bitte Datum und Uhrzeit vollständig auswählen.');
            }
        }
        if (!checkdate($parts['month'], $parts['day'], $parts['year']) || $parts['hour'] < 0 || $parts['hour'] > 23 || $parts['minute'] < 0 || $parts['minute'] > 59 || $parts['second'] < 0 || $parts['second'] > 59) {
            throw new RuntimeException('Ungültiges Datum oder ungültige Uhrzeit.');
        }
        $text = sprintf('%04d-%02d-%02d %02d:%02d:%02d', $parts['year'], $parts['month'], $parts['day'], $parts['hour'], $parts['minute'], $parts['second']);
        $naive = (new DateTimeImmutable($text, new DateTimeZone('UTC')))->getTimestamp();
        $tz = new DateTimeZone('Europe/Berlin');
        $matches = [];
        foreach ($tz->getTransitions($naive - 2 * 86400, $naive + 2 * 86400) as $transition) {
            $t = $naive - $transition['offset'];
            if ((new DateTimeImmutable('@' . $t))->setTimezone($tz)->format('Y-m-d H:i:s') === $text) {
                $matches[$t] = true;
            }
        }
        if (count($matches) !== 1) {
            throw new RuntimeException('Diese Uhrzeit ist wegen der Zeitumstellung nicht eindeutig oder existiert nicht.');
        }
        return (int) array_key_first($matches);
    }

    public static function read(string $json): array
    {
        $log = Forecast::decode($json);
        if (($log['schema'] ?? null) !== 1 || !is_array($log['entries'] ?? null) || !array_is_list($log['entries']) || count($log['entries']) > 1000) {
            throw new RuntimeException('Ungültiges Kaminprotokoll.');
        }
        $seen = [];
        foreach ($log['entries'] as $entry) {
            if (!is_array($entry) || !is_int($entry['startedAt'] ?? null) || !is_int($entry['recordedAt'] ?? null) || !array_key_exists('cancelledAt', $entry) || ($entry['cancelledAt'] !== null && (!is_int($entry['cancelledAt']) || $entry['cancelledAt'] < $entry['recordedAt'])) || $entry['startedAt'] <= 0 || $entry['startedAt'] > $entry['recordedAt'] || isset($seen[$entry['startedAt']])) {
                throw new RuntimeException('Beschädigter Kamineintrag.');
            }
            $seen[$entry['startedAt']] = true;
        }
        return $log;
    }

    public static function record(array $log, int $start, int $now): array
    {
        if ($start > $now || $start < $now - 400 * 86400 || $start <= 0) {
            throw new RuntimeException('Der Kaminstart muss in den vergangenen 400 Tagen liegen.');
        }
        foreach ($log['entries'] as $entry) {
            if ($entry['startedAt'] === $start) {
                if ($entry['cancelledAt'] !== null) {
                    throw new RuntimeException('Dieser Start wurde bereits storniert; bitte den korrigierten Zeitpunkt erfassen.');
                }
                return $log;
            }
        }
        if (count($log['entries']) >= 1000) {
            throw new RuntimeException('Kaminprotokoll voll; vor weiterer Erfassung exportieren und verwalten.');
        }
        $log['entries'][] = ['startedAt' => $start, 'recordedAt' => $now, 'cancelledAt' => null];
        return $log;
    }

    public static function cancel(array $log, int $start, int $now): array
    {
        foreach ($log['entries'] as &$entry) {
            if ($entry['startedAt'] === $start) {
                if ($now < $entry['recordedAt']) {
                    throw new RuntimeException('Ungültiger Erfassungszeitpunkt.');
                }
                $entry['cancelledAt'] ??= $now;
                return $log;
            }
        }
        throw new RuntimeException('Kein Eintrag für den ausgewählten Start gefunden.');
    }

    /** A comparison window is a classification policy, never an estimated burn duration. */
    public static function classify(array $log, array $cycle, ?int $knownAt = null): array
    {
        $matches = [];
        foreach ($log['entries'] as $entry) {
            if ($entry['startedAt'] < $cycle['start'] - 24 * 3600 || $entry['startedAt'] >= $cycle['end']) {
                continue;
            }
            if ($knownAt !== null && ($entry['recordedAt'] > $knownAt || $entry['startedAt'] > $knownAt)) {
                continue;
            }
            if ($entry['cancelledAt'] !== null && ($knownAt === null || $entry['cancelledAt'] <= $knownAt)) {
                continue;
            }
            $matches[] = $entry['startedAt'];
        }
        return ['reportedStarts' => $matches, 'group' => $matches === [] ? 'no_reported_start' : 'reported_start',
            'windowFrom' => $cycle['start'] - 24 * 3600, 'windowTo' => $cycle['end']];
    }

    public static function score(array $records, array $log): array
    {
        $groups = ['reported_start' => [], 'no_reported_start' => []];
        foreach ($records as $record) {
            if (($record['measurement']['status'] ?? '') !== 'complete' || !isset($record['measurement']['errorKwh'])) {
                continue;
            }
            $group = self::classify($log, $record['cycle'])['group'];
            $groups[$group][] = abs(Forecast::number($record['measurement']['errorKwh'], 'residual'));
        }
        $result = [];
        foreach ($groups as $group => $errors) {
            $result[$group] = ['nights' => count($errors), 'maeKwh' => $errors === [] ? null : array_sum($errors) / count($errors)];
        }
        return $result;
    }
}
