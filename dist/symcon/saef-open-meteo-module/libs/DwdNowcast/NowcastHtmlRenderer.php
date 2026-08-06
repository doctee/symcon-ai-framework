<?php

declare(strict_types=1);

namespace SAEF\CaseStudy\DwdNowcast;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class NowcastHtmlRenderer
{
    /**
     * @param array<string, mixed> $cache
     * @param array{
     *     rainIn: string,
     *     noRain: string,
     *     now: string,
     *     minuteTooltip: string,
     *     noData: string
     * } $labels
     */
    public static function render(array $cache, string $timezone, array $labels): string
    {
        self::validateLabels($labels);
        $windowMinutes = self::positiveInteger($cache['evaluationWindowMinutes'] ?? null);
        $resolutionMinutes = self::positiveInteger($cache['nativeResolutionMinutes'] ?? null);
        $productTime = self::positiveInteger($cache['productTime'] ?? null);
        $summary = $cache['summary'] ?? null;
        $windowPoints = $cache['windowPoints'] ?? null;
        if (
            $windowMinutes > RequestBuilder::MAXIMUM_HORIZON_MINUTES
            || $windowMinutes % $resolutionMinutes !== 0
            || !is_array($summary)
            || !is_array($windowPoints)
            || !array_is_list($windowPoints)
        ) {
            throw new InvalidArgumentException('Nowcast chart input is invalid.');
        }

        try {
            $time = (new DateTimeImmutable('@' . $productTime))
                ->setTimezone(new DateTimeZone($timezone))
                ->format('H:i');
        } catch (\Throwable $error) {
            throw new InvalidArgumentException('Nowcast chart timezone is invalid.', 0, $error);
        }

        $rainExpected = $summary['rainExpected'] ?? null;
        $rainStarts = $summary['rainStartsInMinutes'] ?? null;
        if (!is_bool($rainExpected) || !is_int($rainStarts)) {
            throw new InvalidArgumentException('Nowcast chart summary is invalid.');
        }
        $status = $rainExpected
            ? sprintf($labels['rainIn'], max(0, $rainStarts))
            : sprintf($labels['noRain'], $windowMinutes);
        $minuteValues = self::minuteValues($windowPoints, $resolutionMinutes, $windowMinutes);
        $bars = [];
        foreach ($minuteValues as $minute => $intensity) {
            $tooltip = sprintf($labels['minuteTooltip'], $minute, $intensity);
            $bars[] = '<span class="saef-nowcast__bar" style="background:'
                . self::colorForIntensity($intensity)
                . '" title="' . self::escape($tooltip) . '" aria-hidden="true"></span>';
        }

        $middle = intdiv($windowMinutes, 2);
        $aria = $time . ' - ' . $status;

        return '<div class="saef-nowcast" role="img" aria-label="' . self::escape($aria) . '">'
            . self::style($windowMinutes)
            . '<div class="saef-nowcast__headline"><span class="saef-nowcast__time">'
            . self::escape($time) . '</span><strong>' . self::escape($status) . '</strong></div>'
            . '<div class="saef-nowcast__bars">' . implode('', $bars) . '</div>'
            . '<div class="saef-nowcast__axis"><span>' . self::escape($labels['now']) . '</span>'
            . '<span>+' . $middle . ' min</span><span>+' . $windowMinutes . ' min</span></div>'
            . '</div>';
    }

    /**
     * @param array{noData: string} $labels
     */
    public static function renderEmpty(array $labels): string
    {
        if ($labels['noData'] === '') {
            throw new InvalidArgumentException('Nowcast empty-chart label is invalid.');
        }

        return '<div class="saef-nowcast saef-nowcast--empty">'
            . self::style(1)
            . '<div class="saef-nowcast__empty">' . self::escape($labels['noData']) . '</div>'
            . '</div>';
    }

