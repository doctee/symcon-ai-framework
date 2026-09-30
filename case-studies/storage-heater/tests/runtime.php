<?php

declare(strict_types=1);

// A deliberately isolated, command-free Symcon fake. All external writes fail.
const VARIABLETYPE_BOOLEAN = 0;
const VARIABLETYPE_INTEGER = 1;
const VARIABLETYPE_FLOAT = 2;
const VARIABLE_PRESENTATION_ENUMERATION = '{52D9E126-D7D2-2CBB-5E62-4CF7BA7C5D82}';
const IS_ACTIVE = 102;
const IS_INACTIVE = 104;
$values = [];
$types = [];
$now = strtotime('2026-01-14 21:46 Europe/Berlin');
$stale = false;
$gap = false;
$badWeather = false;
$commands = 0;
$archiveReads = 0;
$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    ++$checks;
}
function IPS_InstanceExists(int $id): bool
{
    return in_array($id, [11, 12], true);
}
function IPS_GetInstance(int $id): array
{
    return ['ModuleInfo' => ['ModuleID' => $id === 11 ? '{43192F0B-135B-4CE7-A0A7-1475603F3060}' : '{B52FE951-7FBE-4882-B0E6-E143E5B5F31A}']];
}
function IPS_VariableExists(int $id): bool
{
    return isset($GLOBALS['types'][$id]);
}
function IPS_GetObject(int $id): array
{
    return ['ParentID' => $id >= 1000 ? 99 : 12, 'ObjectIdent' => $id === 30 ? 'LastSuccess' : ''];
}
function IPS_GetVariable(int $id): array
{
    return ['VariableType' => $GLOBALS['types'][$id], 'VariableUpdated' => $GLOBALS['now'] - ($GLOBALS['stale'] ? 12 * 3600 : 30)];
}
function IPS_GetChildrenIDs(int $id): array
{
    return [30];
}
function IPS_GetReferenceList(int $id): array
{
    throw new RuntimeException('Native self-reference queries are forbidden during the module lifecycle');
}
function GetValue(int $id): mixed
{
    return $id === 30 ? $GLOBALS['now'] - 600 : $GLOBALS['values'][$id];
}
function SetValue(int $id, mixed $value): void
{
    if ($id < 1000) {
        throw new RuntimeException('External write forbidden');
    } $GLOBALS['values'][$id] = $value;
}
function RequestAction(int $id, mixed $value): void
{
    ++$GLOBALS['commands'];
    throw new RuntimeException('Device command forbidden');
}
function IPS_SemaphoreEnter(string $name, int $wait): bool
{
    return true;
}
function IPS_SemaphoreLeave(string $name): bool
{
    return true;
}
function AC_GetLoggingStatus(int $a, int $id): bool
{
    return true;
}
function AC_GetAggregationType(int $a, int $id): int
{
    return $id === 13 ? 1 : 0;
}
function AC_GetAggregatedValues(int $a, int $id, int $level, int $from, int $to, int $limit): array
{
    ++$GLOBALS['archiveReads'];
    check($to - $from < 48 * 3600 && $limit <= 49, 'Bounded hourly read');
    $rows = [];
    for ($t = $from; $t <= $to; $t += 3600) {
        $rows[] = ['TimeStamp' => $t, 'Duration' => 3600, 'Avg' => $id === 13 || $id === 14 ? 2.0 : 12.0];
    }
    if ($GLOBALS['gap']) {
        array_pop($rows);
    }
    return array_reverse($rows);
}
function AC_GetLoggedValues(int $a, int $id, int $from, int $to, int $limit): array
{
    check($from > 0 && $limit <= 2000, 'Bounded raw read');
    return $limit === 1 ? [['TimeStamp' => $to - 1, 'Value' => $id !== 20]] : [];
}
function OMWEATHER_GetHourlyForecastJson(int $id, int $from, int $to, string $fields): string
{
    $points = [];
    for ($t = $from; $t < $to; $t += 3600) {
        $points[] = ['validFrom' => $t, 'validTo' => $t, 'value' => 5.0, 'unit' => '°C', 'semantics' => 'instant'];
    }
    if ($GLOBALS['badWeather']) {
        array_pop($points);
    }
    return json_encode(['success' => true, 'data' => ['temperature_2m' => $points]], JSON_THROW_ON_ERROR);
}
class IPSModule
{
    public int $InstanceID = 99;
    public array $properties = [];
    public array $attributes = [];
    public array $ids = [];
    public array $references = [];
    public array $actions = [];
    public int $timer = 0;
    public int $status = 104;
    public function Create(): void
    {
    }
    public function ApplyChanges(): void
    {
    }
    public function RegisterPropertyBoolean($k, $v): void
    {
        $this->properties[$k] ??= $v;
    }
    public function RegisterPropertyString($k, $v): void
    {
        $this->properties[$k] ??= $v;
    }
    public function RegisterPropertyInteger($k, $v): void
    {
        $this->properties[$k] ??= $v;
    }
    public function ReadPropertyBoolean($k): bool
    {
        return $this->properties[$k];
    }
    public function ReadPropertyString($k): string
    {
        return $this->properties[$k];
    }
    public function ReadPropertyInteger($k): int
    {
        return $this->properties[$k];
    }
    public function RegisterAttributeString($k, $v): void
    {
        $this->attributes[$k] ??= $v;
    }
    public function ReadAttributeString($k): string
    {
        return $this->attributes[$k];
    }
    public function WriteAttributeString($k, $v): void
    {
        $this->attributes[$k] = $v;
    }
    public function RegisterTimer($k, $i, $s): void
    {
        $this->timer = $i;
    }
    public function SetTimerInterval($k, $i): void
    {
        $this->timer = $i;
    }
    public function RegisterVariableString($k, ...$args): void
    {
        $this->variable($k, 3, '');
    }
    public function RegisterVariableInteger($k, ...$args): void
    {
        $this->variable($k, 1, 0);
    }
    public function RegisterVariableFloat($k, ...$args): void
    {
        $this->variable($k, 2, 0.0);
    }
    public function RegisterVariableBoolean($k, ...$args): void
    {
        $this->variable($k, 0, false);
    }
    private function variable($k, $type, $initial): void
    {
        $id = $this->ids[$k] ?? (1000 + count($this->ids));
        $this->ids[$k] = $id;
        $GLOBALS['types'][$id] = $type;
        $GLOBALS['values'][$id] ??= $initial;
    }
    public function EnableAction($k): bool
    {
        $this->actions[$k] = true;
        return true;
    }
    public function SetValue($k, $v): void
    {
        SetValue($this->ids[$k], $v);
    }
    public function GetIDForIdent($k): int
    {
        return $this->ids[$k];
    }
    public function GetReferenceList(): array
    {
        return array_keys($this->references);
    }
    public function RegisterReference($id): void
    {
        $this->references[$id] = true;
    }
    public function UnregisterReference($id): void
    {
        unset($this->references[$id]);
    }
    public function SetStatus($status): void
    {
        $this->status = $status;
    }
}
require $argv[1] . '/StorageHeaterForecast/module.php';
require __DIR__ . "/TestModule.php";
$b = ['archive' => 11, 'weather' => 12, 'energy' => 13, 'power' => 14, 'outside' => 15, 'gallery' => 16, 'sofa' => 17, 'fan' => 18, 'enabled' => 19, 'reduced' => 20];
foreach ($b as $key => $id) {
    if ($id <= 12) {
        continue;
    }
    $types[$id] = $id >= 18 ? 0 : 2;
    $values[$id] = $id >= 18 ? ($id !== 20) : 12.0;
}
$types[30] = 1;
$model = ['schema' => 1, 'features' => SAEF\StorageHeater\Forecast::FEATURES, 'cutoffHour' => 21, 'window' => '22-07', 'timezone' => 'Europe/Berlin', 'trainedThrough' => 1, 'mean' => array_fill(0, 8, 0.0), 'scale' => array_fill(0, 8, 1.0), 'coefficients' => array_fill(0, 8, 0.0), 'intercept' => 20.0, 'ranges' => array_fill(0, 8, [-100.0, 100.0])];
$m = new TestModule();
$m->Create();
$m->ApplyChanges();
check($m->timer === 0 && $m->status === IS_INACTIVE, 'Disabled by default');
$m->properties['BindingsJSON'] = json_encode($b);
$m->properties['ModelJSON'] = json_encode($model);
$m->properties['HistoryStart'] = '2025-01-01';
$m->properties['Enabled'] = true;
$m->ApplyChanges();
check($m->timer > 0 && $m->status === IS_ACTIVE, 'Valid config enabled');
$m->Tick();
check($m->status === IS_ACTIVE, 'Tick succeeded: ' . GetValue($m->ids['StatusText']));
$j = json_decode($m->GetJournalJson(), true);
check($j['records']['2026-01-14']['forecast']['kwh'] === 20, 'Forecast saved');
$original = $j['records']['2026-01-14']['forecast'];
$m->Tick();
check(json_decode($m->GetJournalJson(), true)['records']['2026-01-14']['forecast'] === $original, 'Duplicate tick immutable');
$now = strtotime('2026-01-15 08:10 Europe/Berlin');
$m->Tick();
$j = json_decode($m->GetJournalJson(), true);
check($j['records']['2026-01-14']['measurement']['kwh'] === 18, 'Night energy correct');
check($j['records']['2026-01-14']['measurement']['errorKwh'] === 2, 'Signed residual correct');
check(GetValue($m->ids['ForecastValid']) === false, 'Expired forecast invalid');
$m->Create();
$m->ApplyChanges();
$m->Tick();
check(json_decode($m->GetJournalJson(), true)['records']['2026-01-14']['forecast'] === $original, 'Restart preserves issued forecast');
$now = strtotime('2026-01-15 22:10 Europe/Berlin');
$m->Tick();
check(json_decode($m->GetJournalJson(), true)['records']['2026-01-15']['forecast'] === null, 'No late forecast');
$now = strtotime('2026-01-16 21:46 Europe/Berlin');
$stale = true;
$m->Tick();
check($m->status === 201 && !GetValue($m->ids['ForecastValid']), 'Stale sensor blocked');
check(!isset(json_decode($m->GetJournalJson(), true)['records']['2026-01-16']), 'Data failure remains retryable');
$stale = false;
$badWeather = true;
$m->Tick();
check($m->status === 201, 'Missing weather endpoint blocked');
$badWeather = false;
$gap = true;
$m->Tick();
check($m->status === 201, 'Archive gap blocked');
$gap = false;
$m->Tick();
check($m->status === IS_ACTIVE, 'Recovers within issue window');
$m->properties['Enabled'] = false;
$m->ApplyChanges();
$reads = $archiveReads;
$m->Tick();
check($archiveReads === $reads && $m->timer === 0, 'Disable stops reads and timer');
$input = '{"year":2026,"month":1,"day":14,"hour":18,"minute":0,"second":0}';
$frozenJournal = $m->attributes['Journal'];
check(str_starts_with($m->RecordFireplaceStart($input), 'Kaminstart gespeichert:'), 'Retrospective entry works while disabled');
$j = json_decode($m->GetJournalJson(), true);
check($m->attributes['Journal'] === $frozenJournal, 'Retrospective fireplace entry does not alter stored journal');
check($j['records']['2026-01-14']['forecast'] === $original, 'Original forecast including knowledge remains unchanged');
check($j['records']['2026-01-14']['fireplaceCurrentClassification']['group'] === 'reported_start', 'Historical comparison updated');
check($j['fireplaceComparison']['reported_start']['nights'] >= 1, 'Reported group includes scored night');
check($j['fireplaceLog']['entries'][0]['recordedAt'] === $now, 'Actual recording time retained');
$m->RecordFireplaceStart($input);
check(count(json_decode($m->GetJournalJson(), true)['fireplaceLog']['entries']) === 1, 'Double click idempotent');
$m->Create();
check(count(json_decode($m->GetJournalJson(), true)['fireplaceLog']['entries']) === 1, 'Lifecycle preserves fireplace log');
check(str_starts_with($m->CancelFireplaceStart($input), 'Kaminstart storniert:'), 'Correction supported');
$j = json_decode($m->GetJournalJson(), true);
check($j['fireplaceComparison']['reported_start']['nights'] === 0, 'Cancellation refreshes comparison');
check($j['fireplaceLog']['entries'][0]['cancelledAt'] === $now, 'Correction audit retained');
check($archiveReads === $reads && $commands === 0, 'Fireplace logging needs no archive or device calls');
// Native visualization edits remain independent of observation and device control.
$draft = $now - 3600;
$beforeInput = $m->attributes['FireplaceLog'];
$m->RequestAction('FireplaceStartInput', $draft);
check($m->attributes['FireplaceLog'] === $beforeInput, 'Draft selection does not log a fire');
$m->Create();
check(GetValue($m->ids['FireplaceStartInput']) === $draft, 'Lifecycle preserves draft');
check(isset($m->actions['FireplaceStartInput'], $m->actions['FireplaceAction']), 'Native controls enabled');
$m->RequestAction('FireplaceAction', 1);
$j = json_decode($m->GetJournalJson(), true);
check(count($j['fireplaceLog']['entries']) === 2 && $j['fireplaceLog']['entries'][1]['startedAt'] === $draft, 'Explicit save records selected timestamp');
$m->RequestAction('FireplaceAction', 1);
check(count(json_decode($m->GetJournalJson(), true)['fireplaceLog']['entries']) === 2, 'Visualization repeat save is idempotent');
$m->RequestAction('FireplaceAction', 2);
$j = json_decode($m->GetJournalJson(), true);
check($j['fireplaceLog']['entries'][1]['cancelledAt'] === $now, 'Visualization cancellation keeps audit');
check(GetValue($m->ids['FireplaceAction']) === 0, 'Action is not latched');
foreach ([['FireplaceStartInput', 0], ['FireplaceStartInput', $now + 60], ['FireplaceStartInput', $now - 401 * 86400], ['FireplaceStartInput', '123'], ['FireplaceAction', true], ['FireplaceAction', 3], ['Enabled', true]] as [$ident, $value]) {
    $beforeRejected = $m->attributes;
    $rejected = false;
    try {
        $m->RequestAction($ident, $value);
    } catch (InvalidArgumentException $error) {
        $rejected = true;
    }
    check($rejected && $beforeRejected === $m->attributes, 'Invalid visualization action rejected without log edits');
}
check($m->attributes['Journal'] === $frozenJournal && $archiveReads === $reads && $commands === 0, 'Visualization actions preserve forecasts and never access devices');
$m->properties['Enabled'] = true;
$b['energy'] = 0;
$m->properties['BindingsJSON'] = json_encode($b);
$m->ApplyChanges();
check($m->status === 201 && $m->timer === 0, 'Root source rejected before activation');
check($commands === 0, 'No device actions');
echo $checks . " runtime assertions passed; no external writes or device actions\n";
