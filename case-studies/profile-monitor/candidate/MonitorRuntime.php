<?php

declare(strict_types=1);

namespace SAEF\ProfileMonitor;

use RuntimeException;

/** Module-only adapter. Preview and collection never write Symcon state. */
trait MonitorRuntime
{
    public function PreviewPresentations(): string
    {
        return json_encode($this->CollectMonitorAnalysis(true), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }

    private function CollectMonitorAnalysis(bool $preview = false): array
    {
        $enabled = $preview || $this->ReadPropertyBoolean('PresentationMonitoring');
        $rules = [];
        if ($enabled) {
            if (!function_exists('IPS_GetVariablePresentation')) {
                throw new RuntimeException('Presentation monitoring requires Symcon 8.1 or newer');
            }
            $rules = array_merge(
                Evaluator::presets(
                    $this->ReadPropertyBoolean('MonitorZigbeeBattery'),
                    $this->ReadPropertyBoolean('MonitorBlinkBattery'),
                    (float) $this->ReadPropertyInteger('PresentationPercentThreshold'),
                    $this->ReadPropertyInteger('BlinkBatteryThreshold')
                ),
                $this->DecodeMonitorRows($this->ReadPropertyString('PresentationRules'))
            );
        }
        $evaluator = new Evaluator(
            $this->DecodeMonitorRows($this->ReadPropertyString('Profiles2Monitor')),
            $this->DecodeMonitorRows($this->ReadPropertyString('IDs2Ignore')),
            $rules,
            $this->ReadPropertyBoolean('SkipNeverUpdated')
        );
        $snapshot = [];
        $modules = [];
        $warningID = $this->GetIDForIdent('Warning');
        foreach (IPS_GetVariableList() as $id) {
            if ($id <= 0) {
                throw new RuntimeException('Invalid variable inventory');
            }
            if ($id === $warningID) {
                continue;
            }
            // A concurrent deletion aborts before any module output is written.
            $v = IPS_GetVariable($id);
            $profile = IPS_VariableProfileExists($v['VariableProfile']) ? $v['VariableProfile'] : '';
            if ($v['VariableCustomProfile'] !== '' && IPS_VariableProfileExists($v['VariableCustomProfile'])) {
                $profile = $v['VariableCustomProfile'];
            }
            $row = ['id' => $id, 'type' => $v['VariableType'], 'value' => GetValue($id),
                'updated' => $v['VariableUpdated'], 'profile' => $profile];
            if ($enabled) {
                $o = IPS_GetObject($id);
                $parent = $o['ParentID'];
                if (!array_key_exists($parent, $modules)) {
                    $modules[$parent] = $parent > 0 && IPS_InstanceExists($parent)
                        ? IPS_GetInstance($parent)['ModuleInfo']['ModuleID'] : null;
                }
                $row['moduleId'] = $modules[$parent];
                $row['ident'] = $o['ObjectIdent'];
                $row['action'] = $v['VariableCustomAction'] > 1
                    || ($v['VariableCustomAction'] === 0 && $v['VariableAction'] > 0);
                // Read resolved presentations only for a selected identity, not the whole installation.
                foreach ($rules as $rule) {
                    if (
                        (isset($rule['variableId']) && $rule['variableId'] === $id)
                        || (isset($rule['moduleId'], $rule['ident']) && $row['moduleId'] === $rule['moduleId'] && $row['ident'] === $rule['ident'])
                    ) {
                        $row['presentation'] = IPS_GetVariablePresentation($id);
                        break;
                    }
                }
            }
            $snapshot[] = $row;
        }
        return $evaluator->evaluate($snapshot);
    }

    private function DecodeMonitorRows(string $json): array
    {
        if (trim($json) === '') {
            return [];
        }
        if (!is_array(json_decode($json, false, 512, JSON_THROW_ON_ERROR))) {
            throw new RuntimeException('Monitor configuration must be a JSON list');
        }
        $rows = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($rows) || array_values($rows) !== $rows) {
            throw new RuntimeException('Monitor configuration must be a JSON list');
        }
        return $rows;
    }
}
