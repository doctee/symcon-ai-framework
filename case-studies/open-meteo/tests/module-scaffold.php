<?php

declare(strict_types=1);

/** @var array<string, array<string, mixed>> $scaffoldProfiles */
$scaffoldProfiles = [];

/** @var array<int, array<string, mixed>> $scaffoldInstances */
$scaffoldInstances = [];

/** @var array<int, SharedLocation> $scaffoldLocationModules */
$scaffoldLocationModules = [];

/** @var array<int, OpenMeteoWeather> $scaffoldWeatherModules */
$scaffoldWeatherModules = [];

/** @var array<int, array{ObjectType: int, ParentID: int, ObjectIsHidden: bool}> $scaffoldObjects */
$scaffoldObjects = [];

/** @var list<array{id: int, hidden: bool}> $scaffoldHiddenMutations */
$scaffoldHiddenMutations = [];

$scaffoldNextVariableId = 1000;

class IPSModule
{
    public int $InstanceID = 42;

    /** @var array<string, mixed> */
    private array $properties = [];

    /** @var array<string, int|string> */
    private array $attributes = [];

    /** @var array<string, array{id: int, type: string, profile: string, position: int}> */
    private array $variables = [];

    private int $status = 0;
    /** @var array<string, array{interval: int, script: string}> */
    private array $timers = [];

    /** @var list<string> */
    private array $translationCalls = [];
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

