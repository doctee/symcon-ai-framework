<?php
declare(strict_types=1);

/**
 * SAEF Common Helper: Validation
 *
 * Shared validation functions for SAEF helper files.
 */

if (!defined('SAEF_HELPER_VALIDATION')) {
    define('SAEF_HELPER_VALIDATION', true);

    function SAEF_ValidateParentObject(int $parentID): void
    {
        if ($parentID <= 0 || !IPS_ObjectExists($parentID)) {
            throw new InvalidArgumentException('Parent object does not exist: ' . $parentID);
        }
    }

    /**
     * Rejects the root object and validates a concrete mutation target.
     *
     * Object ID 0 is the IP-Symcon root category. Failed lookups and creation
     * calls can be coerced to 0 in weakly typed caller code, so every object
     * mutator must validate its target before the first write.
     */
    function SAEF_ValidateMutableObject(int $objectID, ?int $expectedObjectType = null): void
    {
        if ($objectID <= 0) {
            throw new InvalidArgumentException('Mutable object ID must be greater than zero.');
        }
        if (!IPS_ObjectExists($objectID)) {
            throw new RuntimeException('Mutable object does not exist: ' . $objectID);
        }
        if ($expectedObjectType === null) {
            return;
        }

        $object = IPS_GetObject($objectID);
        if (($object['ObjectType'] ?? null) !== $expectedObjectType) {
            throw new RuntimeException(sprintf(
                'Mutable object %d has type %s, expected %d.',
                $objectID,
                (string) ($object['ObjectType'] ?? 'missing'),
                $expectedObjectType
            ));
        }
    }

    function SAEF_ValidateIdent(string $ident): void
    {
        if ($ident === '') {
            throw new InvalidArgumentException('Ident must not be empty.');
        }

        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $ident)) {
            throw new InvalidArgumentException(
                'Ident must start with a letter or underscore and contain only letters, digits and underscores: ' . $ident
            );
        }
    }

    function SAEF_ValidateVariableType(int $type): void
    {
        if (!in_array($type, [0, 1, 2, 3], true)) {
            throw new InvalidArgumentException('Invalid variable type: ' . $type);
        }
    }

    function SAEF_ValidateObjectName(string $name): void
    {
        if ($name === '') {
            throw new InvalidArgumentException('Object name must not be empty.');
        }
    }

    function SAEF_ValidateModuleGuid(string $moduleGuid): void
    {
        if (!preg_match('/^\{[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12}\}$/', $moduleGuid)) {
            throw new InvalidArgumentException('Invalid module GUID: ' . $moduleGuid);
        }

        if (!in_array($moduleGuid, IPS_GetModuleList(), true)) {
            throw new RuntimeException('Module GUID is not available in this installation: ' . $moduleGuid);
        }
    }

    function SAEF_ValidateScriptType(int $scriptType): void
    {
        if (!in_array($scriptType, [0, 1], true)) {
            throw new InvalidArgumentException('Invalid script type: ' . $scriptType);
        }
    }
}
