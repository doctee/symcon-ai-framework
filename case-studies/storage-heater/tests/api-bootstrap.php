<?php

declare(strict_types=1);

// Additive analysis-only declarations, verified against official Symcon documentation.
require_once __DIR__ . '/../../../stubs/symcon.php';
define('VARIABLETYPE_BOOLEAN', 0);
define('VARIABLETYPE_INTEGER', 1);
define('VARIABLETYPE_FLOAT', 2);
define('VARIABLE_PRESENTATION_ENUMERATION', '{52D9E126-D7D2-2CBB-5E62-4CF7BA7C5D82}');
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
