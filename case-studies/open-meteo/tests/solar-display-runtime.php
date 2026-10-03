<?php

declare(strict_types=1);

require_once __DIR__ . '/solar-display-plan.php';
require_once __DIR__ . '/../candidate/SolarDisplayReader.php';
require_once __DIR__ . '/../candidate/SolarDisplayArchive.php';

/** Fake only documented platform calls; no live globals are defined. */
final class DisplayPlatformFake
{
    public int $now;
    /** @var array<int, array<string, mixed>> */
    public array $sources;
    /** @var array<int, list<array{TimeStamp: int, Value: float}>> */
    public array $rows = [21 => [], 22 => []];
    /** @var array<int, list<array<string, mixed>>> */
    public array $aggregates = [21 => [], 22 => []];
    /** @var list<string> */
    public array $writes = [];
    /** @var array<string, bool> */
    public array $locks = [];
    public string $generation;
    public string $fault = '';
    public bool $delayAggregation = false;
    public bool $drift = false;
    public bool $sourceRace = false;

    public function __construct()
    {
        $this->sources = [11 => displaySource('plant_a', 11, '2026-10-03', 3.0), 12 => displaySource('plant_b', 12, '2026-10-03', 2.0)];
        $this->now = $this->sources[11]['lastSuccess'] + 100;
        $this->generation = str_repeat('b', 32);
    }

