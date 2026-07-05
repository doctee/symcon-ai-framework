<?php
declare(strict_types=1);

/**
 * PHPStan stubs for IP-Symcon runtime functions.
 * This file is only used for static analysis outside IP-Symcon.
 */

/** @var array{SELF:int} $_IPS */
$_IPS = ['SELF' => 0];

function IPS_ObjectExists(int $id): bool {}
function IPS_VariableExists(int $id): bool {}
function IPS_ScriptExists(int $id): bool {}
function IPS_VariableProfileExists(string $name): bool {}

function IPS_GetModuleList(): array {}
function IPS_GetObjectIDByIdent(string $ident, int $parentID): int|false {}
function IPS_GetObject(int $id): array {}
function IPS_GetVariable(int $id): array {}
function IPS_GetEvent(int $id): array {}
function IPS_GetInstance(int $id): array {}
function IPS_GetVariableProfile(string $name): array {}

function IPS_CreateCategory(): int {}
function IPS_CreateVariable(int $type): int {}
function IPS_CreateEvent(int $type): int {}
function IPS_CreateInstance(string $moduleID): int {}
function IPS_CreateLink(): int {}
function IPS_CreateScript(int $type): int {}
function IPS_CreateVariableProfile(string $name, int $type): void {}

function IPS_SetParent(int $id, int $parentID): void {}
function IPS_SetIdent(int $id, string $ident): void {}
function IPS_SetName(int $id, string $name): void {}
function IPS_SetPosition(int $id, int $position): void {}
function IPS_SetIcon(int $id, string $icon): void {}
function IPS_SetHidden(int $id, bool $hidden): void {}

function IPS_SetEventCyclic(int $id, int $dateType, int $dateInterval, int $dateDays, int $dateDayInterval, int $timeType, int $timeInterval): void {}
function IPS_SetEventScript(int $id, int $scriptID): void {}
function IPS_SetEventAction(int $id, string $actionID, array $parameters): void {}
function IPS_SetEventActive(int $id, bool $active): void {}

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

function GetValue(int $id): mixed {}
function SetValue(int $id, mixed $value): void {}
