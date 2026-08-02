<?php

declare(strict_types=1);

/** @var array<string, array<string, mixed>> $scaffoldProfiles */
$scaffoldProfiles = [];

/** @var array<int, array<string, mixed>> $scaffoldInstances */
$scaffoldInstances = [];

/** @var array<int, SharedLocation> $scaffoldLocationModules */
$scaffoldLocationModules = [];

class IPSModule
{
    public int $InstanceID = 42;

    /** @var array<string, mixed> */
    private array $properties = [];

    /** @var array<string, int|string> */
    private array $attributes = [];

    /** @var array<string, array{id: int, type: string, profile: string, position: int}> */
    private array $variables = [];

    private int $nextVariableId = 1000;
    private int $status = 0;
    /** @var array<string, array{interval: int, script: string}> */
    private array $timers = [];
    private int $parentLifecycleCalls = 0;

    /** @var array<string, mixed> */
    private array $values = [];

    /** @var array<int, true> */
    private array $references = [];

    public function Create(): void
    {
        $this->parentLifecycleCalls++;
    }

    public function ApplyChanges(): void
    {
        $this->parentLifecycleCalls++;
    }

    protected function RegisterPropertyBoolean(string $ident, bool $default): void
    {
        $this->properties[$ident] ??= $default;
    }

    protected function RegisterPropertyFloat(string $ident, float $default): void
    {
        $this->properties[$ident] ??= $default;
    }

    protected function RegisterPropertyInteger(string $ident, int $default): void
    {
        $this->properties[$ident] ??= $default;
    }

    protected function RegisterPropertyString(string $ident, string $default): void
    {
        $this->properties[$ident] ??= $default;
    }

    protected function RegisterAttributeInteger(string $ident, int $default): void
    {
        $this->attributes[$ident] ??= $default;
    }

    protected function ReadAttributeInteger(string $ident): int
    {
        if (!array_key_exists($ident, $this->attributes)) {
            throw new RuntimeException('Unknown test attribute.');
        }

        return (int) $this->attributes[$ident];
    }

    protected function WriteAttributeInteger(string $ident, int $value): void
    {
        $this->ReadAttributeInteger($ident);
        $this->attributes[$ident] = $value;
    }

    protected function RegisterAttributeString(string $ident, string $default): void
    {
        $this->attributes[$ident] ??= $default;
    }

    protected function ReadAttributeString(string $ident): string
    {
        if (!array_key_exists($ident, $this->attributes)) {
            throw new RuntimeException('Unknown test attribute.');
        }

        return (string) $this->attributes[$ident];
    }

    protected function WriteAttributeString(string $ident, string $value): void
    {
        $this->ReadAttributeString($ident);
        $this->attributes[$ident] = $value;
    }

    protected function ReadPropertyBoolean(string $ident): bool
    {
        return (bool) $this->property($ident);
    }

    protected function ReadPropertyFloat(string $ident): float
    {
        return (float) $this->property($ident);
    }

    protected function ReadPropertyInteger(string $ident): int
    {
        return (int) $this->property($ident);
    }

    protected function ReadPropertyString(string $ident): string
    {
        return (string) $this->property($ident);
    }

    protected function RegisterVariableBoolean(
        string $ident,
        string $name,
        string $profile,
        int $position
    ): void {
        $this->registerVariable($ident, 'boolean', $profile, $position);
    }

    protected function RegisterVariableFloat(
        string $ident,
        string $name,
        string $profile,
        int $position
    ): void {
        $this->registerVariable($ident, 'float', $profile, $position);
    }

    protected function RegisterVariableInteger(
        string $ident,
        string $name,
        string $profile,
        int $position
    ): void {
        $this->registerVariable($ident, 'integer', $profile, $position);
    }

    protected function RegisterVariableString(
        string $ident,
        string $name,
        string $profile,
        int $position
    ): void {
        $this->registerVariable($ident, 'string', $profile, $position);
    }

    protected function RegisterTimer(string $ident, int $interval, string $script): void
    {
        $this->timers[$ident] ??= ['interval' => $interval, 'script' => $script];
    }