    public static function colorForIntensity(float $intensity): string
    {
        if (!is_finite($intensity) || $intensity < 0.0) {
            throw new InvalidArgumentException('Nowcast chart intensity is invalid.');
        }

        return match (true) {
            $intensity === 0.0 => '#4b5563',
            $intensity < 0.1 => '#38bdf8',
            $intensity < 0.5 => '#1677ff',
            $intensity < 1.0 => '#00c853',
            $intensity < 2.5 => '#ffd600',
            $intensity < 5.0 => '#ff9100',
            default => '#e00000',
        };
    }

    /**
     * @param list<array<string, mixed>> $windowPoints
     *
     * @return list<float>
     */
    private static function minuteValues(
        array $windowPoints,
        int $resolutionMinutes,
        int $windowMinutes
    ): array {
        $expectedPointCount = intdiv($windowMinutes, $resolutionMinutes);
        if (count($windowPoints) !== $expectedPointCount) {
            throw new InvalidArgumentException('Nowcast chart point count differs.');
        }

        $leadValues = [];
        foreach ($windowPoints as $index => $point) {
            $expectedLead = ($index + 1) * $resolutionMinutes;
            $lead = $point['leadMinutes'] ?? null;
            $intensity = $point['intensityMmPerHour'] ?? null;
            if (
                $lead !== $expectedLead
                || (!is_int($intensity) && !is_float($intensity))
                || !is_finite((float) $intensity)
                || (float) $intensity < 0.0
            ) {
                throw new InvalidArgumentException('Nowcast chart point is invalid.');
            }
            $leadValues[] = (float) $intensity;
        }

        $minuteValues = [];
        foreach ($leadValues as $index => $value) {
            $nextValue = $leadValues[$index + 1] ?? $value;
            $delta = $nextValue - $value;
            for ($offset = 0; $offset < $resolutionMinutes; $offset++) {
                $progress = $resolutionMinutes === 1
                    ? 1.0
                    : $offset / ($resolutionMinutes - 1);
                $smooth = $progress * $progress * (3.0 - (2.0 * $progress));
                $minuteValues[] = round(max(0.0, $value + ($delta * $smooth * 0.8)), 3);
            }
        }

        return array_slice($minuteValues, 0, $windowMinutes);
    }

    /** @param array<string, mixed> $labels */
    private static function validateLabels(array $labels): void
    {
        foreach (['rainIn', 'noRain', 'now', 'minuteTooltip', 'noData'] as $key) {
            if (!isset($labels[$key]) || !is_string($labels[$key]) || $labels[$key] === '') {
                throw new InvalidArgumentException('Nowcast chart label is invalid.');
            }
        }
    }

    private static function positiveInteger(mixed $value): int
    {
        if (!is_int($value) || $value <= 0) {
            throw new InvalidArgumentException('Nowcast chart integer is invalid.');
        }

        return $value;
    }

    private static function style(int $barCount): string
    {
        return '<style>'
            . '.saef-nowcast{box-sizing:border-box;width:100%;padding:6px;color:inherit;'
            . 'font:11px/1.35 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}'
            . '.saef-nowcast *{box-sizing:border-box}'
            . '.saef-nowcast__headline{display:flex;align-items:baseline;gap:8px;margin:0 0 2px}'
            . '.saef-nowcast__time,.saef-nowcast__axis{opacity:.62}'
            . '.saef-nowcast__bars{display:grid;grid-template-columns:repeat(' . $barCount
            . ',minmax(1px,1fr));gap:1px;height:14px;overflow:hidden;border-radius:4px}'
            . '.saef-nowcast__bar{display:block;min-width:0}'
            . '.saef-nowcast__axis{display:grid;grid-template-columns:1fr auto 1fr;margin-top:3px;font-size:9px}'
            . '.saef-nowcast__axis span:nth-child(2){text-align:center}'
            . '.saef-nowcast__axis span:last-child{text-align:right}'
            . '.saef-nowcast__empty{padding:18px 8px;text-align:center;opacity:.62}'
            . '</style>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
