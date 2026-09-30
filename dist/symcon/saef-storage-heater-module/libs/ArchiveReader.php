<?php

declare(strict_types=1);

namespace SAEF\StorageHeater;

use RuntimeException;

/** Read-only adapter. Deliberately contains no archive configuration or correction API. */
final class ArchiveReader
{
    public function __construct(private int $archive, private int $historyStart)
    {
        if ($archive <= 0 || $historyStart <= 0 || !IPS_InstanceExists($archive) || IPS_GetInstance($archive)['ModuleInfo']['ModuleID'] !== '{43192F0B-135B-4CE7-A0A7-1475603F3060}') {
            throw new RuntimeException('Invalid archive configuration.');
        }
    }

    public function hours(int $id, int $from, int $to, bool $counter = false): array
    {
        $this->validate($id, $counter ? 1 : 0);
        if ($from < $this->historyStart || $to <= $from || $to - $from > 48 * 3600 || $to > time()) {
            throw new RuntimeException('Archive window outside approved history.');
        }
        $rows = AC_GetAggregatedValues($this->archive, $id, 0, $from, $to - 1, 49);
        $values = Forecast::hourly($rows, $from, $to);
        if ($counter && min($values) < 0) {
            throw new RuntimeException('Negative counter delta.');
        }
        return $values;
    }

    public function duty(int $id, int $from, int $to): array
    {
        $this->validate($id, 0);
        if (IPS_GetVariable($id)['VariableType'] !== VARIABLETYPE_BOOLEAN || $from < $this->historyStart || $to > time() || $to <= $from || $to - $from > 48 * 3600) {
            throw new RuntimeException('Invalid boolean archive window.');
        }
        $seed = AC_GetLoggedValues($this->archive, $id, $this->historyStart, $from, 1);
        $rows = AC_GetLoggedValues($this->archive, $id, $from + 1, $to - 1, 2000);
        if (!is_array($seed) || !is_array($rows) || count($seed) !== 1 || count($rows) >= 2000) {
            throw new RuntimeException('Incomplete or saturated boolean archive.');
        }
        return Forecast::duty(array_merge($seed, $rows), $from, $to);
    }

    private function validate(int $id, int $type): void
    {
        if ($id <= 0 || !IPS_VariableExists($id) || !AC_GetLoggingStatus($this->archive, $id) || AC_GetAggregationType($this->archive, $id) !== $type) {
            throw new RuntimeException('Archive source missing or aggregation type incompatible.');
        }
    }
}
