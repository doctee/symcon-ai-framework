<?php
declare(strict_types=1);

/**
 * PHPStan stubs for IP-Symcon runtime functions.
 * This file is only used for static analysis outside IP-Symcon.
 */

/** @var array{SELF:int} $_IPS */
$_IPS = ['SELF' => 0];

class IPSModule
{
    public int $InstanceID;

    public function Create() {}
    public function ApplyChanges() {}

    protected function RegisterPropertyString(string $ident, string $default): void {}
    protected function RegisterPropertyInteger(string $ident, int $default): void {}
    protected function RegisterPropertyFloat(string $ident, float $default): void {}
    protected function RegisterPropertyBoolean(string $ident, bool $default): void {}
    protected function ReadPropertyString(string $ident): string {}
    protected function ReadPropertyInteger(string $ident): int {}
    protected function ReadPropertyFloat(string $ident): float {}
    protected function ReadPropertyBoolean(string $ident): bool {}

    protected function RegisterAttributeString(string $ident, string $default): void {}
    protected function RegisterAttributeInteger(string $ident, int $default): void {}
    protected function RegisterAttributeBoolean(string $ident, bool $default): void {}
    protected function ReadAttributeString(string $ident): string {}
    protected function ReadAttributeInteger(string $ident): int {}
    protected function ReadAttributeBoolean(string $ident): bool {}
    protected function WriteAttributeString(string $ident, string $value): void {}
    protected function WriteAttributeInteger(string $ident, int $value): void {}
    protected function WriteAttributeBoolean(string $ident, bool $value): void {}

    protected function RegisterVariableString(
        string $ident,
        string $name,
        string $profile,
        int $position
    ): void {}

    protected function RegisterVariableInteger(
        string $ident,
        string $name,
        string $profile,
        int $position
    ): void {}

    protected function RegisterVariableBoolean(
        string $ident,
        string $name,
        string $profile,
        int $position
    ): void {}

    protected function RegisterVariableFloat(
        string $ident,
        string $name,
        string $profile,
        int $position
    ): void {}

    protected function SetValue(string $ident, mixed $value): void {}
    protected function GetValue(string $ident): mixed {}
    protected function GetIDForIdent(string $ident): int {}
    protected function RegisterReference(int $id): void {}
    protected function UnregisterReference(int $id): void {}
    protected function RegisterTimer(string $ident, int $interval, string $script): void {}
    protected function SetTimerInterval(string $ident, int $interval): void {}
    protected function SetStatus(int $status): void {}
    protected function SendDataToParent(string $json): string {}
    protected function SendDataToChildren(string $json): void {}
    protected function SendDebug(string $message, mixed $data, int $format): void {}
    protected function Translate(string $text): string {}
}

function IPS_ObjectExists(int $id): bool {}
function IPS_VariableExists(int $id): bool {}
function IPS_ScriptExists(int $id): bool {}
function IPS_EventExists(int $id): bool {}
function IPS_InstanceExists(int $id): bool {}
function IPS_VariableProfileExists(string $name): bool {}
function HasAction(int $variableID): bool {}

function IPS_GetModuleList(): array {}
function IPS_GetInstanceListByModuleID(string $moduleID): array {}
function IPS_GetKernelDir(): string {}
function IPS_GetObjectIDByIdent(string $ident, int $parentID): int|false {}
function IPS_GetObject(int $id): array {}
function IPS_GetParent(int $id): int {}
function IPS_GetChildrenIDs(int $id): array {}
/**
 * @return array{
 *   VariableAction: int,
 *   VariableChanged: int,
 *   VariableCustomAction: int,
 *   VariableCustomPresentation?: array<string, mixed>,
 *   VariableCustomProfile: string,
 *   VariableID: int,
 *   VariableIsLocked: bool,
 *   VariablePresentation?: array<string, mixed>,
 *   VariableProfile: string,
 *   VariableType: int,
 *   VariableUpdated: int
 * }
 */
