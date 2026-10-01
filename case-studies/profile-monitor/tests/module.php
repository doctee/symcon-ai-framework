<?php

declare(strict_types=1);

// Full original/candidate module execution in separate CLI processes only.
// Deliberately no real Symcon, SMTP or notification transport is available.
if ($argc !== 3 && $argc !== 4) {
    throw new InvalidArgumentException('Usage: module.php private/module.php legacy|native|preview|failure|trigger|inactive');
}
$mode = $argv[2];
$strictSdk = $mode === 'rust';
$vars = [11 => ['value' => true, 'profile' => '~Battery', 'type' => 0],
    12 => ['value' => 0, 'profile' => '~Battery.100', 'type' => 1],
    13 => ['value' => 20, 'profile' => '~Battery.100', 'type' => 1],
    14 => ['value' => 1, 'profile' => '', 'type' => 1, 'ident' => 'battery'],
    15 => ['value' => false, 'profile' => '~Battery.Reversed', 'type' => 0]];
$values = [];
$writes = [];
$notifications = [];
$fail = false;
define('VM_UPDATE', 10603);
class IPSModule
{
    public $InstanceID = 801;
    public $properties = [];
    public $ids = [];
    public $timer = [];
    public $buffers = [];
    public $status;
    public function Create()
    {
    }
    public function ApplyChanges()
    {
    }
    public function RegisterPropertyString($key, $default)
    {
        $this->properties[$key] = (string) $default;
    }
    public function RegisterPropertyInteger($key, $default)
    {
        $this->properties[$key] = (int) $default;
    }
    public function RegisterPropertyBoolean($key, $default)
    {
        $this->properties[$key] = (bool) $default;
    }
    public function ReadPropertyString($key)
    {
        return $this->properties[$key];
    }
    public function ReadPropertyInteger($key)
    {
        return $this->properties[$key];
    }
    public function ReadPropertyBoolean($key)
    {
        return $this->properties[$key];
    }
    public function RegisterVariableString($ident, ...$args)
    {
        global $strictSdk;
        if ($strictSdk && count($args) < 2) {
            throw new RuntimeException('Missing explicit profile/presentation');
        }
        $this->GetIDForIdent($ident);
    }
    public function RegisterVariableInteger($ident, ...$args)
    {
        global $strictSdk;
        if ($strictSdk && count($args) < 2) {
            throw new RuntimeException('Missing explicit profile/presentation');
        }
        $this->GetIDForIdent($ident);
    }
    public function RegisterVariableBoolean($ident, ...$args)
    {
        global $strictSdk;
        if ($strictSdk && count($args) < 2) {
            throw new RuntimeException('Missing explicit profile/presentation');
        }
        $this->GetIDForIdent($ident);
    }
    public function MaintainVariable($ident, ...$args)
    {
        $this->GetIDForIdent($ident);
    }
    public function GetIDForIdent($ident)
    {
        if (!isset($this->ids[$ident])) {
            $this->ids[$ident] = 901 + count($this->ids);
        } return $this->ids[$ident];
    }
    public function SetValue($ident, $value)
    {
        SetValue($this->GetIDForIdent($ident), $value);
    }
    public function RegisterMessage(...$args)
    {
    }
    public function EnableAction(...$args)
    {
    }
    public function RegisterTimer($ident, $interval, $script)
    {
        $this->timer = [$ident, $interval, $script];
    }
    public function SetTimerInterval($ident, $interval)
    {
        $this->timer[1] = $interval;
    }
    public function SetStatus($status)
    {
        $this->status = $status;
    }
    public function Translate($text)
    {
        return $text;
    }
    public function SendDebug(...$args)
    {
    }
    public function SetBuffer($key, $value)
    {
        $this->buffers[$key] = $value;
    }
    public function GetBuffer($key)
    {
        return $this->buffers[$key] ?? '';
    }
}
function IPS_GetVariableList()
{
    global $vars;
    return array_keys($vars);
}
function IPS_GetVariable($id)
{
    global $vars, $fail;
    if ($fail) {
        throw new RuntimeException('Deleted during scan');
    } return ['VariableType' => $vars[$id]['type'], 'VariableProfile' => $vars[$id]['profile'], 'VariableCustomProfile' => '', 'VariableUpdated' => 100, 'VariableChanged' => 100, 'VariableAction' => 0, 'VariableCustomAction' => 0];
}
function IPS_VariableProfileExists($profile)
{
    return $profile !== '';
}
function GetValue($id)
{
    global $vars, $values;
    return $vars[$id]['value'] ?? $values[$id] ?? false;
}
function GetValueFormatted($id)
{
    return (string) GetValue($id);
}
function SetValue($id, $value)
{
    global $values, $writes;
    if ($id <= 0) {
        throw new RuntimeException('Invalid write');
    } $values[$id] = $value;
    $writes[] = $id;
}
function SetValueString($id, $value)
{
    SetValue($id, $value);
}
function SetValueBoolean($id, $value)
{
    SetValue($id, $value);
}
function IPS_GetObjectIDByIdent($ident, $parent)
{
    global $module;
    return $module->GetIDForIdent($ident);
}
function IPS_GetName($id)
{
    return 'Fixture ' . $id;
}
function IPS_GetParent($id)
{
    return 800;
}
function IPS_GetLocation($id)
{
    return 'Fixture location';
}
function IPS_GetObject($id)
{
    global $vars;
    return ['ParentID' => 800, 'ObjectIdent' => $vars[$id]['ident'] ?? ''];
}
function IPS_InstanceExists($id)
{
    return $id === 800;
}
function IPS_GetInstance($id)
{
    return ['ModuleInfo' => ['ModuleID' => '{7D2B8EFA-23D0-D29C-DBEE-E81F1FC2DBDC}']];
}
function IPS_GetVariablePresentation($id)
{
    return ['PRESENTATION' => '{3319437D-7CDE-699D-750A-3C6A3841FA75}'];
}
function IPS_GetInstanceListByModuleID($id)
{
    return [802];
}
function SMTP_SendMail(...$args)
{
    global $notifications;
    $notifications[] = ['email', $args];
}
function WFC_PushNotification(...$args)
{
    global $notifications;
    $notifications[] = ['app', $args];
}
function VISU_PostNotification(...$args)
{
    global $notifications;
    $notifications[] = ['visu', $args];
}
require $argv[1];
$module = new ProfileMonitor();
$module->Create();
$module->properties = array_replace($module->properties, ['Active' => true, 'Variable_Output' => true,
    'All_Variable_Output' => true, 'Webfront_HTML' => true, 'IDs2Ignore' => '[{"ID2Ignore":12}]',
    'TimerMethod' => 1, 'ExecutionMinuteMinute' => 30, 'NotifyByEmail' => true, 'EmailVariable' => 802,
    'NotifyByApp' => true, 'WebfrontVariable' => 802,
    'Profiles2Monitor' => '[{"ProfileName":"~Battery","ProfileValue":true},{"ProfileName":"~Battery.Reversed","ProfileValue":false},{"ProfileName":"~Battery.100","ProfileValue":"10"}]']);
