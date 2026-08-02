<?php

declare(strict_types=1);

/** @var array<string, array<string, mixed>> $scaffoldProfiles */
$scaffoldProfiles = [];

/** @var array<int, array<string, mixed>> $scaffoldInstances */
$scaffoldInstances = [];

class IPSModule
{
    /** @var array<string, mixed> */
    private array $properties = [];

    /** @var array<string, int> */
    private array $attributes = [];

    /** @var array<string, array{id: int, type: string, profile: string, position: int}> */
    private array $variables = [];

    private int $nextVariableId = 1000;
    private int $status = 0;
    private int $timerRegistrations = 0;
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

        return $this->attributes[$ident];
    }

    protected function WriteAttributeInteger(string $ident, int $value): void
    {
        $this->ReadAttributeInteger($ident);
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
        $this->timerRegistrations++;
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
        $this->values[$ident] = $value;
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
        return $this->timerRegistrations;
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

require_once dirname(__DIR__, 3) . '/helpers/object/EnsureProfile.php';
require_once dirname(__DIR__, 3) . '/helpers/diagnostics/ConfigurationHash.php';
require_once __DIR__ . '/../distribution/OpenMeteoWeather/module.php';
require_once __DIR__ . '/../distribution/OpenMeteoSolarForecast/module.php';

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
foreach (['OpenMeteoWeather', 'OpenMeteoSolarForecast'] as $moduleName) {
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
    foreach (
        [
        'RegisterTimer',
        'SetTimerInterval',
        'SendDataToParent',
        'curl_',
        'UpdateData(',
        ] as $forbidden
    ) {
        scaffoldCheck(
            !str_contains($source, $forbidden),
            'Inactive module contains an activation surface.'
        );
    }
}

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
scaffoldCheck($weather->testTimerRegistrations() === 0, 'Weather scaffold registered a timer.');

$weather->testSetProperty('LocationConfigured', true);
$weather->testSetProperty('Latitude', 48.0);
$weather->testSetProperty('Longitude', 11.0);
$weather->ApplyChanges();
scaffoldCheck($weather->testStatus() === 104, 'Valid weather scaffold must remain inactive.');
$weather->testSetProperty('Timezone', 'Invalid/Zone');
$weather->ApplyChanges();
scaffoldCheck($weather->testStatus() === 200, 'Invalid weather configuration must fail closed.');

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
