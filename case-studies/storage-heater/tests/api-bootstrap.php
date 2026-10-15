<?php

declare(strict_types=1);

const EVENTTYPE_CYCLIC = 1;
const EVENTCYCLICDATETYPE_ONCE = 1;
const EVENTCYCLICTIMETYPE_ONCE = 0;
const EM_UPDATE = 10803;
const EM_CHANGEACTIVE = 10804;
const EM_CHANGECYCLIC = 10810;
const EM_CHANGECYCLICDATEFROM = 10811;
const EM_CHANGECYCLICTIMEFROM = 10813;
const VM_UPDATE = 10603;

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
