<?php

declare(strict_types=1);

require_once __DIR__ . '/SolarDisplayPlan.php';

/** Read-only adapter; no refresh, output writes, event changes or object creation. */
final class SolarDisplayReader
{
    /** @param Closure(string, list<mixed>): mixed $call */
    public function __construct(private Closure $call, private Closure $clock)
    {
    }

    /**
     * @param list<array{key: string, instanceId: int, expectedConfigurationHash: string}> $sources
     * @return array<string, mixed>
     */
    public function read(array $sources, string $timezone, int $maximumAge): array
    {
        if (count($sources) !== 2 || $sources[0]['instanceId'] === $sources[1]['instanceId']) {
            throw new InvalidArgumentException('Exactly two distinct sources required.');
        }
        $locks = array_column($sources, 'instanceId');
        sort($locks, SORT_NUMERIC);
        $held = [];
        try {
            foreach ($locks as $id) {
                self::positiveId($id);
                $name = 'OpenMeteoSolarForecast.' . $id;
                if (($this->call)('IPS_SemaphoreEnter', [$name, 100]) !== true) {
                    return ['success' => false, 'code' => 'source_busy'];
                }
                $held[] = $name;
            }
            $now = ($this->clock)();
            if (!is_int($now) || $now <= 0) {
                throw new RuntimeException('clock_invalid');
            }
            $date = (new DateTimeImmutable('@' . $now))->setTimezone(new DateTimeZone($timezone))->format('Y-m-d');
            $bounds = \SAEF\CaseStudy\OpenMeteo\IntervalAligner::localDayBounds($date, $timezone);
            $envelopes = [];
            foreach ($sources as $source) {
                $before = $this->metadata($source['instanceId']);
                $power = $this->forecast('OMSOLAR_GetPowerForecastJson', $source['instanceId'], $bounds);
                $daily = $this->forecast('OMSOLAR_GetDailyEnergyForecastJson', $source['instanceId'], $bounds);
                $after = $this->metadata($source['instanceId']);
                if ($before !== $after) {
                    return ['success' => false, 'code' => 'source_changed_during_read'];
                }
                $envelopes[] = array_merge($source, $before, [
                    'readbackLastSuccess' => $after['lastSuccess'],
                    'readbackConfigurationHash' => $after['configurationHash'],
                    'power' => $power, 'daily' => $daily,
                ]);
            }
            $finished = ($this->clock)();
            if (!is_int($finished) || $finished < $now || $finished - $now > 5) {
                return ['success' => false, 'code' => 'read_deadline_exceeded'];
            }
            // Re-evaluate at completion: midnight/expiry during reads cannot leak a valid old sum.
            return SolarDisplayPlan::evaluate($envelopes, $finished, $timezone, $maximumAge);
        } catch (Throwable $error) {
            return ['success' => false, 'code' => 'source_read_failed', 'detail' => $error->getMessage()];
        } finally {
            foreach (array_reverse($held) as $name) {
                if (($this->call)('IPS_SemaphoreLeave', [$name]) !== true) {
                    throw new RuntimeException('Source lock release failed.');
                }
            }
        }
    }

    public static function positiveId(int $id): void
    {
        if ($id <= 0 || $id > 65535) {
            throw new InvalidArgumentException('Invalid positive object identity.');
        }
    }

    /** @return array<string, mixed> */
    private function metadata(int $id): array
    {
        if (($this->call)('IPS_ObjectExists', [$id]) !== true) {
            throw new RuntimeException('Source missing.');
        }
        $object = ($this->call)('IPS_GetObject', [$id]);
        $instance = ($this->call)('IPS_GetInstance', [$id]);
        // Explicit compatibility with platforms predating the named type constants.
        $instanceType = defined('OBJECTTYPE_INSTANCE') ? constant('OBJECTTYPE_INSTANCE') : 1;
        $variableType = defined('OBJECTTYPE_VARIABLE') ? constant('OBJECTTYPE_VARIABLE') : 2;
        if (
            !is_array($object) || ($object['ObjectType'] ?? null) !== $instanceType
            || !is_array($instance)
            || ($instance['ModuleInfo']['ModuleID'] ?? null) !== '{C86E5442-13CF-4145-B23C-EF2B7635D79E}'
        ) {
            throw new RuntimeException('Wrong solar source identity.');
        }
        $result = ['status' => $instance['InstanceStatus'] ?? null];
        foreach (['LastSuccess' => 'lastSuccess', 'DataState' => 'dataState', 'ConfigurationHash' => 'configurationHash', 'TodayEnergyForecast' => 'todayValue'] as $ident => $field) {
            $child = ($this->call)('IPS_GetObjectIDByIdent', [$ident, $id]);
            if (!is_int($child)) {
                throw new RuntimeException('Source output missing.');
            }
            self::positiveId($child);
            $meta = ($this->call)('IPS_GetObject', [$child]);
            if (!is_array($meta) || ($meta['ObjectType'] ?? null) !== $variableType || ($meta['ParentID'] ?? null) !== $id || ($meta['ObjectIdent'] ?? null) !== $ident) {
                throw new RuntimeException('Source output identity drift.');
            }
            $result[$field] = ($this->call)('GetValue', [$child]);
            $result['identity.' . $field] = $child;
        }
        return $result;
    }

    /** @param array{from: int, to: int} $bounds
     * @return array<string, mixed>
     */
    private function forecast(string $function, int $id, array $bounds): array
    {
        $json = ($this->call)($function, [$id, $bounds['from'], $bounds['to'], 'system']);
        if (!is_string($json) || strlen($json) > 256 * 1024) {
            throw new RuntimeException('Forecast response too large or invalid.');
        }
        $value = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($value) || array_is_list($value)) {
            throw new RuntimeException('Forecast response is not an object.');
        }
        return $value;
    }
}
