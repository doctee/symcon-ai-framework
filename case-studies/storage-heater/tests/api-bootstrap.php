<?php

declare(strict_types=1);

// Additive analysis-only declarations, verified against official Symcon documentation.
require_once __DIR__ . '/../../../stubs/symcon.php';
define('VARIABLETYPE_BOOLEAN', 0);
define('VARIABLETYPE_INTEGER', 1);
define('VARIABLETYPE_FLOAT', 2);
function AC_GetAggregatedValues(int $archive, int $variable, int $level, int $from, int $to, int $limit): array
{
    return [];
}
function AC_GetAggregationType(int $archive, int $variable): int
{
    return 0;
}
function OMWEATHER_GetHourlyForecastJson(int $instance, int $from, int $to, string $fields): string
{
    return '{}';
}