    /** @param list<mixed> $args */
    public function call(string $name, array $args): mixed
    {
        $id = $args[0] ?? null;
        if ($name === 'IPS_SemaphoreEnter') {
            if ($this->locks[$id] ?? false) {
                return false;
            }
            $this->locks[$id] = true;
            return true;
        }
        if ($name === 'IPS_SemaphoreLeave') {
            unset($this->locks[$id]);
            return true;
        }
        if ($name === 'IPS_ObjectExists') {
            return in_array($id, [11, 12, 21, 22, 31, 41], true);
        }
        if ($name === 'IPS_GetObject') {
            if ($id === 31) {
                return ['ObjectType' => 3, 'ObjectInfo' => 'SAEF SolarDisplay owner v1 ' . $this->generation];
            }
            if (isset($this->sources[$id])) {
                return ['ObjectType' => 1];
            }
            if (in_array($id, [21, 22], true)) {
                return ['ObjectType' => 2, 'ParentID' => $this->drift ? 99 : 31, 'ObjectIdent' => 'SolarDisplay_Chart_plant_' . ($id === 21 ? 'a' : 'b'), 'ObjectInfo' => SolarDisplayArchive::OWNER_MARKER . ' ' . $this->generation];
            }
            return ['ObjectType' => 2, 'ParentID' => intdiv($id, 10), 'ObjectIdent' => ['LastSuccess', 'DataState', 'ConfigurationHash', 'TodayEnergyForecast'][$id % 10]];
        }
        if ($name === 'IPS_GetInstance') {
            return ['InstanceStatus' => 102, 'ModuleInfo' => ['ModuleID' => $id === 41 ? '{43192F0B-135B-4CE7-A0A7-1475603F3060}' : '{C86E5442-13CF-4145-B23C-EF2B7635D79E}']];
        }
        if ($name === 'IPS_GetObjectIDByIdent') {
            return $args[1] * 10 + array_search($id, ['LastSuccess', 'DataState', 'ConfigurationHash', 'TodayEnergyForecast'], true);
        }
        if ($name === 'GetValue') {
            return $this->sources[intdiv($id, 10)][['lastSuccess', 'dataState', 'configurationHash', 'todayValue'][$id % 10]];
        }
        if (str_starts_with($name, 'OMSOLAR_')) {
            if ($this->sourceRace) {
                $this->sources[$id]['lastSuccess']++;
            }
            return json_encode($this->sources[$id][str_contains($name, 'Power') ? 'power' : 'daily'], JSON_THROW_ON_ERROR);
        }
        if ($name === 'IPS_GetVariable') {
            return ['VariableType' => 2, 'VariableCustomProfile' => '~Electricity', 'VariableAction' => 0, 'VariableCustomAction' => 0];
        }
        if ($name === 'AC_GetLoggingStatus') {
            return true;
        }
        if ($name === 'AC_GetAggregationType') {
            return 1;
        }
        if ($name === 'AC_GetCounterIgnoreZeros') {
            return false;
        }
        if ($name === 'AC_GetCompaction') {
            return [];
        }
        $target = $args[1];
        if ($name === 'AC_GetLoggedValues') {
            return array_reverse(array_values(array_filter($this->rows[$target], static fn(array $r): bool => $r['TimeStamp'] >= $args[2] && $r['TimeStamp'] <= $args[3])));
        }
        if ($name === 'AC_GetAggregatedValues') {
            return array_values(array_filter($this->aggregates[$target], static fn(array $r): bool => $r['TimeStamp'] >= $args[3] && $r['TimeStamp'] <= $args[4]));
        }
        if (!in_array($target, [21, 22], true)) {
            throw new RuntimeException('Attempted out-of-scope archive mutation.');
        }
        $this->writes[] = $name;
        if ($name === 'AC_DeleteVariableData') {
            $old = $this->rows[$target];
            $this->rows[$target] = array_values(array_filter($old, static fn(array $r): bool => $r['TimeStamp'] < $args[2] || $r['TimeStamp'] > $args[3]));
            if ($this->fault === 'after_delete') {
                throw new RuntimeException('Simulated crash after deletion.');
            }
            return count($old) - count($this->rows[$target]);
        }
        if ($name === 'AC_AddLoggedValues') {
            foreach ($args[2] as $row) {
                $this->rows[$target][] = $row;
                if ($this->fault === 'partial_insert') {
                    throw new RuntimeException('Simulated partial insertion.');
                }
            }
            if ($this->fault === 'after_insert') {
                throw new RuntimeException('Simulated crash after insertion.');
            }
            return true;
        }
        if ($name === 'AC_ReAggregateVariable') {
            if (!$this->delayAggregation) {
                $rows = $this->rows[$target];
                usort($rows, static fn(array $a, array $b): int => $a['TimeStamp'] <=> $b['TimeStamp']);
                $daily = [];
                $previous = null;
                foreach ($rows as $row) {
                    $day = (new DateTimeImmutable('@' . $row['TimeStamp']))->setTimezone(new DateTimeZone('Europe/Berlin'))->setTime(0, 0)->getTimestamp();
                    $daily[$day] = ($daily[$day] ?? 0.0) + ($previous === null ? 0.0 : max(0.0, $row['Value'] - $previous));
                    $previous = $row['Value'];
                }
                $this->aggregates[$target] = [];
                foreach ($daily as $day => $energy) {
                    $this->aggregates[$target][] = ['TimeStamp' => $day, 'Avg' => $energy];
                }
            }
            if ($this->fault === 'after_aggregate') {
                throw new RuntimeException('Simulated crash after aggregation.');
            }
            return true;
        }
        throw new RuntimeException('Unexpected API: ' . $name);
    }
}

/** @return array{DisplayPlatformFake, SolarDisplayReader, Closure(): array<string, mixed>} */
function displayRuntimeFixture(): array
{
    $fake = new DisplayPlatformFake();
    $reader = new SolarDisplayReader($fake->call(...), static fn(): int => $fake->now);
    $config = [['key' => 'plant_a', 'instanceId' => 11, 'expectedConfigurationHash' => str_repeat('a', 64)], ['key' => 'plant_b', 'instanceId' => 12, 'expectedConfigurationHash' => str_repeat('a', 64)]];
    $read = static fn(): array => $reader->read($config, 'Europe/Berlin', 10800);
    return [$fake, $reader, $read];
}

/** @param Closure(): array<string, mixed> $read */
function displayWriter(DisplayPlatformFake $fake, Closure $read, string $path): SolarDisplayArchive
{
    return new SolarDisplayArchive($fake->call(...), $read, static fn(): int => $fake->now, new SolarDisplayJournal($path), 31, 41, ['plant_a' => 21, 'plant_b' => 22], str_repeat('b', 32), str_repeat('c', 64));
}