    protected function SetTimerInterval(string $ident, int $interval): void
    {
        if (!isset($this->timers[$ident])) {
            throw new RuntimeException('Unknown test timer.');
        }
        $this->timers[$ident]['interval'] = $interval;
    }

    protected function RegisterReference(int $objectId): void
    {
        $this->references[$objectId] = true;
    }

    protected function UnregisterReference(int $objectId): void
    {
        unset($this->references[$objectId]);
    }

    protected function SetValue(string $ident, mixed $value): void
    {
        if (!isset($this->variables[$ident])) {
            throw new RuntimeException('Unknown test variable.');
        }
        $this->values[$ident] = match ($this->variables[$ident]['type']) {
            'boolean' => (bool) $value,
            'float' => (float) $value,
            'integer' => (int) $value,
            default => (string) $value,
        };
    }

    protected function SetStatus(int $status): void
    {
        $this->status = $status;
    }

    public function testSetProperty(string $ident, mixed $value): void
    {
        $this->property($ident);
        $this->properties[$ident] = $value;
    }

    /** @return array<string, array{id: int, type: string, profile: string, position: int}> */
    public function testVariables(): array
    {
        return $this->variables;
    }

    public function testStatus(): int
    {
        return $this->status;
    }

    public function testTimerRegistrations(): int
    {
        return count($this->timers);
    }

    public function testTimerInterval(string $ident): int
    {
        return $this->timers[$ident]['interval'] ?? -1;
    }

    /** @return list<int> */
    public function testReferences(): array
    {
        $references = array_keys($this->references);
        sort($references, SORT_NUMERIC);

        return $references;
    }

    public function testReadValue(string $ident): mixed
    {
        return $this->values[$ident] ?? null;
    }

    private function property(string $ident): mixed
    {
        if (!array_key_exists($ident, $this->properties)) {
            throw new RuntimeException('Unknown test property.');
        }

        return $this->properties[$ident];
    }

    private function registerVariable(
        string $ident,
        string $type,
        string $profile,
        int $position
    ): void {
        if (isset($this->variables[$ident])) {
            if (
                $this->variables[$ident]['type'] !== $type
                || $this->variables[$ident]['profile'] !== $profile
                || $this->variables[$ident]['position'] !== $position
            ) {
                throw new RuntimeException('Variable contract changed during repeated registration.');
            }

            return;
        }

        $this->variables[$ident] = [
            'id' => $this->nextVariableId++,
            'type' => $type,
            'profile' => $profile,
            'position' => $position,
        ];
    }
}

function IPS_VariableProfileExists(string $name): bool
{
    global $scaffoldProfiles;

    return isset($scaffoldProfiles[$name]);
}

function IPS_CreateVariableProfile(string $name, int $type): void
{
    global $scaffoldProfiles;

    $scaffoldProfiles[$name] = [
        'ProfileType' => $type,
        'icon' => '',
        'prefix' => '',
        'suffix' => '',
        'minimum' => 0,
        'maximum' => 0,
        'step' => 0,
        'digits' => 0,
        'associations' => [],
    ];
}

/** @return array<string, mixed> */
function IPS_GetVariableProfile(string $name): array
{
    global $scaffoldProfiles;

    return $scaffoldProfiles[$name];
}

function IPS_SetVariableProfileIcon(string $name, string $icon): void
{
    global $scaffoldProfiles;

    $scaffoldProfiles[$name]['icon'] = $icon;
}

function IPS_SetVariableProfileText(string $name, string $prefix, string $suffix): void
{
    global $scaffoldProfiles;

    $scaffoldProfiles[$name]['prefix'] = $prefix;
    $scaffoldProfiles[$name]['suffix'] = $suffix;
}

function IPS_SetVariableProfileDigits(string $name, int $digits): void
{
    global $scaffoldProfiles;

    $scaffoldProfiles[$name]['digits'] = $digits;
}

function IPS_SetVariableProfileValues(
    string $name,
    int|float $minimum,
    int|float $maximum,
    int|float $step
): void {
    global $scaffoldProfiles;

    $scaffoldProfiles[$name]['minimum'] = $minimum;
    $scaffoldProfiles[$name]['maximum'] = $maximum;
    $scaffoldProfiles[$name]['step'] = $step;
}

