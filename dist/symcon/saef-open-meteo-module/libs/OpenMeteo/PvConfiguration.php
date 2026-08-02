<?php

declare(strict_types=1);

namespace SAEF\CaseStudy\OpenMeteo;

use InvalidArgumentException;

final class PvConfiguration
{
    /**
     * @var list<array{
     *     ident: string,
     *     peakPowerKw: float,
     *     tiltDegrees: float,
     *     azimuthDegrees: float,
     *     temperatureCoefficientPctPerC: float,
     *     noctDeltaCAt800Wm2: float,
     *     derateFactor: float,
     *     inverterIdent: string,
     *     orientationKey: string
     * }>
     */
    private array $arrays;

    /** @var array<string, array{ident: string, acLimitKw: float, efficiencyFactor: float}> */
    private array $inverters;

    /**
     * @param list<array<string, mixed>> $arrays
     * @param list<array<string, mixed>> $inverters
     */
    public function __construct(array $arrays, array $inverters)
    {
        if ($arrays === [] || $inverters === []) {
            throw new InvalidArgumentException('PV arrays and inverters must not be empty.');
        }

        $this->inverters = $this->normalizeInverters($inverters);
        $this->arrays = $this->normalizeArrays($arrays, $this->inverters);
    }

    /**
     * @return list<array{
     *     ident: string,
     *     peakPowerKw: float,
     *     tiltDegrees: float,
     *     azimuthDegrees: float,
     *     temperatureCoefficientPctPerC: float,
     *     noctDeltaCAt800Wm2: float,
     *     derateFactor: float,
     *     inverterIdent: string,
     *     orientationKey: string
     * }>
     */
    public function arrays(): array
    {
        return $this->arrays;
    }

    /** @return array<string, array{ident: string, acLimitKw: float, efficiencyFactor: float}> */
    public function inverters(): array
    {
        return $this->inverters;
    }

    /** @return array<string, array{tiltDegrees: float, azimuthDegrees: float}> */
    public function uniqueOrientations(): array
    {
        $orientations = [];
        foreach ($this->arrays as $array) {
            $orientations[$array['orientationKey']] = [
                'tiltDegrees' => $array['tiltDegrees'],
                'azimuthDegrees' => $array['azimuthDegrees'],
            ];
        }
        ksort($orientations);

        return $orientations;
    }

    public static function orientationKey(float $tiltDegrees, float $azimuthDegrees): string
    {
        self::assertRange($tiltDegrees, 0.0, 90.0, 'PV array tilt');
        self::assertRange($azimuthDegrees, -180.0, 180.0, 'PV array azimuth');

        return self::formatFloat($tiltDegrees) . ':' . self::formatFloat($azimuthDegrees);
    }