if ($argc === 4) {
    $fixture = json_decode(file_get_contents($argv[3]), true, 512, JSON_THROW_ON_ERROR);
    $vars = $fixture['variables'];
    $module->properties = array_replace($module->properties, $fixture['properties']);
}
$module->ApplyChanges();
$beforeIds = $module->ids;
$module->ApplyChanges();
if ($module->ids !== $beforeIds) {
    throw new RuntimeException('ApplyChanges changed IDs');
}
if ($mode === 'rust') {
    foreach (['EmailApp' => 'EmailVariable', 'NotifyApp' => 'WebfrontVariable'] as $method => $property) {
        $module->properties[$property] = 0;
        ob_start();
        $error = null;
        try {
            $module->$method();
        } catch (RuntimeException $e) {
            $error = $e;
        }
        $output = ob_get_clean();
        if ($error === null || $output !== '' || $writes !== [] || $notifications !== []) {
            throw new LogicException('Unconfigured notification must fail without output or effects');
        }
    }
    echo json_encode(['strictRegistration' => true, 'outputFreeErrors' => true]);
    exit;
}
if ($mode !== 'legacy') {
    $module->properties['PresentationMonitoring'] = true;
    $module->properties['MonitorBlinkBattery'] = true;
}
if ($mode === 'preview') {
    echo $module->PreviewPresentations();
    if ($writes !== [] || $notifications !== []) {
        throw new RuntimeException('Preview side effect');
    }
    exit;
}
if ($mode === 'failure') {
    $fail = true;
    try {
        $module->Check();
        throw new LogicException('Failure not detected');
    } catch (RuntimeException $e) {
        if ($writes !== [] || $notifications !== []) {
            throw new LogicException('Failed scan produced effects');
        }
        echo json_encode(['failureBeforeWrites' => true]);
        exit;
    }
}
if ($mode === 'inactive') {
    $module->properties['Active'] = false;
    $module->ApplyChanges();
    if ($module->timer[1] !== 0) {
        throw new RuntimeException('Timer not disabled');
    }
}
if ($mode === 'trigger') {
    $values[$module->GetIDForIdent('RemoteTrigger')] = true;
    $module->MessageSink(time(), $module->GetIDForIdent('RemoteTrigger'), VM_UPDATE, []);
} else {
    $module->Check();
}
$out = [];
foreach ($module->ids as $ident => $id) {
    if ($ident !== 'LastUpdate') {
        $out[$ident] = $values[$id] ?? null;
    }
}
echo json_encode(['values' => $out, 'notifications' => $notifications, 'timer' => $module->timer], JSON_THROW_ON_ERROR);