    protected function Translate(string $text): string
    {
        $this->translationCalls[] = $text;

        return $text;
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

    protected function GetIDForIdent(string $ident): int
    {
        return $this->variables[$ident]['id'] ?? 0;
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

    /** @return list<string> */
    public function testTranslationCalls(): array
    {
        return $this->translationCalls;
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

    public function testDropReferences(): void
    {
        $this->references = [];
    }

    public function testReadValue(string $ident): mixed
    {
        return $this->values[$ident] ?? null;
    }

    public function testVariableHidden(string $ident): bool
    {
        global $scaffoldObjects;

        $id = $this->variables[$ident]['id'] ?? 0;
        if ($id <= 0 || !isset($scaffoldObjects[$id])) {
            throw new RuntimeException('Unknown test variable visibility.');
        }

        return $scaffoldObjects[$id]['ObjectIsHidden'];
    }

    public function testHiddenMutationCount(): int
    {
        global $scaffoldHiddenMutations;

        return count($scaffoldHiddenMutations);
    }

    public function testSetVariableParent(string $ident, int $parentId): void
    {
        global $scaffoldObjects;

        $id = $this->variables[$ident]['id'] ?? 0;
        if ($id <= 0 || !isset($scaffoldObjects[$id])) {
            throw new RuntimeException('Unknown test variable ownership.');
        }
        $scaffoldObjects[$id]['ParentID'] = $parentId;
    }

    public function testSetVariableHidden(string $ident, bool $hidden): void
    {
        $id = $this->variables[$ident]['id'] ?? 0;
        if ($id <= 0) {
            throw new RuntimeException('Unknown test variable presentation.');
        }
        IPS_SetHidden($id, $hidden);
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
        global $scaffoldNextVariableId, $scaffoldObjects;

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

        $variableId = $scaffoldNextVariableId++;
        $this->variables[$ident] = [
            'id' => $variableId,
            'type' => $type,
            'profile' => $profile,
            'position' => $position,
        ];
        $scaffoldObjects[$variableId] = [
            'ObjectType' => 2,
            'ParentID' => $this->InstanceID,
            'ObjectIsHidden' => false,
        ];
    }
}

function IPS_ObjectExists(int $id): bool
{
    global $scaffoldObjects;

    return isset($scaffoldObjects[$id]);
}

/** @return array<string, int|bool> */
function IPS_GetObject(int $id): array
{
    global $scaffoldObjects;

    return $scaffoldObjects[$id] ?? [];
}

function IPS_SetHidden(int $id, bool $hidden): void
{
    global $scaffoldObjects, $scaffoldHiddenMutations;

    if (!isset($scaffoldObjects[$id])) {
        throw new RuntimeException('Unknown hidden mutation target.');
    }
    $scaffoldObjects[$id]['ObjectIsHidden'] = $hidden;
    $scaffoldHiddenMutations[] = ['id' => $id, 'hidden' => $hidden];
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

function OMWEATHER_GetLocationDescriptor(int $instanceId): string
{
    global $scaffoldWeatherModules;

    if (!isset($scaffoldWeatherModules[$instanceId])) {
        throw new RuntimeException('Unknown weather test instance.');
    }

    return $scaffoldWeatherModules[$instanceId]->GetLocationDescriptor();
}

/** @param array<string, int|string|bool> $parameters */
function Sys_GetURLContentEx(string $url, array $parameters): string|false
{
    return $url === '' ? '' : false;
}

require_once dirname(__DIR__, 3) . '/helpers/object/EnsureProfile.php';
require_once dirname(__DIR__, 3) . '/helpers/diagnostics/ConfigurationHash.php';
require_once dirname(__DIR__, 3) . '/helpers/common/Validation.php';
require_once __DIR__ . '/../distribution/SharedLocation/module.php';
require_once __DIR__ . '/../distribution/OpenMeteoWeather/module.php';
require_once __DIR__ . '/../distribution/OpenMeteoSolarForecast/module.php';
require_once __DIR__ . '/../distribution/DwdPrecipitationNowcast/module.php';
require_once __DIR__ . '/TestOpenMeteoWeather.php';
require_once __DIR__ . '/TestOpenMeteoSolarForecast.php';
require_once __DIR__ . '/TestDwdPrecipitationNowcast.php';

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
foreach (
    [
        'SharedLocation',
        'OpenMeteoWeather',
        'OpenMeteoSolarForecast',
        'DwdPrecipitationNowcast',
    ] as $moduleName
) {
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
$soilVariableIdents = [
    'SoilTemperature0cm',
    'SoilTemperature6cm',
    'SoilTemperature18cm',
    'SoilTemperature54cm',
    'SoilMoisture0To1cm',
    'SoilMoisture1To3cm',
    'SoilMoisture3To9cm',
    'SoilMoisture9To27cm',
    'SoilMoisture27To81cm',
];
foreach ($soilVariableIdents as $ident) {
    scaffoldCheck(
        !$weather->testVariableHidden($ident),
        'Unmanaged soil presentation changed on module creation.'
    );
}
scaffoldCheck(!$weather->testVariableHidden('Temperature'), 'Weather variable was hidden.');
$weather->testSetVariableHidden('SoilTemperature0cm', true);
$hiddenMutationCount = $weather->testHiddenMutationCount();
$weather->ApplyChanges();
scaffoldCheck(
    $weather->testVariables() === $weatherVariables,
    'Repeated weather ApplyChanges changed variable identity.'
);
scaffoldCheck(
    $weather->testHiddenMutationCount() === $hiddenMutationCount,
    'Repeated weather ApplyChanges changed stable soil presentation.'
);
scaffoldCheck(
    $weather->testVariableHidden('SoilTemperature0cm'),
    'Unmanaged user soil visibility was not preserved.'
);
scaffoldCheck($scaffoldProfiles === $profiles, 'Repeated profile creation was not idempotent.');
scaffoldCheck($weather->testTimerRegistrations() === 1, 'Weather update timer is missing.');
scaffoldCheck($weather->testTimerInterval('UpdateData') === 0, 'Unconfigured timer must be disabled.');

$weather->testSetProperty('ManageSoilVariableVisibility', true);
$weather->ApplyChanges();
foreach ($soilVariableIdents as $ident) {
    scaffoldCheck($weather->testVariableHidden($ident), 'Managed disabled soil variable is visible.');
}
$managedHiddenMutationCount = $weather->testHiddenMutationCount();
$weather->ApplyChanges();
scaffoldCheck(
    $weather->testHiddenMutationCount() === $managedHiddenMutationCount,
    'Repeated managed soil presentation reconciliation was not a no-op.'
);
$weather->testSetProperty('WithSoil', true);
$weather->ApplyChanges();
foreach ($soilVariableIdents as $ident) {
    scaffoldCheck(!$weather->testVariableHidden($ident), 'Enabled soil variable must be visible.');
}
$weather->testSetProperty('ShowSoilVariables', false);
$weather->ApplyChanges();
foreach ($soilVariableIdents as $ident) {
    scaffoldCheck($weather->testVariableHidden($ident), 'Explicitly hidden soil variable is visible.');
}
$weather->testSetProperty('ShowSoilVariables', true);
$weather->ApplyChanges();
foreach ($soilVariableIdents as $ident) {
    scaffoldCheck(!$weather->testVariableHidden($ident), 'Explicitly shown soil variable is hidden.');
}
$weather->testSetProperty('WithSoil', false);
$weather->ApplyChanges();
foreach ($soilVariableIdents as $ident) {
    scaffoldCheck($weather->testVariableHidden($ident), 'Disabled soil variable became visible.');
}

$ownershipWeather = new OpenMeteoWeather();
$ownershipWeather->Create();
$ownershipWeather->testSetProperty('ManageSoilVariableVisibility', true);
$ownershipWeather->ApplyChanges();
$ownershipWeather->testSetVariableParent('SoilTemperature0cm', 0);
$ownershipMutationCount = $ownershipWeather->testHiddenMutationCount();
$ownershipWeather->ApplyChanges();
scaffoldCheck(
    $ownershipWeather->testStatus() === 200,
    'Soil presentation ownership drift did not fail closed.'
);
scaffoldCheck(
    $ownershipWeather->testHiddenMutationCount() === $ownershipMutationCount,
    'Soil presentation ownership drift performed a mutation.'
);
$ownershipWeather->testSetVariableParent('SoilTemperature0cm', $ownershipWeather->InstanceID);
$ownershipWeather->ApplyChanges();
scaffoldCheck(
    $ownershipWeather->testStatus() === 104,
    'Restored soil presentation ownership did not recover.'
);

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
$weather->testDropReferences();
$weather->ApplyChanges();
scaffoldCheck(
    $weather->testReferences() === [2001],
    'Shared location reference was not restored after registry drift.'
);
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

$soilRuntimeWeather = new TestOpenMeteoWeather();
$soilRuntimeWeather->Create();
$soilRuntimeWeather->testSetProperty('LocationConfigured', true);
$soilRuntimeWeather->testSetProperty('Latitude', 48.0);
$soilRuntimeWeather->testSetProperty('Longitude', 11.0);
$soilRuntimeWeather->testSetProperty('WithSoil', true);
$soilRuntimeWeather->testSetProperty('ManageSoilVariableVisibility', true);
$soilRuntimeWeather->ApplyChanges();
$soilRuntimeWeather->testQueueResponse(scaffoldWeatherResponse(true));
$soilRuntimeResult = json_decode(
    $soilRuntimeWeather->UpdateData(),
    true,
    16,
    JSON_THROW_ON_ERROR
);
scaffoldCheck(
    ($soilRuntimeResult['success'] ?? null) === true,
    'Soil weather update did not succeed.'
);
scaffoldCheck(
    $soilRuntimeWeather->testReadValue('SoilMoisture0To1cm') === 0.25,
    'Soil weather value differs.'
);
$soilLastAttempt = $soilRuntimeWeather->testReadValue('LastFetchAttempt');
$soilLastSuccess = $soilRuntimeWeather->testReadValue('LastSuccess');
$soilRuntimeWeather->testSetProperty('ShowSoilVariables', false);
$soilRuntimeWeather->ApplyChanges();
foreach ($soilVariableIdents as $ident) {
    scaffoldCheck(
        $soilRuntimeWeather->testVariableHidden($ident),
        'Presentation-disabled soil variable is visible.'
    );
}
$soilCacheAfterHide = json_decode(
    $soilRuntimeWeather->GetCurrentJson(),
    true,
    32,
    JSON_THROW_ON_ERROR
);
scaffoldCheck(
    ($soilCacheAfterHide['success'] ?? null) === true,
    'Soil presentation change invalidated the weather cache.'
);
scaffoldCheck(
    $soilRuntimeWeather->testReadValue('LastFetchAttempt') === $soilLastAttempt
    && $soilRuntimeWeather->testReadValue('LastSuccess') === $soilLastSuccess,
    'Soil presentation change altered request markers.'
);
$soilRuntimeWeather->testSetProperty('ShowSoilVariables', true);
$soilRuntimeWeather->ApplyChanges();
foreach ($soilVariableIdents as $ident) {
    scaffoldCheck(
        !$soilRuntimeWeather->testVariableHidden($ident),
        'Presentation-enabled soil variable is hidden.'
    );
}
scaffoldCheck(
    $soilRuntimeWeather->testReadValue('LastFetchAttempt') === $soilLastAttempt
    && $soilRuntimeWeather->testReadValue('LastSuccess') === $soilLastSuccess,
    'Showing soil variables altered request markers.'
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

/** @return list<array<string, mixed>> */
function scaffoldSolarArrays(): array
{
    return [
        [
            'Ident' => 'ArrayA',
            'PeakPowerKw' => 0.7,
            'TiltDegrees' => 30.0,
            'AzimuthDegrees' => 0.0,
            'TemperatureCoefficientPctPerC' => -0.35,
            'NoctDeltaCAt800Wm2' => 25.0,
            'DerateFactor' => 1.0,
            'InverterIdent' => 'InverterA',
        ],
        [
            'Ident' => 'ArrayB',
            'PeakPowerKw' => 0.7,
            'TiltDegrees' => 20.0,
            'AzimuthDegrees' => -90.0,
            'TemperatureCoefficientPctPerC' => -0.35,
            'NoctDeltaCAt800Wm2' => 25.0,
            'DerateFactor' => 1.0,
            'InverterIdent' => 'InverterA',
        ],
    ];
}

/** @return list<array<string, mixed>> */
function scaffoldSolarInverters(): array
{
    return [[
        'Ident' => 'InverterA',
        'AcLimitKw' => 0.8,
        'EfficiencyFactor' => 1.0,
        'PvInputLimitKw' => 1.0,
    ]];
}

function scaffoldConfigureSolar(IPSModule $solar, int $weatherInstanceId): void
{
    $solar->testSetProperty('WeatherInstanceId', $weatherInstanceId);
    $solar->testSetProperty(
        'ArraysJson',
        json_encode(scaffoldSolarArrays(), JSON_THROW_ON_ERROR)
    );
    $solar->testSetProperty(
        'InvertersJson',
        json_encode(scaffoldSolarInverters(), JSON_THROW_ON_ERROR)
    );
}

function scaffoldSolarResponse(float $irradiance): string
{
    return json_encode([
        'latitude' => 48.0,
        'longitude' => 11.0,
        'timezone' => 'Europe/Berlin',
        'utc_offset_seconds' => 3600,
        'hourly_units' => [
            'temperature_2m' => '°C',
            'global_tilted_irradiance' => 'W/m²',
        ],
        'hourly' => [
            'time' => [1735718400, 1735722000, 1735725600],
            'temperature_2m' => [25.0, 25.0, 25.0],
            'global_tilted_irradiance' => [$irradiance, $irradiance, $irradiance],
        ],
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
}

$scaffoldInstances[1001] = [
    'ModuleInfo' => [
        'ModuleID' => '{B52FE951-7FBE-4882-B0E6-E143E5B5F31A}',
    ],
];
$scaffoldWeatherModules[1001] = $weather;

$solar = new OpenMeteoSolarForecast();
$solar->Create();
$solar->ApplyChanges();
scaffoldCheck($solar->testStatus() === 104, 'Unconfigured solar module must be inactive.');
scaffoldCheck(count($solar->testVariables()) === 10, 'Solar variable contract differs.');
scaffoldCheck($solar->testTimerRegistrations() === 1, 'Solar update timer is missing.');
scaffoldCheck($solar->testTimerInterval('UpdateData') === 0, 'Solar timer must start disabled.');
$solarVariables = $solar->testVariables();

scaffoldConfigureSolar($solar, 1001);
$solar->ApplyChanges();
scaffoldCheck($solar->testStatus() === 102, 'Valid solar module must become active.');
scaffoldCheck($solar->testReferences() === [1001], 'Weather reference was not registered.');
$solar->testDropReferences();
$solar->ApplyChanges();
scaffoldCheck(
    $solar->testReferences() === [1001],
    'Weather reference was not restored after registry drift.'
);
scaffoldCheck($solar->testTimerInterval('UpdateData') === 0, 'Solar automatic updates must default off.');
scaffoldCheck(
    is_string($solar->testReadValue('ConfigurationHash'))
    && strlen($solar->testReadValue('ConfigurationHash')) === 64,
    'Solar configuration hash is missing.'
);
$solar->ApplyChanges();
scaffoldCheck(
    $solar->testVariables() === $solarVariables,
    'Repeated solar ApplyChanges changed variable identity.'
);

$solar->testSetProperty('WeatherInstanceId', 1002);
$solar->ApplyChanges();
scaffoldCheck($solar->testStatus() === 200, 'Unknown weather instance must fail closed.');
scaffoldCheck($solar->testReferences() === [1001], 'Invalid reference replaced last valid reference.');
$scaffoldInstances[1002] = [
    'ModuleInfo' => [
        'ModuleID' => '{B52FE951-7FBE-4882-B0E6-E143E5B5F31A}',
    ],
];
$scaffoldWeatherModules[1002] = $weather;
$solar->ApplyChanges();
scaffoldCheck($solar->testReferences() === [1002], 'Weather reference was not reconciled.');

$solar->testSetProperty('EnableCalibration', true);
$solar->ApplyChanges();
scaffoldCheck($solar->testStatus() === 200, 'Deferred calibration must fail closed.');
scaffoldCheck($solar->testTimerInterval('UpdateData') === 0, 'Invalid solar timer must be disabled.');

$runtimeSolar = new TestOpenMeteoSolarForecast();
$runtimeSolar->Create();
scaffoldConfigureSolar($runtimeSolar, 1001);
$runtimeSolar->ApplyChanges();
$runtimeSolar->testQueueResponse(scaffoldSolarResponse(1000.0));
$runtimeSolar->testQueueResponse(scaffoldSolarResponse(1000.0));
$solarSuccess = json_decode($runtimeSolar->UpdateData(), true, 16, JSON_THROW_ON_ERROR);
scaffoldCheck(($solarSuccess['success'] ?? null) === true, 'Solar update did not succeed.');
scaffoldCheck(count($runtimeSolar->testRequestedUrls()) === 2, 'Solar orientations were not serialized.');
scaffoldCheck($runtimeSolar->testReadValue('DataState') === 2, 'Solar data is not current.');
scaffoldCheck(
    abs((float) $runtimeSolar->testReadValue('CurrentPowerForecast') - 0.8) < 0.000001,
    'Solar inverter clipping differs.'
);
scaffoldCheck(
    (float) $runtimeSolar->testReadValue('TodayEnergyForecast') > 0.0,
    'Solar daily energy was not projected.'
);
$powerForecast = json_decode(
    $runtimeSolar->GetPowerForecastJson(1735714800, 1735725601),
    true,
    64,
    JSON_THROW_ON_ERROR
);
$powerPoints = $powerForecast['data']['system'] ?? [];
scaffoldCheck(
    count($powerPoints) === 3,
    'Bounded solar power forecast differs.'
);
foreach ($powerPoints as $powerPoint) {
    scaffoldCheck(
        is_array($powerPoint)
        && ($powerPoint['unit'] ?? null) === 'kW'
        && ($powerPoint['semantics'] ?? null) === 'preceding_interval'
        && is_numeric($powerPoint['value'] ?? null)
        && is_finite((float) $powerPoint['value'])
        && (float) $powerPoint['value'] >= 0.0,
        'Solar power cache unit, semantics or value differs.'
    );
}
$dailyEnergyForecast = json_decode(
    $runtimeSolar->GetDailyEnergyForecastJson(1735686000, 1735772400),
    true,
    64,
    JSON_THROW_ON_ERROR
);
$dailyEnergyPoints = $dailyEnergyForecast['data']['system'] ?? [];
scaffoldCheck(count($dailyEnergyPoints) === 1, 'Bounded solar daily energy differs.');
scaffoldCheck(
    is_array($dailyEnergyPoints[0] ?? null)
    && ($dailyEnergyPoints[0]['unit'] ?? null) === 'kWh'
    && ($dailyEnergyPoints[0]['semantics'] ?? null) === 'local_day'
    && is_numeric($dailyEnergyPoints[0]['value'] ?? null)
    && is_finite((float) $dailyEnergyPoints[0]['value'])
    && (float) $dailyEnergyPoints[0]['value'] >= 0.0,
    'Solar daily energy cache unit, semantics or value differs.'
);
$unsupported = json_decode(
    $runtimeSolar->GetPowerForecastJson(1735714800, 1735725601, 'array'),
    true,
    16,
    JSON_THROW_ON_ERROR
);
scaffoldCheck(
    ($unsupported['code'] ?? null) === 'breakdown_unsupported',
    'Unsupported solar breakdown was not rejected.'
);

$runtimeSolar->testSetNow(1735716660);
$runtimeSolar->testQueueResponse(false);
$solarFailure = json_decode($runtimeSolar->UpdateData(), true, 16, JSON_THROW_ON_ERROR);
scaffoldCheck(($solarFailure['code'] ?? null) === 'transport_error', 'Solar failure differs.');
scaffoldCheck($runtimeSolar->testReadValue('DataState') === 4, 'Solar last-good failure must warn.');
scaffoldCheck(
    abs((float) $runtimeSolar->testReadValue('CurrentPowerForecast') - 0.8) < 0.000001,
    'Solar last-good value was cleared.'
);
scaffoldCheck(
    $runtimeSolar->testTimerInterval('UpdateData') === 0,
    'Manual solar failure enabled a retry timer.'
);
$runtimeSolar->testSetProperty('ForecastDays', 5);
$runtimeSolar->ApplyChanges();
scaffoldCheck(
    (float) $runtimeSolar->testReadValue('CurrentPowerForecast') === 0.0,
    'Changed solar configuration retained an incompatible public value.'
);
$invalidatedCache = json_decode(
    $runtimeSolar->GetPowerForecastJson(1735714800, 1735725601),
    true,
    16,
    JSON_THROW_ON_ERROR
);
scaffoldCheck(
    ($invalidatedCache['code'] ?? null) === 'cache_empty',
    'Changed solar configuration retained an incompatible cache.'
);
$runtimeSolar->testSetProperty('ForecastOutputMode', 'pv_harvest');
$runtimeSolar->ApplyChanges();
$runtimeSolar->testQueueResponse(scaffoldSolarResponse(1000.0));
$runtimeSolar->testQueueResponse(scaffoldSolarResponse(1000.0));
$harvestSuccess = json_decode($runtimeSolar->UpdateData(), true, 16, JSON_THROW_ON_ERROR);
scaffoldCheck(($harvestSuccess['success'] ?? null) === true, 'PV harvest update failed.');
scaffoldCheck(
    abs((float) $runtimeSolar->testReadValue('CurrentPowerForecast') - 1.0) < 0.000001,
    'Storage-coupled PV harvest did not use its separate PV-input limit.'
);
$harvestForecast = json_decode(
    $runtimeSolar->GetPowerForecastJson(1735714800, 1735725601),
    true,
    64,
    JSON_THROW_ON_ERROR
);
foreach ($harvestForecast['data']['system'] ?? [] as $harvestPoint) {
    scaffoldCheck(
        is_array($harvestPoint)
        && ($harvestPoint['unit'] ?? null) === 'kW'
        && (float) ($harvestPoint['value'] ?? -1.0) >= 0.0
        && (float) ($harvestPoint['value'] ?? 2.0) <= 1.0,
        'PV harvest cache did not preserve the kW contract or PV-input limit.'
    );
}

$automaticSolar = new TestOpenMeteoSolarForecast();
$automaticSolar->Create();
scaffoldConfigureSolar($automaticSolar, 1001);
$automaticSolar->testSetProperty('EnableAutomaticUpdates', true);
$automaticSolar->ApplyChanges();
scaffoldCheck(
    $automaticSolar->testTimerInterval('UpdateData') === 3600000,
    'Automatic solar polling interval differs.'
);
$automaticSolar->testQueueResponse(false);
$automaticSolar->UpdateData();
scaffoldCheck(
    $automaticSolar->testTimerInterval('UpdateData') === 300000,
    'Solar first retry interval differs.'
);

$dwdNowcast = new TestDwdPrecipitationNowcast();
$dwdNowcast->Create();
$dwdNowcast->ApplyChanges();
scaffoldCheck($dwdNowcast->testStatus() === 104, 'Unconfigured DWD nowcast must be inactive.');
scaffoldCheck(count($dwdNowcast->testVariables()) === 18, 'DWD nowcast variable contract differs.');
scaffoldCheck(
    ($dwdNowcast->testVariables()['NowcastChart']['profile'] ?? null) === '~HTMLBox',
    'DWD nowcast chart profile differs.'
);
scaffoldCheck(
    in_array('Rain forecast', $dwdNowcast->testTranslationCalls(), true),
    'DWD nowcast chart title bypassed module translation.'
);
scaffoldCheck($dwdNowcast->testTimerInterval('UpdateData') === 0, 'Inactive DWD timer is enabled.');
$dwdVariables = $dwdNowcast->testVariables();
$dwdNowcast->testSetProperty('LocationInstanceId', 2001);
$dwdNowcast->ApplyChanges();
scaffoldCheck($dwdNowcast->testStatus() === 102, 'Configured DWD nowcast must be active.');
scaffoldCheck($dwdNowcast->testReferences() === [2001], 'DWD location reference is missing.');
scaffoldCheck(
    $dwdNowcast->testTimerInterval('UpdateData') === 300000,
    'DWD polling interval differs.'
);
$dwdNowcast->ApplyChanges();
scaffoldCheck(
    $dwdNowcast->testVariables() === $dwdVariables,
    'Repeated DWD ApplyChanges changed variable identity.'
);
$dwdNowcast->testQueueResponse(scaffoldDwdResponse(1735718400));
$dwdSuccess = json_decode($dwdNowcast->UpdateData(), true, 16, JSON_THROW_ON_ERROR);
scaffoldCheck(($dwdSuccess['success'] ?? null) === true, 'DWD nowcast update did not succeed.');
scaffoldCheck($dwdNowcast->testReadValue('DataState') === 2, 'DWD nowcast data is not current.');
scaffoldCheck($dwdNowcast->testReadValue('RainExpected') === true, 'DWD rain was not detected.');
scaffoldCheck(
    $dwdNowcast->testReadValue('RainStartsInMinutes') === 15,
    'DWD rain start differs.'
);
scaffoldCheck(
    abs((float) $dwdNowcast->testReadValue('PrecipitationSum') - 0.3) < 0.000001,
    'DWD window precipitation sum differs.'
);
$dwdChart = (string) $dwdNowcast->testReadValue('NowcastChart');
scaffoldCheck(
    substr_count($dwdChart, 'class="saef-nowcast__bar"') === 60,
    'DWD nowcast chart minute count differs.'
);
scaffoldCheck(str_contains($dwdChart, '+30 min'), 'DWD nowcast chart midpoint is missing.');
scaffoldCheck(str_contains($dwdChart, '+60 min'), 'DWD nowcast chart endpoint is missing.');
$dwdNowcast->ApplyChanges();
scaffoldCheck(
    $dwdNowcast->testReadValue('NowcastChart') === $dwdChart,
    'DWD ApplyChanges did not republish the compatible cached chart.'
);
$dwdForecast = json_decode($dwdNowcast->GetForecastJson(), true, 64, JSON_THROW_ON_ERROR);
scaffoldCheck(
    count($dwdForecast['data']['points'] ?? []) === 24,
    'DWD full native horizon differs.'
);
$dwdNowcast->testQueueResponse(false);
$dwdFailure = json_decode($dwdNowcast->UpdateData(), true, 16, JSON_THROW_ON_ERROR);
scaffoldCheck(($dwdFailure['code'] ?? null) === 'transport_error', 'DWD failure differs.');
scaffoldCheck($dwdNowcast->testReadValue('DataState') === 4, 'DWD last-good failure must warn.');
scaffoldCheck(
    $dwdNowcast->testTimerInterval('UpdateData') === 60000,
    'DWD first retry interval differs.'
);
$dwdNowcast->testSetProperty('ForecastWindowMinutes', 30);
$dwdNowcast->ApplyChanges();
scaffoldCheck(
    $dwdNowcast->testReadValue('ForecastPointCount') === 0,
    'Changed DWD window retained an incompatible cache.'
);
$dwdNowcast->testSetProperty('ForecastWindowMinutes', 61);
$dwdNowcast->ApplyChanges();
scaffoldCheck($dwdNowcast->testStatus() === 200, 'Invalid DWD window did not fail closed.');
scaffoldCheck($dwdNowcast->testTimerInterval('UpdateData') === 0, 'Invalid DWD timer is enabled.');

echo "module-scaffold: ok\n";

function scaffoldDwdResponse(int $productTime): string
{
    $features = [];
    for ($lead = 5; $lead <= 120; $lead += 5) {
        $features[] = [
            'type' => 'Feature',
            'geometry' => null,
            'properties' => [
                'RV_ANALYSIS' => $lead >= 20 && $lead <= 30 ? 1.2 : -0.001,
                'TIME' => gmdate('Y-m-d\TH:i:s\Z', $productTime + ($lead * 60)),
                'REFERENCE_TIME' => gmdate('Y-m-d\TH:i:s\Z', $productTime),
            ],
        ];
    }

    return json_encode(
        ['type' => 'FeatureCollection', 'features' => $features],
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
    );
}