function IPS_SetVariableProfileAssociation(
    string $name,
    int|float|string $value,
    string $label,
    string $icon,
    int $color
): void {
    global $scaffoldProfiles;

    $scaffoldProfiles[$name]['associations'][(string) $value] = [
        'label' => $label,
        'icon' => $icon,
        'color' => $color,
    ];
}

function IPS_InstanceExists(int $instanceId): bool
{
    global $scaffoldInstances;

    return isset($scaffoldInstances[$instanceId]);
}

/** @return array<string, mixed> */
function IPS_GetInstance(int $instanceId): array
{
    global $scaffoldInstances;

    return $scaffoldInstances[$instanceId] ?? [];
}

function IPS_SemaphoreEnter(string $name, int $milliseconds): bool
{
    return true;
}

function IPS_SemaphoreLeave(string $name): bool
{
    return true;
}

function IPS_LogMessage(string $sender, string $message): void
{
}

function SAEFLOCATION_GetDescriptor(int $instanceId): string
{
    global $scaffoldLocationModules;

    if (!isset($scaffoldLocationModules[$instanceId])) {
        throw new RuntimeException('Unknown shared location test instance.');
    }

    return $scaffoldLocationModules[$instanceId]->GetDescriptor();
}

/** @param array<string, int|string|bool> $parameters */
function Sys_GetURLContentEx(string $url, array $parameters): string|false
{
    return $url === '' ? '' : false;
}

require_once dirname(__DIR__, 3) . '/helpers/object/EnsureProfile.php';
require_once dirname(__DIR__, 3) . '/helpers/diagnostics/ConfigurationHash.php';
require_once __DIR__ . '/../distribution/SharedLocation/module.php';
require_once __DIR__ . '/../distribution/OpenMeteoWeather/module.php';
require_once __DIR__ . '/../distribution/OpenMeteoSolarForecast/module.php';
require_once __DIR__ . '/TestOpenMeteoWeather.php';

function scaffoldCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__) . '/distribution';
$library = json_decode(
    (string) file_get_contents($root . '/library.json'),
    true,
    32,
    JSON_THROW_ON_ERROR
);
scaffoldCheck(
    ($library['id'] ?? null) === '{6D115210-EB10-4741-B479-6331C860B200}',
    'Library identity differs.'
);

$moduleIds = [];
foreach (['SharedLocation', 'OpenMeteoWeather', 'OpenMeteoSolarForecast'] as $moduleName) {
    $moduleRoot = $root . '/' . $moduleName;
    foreach (['module.json', 'form.json', 'locale.json'] as $jsonFile) {
        $decoded = json_decode(
            (string) file_get_contents($moduleRoot . '/' . $jsonFile),
            true,
            64,
            JSON_THROW_ON_ERROR
        );
        scaffoldCheck(is_array($decoded), 'Module JSON must contain an object.');
    }
    $metadata = json_decode(
        (string) file_get_contents($moduleRoot . '/module.json'),
        true,
        32,
        JSON_THROW_ON_ERROR
    );
    $moduleId = $metadata['id'] ?? null;
    scaffoldCheck(
        is_string($moduleId) && !isset($moduleIds[$moduleId]),
        'Module GUID must be present and unique.'
    );
    $moduleIds[$moduleId] = true;
    scaffoldCheck(
        ($metadata['name'] ?? null) === $moduleName,
        'Module metadata name must match its folder and PHP class name.'
    );
    scaffoldCheck(
        preg_match('/^[A-Za-z0-9_](?:[A-Za-z0-9_ ]*[A-Za-z0-9_])?$/', $moduleName) === 1,
        'Module metadata name contains an unsupported character.'
    );
    scaffoldCheck(($metadata['type'] ?? null) === 3, 'Module type differs.');
    scaffoldCheck(
        ($metadata['parentRequirements'] ?? null) === []
        && ($metadata['childRequirements'] ?? null) === []
        && ($metadata['implemented'] ?? null) === [],
        'Inactive module must not expose a transport interface.'
    );

    $source = (string) file_get_contents($moduleRoot . '/module.php');
    scaffoldCheck(
        str_contains($source, 'class ' . $moduleName . ' extends IPSModule'),
        'Module PHP class does not match its metadata name.'
    );
    scaffoldCheck(!str_contains($source, 'curl_'), 'Module must not use direct cURL calls.');
    scaffoldCheck(!str_contains($source, 'SendDataToParent'), 'Module transport boundary differs.');
    if ($moduleName === 'OpenMeteoSolarForecast') {
        foreach (['RegisterTimer', 'SetTimerInterval', 'UpdateData('] as $forbidden) {
            scaffoldCheck(
                !str_contains($source, $forbidden),
                'Inactive solar module contains an activation surface.'
            );
        }
    }
}

