<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use SAEF\CaseStudy\OpenMeteo\FieldCatalog;
use SAEF\CaseStudy\OpenMeteo\ResponseParser;

$forecast = ResponseParser::parse(
    fixture('weather-core-success.json'),
    ['temperature_2m', 'relative_humidity_2m', 'precipitation', 'weather_code'],
    ['temperature_2m', 'precipitation', 'wind_gusts_10m'],
    ['temperature_2m_max', 'precipitation_sum']
);
same('Europe/Berlin', $forecast->timezone(), 'Timezone differs.');
same(3, $forecast->hourly('temperature_2m')->count(), 'Hourly series length differs.');

$temperature = $forecast->current('temperature_2m')->points()[0];
same(FieldCatalog::SEMANTICS_INSTANT, $temperature->semantics(), 'Temperature semantics differ.');
same($temperature->validFrom(), $temperature->validTo(), 'Instant must have zero duration.');

$currentPrecipitation = $forecast->current('precipitation')->points()[0];
same(900, $currentPrecipitation->durationSeconds(), 'Current aggregate interval differs.');
$hourlyPrecipitation = $forecast->hourly('precipitation')->points()[0];
same(3600, $hourlyPrecipitation->durationSeconds(), 'Hourly aggregate interval differs.');
same(1735714800, $hourlyPrecipitation->validFrom(), 'Preceding-hour start differs.');

$daily = $forecast->daily('temperature_2m_max')->points()[0];
same(86400, $daily->durationSeconds(), 'Normal local day must have 24 hours.');

$soil = ResponseParser::parse(
    fixture('weather-soil-success.json'),
    [],
    ['temperature_2m', 'soil_temperature_6cm', 'soil_moisture_3_to_9cm'],
    []
);
same('m³/m³', $soil->hourly('soil_moisture_3_to_9cm')->unit(), 'Soil unit differs.');

throws(
    static fn () => ResponseParser::parse(fixture('error-response.json'), [], [], []),
    UnexpectedValueException::class,
    'Provider error envelope must be rejected.'
);
throws(
    static fn () => ResponseParser::parse('{', [], [], []),
    UnexpectedValueException::class,
    'Malformed JSON must be rejected.'
);

$mutate = static function (callable $callback): string {
    $payload = json_decode(fixture('weather-core-success.json'), true, 512, JSON_THROW_ON_ERROR);
    $callback($payload);

    return json_encode($payload, JSON_THROW_ON_ERROR);
};
throws(
    static fn () => ResponseParser::parse(
        $mutate(static function (array &$payload): void {
            unset($payload['hourly_units']['precipitation']);
        }),
        [],
        ['precipitation'],
        []
    ),
    UnexpectedValueException::class,
    'Missing units must be rejected.'
);
throws(
    static fn () => ResponseParser::parse(
        $mutate(static function (array &$payload): void {
            $payload['hourly_units']['precipitation'] = 'inch';
        }),
        [],
        ['precipitation'],
        []
    ),
    UnexpectedValueException::class,
    'Incompatible units must be rejected.'
);
throws(
    static fn () => ResponseParser::parse(
        $mutate(static function (array &$payload): void {
            array_pop($payload['hourly']['precipitation']);
        }),
        [],
        ['precipitation'],
        []
    ),
    UnexpectedValueException::class,
    'Unequal parallel arrays must be rejected.'
);
throws(
    static fn () => ResponseParser::parse(
        $mutate(static function (array &$payload): void {
            $payload['hourly']['temperature_2m'][1] = null;
        }),
        [],
        ['temperature_2m'],
        []
    ),
    UnexpectedValueException::class,
    'Null values must be rejected.'
);
$visibilityWithGap = ResponseParser::parse(
    $mutate(static function (array &$payload): void {
        $payload['hourly_units']['visibility'] = 'm';
        $payload['hourly']['visibility'] = [10000.0, null, 8000.0];
    }),
    [],
    ['visibility'],
    []
);
same(2, $visibilityWithGap->hourly('visibility')->count(), 'Visibility gap was not omitted.');
same(
    1735725600,
    $visibilityWithGap->hourly('visibility')->points()[1]->sourceTimestamp(),
    'Visibility timestamps were not preserved across a gap.'
);
throws(
    static fn () => ResponseParser::parse(
        $mutate(static function (array &$payload): void {
            $payload['hourly_units']['visibility'] = 'm';
            $payload['hourly']['visibility'] = [null, null, null];
        }),
        [],
        ['visibility'],
        []
    ),
    UnexpectedValueException::class,
    'An entirely unavailable visibility series must be rejected.'
);
throws(
    static fn () => ResponseParser::parse(
        $mutate(static function (array &$payload): void {
            $payload['hourly']['time'][1] = $payload['hourly']['time'][0];
        }),
        [],
        ['temperature_2m'],
        []
    ),
    UnexpectedValueException::class,
    'Duplicate timestamps must be rejected.'
);
throws(
    static fn () => ResponseParser::parse(
        str_replace('400.0', '-1.0', fixture('solar-south-success.json')),
        [],
        ['temperature_2m', 'global_tilted_irradiance'],
        []
    ),
    UnexpectedValueException::class,
    'Negative tilted irradiance must be rejected.'
);

echo "response-parser: ok\n";
