<?php

declare(strict_types=1);

$library = dirname(__DIR__) . '/distribution/libs/OpenMeteo/';
foreach (
    [
    'FieldCatalog.php',
    'ForecastPoint.php',
    'ForecastSeries.php',
    'ParsedForecast.php',
    'IntervalAligner.php',
    'RequestBuilder.php',
    'ResponseParser.php',
    'WeatherForecastProjector.php',
    'PvConfiguration.php',
    'SolarForecastCalculator.php',
    'ForecastStateReducer.php',
    ] as $file
) {
    require_once $library . $file;
}

function fixture(string $name): string
{
    $content = file_get_contents(dirname(__DIR__) . '/fixtures/' . $name);
    if ($content === false) {
        throw new RuntimeException('Fixture could not be read.');
    }

    return $content;
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function same(mixed $expected, mixed $actual, string $message): void
{
    check($expected === $actual, $message);
}

function near(float $expected, float $actual, float $tolerance, string $message): void
{
    check(abs($expected - $actual) <= $tolerance, $message);
}

/** @param class-string<Throwable> $exceptionClass */
function throws(callable $callback, string $exceptionClass, string $message): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        check($exception instanceof $exceptionClass, $message . ' (wrong exception)');

        return;
    }

    throw new RuntimeException($message . ' (no exception)');
}