$temporary = realpath(sys_get_temp_dir()) . '/saef-display-' . bin2hex(random_bytes(8));
mkdir($temporary, 0700);
$directories = [];
try {
    [$fake, $reader, $read] = displayRuntimeFixture();
    same(true, $read()['success'], 'Read coherent module outputs.');
    same([], $fake->writes, 'Reader never mutates.');
    same([], $fake->locks, 'Reader releases both locks.');
    $fake->sourceRace = true;
    same('source_changed_during_read', $read()['code'], 'Reject metadata race.');
    $fake->sourceRace = false;
    $fake->locks['OpenMeteoSolarForecast.12'] = true;
    same('source_busy', $read()['code'], 'Busy source fails closed.');
    check(!isset($fake->locks['OpenMeteoSolarForecast.11']), 'Release earlier lock on failure.');
    unset($fake->locks['OpenMeteoSolarForecast.12']);
    $fake->now += 2 * 86400;
    same(false, $read()['success'], 'Stale source has no fresh sum.');
    $path = $temporary . '/rejection';
    mkdir($path, 0700);
    $directories[] = $path;
    $writer = displayWriter($fake, $read, $path);
    same(false, $writer->apply()['success'], 'Invalid sources are not converted to zero.');
    same([], $fake->writes, 'Invalid inputs cannot mutate chart.');
    check(!file_exists($path . '/pending'), 'Invalid input creates no intent.');
    throws(static fn() => new SolarDisplayArchive($fake->call(...), $read, static fn(): int => $fake->now, new SolarDisplayJournal($path), 31, 41, ['plant_a' => 0, 'plant_b' => 22], str_repeat('b', 32), str_repeat('c', 64)), InvalidArgumentException::class, 'Root is never a mutation target.');

    foreach (['none', 'after_delete', 'partial_insert', 'after_insert', 'after_aggregate', 'rollback', 'drift', 'conflict', 'async', 'tamper', 'generation', 'contract', 'midnight'] as $scenario) {
        $path = $temporary . '/' . $scenario;
        mkdir($path, 0700);
        $directories[] = $path;
        [$fake, $reader, $read] = displayRuntimeFixture();
        $writer = displayWriter($fake, $read, $path);
        same(true, $writer->preview()['success'], 'Preview succeeds.');
        same([], $fake->writes, 'Preview writes nothing.');
        if ($scenario === 'none') {
            same('applied', $writer->apply()['code'], 'Initial apply.');
            $count = count($fake->writes);
            same('unchanged', $writer->apply()['code'], 'Same plan is idempotent.');
            same($count, count($fake->writes), 'No duplicate mutations.');
            $fake->sources[11] = displaySource('plant_a', 11, '2026-10-03', 1.0);
            same('applied', $writer->apply()['code'], 'Falling forecast replaces projection.');
            near(1.0, $fake->aggregates[21][0]['Avg'], 0.000001, 'No accumulated positive revisions.');
            continue;
        }
        $fake->fault = in_array($scenario, ['after_delete', 'partial_insert', 'after_insert', 'after_aggregate'], true) ? $scenario : 'partial_insert';
        throws(static fn() => $writer->apply(), RuntimeException::class, 'Simulated crash must retain recovery evidence.');
        check(is_file($path . '/pending'), 'Journal published before mutation.');
        same([], $fake->locks, 'Release writer lock after exception.');
        $fake->fault = '';
        // A new object simulates script/service restart, not retained process memory.
        $writer = displayWriter($fake, $read, $path);
        if ($scenario === 'rollback') {
            same('restored', $writer->rollbackPending()['code'], 'Restore saved empty before-state.');
            same([], $fake->rows[21], 'No partial records left.');
        } elseif ($scenario === 'drift') {
            $fake->drift = true;
            $count = count($fake->writes);
            throws(static fn() => $writer->apply(), RuntimeException::class, 'Reject drift during recovery.');
            same($count, count($fake->writes), 'Drift causes no further mutation.');
        } elseif ($scenario === 'conflict') {
            $fake->rows[21][0]['Value'] = 42.0;
            $count = count($fake->writes);
            throws(static fn() => $writer->apply(), RuntimeException::class, 'Reject unknown external data.');
            same($count, count($fake->writes), 'Conflict never overwritten.');
        } elseif ($scenario === 'tamper') {
            $journal = trim((string) file_get_contents($path . '/pending'));
            file_put_contents($path . '/' . $journal . '.json', '{}');
            $count = count($fake->writes);
            throws(static fn() => $writer->apply(), RuntimeException::class, 'Reject corrupt journal.');
            same($count, count($fake->writes), 'Corrupt journal cannot mutate archive.');
        } elseif ($scenario === 'generation') {
            $fake->generation = str_repeat('d', 32);
            $count = count($fake->writes);
            throws(static fn() => $writer->apply(), RuntimeException::class, 'Reject object replacement generation.');
            same($count, count($fake->writes), 'Generation mismatch cannot mutate archive.');
        } elseif ($scenario === 'contract') {
            $writer = new SolarDisplayArchive($fake->call(...), $read, static fn(): int => $fake->now, new SolarDisplayJournal($path), 31, 41, ['plant_a' => 21, 'plant_b' => 22], str_repeat('b', 32), str_repeat('d', 64));
            $count = count($fake->writes);
            throws(static fn() => $writer->apply(), RuntimeException::class, 'Reject changed source contract.');
            same($count, count($fake->writes), 'Source contract mismatch cannot mutate archive.');
        } elseif ($scenario === 'midnight') {
            $fake->now += 86400;
            same('applied', $writer->apply()['code'], 'Finish committed old-day intent after restart.');
            same(false, $read()['success'], 'Recovered projection does not make stale sources fresh.');
        } elseif ($scenario === 'async') {
            $fake->delayAggregation = true;
            same('aggregation_pending', $writer->apply()['code'], 'Asynchronous aggregate not prematurely accepted.');
            $count = count($fake->writes);
            same('aggregation_pending', $writer->apply()['code'], 'Pending aggregate is polled.');
            same($count, count($fake->writes), 'Do not restart aggregation every poll.');
            $fake->now += 601;
            same('aggregation_timeout', $writer->apply()['code'], 'Bounded aggregation wait.');
        } else {
            same('applied', $writer->apply()['code'], 'Recover interrupted operation.');
            same(2, count($fake->rows[21]), 'Exactly two points after resume.');
            same('unchanged', $writer->apply()['code'], 'Recovered operation idempotent.');
        }
    }
    foreach (['2026-03-29', '2026-10-25', '2026-12-31'] as $date) {
        $path = $temporary . '/day-' . $date;
        mkdir($path, 0700);
        $directories[] = $path;
        [$fake, $reader, $read] = displayRuntimeFixture();
        $fake->sources = [11 => displaySource('plant_a', 11, $date, 0.0), 12 => displaySource('plant_b', 12, $date, 2.0)];
        $fake->now = $fake->sources[11]['lastSuccess'] + 100;
        $start = $fake->sources[11]['daily']['data']['system'][0]['validFrom'];
        $previous = (new DateTimeImmutable('@' . $start))->setTimezone(new DateTimeZone('Europe/Berlin'))->modify('-1 day')->getTimestamp();
        foreach ([21, 22] as $id) {
            $fake->rows[$id] = [['TimeStamp' => $previous, 'Value' => 0.0], ['TimeStamp' => $previous + 1, 'Value' => 4.0]];
        }
        $writer = displayWriter($fake, $read, $path);
        same('applied', $writer->apply()['code'], 'Apply zero/day-boundary projection.');
        same([['TimeStamp' => $previous, 'Value' => 0.0], ['TimeStamp' => $previous + 1, 'Value' => 4.0]], array_slice($fake->rows[21], 0, 2), 'Neighbor day unchanged.');
        near(4.0, array_sum(array_column($fake->aggregates[21], 'Avg')), 0.000001, 'Daily totals preserve prior day and true zero.');
        near(6.0, array_sum(array_column($fake->aggregates[22], 'Avg')), 0.000001, 'Cross-day sum contains forecast only once.');
        same('unchanged', $writer->apply()['code'], 'Zero projection is idempotent.');
    }
} finally {
    foreach ($directories as $path) {
        foreach (glob($path . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($path);
    }
    rmdir($temporary);
}
echo "solar display runtime: ok\n";
