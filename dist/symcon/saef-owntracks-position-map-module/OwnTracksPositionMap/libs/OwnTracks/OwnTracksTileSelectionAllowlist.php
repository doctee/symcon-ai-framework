<?php

declare(strict_types=1);

namespace OwnTracksPositionMap\Prototype;

use InvalidArgumentException;

/**
 * Case-study-local XYZ allowlist derived from one private WGS84 selection.
 *
 * The object intentionally exposes no input bounds or tile ranges. Only the
 * authorization decision, a non-reversible fingerprint and aggregate size are
 * available to callers and diagnostics.
 */
final class OwnTracksTileSelectionAllowlist
{
    private const WEB_MERCATOR_LIMIT = 85.05112878;

    /** @var array<int, list<array{0: int, 1: int, 2: int, 3: int}>> */
    private readonly array $rangesByZoom;
    private readonly int $tileCount;
    private readonly string $fingerprint;

    /**
     * @param array<string, mixed> $fitBounds
     */
    public static function fromFitBounds(
        array $fitBounds,
        int $minimumZoom,
        int $maximumZoom,
        int $viewportRingTiles = 1,
        int $maximumTiles = 512
    ): self {
        if (
            $minimumZoom < 0
            || $maximumZoom < $minimumZoom
            || $maximumZoom > 22
            || $viewportRingTiles < 0
            || $viewportRingTiles > 2
            || $maximumTiles < 1
            || $maximumTiles > 2048
        ) {
            throw new InvalidArgumentException('Tile allowlist policy is invalid.');
        }
        $west = self::finiteCoordinate($fitBounds['west'] ?? null, -180.0, 180.0);
        $east = self::finiteCoordinate($fitBounds['east'] ?? null, -180.0, 180.0);
        $south = self::finiteCoordinate(
            $fitBounds['south'] ?? null,
            -self::WEB_MERCATOR_LIMIT,
            self::WEB_MERCATOR_LIMIT
        );
        $north = self::finiteCoordinate(
            $fitBounds['north'] ?? null,
            -self::WEB_MERCATOR_LIMIT,
            self::WEB_MERCATOR_LIMIT
        );
        if ($south > $north) {
            throw new InvalidArgumentException('Tile allowlist latitude bounds are invalid.');
        }
        $crossesAntimeridian = ($fitBounds['crossesAntimeridian'] ?? false) === true
            || $east < $west;
        $longitudeRanges = $crossesAntimeridian
            ? [[$west, 180.0], [-180.0, $east]]
            : [[$west, $east]];

        $rangesByZoom = [];
        $tileCount = 0;
        for ($zoom = $minimumZoom; $zoom <= $maximumZoom; $zoom++) {
            $side = 2 ** $zoom;
            $minimumY = max(0, self::latitudeToY($north, $zoom) - $viewportRingTiles);
            $maximumY = min(
                $side - 1,
                self::latitudeToY($south, $zoom) + $viewportRingTiles
            );
            foreach ($longitudeRanges as [$rangeWest, $rangeEast]) {
                $minimumX = max(
                    0,
                    self::longitudeToX($rangeWest, $zoom) - $viewportRingTiles
                );
                $maximumX = min(
                    $side - 1,
                    self::longitudeToX($rangeEast, $zoom) + $viewportRingTiles
                );
                $range = [$minimumX, $maximumX, $minimumY, $maximumY];
                $rangesByZoom[$zoom][] = $range;
                $tileCount += ($maximumX - $minimumX + 1)
                    * ($maximumY - $minimumY + 1);
                if ($tileCount > $maximumTiles) {
                    throw new InvalidArgumentException(
                        'Tile allowlist exceeds the per-selection tile budget.'
                    );
                }
            }
        }

        return new self($rangesByZoom, $tileCount);
    }

    /**
     * @param array<int, list<array{0: int, 1: int, 2: int, 3: int}>> $rangesByZoom
     */
    private function __construct(array $rangesByZoom, int $tileCount)
    {
        $this->rangesByZoom = $rangesByZoom;
        $this->tileCount = $tileCount;
        $encoded = json_encode($rangesByZoom, JSON_THROW_ON_ERROR);
        $this->fingerprint = hash('sha256', $encoded);
    }

    public function allows(int $zoom, int $x, int $y): bool
    {
        foreach ($this->rangesByZoom[$zoom] ?? [] as $range) {
            if (
                $x >= $range[0]
                && $x <= $range[1]
                && $y >= $range[2]
                && $y <= $range[3]
            ) {
                return true;
            }
        }

        return false;
    }

    public function tileCount(): int
    {
        return $this->tileCount;
    }

    public function fingerprint(): string
    {
        return $this->fingerprint;
    }

    private static function finiteCoordinate(mixed $value, float $minimum, float $maximum): float
    {
        if (
            !is_int($value)
            && !is_float($value)
        ) {
            throw new InvalidArgumentException('Tile allowlist coordinate is invalid.');
        }
        $coordinate = (float) $value;
        if (!is_finite($coordinate) || $coordinate < $minimum || $coordinate > $maximum) {
            throw new InvalidArgumentException('Tile allowlist coordinate is out of range.');
        }

        return $coordinate;
    }

    private static function longitudeToX(float $longitude, int $zoom): int
    {
        $side = 2 ** $zoom;

        return min(
            $side - 1,
            max(0, (int) floor(($longitude + 180.0) / 360.0 * $side))
        );
    }

    private static function latitudeToY(float $latitude, int $zoom): int
    {
        $side = 2 ** $zoom;
        $radians = deg2rad($latitude);
        $normalized = (1.0 - asinh(tan($radians)) / M_PI) / 2.0;

        return min($side - 1, max(0, (int) floor($normalized * $side)));
    }
}
