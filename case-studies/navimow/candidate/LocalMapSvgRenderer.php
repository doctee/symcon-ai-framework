<?php

declare(strict_types=1);

namespace Navimow\Prototype;

use InvalidArgumentException;

final class LocalMapSvgRenderer
{
    private const MAX_ZONES = 32;
    private const MAX_OBSTACLES = 256;
    private const MAX_SEGMENTS = 64;
    private const MAX_POINTS = 8192;
    private const MAX_OUTPUT_BYTES = 1024 * 1024;
    private const COLORS = [
        ['fill' => '#dbeafe', 'stroke' => '#2563eb'],
        ['fill' => '#dcfce7', 'stroke' => '#16a34a'],
        ['fill' => '#fef3c7', 'stroke' => '#d97706'],
        ['fill' => '#fce7f3', 'stroke' => '#db2777'],
        ['fill' => '#e0e7ff', 'stroke' => '#4f46e5'],
        ['fill' => '#ccfbf1', 'stroke' => '#0f766e'],
    ];

    /**
     * @param array<string, mixed> $scene
     * @param array<string, mixed> $options
     */
    public static function render(array $scene, array $options = []): string
    {
        if (
            ($scene['formatVersion'] ?? null) !== 1
            || ($scene['coordinateFrame'] ?? null)
                !== 'navimow-local-map-candidate'
            || !is_array($scene['viewport'] ?? null)
            || !is_array($scene['zones'] ?? null)
            || !array_is_list($scene['zones'])
            || count($scene['zones']) > self::MAX_ZONES
            || !is_array($scene['obstacles'] ?? null)
            || !array_is_list($scene['obstacles'])
            || count($scene['obstacles']) > self::MAX_OBSTACLES
            || !is_array($scene['path'] ?? null)
        ) {
            throw new InvalidArgumentException('Local map scene is invalid.');
        }
        $presentation = self::options($options);
        $viewport = self::viewport($scene['viewport']);
        $pointCount = 0;
        $zoneMarkup = [];
        $labelMarkup = [];
        foreach ($scene['zones'] as $index => $zone) {
            if (!is_array($zone)) {
                throw new InvalidArgumentException('Map zone is invalid.');
            }
            $ring = self::ring($zone['ring'] ?? null, $pointCount);
            $label = self::text($zone['label'] ?? null, 128);
            $color = self::COLORS[$index % count(self::COLORS)];
            $zoneMarkup[] = sprintf(
                '<polygon class="zone" data-zone-sequence="%d" points="%s" fill="%s" stroke="%s"/>',
                $index + 1,
                self::points($ring, $viewport),
                $color['fill'],
                $color['stroke']
            );
            if (
                !in_array(
                    $index + 1,
                    $presentation['hiddenZoneSequences'],
                    true
                )
            ) {
                $center = self::center($ring);
                $projected = self::project($center, $viewport);
                $suffix = self::progressSuffix($zone['statistics'] ?? null);
                $labelMarkup[] = sprintf(
                    '<text class="zone-label" x="%s" y="%s">%s%s</text>',
                    self::number($projected[0]),
                    self::number($projected[1]),
                    self::escape($label),
                    self::escape($suffix)
                );
            }
        }

        $obstacleMarkup = [];
        foreach ($scene['obstacles'] as $index => $obstacle) {
            if (!is_array($obstacle)) {
                throw new InvalidArgumentException(
                    'Map obstacle is invalid.'
                );
            }
            $ring = self::ring($obstacle['ring'] ?? null, $pointCount);
            $status = $obstacle['ownership']['status'] ?? null;
            if (!is_string($status)) {
                throw new InvalidArgumentException(
                    'Obstacle ownership is invalid.'
                );
            }
            $obstacleMarkup[] = sprintf(
                '<polygon class="obstacle obstacle-%s" data-obstacle-sequence="%d" points="%s"/>',
                self::escape($status),
                $index + 1,
                self::points($ring, $viewport)
            );
        }

        $pathMarkup = [];
        $diagnosticPointMarkup = [];
        $diagnosticPointSequence = 0;
        $markerRadius = max(
            0.7,
            max($viewport['width'], $viewport['height']) / 100.0
        );
        $segments = $scene['path']['segments'] ?? null;
        if (
            !is_array($segments)
            || !array_is_list($segments)
            || count($segments) > self::MAX_SEGMENTS
        ) {
            throw new InvalidArgumentException('Map path is invalid.');
        }
        $latest = null;
        foreach ($segments as $index => $segment) {
            if (!is_array($segment)) {
                throw new InvalidArgumentException(
                    'Map path segment is invalid.'
                );
            }
            $values = $segment['points'] ?? null;
            if (!is_array($values) || !array_is_list($values)) {
                throw new InvalidArgumentException(
                    'Map path points are invalid.'
                );
            }
            $points = [];
            foreach ($values as $value) {
                if (!is_array($value)) {
                    throw new InvalidArgumentException(
                        'Map path point is invalid.'
                    );
                }
                $points[] = [
                    self::finite($value['localX'] ?? null),
                    self::finite($value['localY'] ?? null),
                ];
                $latest = $points[array_key_last($points)];
                ++$diagnosticPointSequence;
                $attribution = $value['attribution'] ?? null;
                if (!is_array($attribution)) {
                    throw new InvalidArgumentException(
                        'Map path attribution is invalid.'
                    );
                }
                $source = $attribution['source'] ?? null;
                if (!is_string($source)) {
                    throw new InvalidArgumentException(
                        'Map path attribution source is invalid.'
                    );
                }
                if (
                    in_array(
                        $source,
                        ['outside', 'ambiguous', 'unknown-task-zone'],
                        true
                    )
                ) {
                    $projectedPoint = self::project($latest, $viewport);
                    $diagnosticPointMarkup[] = sprintf(
                        '<circle class="path-point path-point-%s" data-point-sequence="%d" cx="%s" cy="%s" r="%s"><title>%s</title></circle>',
                        self::escape($source),
                        $diagnosticPointSequence,
                        self::number($projectedPoint[0]),
                        self::number($projectedPoint[1]),
                        self::number($markerRadius),
                        self::escape(self::diagnosticTitle($source))
                    );
                }
                ++$pointCount;
                if ($pointCount > self::MAX_POINTS) {
                    throw new InvalidArgumentException(
                        'Rendered point count exceeds the limit.'
                    );
                }
            }
            if ($points === []) {
                continue;
            }
            $pathMarkup[] = sprintf(
                '<polyline class="path" data-segment-sequence="%d" points="%s"/>',
                $index + 1,
                self::points($points, $viewport)
            );
        }

        $stationMarkup = '';
        if (($scene['station'] ?? null) !== null) {
            if (!is_array($scene['station'])) {
                throw new InvalidArgumentException('Map station is invalid.');
            }
            $station = self::project([
                self::finite($scene['station']['x'] ?? null),
                self::finite($scene['station']['y'] ?? null),
            ], $viewport);
            $direction = $scene['station']['direction'] ?? null;
            $rotation = $direction === null
                ? 0.0
                : -rad2deg(self::finite($direction));
            $stationState = $presentation['stationState'];
            $stationMarkup = sprintf(
                '<g class="station station-%s" transform="translate(%s %s) rotate(%s)"><title>%s</title><rect x="-2.6" y="-1.7" width="5.2" height="3.4" rx=".6"/><path d="M-1.6 0h3.2M0-1v2"/></g>',
                self::escape($stationState),
                self::number($station[0]),
                self::number($station[1]),
                self::number($rotation),
                self::escape(self::stationTitle($stationState))
            );
        }

        $mowerMarkup = '';
        if ($latest !== null) {
            $mower = self::project($latest, $viewport);
            $mowerMarkup = sprintf(
                '<g class="mower" transform="translate(%s %s)"><circle r="1.8"/><circle r="0.55"/></g>',
                self::number($mower[0]),
                self::number($mower[1])
            );
        }

        $viewBox = implode(' ', array_map(
            [self::class, 'number'],
            [
                $viewport['minimumX'],
                $viewport['minimumY'],
                $viewport['width'],
                $viewport['height'],
            ]
        ));
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Local mower map" viewBox="%s" preserveAspectRatio="xMidYMid meet"><style>%s</style><rect class="background" x="%s" y="%s" width="%s" height="%s"/>%s%s%s%s%s%s</svg>',
            $viewBox,
            self::styles($viewport),
            self::number($viewport['minimumX']),
            self::number($viewport['minimumY']),
            self::number($viewport['width']),
            self::number($viewport['height']),
            implode('', $zoneMarkup),
            implode('', $obstacleMarkup),
            implode('', $pathMarkup),
            implode('', $diagnosticPointMarkup),
            $stationMarkup . $mowerMarkup,
            implode('', $labelMarkup)
        );
        if (strlen($svg) > self::MAX_OUTPUT_BYTES) {
            throw new InvalidArgumentException(
                'Rendered map exceeds the output limit.'
            );
        }