$sharedLocation = new SharedLocation();
$sharedLocation->Create();
$sharedLocation->ApplyChanges();
scaffoldCheck(
    $sharedLocation->testStatus() === 104,
    'Unconfigured shared location must be inactive.'
);
scaffoldCheck(
    $sharedLocation->testVariables() === [],
    'Shared location must not create child variables.'
);
scaffoldCheck(
    $sharedLocation->testTimerRegistrations() === 0,
    'Shared location must not register a timer.'
);
$sharedLocation->testSetProperty('LocationConfigured', true);
$sharedLocation->testSetProperty('LocationKey', 'location_a');
$sharedLocation->testSetProperty('Latitude', 48.0);
$sharedLocation->testSetProperty('Longitude', 11.0);
$sharedLocation->ApplyChanges();
scaffoldCheck($sharedLocation->testStatus() === 102, 'Valid shared location must become active.');
$sharedDescriptor = json_decode(
    $sharedLocation->GetDescriptor(),
    true,
    16,
    JSON_THROW_ON_ERROR
);
scaffoldCheck(
    ($sharedDescriptor['location']['key'] ?? null) === 'location_a',
    'Shared location key differs.'
);
$sharedLocation->testSetProperty('LocationKey', 'Invalid Key');
$sharedLocation->ApplyChanges();
scaffoldCheck(
    $sharedLocation->testStatus() === 200,
    'Invalid shared location must fail closed.'
);
$sharedLocation->testSetProperty('LocationKey', 'location_a');
$sharedLocation->ApplyChanges();
$scaffoldInstances[2001] = [
    'ModuleInfo' => [
        'ModuleID' => '{3B6B9CB0-8D95-4358-874A-13FF1A8BECD1}',
    ],
];
$scaffoldLocationModules[2001] = $sharedLocation;

$weather = new OpenMeteoWeather();
$weather->Create();
$weather->ApplyChanges();
scaffoldCheck($weather->testStatus() === 104, 'Unconfigured weather scaffold must be inactive.');
scaffoldCheck(count($weather->testVariables()) === 41, 'Weather variable contract differs.');
scaffoldCheck(count($scaffoldProfiles) === 11, 'Open-Meteo profile contract differs.');
$profiles = $scaffoldProfiles;
$weatherVariables = $weather->testVariables();
$weather->ApplyChanges();
scaffoldCheck(
    $weather->testVariables() === $weatherVariables,
    'Repeated weather ApplyChanges changed variable identity.'
);
scaffoldCheck($scaffoldProfiles === $profiles, 'Repeated profile creation was not idempotent.');
scaffoldCheck($weather->testTimerRegistrations() === 1, 'Weather update timer is missing.');
scaffoldCheck($weather->testTimerInterval('UpdateData') === 0, 'Unconfigured timer must be disabled.');

