<?php

declare(strict_types=1);

namespace SAEF\CaseStudy\OpenMeteo;

final class Profiles
{
    public static function ensure(): void
    {
        \SAEF_EnsureProfile(
            'OPENMETEO.DataState',
            1,
            '',
            '',
            '',
            0,
            5,
            1,
            null,
            [
                [0, 'Unconfigured', '', -1],
                [1, 'Fetching', '', -1],
                [2, 'Current', '', -1],
                [3, 'Stale', '', -1],
                [4, 'Warning', '', -1],
                [5, 'Error', '', -1],
            ]
        );
        self::integerProfile('OPENMETEO.WeatherCode', '', 0, 99);
        self::floatProfile('OPENMETEO.Pressure', ' hPa', 800.0, 1200.0, 0.1, 1);
        self::floatProfile('OPENMETEO.WindSpeed', ' km/h', 0.0, 300.0, 0.1, 1);
        self::integerProfile('OPENMETEO.Direction', ' °', 0, 360);
        self::floatProfile('OPENMETEO.WaterDepth', ' mm', 0.0, 500.0, 0.1, 1);
        self::floatProfile('OPENMETEO.Snowfall', ' cm', 0.0, 500.0, 0.1, 1);
        self::floatProfile('OPENMETEO.SoilMoisture', ' m³/m³', 0.0, 1.0, 0.001, 3);
        self::integerProfile('OPENMETEO.Duration', ' s', 0, 172800);
        self::floatProfile('OPENMETEO.Power', ' kW', 0.0, 100000.0, 0.01, 2);
        self::floatProfile('OPENMETEO.Energy', ' kWh', 0.0, 1000000.0, 0.01, 2);
    }

    private static function integerProfile(
        string $name,
        string $suffix,
        int $minimum,
        int $maximum
    ): void {
        \SAEF_EnsureProfile(
            $name,
            1,
            '',
            '',
            $suffix,
            $minimum,
            $maximum,
            1
        );
    }

    private static function floatProfile(
        string $name,
        string $suffix,
        float $minimum,
        float $maximum,
        float $step,
        int $digits
    ): void {
        \SAEF_EnsureProfile(
            $name,
            2,
            '',
            '',
            $suffix,
            $minimum,
            $maximum,
            $step,
            $digits
        );
    }
}
