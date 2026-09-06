<?php

declare(strict_types=1);

namespace OwnTracksPositionMap\Prototype;

use InvalidArgumentException;

/**
 * Case-study-local privacy and ownership contract for map/route authorities.
 */
final class OwnTracksProviderPolicy
{
    private const KEY_PATTERN = '/^[a-z0-9][a-z0-9._-]{0,63}$/D';
    private const MAX_TEXT_LENGTH = 160;
    private const OSM_ATTRIBUTION_URL =
        'https://www.openstreetmap.org/copyright';

    /**
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    public static function normalize(array $configuration): array
    {
        $basemap = self::basemap($configuration['basemap'] ?? null);
        $routing = self::routing($configuration['routing'] ?? null);

        return [
            'basemap' => $basemap,
            'routing' => $routing,
            'externalLocationDisclosure' => false,
            'rendererCredentialCount' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function basemap(mixed $configuration): array
    {
        if (!is_array($configuration)) {
            throw new InvalidArgumentException(
                'Basemap provider configuration is missing.'
            );
        }
        $mode = $configuration['mode'] ?? null;
        if ($mode === 'none') {
            return [
                'mode' => 'none',
                'enabled' => false,
                'tileLayerKind' => 'none',
                'locationDisclosure' => 'none',
                'attributionText' => 'OpenLayers · no map tiles',
            ];
        }
        if ($mode !== 'same-origin-xyz') {
            throw new InvalidArgumentException(
                'External basemap providers are not authorized.'
            );
        }

        $authorityKey = self::key(
            $configuration['authorityKey'] ?? null,
            'Basemap authority key'
        );
        $urlTemplate = $configuration['urlTemplate'] ?? null;
        if (!self::sameOriginXyzTemplate($urlTemplate)) {
            throw new InvalidArgumentException(
                'Basemap URL must be a same-origin XYZ template.'
            );
        }
        $attributionText = self::plainText(
            $configuration['attributionText'] ?? null,
            'Basemap attribution'
        );
        $attributionUrl = $configuration['attributionUrl'] ?? null;
        if ($attributionUrl !== self::OSM_ATTRIBUTION_URL) {
            throw new InvalidArgumentException(
                'OSM-derived tiles require the selected attribution URL.'
            );
        }
        $maximumZoom = $configuration['maximumZoom'] ?? null;
        if (!is_int($maximumZoom) || $maximumZoom < 1 || $maximumZoom > 22) {
            throw new InvalidArgumentException(
                'Basemap maximum zoom is invalid.'
            );
        }

        return [
            'mode' => 'same-origin-xyz',
            'enabled' => true,
            'authorityKey' => $authorityKey,
            'tileLayerKind' => 'xyz',
            'urlTemplate' => $urlTemplate,
            'maximumZoom' => $maximumZoom,
            'attributionText' => $attributionText,
            'attributionUrl' => $attributionUrl,
            'locationDisclosure' => 'same-origin-tile-index',
            'credentialMode' => 'none',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function routing(mixed $configuration): array
    {
        if (!is_array($configuration)) {
            throw new InvalidArgumentException(
                'Routing provider configuration is missing.'
            );
        }
        $mode = $configuration['mode'] ?? null;
        $allowFallback = $configuration['allowGeodesicFallback'] ?? null;
        if (!is_bool($allowFallback)) {
            throw new InvalidArgumentException(
                'Routing fallback policy is invalid.'
            );
        }
        if ($mode === 'none') {
            return [
                'mode' => 'none',
                'enabled' => false,
                'transport' => 'none',
                'routeAware' => false,
                'allowGeodesicFallback' => $allowFallback,
                'locationDisclosure' => 'none',
            ];
        }
        if ($mode !== 'internal-osrm') {
            throw new InvalidArgumentException(
                'External routing providers are not authorized.'
            );
        }

        $authorityKey = self::key(
            $configuration['authorityKey'] ?? null,
            'Routing authority key'
        );
        $endpointReference = self::key(
            $configuration['endpointReference'] ?? null,
            'Routing endpoint reference'
        );
        $profileKey = self::key(
            $configuration['profileKey'] ?? null,
            'Routing profile key'
        );
        $timeoutMilliseconds = $configuration['timeoutMilliseconds'] ?? null;
        $maximumRouteAgeSeconds = $configuration['maximumRouteAgeSeconds']
            ?? null;
        if (
            !is_int($timeoutMilliseconds)
            || $timeoutMilliseconds < 100
            || $timeoutMilliseconds > 5000
            || !is_int($maximumRouteAgeSeconds)
            || $maximumRouteAgeSeconds < 30
            || $maximumRouteAgeSeconds > 3600
        ) {
            throw new InvalidArgumentException(
                'Routing time bounds are invalid.'
            );
        }

        return [
            'mode' => 'internal-osrm',
            'enabled' => true,
            'authorityKey' => $authorityKey,
            'endpointReference' => $endpointReference,
            'profileKey' => $profileKey,
            'transport' => 'server-side',
            'routeAware' => true,
            'timeoutMilliseconds' => $timeoutMilliseconds,
            'maximumRouteAgeSeconds' => $maximumRouteAgeSeconds,
            'allowGeodesicFallback' => $allowFallback,
            'locationDisclosure' => 'internal-routing-authority',
            'credentialMode' => 'none',
        ];
    }

    private static function key(mixed $value, string $label): string
    {
        if (!is_string($value) || preg_match(self::KEY_PATTERN, $value) !== 1) {
            throw new InvalidArgumentException($label . ' is invalid.');
        }

        return $value;
    }

    private static function plainText(mixed $value, string $label): string
    {
        if (
            !is_string($value)
            || $value === ''
            || strlen($value) > self::MAX_TEXT_LENGTH
            || preg_match('/[<>\x00-\x1F]/', $value) === 1
        ) {
            throw new InvalidArgumentException($label . ' is invalid.');
        }

        return $value;
    }

    private static function sameOriginXyzTemplate(mixed $value): bool
    {
        return is_string($value)
            && strlen($value) <= 256
            && str_starts_with($value, '/')
            && !str_starts_with($value, '//')
            && !str_contains($value, '..')
            && !str_contains($value, '\\')
            && !str_contains($value, '?')
            && !str_contains($value, '#')
            && substr_count($value, '{z}') === 1
            && substr_count($value, '{x}') === 1
            && substr_count($value, '{y}') === 1
            && preg_match('/[\x00-\x20]/', $value) !== 1;
    }
}