$weather->testSetProperty('LocationConfigured', true);
$weather->testSetProperty('Latitude', 48.0);
$weather->testSetProperty('Longitude', 11.0);
$weather->ApplyChanges();
scaffoldCheck($weather->testStatus() === 102, 'Valid weather module must become active.');
scaffoldCheck(
    $weather->testTimerInterval('UpdateData') === 3600000,
    'Weather polling interval differs.'
);
$weather->testSetProperty('EnableAutomaticUpdates', false);
$weather->ApplyChanges();
scaffoldCheck($weather->testStatus() === 102, 'Manual-only weather module must remain active.');
scaffoldCheck(
    $weather->testTimerInterval('UpdateData') === 0,
    'Manual-only weather timer must be disabled.'
);
$weather->testSetProperty('EnableAutomaticUpdates', true);
$weather->testSetProperty('Timezone', 'Invalid/Zone');
$weather->ApplyChanges();
scaffoldCheck($weather->testStatus() === 200, 'Invalid weather configuration must fail closed.');
scaffoldCheck($weather->testTimerInterval('UpdateData') === 0, 'Invalid timer must be disabled.');
$weather->testSetProperty('LocationConfigured', false);
$weather->testSetProperty('LocationInstanceId', 2001);
$weather->ApplyChanges();
scaffoldCheck($weather->testStatus() === 102, 'Shared-location weather module must become active.');
scaffoldCheck($weather->testReferences() === [2001], 'Shared location reference was not registered.');
$weatherDescriptor = json_decode(
    $weather->GetLocationDescriptor(),
    true,
    16,
    JSON_THROW_ON_ERROR
);
scaffoldCheck(
    (float) ($weatherDescriptor['location']['latitude'] ?? 0.0) === 48.0,
    'Weather did not resolve the shared latitude.'
);
$weather->testSetProperty('LocationInstanceId', 2002);
$weather->ApplyChanges();
scaffoldCheck($weather->testStatus() === 200, 'Unknown shared location must fail closed.');
scaffoldCheck(
    $weather->testReferences() === [2001],
    'Invalid shared location replaced the last valid reference.'
);
$weather->testSetProperty('LocationInstanceId', 0);
$weather->testSetProperty('LocationConfigured', true);
$weather->testSetProperty('Timezone', 'Europe/Berlin');
$weather->ApplyChanges();
scaffoldCheck($weather->testStatus() === 102, 'Legacy location fallback must remain active.');
scaffoldCheck($weather->testReferences() === [], 'Legacy location fallback retained a reference.');

/**
 * @param list<string> $fields
 *
 * @return array<string, string>
 */
function scaffoldWeatherUnits(array $fields): array
{
    $units = [];
    foreach ($fields as $field) {
        $units[$field] = match (true) {
            str_contains($field, 'temperature'), str_contains($field, 'dew_point') => '°C',
            str_contains($field, 'humidity'), str_contains($field, 'probability'),
                str_contains($field, 'cloud_cover') => '%',
            str_contains($field, 'soil_moisture') => 'm³/m³',
            str_contains($field, 'snowfall') => 'cm',
            $field === 'vapour_pressure_deficit' => 'kPa',
            str_contains($field, 'pressure') => 'hPa',
            str_contains($field, 'wind_direction') => '°',
            str_contains($field, 'wind_speed'), str_contains($field, 'wind_gusts') => 'km/h',
            $field === 'weather_code' => 'wmo code',
            $field === 'is_day' => '',
            $field === 'visibility' => 'm',
            $field === 'sunshine_duration' => 's',
            $field === 'precipitation_hours' => 'h',
            $field === 'shortwave_radiation_sum' => 'MJ/m²',
            $field === 'sunrise', $field === 'sunset' => 'unixtime',
            default => 'mm',
        };
    }

    return $units;
}

function scaffoldWeatherValue(string $field, int $index): int|float
{
    return match (true) {
        $field === 'weather_code' => 1,
        $field === 'is_day' => 1,
        $field === 'sunrise' => 1735707600 + ($index * 86400),
        $field === 'sunset' => 1735743600 + ($index * 86400),
        str_contains($field, 'wind_direction') => 180,
        str_contains($field, 'probability'), str_contains($field, 'humidity'),
            str_contains($field, 'cloud_cover') => 50,
        str_contains($field, 'soil_moisture') => 0.25,
        default => 10.0 + $index,
    };
}

