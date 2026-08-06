<?php

declare(strict_types=1);

namespace SAEF\CaseStudy\DwdNowcast;

use InvalidArgumentException;

final class RequestBuilder
{
    public const NATIVE_RESOLUTION_MINUTES = 5;
    public const MAXIMUM_HORIZON_MINUTES = 120;

    private const ENDPOINT = 'https://maps.dwd.de/geoserver/wms';
    private const LAYER = 'dwd:Niederschlagsradar';
    private const BOUNDING_BOX_HALF_SIZE_DEGREES = 0.01;
    private const LOOKBACK_MINUTES = 10;
    private const LOOKAHEAD_MINUTES = 130;

    public static function build(float $latitude, float $longitude, int $now): string
    {
        self::validateCoordinates($latitude, $longitude);
        if ($now <= 0) {
            throw new InvalidArgumentException('Nowcast request timestamp must be positive.');
        }

        $resolutionSeconds = self::NATIVE_RESOLUTION_MINUTES * 60;
        $anchor = intdiv($now, $resolutionSeconds) * $resolutionSeconds;
        $from = $anchor - (self::LOOKBACK_MINUTES * 60);
        $to = $anchor + (self::LOOKAHEAD_MINUTES * 60);
        $delta = self::BOUNDING_BOX_HALF_SIZE_DEGREES;

        $query = [
            'SERVICE' => 'WMS',
            'VERSION' => '1.3.0',
            'REQUEST' => 'GetFeatureInfo',
            'LAYERS' => self::LAYER,
            'QUERY_LAYERS' => self::LAYER,
            'CRS' => 'EPSG:4326',
            // WMS 1.3.0 uses latitude,longitude axis order for EPSG:4326.
            'BBOX' => implode(',', [
                self::decimal($latitude - $delta),
                self::decimal($longitude - $delta),
                self::decimal($latitude + $delta),
                self::decimal($longitude + $delta),
            ]),
            'WIDTH' => '3',
            'HEIGHT' => '3',
            'I' => '1',
            'J' => '1',
            'INFO_FORMAT' => 'application/json',
            'FEATURE_COUNT' => '64',
            'TIME' => self::isoUtc($from) . '/' . self::isoUtc($to) . '/PT5M',
            'REFERENCE_TIME' => 'current',
        ];

        return self::ENDPOINT . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private static function validateCoordinates(float $latitude, float $longitude): void
    {
        if (
            !is_finite($latitude)
            || !is_finite($longitude)
            || $latitude < -90.0
            || $latitude > 90.0
            || $longitude < -180.0
            || $longitude > 180.0
        ) {
            throw new InvalidArgumentException('Nowcast coordinates are invalid.');
        }
    }

    private static function decimal(float $value): string
    {
        return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
    }

    private static function isoUtc(int $timestamp): string
    {
        return gmdate('Y-m-d\TH:i:s\Z', $timestamp);
    }
}