function IPS_GetVariable(int $id): array {}
function IPS_GetEvent(int $id): array {}
function IPS_GetInstance(int $id): array {}
function IPS_GetConfiguration(int $instanceID): string {}
function IPS_GetVariableProfile(string $name): array {}
function IPS_GetLink(int $id): array {}

function IPS_CreateCategory(): int {}
function IPS_DeleteCategory(int $id): bool {}
function IPS_CreateVariable(int $type): int {}
function IPS_CreateEvent(int $type): int {}
function IPS_CreateInstance(string $moduleID): int {}
function IPS_DeleteVariable(int $id): bool {}
function IPS_DeleteEvent(int $id): bool {}
function IPS_DeleteInstance(int $id): bool {}
function IPS_CreateLink(): int {}
function IPS_CreateScript(int $type): int {}
function IPS_DeleteScript(int $scriptID, bool $deleteFile): bool {}
function IPS_GetScriptContent(int $scriptID): string {}
function IPS_SetScriptContent(int $scriptID, string $content): bool {}
function IPS_CreateVariableProfile(string $name, int $type): void {}
function IPS_ConnectInstance(int $instanceID, int $parentID): void {}
function IPS_DisconnectInstance(int $instanceID): void {}
function IPS_SetConfiguration(int $instanceID, string $configuration): void {}
function IPS_ApplyChanges(int $instanceID): void {}

function IPS_SetParent(int $id, int $parentID): void {}
function IPS_SetIdent(int $id, string $ident): void {}
function IPS_SetName(int $id, string $name): void {}
function IPS_SetPosition(int $id, int $position): void {}
function IPS_SetIcon(int $id, string $icon): void {}
function IPS_SetHidden(int $id, bool $hidden): void {}

function IPS_SetEventCyclic(int $id, int $dateType, int $dateInterval, int $dateDays, int $dateDayInterval, int $timeType, int $timeInterval): void {}
function IPS_SetEventScript(int $id, string $script): void {}
function IPS_SetEventAction(int $id, string $actionID, array $parameters): void {}
function IPS_SetEventActive(int $id, bool $active): void {}
function IPS_SetEventTrigger(int $id, int $triggerType, int $variableID): void {}

function IPS_SetLinkTargetID(int $id, int $targetID): void {}

function IPS_SetVariableCustomProfile(int $id, string $profile): void {}
function IPS_SetVariableCustomAction(int $id, int $scriptID): void {}
function IPS_SetVariableProfileIcon(string $name, string $icon): void {}
function IPS_SetVariableProfileText(string $name, string $prefix, string $suffix): void {}
function IPS_SetVariableProfileDigits(string $name, int $digits): void {}
function IPS_SetVariableProfileValues(string $name, int|float $minValue, int|float $maxValue, int|float $stepSize): void {}
function IPS_SetVariableProfileAssociation(string $name, int|float $value, string $label, string $icon, int $color): void {}

function IPS_Sleep(int $milliseconds): void {}
function IPS_LogMessage(string $sender, string $message): void {}
/** @param array<string, int|string|bool> $parameters */
function Sys_GetURLContentEx(string $url, array $parameters): string|false {}
function IPS_SemaphoreEnter(string $name, int $milliseconds): bool {}
function IPS_SemaphoreLeave(string $name): bool {}
function AC_GetLoggingStatus(int $archiveID, int $variableID): bool {}
/** @return array<int, array{TimeStamp: int, Value: int|float}>|false */
function AC_GetLoggedValues(int $archiveID, int $variableID, int $startTime, int $endTime, int $limit): array|false {}
function SAEFLOCATION_GetDescriptor(int $instanceId): string {}
function OMWEATHER_GetLocationDescriptor(int $instanceId): string {}
function OMSOLAR_GetPowerForecastJson(int $instanceId, int $from, int $to, string $scope): string {}
function OMSOLAR_GetDailyEnergyForecastJson(int $instanceId, int $from, int $to, string $scope): string {}

function GetValue(int $id): mixed {}
function SetValue(int $id, mixed $value): void {}
function RequestAction(int $variableID, mixed $value): bool {}