function scaffoldWeatherResponse(bool $withSoil): string
{
    $currentFields = \SAEF\CaseStudy\OpenMeteo\FieldCatalog::weatherCurrentFields();
    $hourlyFields = \SAEF\CaseStudy\OpenMeteo\FieldCatalog::weatherHourlyFields($withSoil);
    $dailyFields = \SAEF\CaseStudy\OpenMeteo\FieldCatalog::weatherDailyFields();
    $payload = [
        'latitude' => 48.0,
        'longitude' => 11.0,
        'timezone' => 'Europe/Berlin',
        'utc_offset_seconds' => 3600,
        'current_units' => scaffoldWeatherUnits($currentFields),
        'current' => ['time' => 1735718400, 'interval' => 900],
        'hourly_units' => scaffoldWeatherUnits($hourlyFields),
        'hourly' => ['time' => [1735718400, 1735722000, 1735725600]],
        'daily_units' => scaffoldWeatherUnits($dailyFields),
        'daily' => ['time' => [1735686000, 1735772400]],
    ];
    foreach ($currentFields as $field) {
        $payload['current'][$field] = scaffoldWeatherValue($field, 0);
    }
    foreach ($hourlyFields as $field) {
        $payload['hourly'][$field] = [
            scaffoldWeatherValue($field, 0),
            scaffoldWeatherValue($field, 1),
            scaffoldWeatherValue($field, 2),
        ];
    }
    foreach ($dailyFields as $field) {
        $payload['daily'][$field] = [
            scaffoldWeatherValue($field, 0),
            scaffoldWeatherValue($field, 1),
        ];
    }

    return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
}

$runtimeWeather = new TestOpenMeteoWeather();
$runtimeWeather->Create();
$runtimeWeather->ApplyChanges();
$runtimeWeather->testSetProperty('LocationConfigured', true);
$runtimeWeather->testSetProperty('Latitude', 48.0);
$runtimeWeather->testSetProperty('Longitude', 11.0);
$runtimeWeather->ApplyChanges();
$runtimeWeather->testQueueResponse(scaffoldWeatherResponse(false));
$success = json_decode($runtimeWeather->UpdateData(), true, 16, JSON_THROW_ON_ERROR);
scaffoldCheck(
    ($success['success'] ?? null) === true,
    'Weather update did not succeed: ' . json_encode($success)
);
scaffoldCheck($runtimeWeather->testReadValue('DataState') === 2, 'Weather data is not current.');
scaffoldCheck($runtimeWeather->testReadValue('Temperature') === 10.0, 'Current value differs.');
scaffoldCheck(
    $runtimeWeather->testReadValue('TodayTemperatureMax') === 10.0,
    'Daily value differs.'
);
$current = json_decode($runtimeWeather->GetCurrentJson(), true, 32, JSON_THROW_ON_ERROR);
scaffoldCheck(isset($current['data']['temperature_2m']), 'Current cache is unavailable.');
$hourly = json_decode(
    $runtimeWeather->GetHourlyForecastJson(
        1735718400,
        1735725601,
        '["temperature_2m","precipitation"]'
    ),
    true,
    64,
    JSON_THROW_ON_ERROR
);
scaffoldCheck(
    count($hourly['data']['temperature_2m'] ?? []) === 3,
    'Bounded hourly forecast differs.'
);

$runtimeWeather->testSetNow(1735718460);
$runtimeWeather->testQueueResponse(false);
$failure = json_decode($runtimeWeather->UpdateData(), true, 16, JSON_THROW_ON_ERROR);
scaffoldCheck(($failure['code'] ?? null) === 'transport_error', 'Failure classification differs.');
scaffoldCheck($runtimeWeather->testReadValue('DataState') === 4, 'Last-good failure must warn.');
scaffoldCheck($runtimeWeather->testReadValue('Temperature') === 10.0, 'Last-good value was cleared.');
scaffoldCheck(
    $runtimeWeather->testTimerInterval('UpdateData') === 300000,
    'First retry interval differs.'
);

$runtimeWeather->testSetNow(1735729261);
$runtimeWeather->testQueueResponse(false);
$runtimeWeather->UpdateData();
scaffoldCheck($runtimeWeather->testReadValue('DataState') === 3, 'Old last-good data must be stale.');
scaffoldCheck(
    $runtimeWeather->testTimerInterval('UpdateData') === 900000,
    'Second retry interval differs.'
);

