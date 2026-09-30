<?php

declare(strict_types=1);

require __DIR__ . '/core.php';
require __DIR__ . '/../distribution/libs/FireplaceLog.php';

use SAEF\StorageHeater\FireplaceLog;
use SAEF\StorageHeater\Forecast;

function localInput(string $date): string
{
    $time = new DateTimeImmutable($date, new DateTimeZone('UTC'));
    return json_encode(array_combine(
        ['year', 'month', 'day', 'hour', 'minute', 'second'],
        array_map('intval', explode('-', $time->format('Y-m-d-H-i-s')))
    ), JSON_THROW_ON_ERROR);
}

$before = $checks;
$cycle = Forecast::cycle('2026-01-14', 'Europe/Berlin');
$start = FireplaceLog::localStart(localInput('2026-01-14 18:00:00'));
check($start === strtotime('2026-01-14 18:00 Europe/Berlin'), 'Explicit Berlin timezone');
rejects(fn() => FireplaceLog::localStart(localInput('2026-03-29 02:30:00')), 'DST nonexistent time rejected');
rejects(fn() => FireplaceLog::localStart(localInput('2026-10-25 02:30:00')), 'DST ambiguous time rejected');
rejects(fn() => FireplaceLog::localStart('{}'), 'Empty date rejected');
$empty = ['schema' => 1, 'entries' => []];
$now = $cycle['end'] + 3600;
$log = FireplaceLog::record($empty, $start, $now);
check($log['entries'][0] === ['startedAt' => $start, 'recordedAt' => $now, 'cancelledAt' => null], 'Only start and audit timestamps');
check(FireplaceLog::read(Forecast::encode($log)) === $log, 'Persistent roundtrip');
check(FireplaceLog::record($log, $start, $now + 60) === $log, 'Duplicate does not alter first recording time');
rejects(fn() => FireplaceLog::record($empty, $now + 1, $now), 'Future start rejected');
rejects(fn() => FireplaceLog::record($empty, $now - 400 * 86400 - 1, $now), 'Too old start rejected');
check(FireplaceLog::classify($log, $cycle)['group'] === 'reported_start', 'Retrospective classification');
check(FireplaceLog::classify($log, $cycle, $cycle['issueFrom'])['group'] === 'no_reported_start', 'No lookahead from later recording');
check(FireplaceLog::classify($empty, $cycle)['group'] === 'no_reported_start', 'Unreported is not a claim of no fire');
$cancelled = FireplaceLog::cancel($log, $start, $now + 60);
check(count($cancelled['entries']) === 1 && $cancelled['entries'][0]['cancelledAt'] === $now + 60, 'Cancellation retains audit entry');
check(FireplaceLog::classify($cancelled, $cycle)['group'] === 'no_reported_start', 'Cancelled entry excluded today');
check(FireplaceLog::classify($cancelled, $cycle, $now)['group'] === 'reported_start', 'Cancellation does not rewrite previous knowledge');
check(FireplaceLog::cancel($cancelled, $start, $now + 120) === $cancelled, 'Cancellation idempotent');
rejects(fn() => FireplaceLog::cancel($empty, $start, $now), 'Missing cancellation rejected');
$bounds = FireplaceLog::record($empty, $cycle['start'] - 86400, $now);
$bounds = FireplaceLog::record($bounds, $cycle['end'], $now);
check(count(FireplaceLog::classify($bounds, $cycle)['reportedStarts']) === 1, 'Comparison boundaries are half open');
$records = [['cycle' => $cycle, 'measurement' => ['status' => 'complete', 'errorKwh' => -2.0]],
    ['cycle' => $cycle, 'measurement' => ['status' => 'complete', 'errorKwh' => null]]];
$score = FireplaceLog::score($records, $log);
check($score['reported_start'] === ['nights' => 1, 'maeKwh' => 2.0], 'Only scored forecasts enter comparison');
check($score['no_reported_start']['maeKwh'] === null, 'No samples means unknown error');
rejects(fn() => FireplaceLog::read('{"schema":1,"entries":[{}]}'), 'Corrupt log rejected');
echo ($checks - $before) . " fireplace checks passed\n";