    /**
     * @param list<array<string, mixed>> $entries
     *
     * @return array<string, array{ident: string, acLimitKw: float, efficiencyFactor: float}>
     */
    private function normalizeInverters(array $entries): array
    {
        $result = [];
        foreach ($entries as $entry) {
            $ident = self::ident($entry, 'Ident', 'PV inverter');
            if (isset($result[$ident])) {
                throw new InvalidArgumentException('PV inverter Idents must be unique.');
            }
            $acLimitKw = self::number($entry, 'AcLimitKw', 'PV inverter');
            $efficiencyFactor = self::number($entry, 'EfficiencyFactor', 'PV inverter');
            self::assertRange($acLimitKw, PHP_FLOAT_MIN, PHP_FLOAT_MAX, 'PV inverter AC limit');
            self::assertRange($efficiencyFactor, 0.0, 1.0, 'PV inverter efficiency');
            $result[$ident] = [
                'ident' => $ident,
                'acLimitKw' => $acLimitKw,
                'efficiencyFactor' => $efficiencyFactor,
            ];
        }

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @param array<string, array{ident: string, acLimitKw: float, efficiencyFactor: float}> $inverters
     *
     * @return list<array{
     *     ident: string,
     *     peakPowerKw: float,
     *     tiltDegrees: float,
     *     azimuthDegrees: float,
     *     temperatureCoefficientPctPerC: float,
     *     noctDeltaCAt800Wm2: float,
     *     derateFactor: float,
     *     inverterIdent: string,
     *     orientationKey: string
     * }>
     */
    private function normalizeArrays(array $entries, array $inverters): array
    {
        $result = [];
        $seenIdents = [];
        foreach ($entries as $entry) {
            $ident = self::ident($entry, 'Ident', 'PV array');
            if (isset($seenIdents[$ident])) {
                throw new InvalidArgumentException('PV array Idents must be unique.');
            }
            $seenIdents[$ident] = true;

            $inverterIdent = self::ident($entry, 'InverterIdent', 'PV array inverter');
            if (!isset($inverters[$inverterIdent])) {
                throw new InvalidArgumentException('PV array references an unknown inverter.');
            }

            $peakPowerKw = self::number($entry, 'PeakPowerKw', 'PV array');
            $tiltDegrees = self::number($entry, 'TiltDegrees', 'PV array');
            $azimuthDegrees = self::number($entry, 'AzimuthDegrees', 'PV array');
            $temperatureCoefficient = self::number(
                $entry,
                'TemperatureCoefficientPctPerC',
                'PV array'
            );
            $noctDelta = self::number($entry, 'NoctDeltaCAt800Wm2', 'PV array');
            $derateFactor = self::number($entry, 'DerateFactor', 'PV array');

            self::assertRange($peakPowerKw, PHP_FLOAT_MIN, PHP_FLOAT_MAX, 'PV array peak power');
            self::assertRange($tiltDegrees, 0.0, 90.0, 'PV array tilt');
            self::assertRange($azimuthDegrees, -180.0, 180.0, 'PV array azimuth');
            self::assertFinite($temperatureCoefficient, 'PV array temperature coefficient');
            self::assertRange($noctDelta, 0.0, PHP_FLOAT_MAX, 'PV array NOCT delta');
            self::assertRange($derateFactor, 0.0, 1.0, 'PV array derate factor');

            $result[] = [
                'ident' => $ident,
                'peakPowerKw' => $peakPowerKw,
                'tiltDegrees' => $tiltDegrees,
                'azimuthDegrees' => $azimuthDegrees,
                'temperatureCoefficientPctPerC' => $temperatureCoefficient,
                'noctDeltaCAt800Wm2' => $noctDelta,
                'derateFactor' => $derateFactor,
                'inverterIdent' => $inverterIdent,
                'orientationKey' => self::orientationKey($tiltDegrees, $azimuthDegrees),
            ];
        }

        usort(
            $result,
            static fn (array $left, array $right): int => $left['ident'] <=> $right['ident']
        );

        return $result;
    }

    /** @param array<string, mixed> $entry */
    private static function ident(array $entry, string $key, string $owner): string
    {
        $value = $entry[$key] ?? null;
        if (!is_string($value) || preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException($owner . ' Ident is invalid.');
        }

        return $value;
    }

    /** @param array<string, mixed> $entry */
    private static function number(array $entry, string $key, string $owner): float
    {
        $value = $entry[$key] ?? null;
        if (!is_int($value) && !is_float($value)) {
            throw new InvalidArgumentException($owner . ' numeric configuration is invalid.');
        }

        return (float) $value;
    }

    private static function assertRange(
        float $value,
        float $minimum,
        float $maximum,
        string $field
    ): void {
        if (!is_finite($value) || $value < $minimum || $value > $maximum) {
            throw new InvalidArgumentException($field . ' is outside the supported range.');
        }
    }

    private static function assertFinite(float $value, string $field): void
    {
        if (!is_finite($value)) {
            throw new InvalidArgumentException($field . ' must be finite.');
        }
    }

    private static function formatFloat(float $value): string
    {
        $formatted = rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');

        return $formatted === '-0' ? '0' : $formatted;
    }
}
