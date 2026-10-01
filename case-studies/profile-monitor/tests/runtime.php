<?php

declare(strict_types=1);

require_once __DIR__ . '/evaluator.php';
require_once __DIR__ . '/../candidate/MonitorRuntime.php';

use SAEF\ProfileMonitor\Evaluator;
use SAEF\ProfileMonitor\MonitorRuntime;

$properties = ['PresentationMonitoring' => true, 'MonitorZigbeeBattery' => true, 'MonitorBlinkBattery' => true,
    'PresentationPercentThreshold' => 10, 'BlinkBatteryThreshold' => 2, 'PresentationRules' => '[]',
    'SkipNeverUpdated' => true, 'Profiles2Monitor' => '[{"ProfileName":"~Battery","ProfileValue":true}]', 'IDs2Ignore' => ''];
$variables = [1 => row(1, true, '~Battery'), 2 => row(2, 1, '', ['ident' => 'battery']),
    3 => row(3, 4, '~Battery', ['customProfile' => 'unmonitored'])];
$reads = 0;
$presentationFailure = false;
$metadataFailure = false;
function IPS_GetVariableList(): array
{
    return [1, 2, 3, 901];
}
function IPS_GetVariable(int $id): array
{
    global $variables, $metadataFailure;
    if ($metadataFailure) {
        throw new RuntimeException('Concurrent deletion');
    }
    $v = $variables[$id];
    return ['VariableType' => $v['type'], 'VariableUpdated' => $v['updated'], 'VariableProfile' => $v['profile'],
        'VariableCustomProfile' => $v['customProfile'] ?? '', 'VariableAction' => 0, 'VariableCustomAction' => 0];
}
function IPS_VariableProfileExists(string $name): bool
{
    return in_array($name, ['~Battery', 'unmonitored'], true);
}
function GetValue(int $id)
{
    global $variables;
    return $variables[$id]['value'];
}
function IPS_GetObject(int $id): array
{
    global $variables;
    return ['ParentID' => 800, 'ObjectIdent' => $variables[$id]['ident'] ?? ''];
}
function IPS_InstanceExists(int $id): bool
{
    return $id === 800;
}
function IPS_GetInstance(int $id): array
{
    return ['ModuleInfo' => ['ModuleID' => Evaluator::BLINK_MODULE]];
}
if (($argv[1] ?? '') !== '--legacy-runtime') {
    function IPS_GetVariablePresentation(int $id): array
    {
        global $reads, $presentationFailure;
        ++$reads;
        if ($presentationFailure) {
            throw new RuntimeException('Presentation failure');
        }
        return ['PRESENTATION' => Evaluator::VALUE_PRESENTATION];
    }
}

class PreviewHarness
{
    use MonitorRuntime;

    public function normal(): array
    {
        return $this->CollectMonitorAnalysis();
    }

    public function ReadPropertyBoolean(string $key): bool
    {
        global $properties;
        return $properties[$key];
    }
    public function ReadPropertyInteger(string $key): int
    {
        global $properties;
        return $properties[$key];
    }
    public function ReadPropertyString(string $key): string
    {
        global $properties;
        return $properties[$key];
    }
    public function GetIDForIdent(string $ident): int
    {
        return 901;
    }
}
$h = new PreviewHarness();
if (($argv[1] ?? '') === '--legacy-runtime') {
    $properties['PresentationMonitoring'] = false;
    expect($h->normal()['checked'], [1], 'legacy runtime without presentation function');
    $properties['PresentationMonitoring'] = true;
    try {
        $h->normal();
        throw new LogicException('Missing API not detected');
    } catch (RuntimeException $e) {
        expect($e->getMessage(), 'Presentation monitoring requires Symcon 8.1 or newer', 'unsupported API detected');
    }
    echo "Profile Monitor legacy runtime: $count cumulative assertions passed\n";
    exit;
}

$r = json_decode($h->PreviewPresentations(), true, 512, JSON_THROW_ON_ERROR);
expect($r['checked'], [1, 2], 'runtime custom profile precedence and own warning excluded');
expect($r['warnings'], [1, 2], 'runtime combines legacy and Blink');
expect($reads, 1, 'presentation read only for selected identity');
$properties['PresentationMonitoring'] = false;
$r = $h->normal();
expect($r['checked'], [1], 'extension opt-in');
expect($reads, 1, 'no presentation API in legacy mode');
$r = json_decode($h->PreviewPresentations(), true);
expect($r['checked'], [1, 2], 'preview configured native rules while master switch off');
$properties['PresentationMonitoring'] = true;
$presentationFailure = true;
try {
    $h->PreviewPresentations();
    throw new LogicException('Failure not propagated');
} catch (RuntimeException $e) {
    expect($e->getMessage(), 'Presentation failure', 'API error visible');
}
$presentationFailure = false;
$metadataFailure = true;
try {
    $h->PreviewPresentations();
    throw new LogicException('Failure not propagated');
} catch (RuntimeException $e) {
    expect($e->getMessage(), 'Concurrent deletion', 'snapshot failure visible');
}
echo "Profile Monitor runtime: $count cumulative assertions passed; no write functions defined\n";