$sharedRuntimeWeather = new TestOpenMeteoWeather();
$sharedRuntimeWeather->Create();
$sharedRuntimeWeather->testSetProperty('LocationInstanceId', 2001);
$sharedRuntimeWeather->testSetProperty('EnableAutomaticUpdates', false);
$sharedRuntimeWeather->ApplyChanges();
$sharedRuntimeWeather->testQueueResponse(scaffoldWeatherResponse(false));
$sharedRuntimeResult = json_decode(
    $sharedRuntimeWeather->UpdateData(),
    true,
    16,
    JSON_THROW_ON_ERROR
);
scaffoldCheck(
    ($sharedRuntimeResult['success'] ?? null) === true,
    'Weather update through a shared location did not succeed.'
);
scaffoldCheck(
    $sharedRuntimeWeather->testReferences() === [2001],
    'Shared-location runtime reference differs.'
);
scaffoldCheck(
    $sharedRuntimeWeather->testTimerInterval('UpdateData') === 0,
    'Manual shared-location update enabled automatic polling.'
);
$sharedRuntimeWeather->testQueueResponse(false);
$sharedRuntimeFailure = json_decode(
    $sharedRuntimeWeather->UpdateData(),
    true,
    16,
    JSON_THROW_ON_ERROR
);
scaffoldCheck(
    ($sharedRuntimeFailure['code'] ?? null) === 'transport_error',
    'Manual shared-location failure classification differs.'
);
scaffoldCheck(
    $sharedRuntimeWeather->testTimerInterval('UpdateData') === 0,
    'Manual shared-location failure enabled a retry timer.'
);

$solar = new OpenMeteoSolarForecast();
$solar->Create();
$solar->ApplyChanges();
scaffoldCheck($solar->testStatus() === 104, 'Unconfigured solar scaffold must be inactive.');
scaffoldCheck(count($solar->testVariables()) === 10, 'Solar variable contract differs.');
$solarVariables = $solar->testVariables();

$solar->testSetProperty('WeatherInstanceId', 1001);
$scaffoldInstances[1001] = [
    'ModuleInfo' => [
        'ModuleID' => '{B52FE951-7FBE-4882-B0E6-E143E5B5F31A}',
    ],
];
$solar->testSetProperty('ArraysJson', json_encode([[
    'Ident' => 'ArrayA',
    'PeakPowerKw' => 5.0,
    'TiltDegrees' => 30.0,
    'AzimuthDegrees' => 0.0,
    'TemperatureCoefficientPctPerC' => -0.4,
    'NoctDeltaCAt800Wm2' => 25.0,
    'DerateFactor' => 0.9,
    'InverterIdent' => 'InverterA',
]], JSON_THROW_ON_ERROR));
$solar->testSetProperty('InvertersJson', json_encode([[
    'Ident' => 'InverterA',
    'AcLimitKw' => 4.0,
    'EfficiencyFactor' => 0.96,
]], JSON_THROW_ON_ERROR));
$solar->ApplyChanges();
scaffoldCheck($solar->testStatus() === 104, 'Valid solar scaffold must remain inactive.');
scaffoldCheck($solar->testReferences() === [1001], 'Weather reference was not registered.');
scaffoldCheck(
    is_string($solar->testReadValue('ConfigurationHash'))
    && strlen($solar->testReadValue('ConfigurationHash')) === 64,
    'Solar configuration hash is missing.'
);
scaffoldCheck(
    $solar->testVariables() === $solarVariables,
    'Repeated solar ApplyChanges changed variable identity.'
);
scaffoldCheck($solar->testTimerRegistrations() === 0, 'Solar scaffold registered a timer.');

$solar->testSetProperty('WeatherInstanceId', 1002);
$solar->ApplyChanges();
scaffoldCheck($solar->testStatus() === 200, 'Unknown weather instance must fail closed.');
scaffoldCheck($solar->testReferences() === [1001], 'Invalid reference replaced last valid reference.');
$scaffoldInstances[1002] = [
    'ModuleInfo' => [
        'ModuleID' => '{B52FE951-7FBE-4882-B0E6-E143E5B5F31A}',
    ],
];
$solar->ApplyChanges();
scaffoldCheck($solar->testReferences() === [1002], 'Weather reference was not reconciled.');

$solar->testSetProperty('EnableCalibration', true);
$solar->ApplyChanges();
scaffoldCheck($solar->testStatus() === 200, 'Deferred calibration must fail closed.');

echo "module-scaffold: ok\n";