        return $svg;
    }

    /** @return array<string, float> */
    private static function viewport(array $value): array
    {
        $result = [];
        foreach (
            [
                'minimumX',
                'minimumY',
                'maximumX',
                'maximumY',
                'width',
                'height',
            ] as $field
        ) {
            $result[$field] = self::finite($value[$field] ?? null);
        }
        if ($result['width'] <= 0.0 || $result['height'] <= 0.0) {
            throw new InvalidArgumentException('Map viewport is invalid.');
        }

        return $result;
    }

    /**
     * @param mixed $value
     * @param int $pointCount
     *
     * @return list<array{0: float, 1: float}>
     */
    private static function ring(mixed $value, int &$pointCount): array
    {
        if (
            !is_array($value)
            || !array_is_list($value)
            || count($value) < 4
            || count($value) > 1025
        ) {
            throw new InvalidArgumentException('Rendered ring is invalid.');
        }
        $ring = [];
        foreach ($value as $point) {
            if (!is_array($point) || count($point) !== 2) {
                throw new InvalidArgumentException(
                    'Rendered ring point is invalid.'
                );
            }
            $ring[] = [
                self::finite($point[0] ?? null),
                self::finite($point[1] ?? null),
            ];
            ++$pointCount;
            if ($pointCount > self::MAX_POINTS) {
                throw new InvalidArgumentException(
                    'Rendered point count exceeds the limit.'
                );
            }
        }

        return $ring;
    }

    /**
     * @param list<array{0: float, 1: float}> $points
     * @param array<string, float> $viewport
     */
    private static function points(array $points, array $viewport): string
    {
        return implode(' ', array_map(
            static function (array $point) use ($viewport): string {
                $projected = self::project($point, $viewport);

                return self::number($projected[0])
                    . ',' . self::number($projected[1]);
            },
            $points
        ));
    }

    /**
     * @param array{0: float, 1: float} $point
     * @param array<string, float> $viewport
     *
     * @return array{0: float, 1: float}
     */
    private static function project(array $point, array $viewport): array
    {
        return [
            $point[0],
            $viewport['minimumY'] + $viewport['maximumY'] - $point[1],
        ];
    }

    /**
     * @param list<array{0: float, 1: float}> $ring
     *
     * @return array{0: float, 1: float}
     */
    private static function center(array $ring): array
    {
        $points = array_slice($ring, 0, -1);

        return [
            array_sum(array_column($points, 0)) / count($points),
            array_sum(array_column($points, 1)) / count($points),
        ];
    }

    private static function progressSuffix(mixed $statistics): string
    {
        if (
            !is_array($statistics)
            || !is_array($statistics['latestPass'] ?? null)
            || !is_float(
                $statistics['latestPass']['passProgressPercent'] ?? null
            )
        ) {
            return '';
        }

        return sprintf(
            ' · %.1f%%',
            $statistics['latestPass']['passProgressPercent']
        );
    }

    /** @param array<string, float> $viewport */
    private static function styles(array $viewport): string
    {
        $span = max($viewport['width'], $viewport['height']);
        $stroke = max(0.18, $span / 280.0);
        $path = max(0.28, $span / 180.0);
        $font = max(1.6, min(2.4, $span / 50.0));

        return sprintf(
            '.background{fill:#f8fafc}.zone{fill-opacity:.62;stroke-width:%1$s;vector-effect:non-scaling-stroke}.obstacle{fill:#94a3b8;fill-opacity:.12;stroke:#64748b;stroke-width:%1$s;stroke-dasharray:1.1 .8;vector-effect:non-scaling-stroke}.obstacle-ambiguous{fill:#dc2626;fill-opacity:.12;stroke:#dc2626}.path{fill:none;stroke:#111827;stroke-width:%2$s;stroke-linecap:round;stroke-linejoin:round;vector-effect:non-scaling-stroke}.path-point{stroke:#ffffff;stroke-width:%1$s;vector-effect:non-scaling-stroke}.path-point-outside{fill:#f97316}.path-point-ambiguous{fill:#dc2626}.path-point-unknown-task-zone{fill:#7c3aed}.station rect{stroke-width:%1$s;vector-effect:non-scaling-stroke}.station path{fill:none;stroke-width:%1$s;stroke-linecap:round;vector-effect:non-scaling-stroke}.station-docked rect{fill:#16a34a;stroke:#14532d}.station-docked path{stroke:#dcfce7}.station-docking rect{fill:#d97706;stroke:#78350f}.station-docking path{stroke:#fef3c7}.station-undocked rect{fill:#64748b;stroke:#1e293b}.station-undocked path{stroke:#f8fafc}.station-unknown rect{fill:#0f766e;stroke:#042f2e}.station-unknown path{stroke:#ccfbf1}.mower circle:first-child{fill:#22c55e;stroke:#052e16;stroke-width:%1$s;vector-effect:non-scaling-stroke}.mower circle:last-child{fill:#052e16}.zone-label{font-family:system-ui,sans-serif;font-size:%3$spx;text-anchor:middle;dominant-baseline:middle;fill:#111827;paint-order:stroke;stroke:#ffffff;stroke-width:.35;stroke-linejoin:round;letter-spacing:0}',
            self::number($stroke),
            self::number($path),
            self::number($font)
        );
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array{hiddenZoneSequences: list<int>, stationState: string}
     */
    private static function options(array $options): array
    {
        $hidden = $options['hiddenZoneSequences'] ?? [];
        $stationState = $options['stationState'] ?? 'unknown';
        if (
            !is_array($hidden)
            || !array_is_list($hidden)
            || count($hidden) > self::MAX_ZONES
            || !is_string($stationState)
            || !in_array(
                $stationState,
                ['docked', 'docking', 'undocked', 'unknown'],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Map presentation options are invalid.'
            );
        }
        $seen = [];
        foreach ($hidden as $sequence) {
            if (
                !is_int($sequence)
                || $sequence < 1
                || $sequence > self::MAX_ZONES
                || isset($seen[$sequence])
            ) {
                throw new InvalidArgumentException(
                    'Hidden zone sequence is invalid.'
                );
            }
            $seen[$sequence] = true;
        }

        return [
            'hiddenZoneSequences' => $hidden,
            'stationState' => $stationState,
        ];
    }

    private static function stationTitle(string $state): string
    {
        return match ($state) {
            'docked' => 'Mower docked',
            'docking' => 'Mower returning to station',
            'undocked' => 'Mower away from station',
            'unknown' => 'Dock state unknown',
            default => throw new InvalidArgumentException(
                'Station state is invalid.'
            ),
        };
    }

    private static function diagnosticTitle(string $source): string
    {
        return match ($source) {
            'outside' => 'Outside mapped zone',
            'ambiguous' => 'Ambiguous zone attribution',
            'unknown-task-zone' => 'Unknown task zone',
            default => throw new InvalidArgumentException(
                'Map diagnostic source is invalid.'
            ),
        };
    }

    private static function text(mixed $value, int $maximum): string
    {
        if (
            !is_string($value)
            || $value === ''
            || strlen($value) > $maximum
            || preg_match('/[\x00-\x1F\x7F]/', $value) === 1
        ) {
            throw new InvalidArgumentException('Map label is invalid.');
        }

        return $value;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1,
            'UTF-8'
        );
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
    }

    private static function finite(mixed $value): float
    {
        if (!is_int($value) && !is_float($value)) {
            throw new InvalidArgumentException('Map number is invalid.');
        }
        $number = (float) $value;
        if (!is_finite($number) || abs($number) > 1000 * 1000) {
            throw new InvalidArgumentException(
                'Map number exceeds the limit.'
            );
        }

        return $number;
    }
}
