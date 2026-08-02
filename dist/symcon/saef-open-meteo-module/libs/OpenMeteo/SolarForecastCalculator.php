<?php

declare(strict_types=1);

namespace SAEF\CaseStudy\OpenMeteo;

use InvalidArgumentException;

final class SolarForecastCalculator
{
    /**
     * @param array<string, ForecastSeries> $gtiByOrientation
     */
    public static function calculate(
        PvConfiguration $configuration,
        array $gtiByOrientation,
        ForecastSeries $temperature
    ): ForecastSeries {
        if ($temperature->field() !== 'temperature_2m' || $temperature->unit() !== '°C') {
            throw new InvalidArgumentException('Solar temperature series contract is invalid.');
        }
        foreach ($temperature->points() as $point) {
            if ($point->semantics() !== FieldCatalog::SEMANTICS_INSTANT) {
                throw new InvalidArgumentException('Solar temperature semantics are invalid.');
            }
        }

        $requiredOrientations = $configuration->uniqueOrientations();
        foreach ($requiredOrientations as $orientationKey => $_orientation) {
            $series = $gtiByOrientation[$orientationKey] ?? null;
            if (!$series instanceof ForecastSeries) {
                throw new InvalidArgumentException('A required solar orientation is missing.');
            }
            if ($series->field() !== 'global_tilted_irradiance' || $series->unit() !== 'W/m²') {
                throw new InvalidArgumentException('Solar irradiance series contract is invalid.');
            }
        }
        if (count($gtiByOrientation) !== count($requiredOrientations)) {
            throw new InvalidArgumentException('Solar orientation set differs from configuration.');
        }

        $reference = reset($gtiByOrientation);
        if (!$reference instanceof ForecastSeries || $reference->count() === 0) {
            throw new InvalidArgumentException('Solar irradiance series must not be empty.');
        }

        $result = [];
        foreach ($reference->points() as $referencePoint) {
            if ($referencePoint->semantics() !== FieldCatalog::SEMANTICS_PRECEDING_INTERVAL) {
                throw new InvalidArgumentException('Solar irradiance interval semantics are invalid.');
            }
            $temperaturePoint = $temperature->pointAtSourceTimestamp(
                $referencePoint->sourceTimestamp()
            );
            if (!$temperaturePoint instanceof ForecastPoint) {
                throw new InvalidArgumentException('Solar temperature timestamp is missing.');
            }

            $dcByInverter = array_fill_keys(array_keys($configuration->inverters()), 0.0);
            foreach ($configuration->arrays() as $array) {
                $gtiPoint = $gtiByOrientation[$array['orientationKey']]
                    ->pointAtSourceTimestamp($referencePoint->sourceTimestamp());
                if (!$gtiPoint instanceof ForecastPoint) {
                    throw new InvalidArgumentException('Solar orientation timestamp is missing.');
                }
                self::assertSameInterval($referencePoint, $gtiPoint);

                $gti = (float) $gtiPoint->value();
                if (!is_finite($gti) || $gti < 0.0) {
                    throw new InvalidArgumentException('Solar irradiance value is invalid.');
                }
                $airTemperature = (float) $temperaturePoint->value();
                if (!is_finite($airTemperature)) {
                    throw new InvalidArgumentException('Solar temperature value is invalid.');
                }

                $cellTemperature = $airTemperature
                    + ($gti / 800.0) * $array['noctDeltaCAt800Wm2'];
                $temperatureFactor = 1.0
                    + ($array['temperatureCoefficientPctPerC'] / 100.0)
                    * ($cellTemperature - 25.0);
                $dcPower = ($gti / 1000.0)
                    * $array['peakPowerKw']
                    * $temperatureFactor
                    * $array['derateFactor'];
                if (!is_finite($dcPower)) {
                    throw new InvalidArgumentException('Calculated solar power is invalid.');
                }
                $dcByInverter[$array['inverterIdent']] += max(0.0, $dcPower);
            }

            $acPower = 0.0;
            foreach ($configuration->inverters() as $inverter) {
                $groupAcPower = $dcByInverter[$inverter['ident']]
                    * $inverter['efficiencyFactor'];
                $acPower += min($groupAcPower, $inverter['acLimitKw']);
            }

            $result[] = new ForecastPoint(
                'ac_power',
                'kW',
                FieldCatalog::SEMANTICS_PRECEDING_INTERVAL,
                $referencePoint->sourceTimestamp(),
                $referencePoint->validFrom(),
                $referencePoint->validTo(),
                $acPower
            );
        }

        return new ForecastSeries('ac_power', 'kW', $result);
    }

    public static function dailyEnergy(
        ForecastSeries $power,
        string $timezone
    ): ForecastSeries {
        if ($power->field() !== 'ac_power' || $power->unit() !== 'kW') {
            throw new InvalidArgumentException('Solar power series contract is invalid.');
        }

        /** @var array<string, array{from: int, to: int, energy: float}> $days */
        $days = [];
        foreach ($power->points() as $point) {
            if ($point->semantics() !== FieldCatalog::SEMANTICS_PRECEDING_INTERVAL) {
                throw new InvalidArgumentException('Solar power interval semantics are invalid.');
            }
            $date = (new \DateTimeImmutable('@' . $point->validFrom()))
                ->setTimezone(new \DateTimeZone($timezone))
                ->format('Y-m-d');
            $bounds = IntervalAligner::localDayBounds($date, $timezone);
            if ($point->validFrom() < $bounds['from'] || $point->validTo() > $bounds['to']) {
                throw new InvalidArgumentException('Solar interval crosses a local-day boundary.');
            }
            if (!isset($days[$date])) {
                $days[$date] = ['from' => $bounds['from'], 'to' => $bounds['to'], 'energy' => 0.0];
            }
            $days[$date]['energy'] += (float) $point->value()
                * ($point->durationSeconds() / 3600.0);
        }

        ksort($days);
        $points = [];
        foreach ($days as $day) {
            $points[] = new ForecastPoint(
                'daily_energy',
                'kWh',
                FieldCatalog::SEMANTICS_LOCAL_DAY,
                $day['from'],
                $day['from'],
                $day['to'],
                $day['energy']
            );
        }

        return new ForecastSeries('daily_energy', 'kWh', $points);
    }

    private static function assertSameInterval(
        ForecastPoint $reference,
        ForecastPoint $candidate
    ): void {
        if (
            $candidate->validFrom() !== $reference->validFrom()
            || $candidate->validTo() !== $reference->validTo()
        ) {
            throw new InvalidArgumentException('Solar orientation intervals do not align.');
        }
    }
}
