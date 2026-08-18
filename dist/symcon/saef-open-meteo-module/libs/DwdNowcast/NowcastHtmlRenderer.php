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
        $rainThresholdMmPerHour = self::positiveFloat(
            $cache['rainThresholdMmPerHour'] ?? null
        );
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
        $minuteValues = self::minuteValues(
            $windowPoints,
            $resolutionMinutes,
            $windowMinutes,
            $rainThresholdMmPerHour
        );
        $bars = [];
        foreach ($minuteValues as $minute => $intensity) {
            $tooltip = sprintf($labels['minuteTooltip'], $minute, $intensity);
            $edgeClass = match (true) {
                $minute < 10 => ' saef-nowcast__bar--start',
                $minute >= $windowMinutes - 10 => ' saef-nowcast__bar--end',
                default => '',
            };
            $bars[] = '<div class="saef-nowcast__bar' . $edgeClass . '" data-tip="'
                . self::escape($tooltip) . '" style="box-sizing:border-box;position:relative;'
                . 'height:14px;min-width:2px;background:'
                . self::colorForIntensity($intensity, $rainThresholdMmPerHour)
                . '"></div>';
        }

        $middle = intdiv($windowMinutes, 2);

        return '<div class="saef-nowcast" style="box-sizing:border-box;width:100%;padding:6px;color:#ddd;'
            . 'font:11px/1.35 -apple-system,BlinkMacSystemFont,&quot;Segoe UI&quot;,sans-serif">'
            . self::tooltipStyle()
            . '<div class="saef-nowcast__headline" style="display:flex;align-items:baseline;gap:8px;'
            . 'margin:0 0 2px"><span class="saef-nowcast__time" style="color:#999;white-space:nowrap">'
            . self::escape($time) . '</span><strong style="color:#fff">'
            . self::escape($status) . '</strong></div>'
            . '<div class="saef-nowcast__bars" style="display:grid;grid-template-columns:repeat('
            . $windowMinutes . ',minmax(1px,1fr));gap:1px;height:14px;overflow:visible;'
            . 'border-radius:4px">' . implode('', $bars) . '</div>'
            . '<div class="saef-nowcast__axis" style="display:grid;grid-template-columns:1fr auto 1fr;'
            . 'margin-top:3px;font-size:9px;color:#888"><span>'
            . self::escape($labels['now']) . '</span><span style="text-align:center">+'
            . $middle . ' min</span><span style="text-align:right">+' . $windowMinutes . ' min</span></div>'
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

        return '<div class="saef-nowcast saef-nowcast--empty" '
            . 'style="box-sizing:border-box;width:100%;padding:6px;color:#ddd;'
            . 'font:11px/1.35 -apple-system,BlinkMacSystemFont,&quot;Segoe UI&quot;,sans-serif">'
            . '<div class="saef-nowcast__empty" style="padding:18px 8px;text-align:center;opacity:.62">'
            . self::escape($labels['noData']) . '</div>'
            . '</div>';
    }

    public static function colorForIntensity(
        float $intensity,
        float $rainThresholdMmPerHour
    ): string
    {
        if (
            !is_finite($intensity)
            || $intensity < 0.0
            || !is_finite($rainThresholdMmPerHour)
            || $rainThresholdMmPerHour <= 0.0
        ) {
            throw new InvalidArgumentException('Nowcast chart intensity is invalid.');
        }

        return match (true) {
            $intensity < $rainThresholdMmPerHour => '#4b5563',
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
        int $windowMinutes,
        float $rainThresholdMmPerHour
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
            $sameThresholdClass = ($value >= $rainThresholdMmPerHour)
                === ($nextValue >= $rainThresholdMmPerHour);
            $delta = $sameThresholdClass ? $nextValue - $value : 0.0;
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

    private static function positiveFloat(mixed $value): float
    {
        if (
            (!is_int($value) && !is_float($value))
            || !is_finite((float) $value)
            || (float) $value <= 0.0
        ) {
            throw new InvalidArgumentException('Nowcast chart threshold is invalid.');
        }

        return (float) $value;
    }

    private static function tooltipStyle(): string
    {
        return '<style>'
            . '.saef-nowcast .saef-nowcast__bar::after{content:attr(data-tip);position:absolute;'
            . 'left:50%;top:50%;transform:translate(-50%,-50%);display:none;z-index:1000;'
            . 'pointer-events:none;white-space:nowrap;width:max-content;max-width:220px;'
            . 'padding:4px 6px;border-radius:5px;background:rgba(0,0,0,.88);color:#fff;'
            . 'font:11px/1.25 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;'
            . 'text-align:center;box-shadow:0 2px 6px rgba(0,0,0,.25)}'
            . '.saef-nowcast .saef-nowcast__bar:hover{z-index:1001}'
            . '.saef-nowcast .saef-nowcast__bar:hover::after{display:block}'
            . '.saef-nowcast .saef-nowcast__bar--start::after{left:0;transform:translate(0,-50%)}'
            . '.saef-nowcast .saef-nowcast__bar--end::after{left:auto;right:0;transform:translate(0,-50%)}'
            . '</style>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
