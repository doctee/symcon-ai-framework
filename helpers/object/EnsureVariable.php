<?php
declare(strict_types=1);

/**
 * SAEF Helper: EnsureVariable
 *
 * Idempotently creates or updates a variable below a known parent object.
 *
 * This helper is intentionally stricter than many private helper libraries:
 * - existing objects with the same Ident must be variables,
 * - existing variables must have the expected variable type,
 * - profiles and action scripts are validated before assignment,
 * - user data is preserved by never deleting/recreating an existing variable.
 *
 * Related SAEF artifacts:
 * - RS-001 Symcon Engineering Standards
 * - EK-004 Internal State Management
 * - EK-005 Idempotent Configuration
 * - RI-001 Idempotent Configuration Script
 */

require_once __DIR__ . '/../common/Validation.php';

if (!defined('SAEF_HELPER_ENSURE_VARIABLE')) {
    define('SAEF_HELPER_ENSURE_VARIABLE', true);

    /**
     * Ensures that a variable exists below a parent object.
     *
     * @param int         $parentID Parent object ID.
     * @param string      $ident    Stable technical Ident.
     * @param string      $name     User-facing variable name.
     * @param int         $type     Variable type: 0 bool, 1 int, 2 float, 3 string.
     * @param string      $profile  Optional custom or standard profile name.
     * @param int|null    $actionID Optional custom action script ID.
     * @param int|null    $position Optional object position.
     * @param string|null $icon     Optional object icon.
     * @param bool        $updateExistingPresentation Whether name, position and icon are managed after creation.
     *
     * @return int Variable ID.
     *
     * @throws InvalidArgumentException On invalid configuration.
     * @throws RuntimeException On incompatible existing object or invalid profile/action.
     */
    function SAEF_EnsureVariable(
        int $parentID,
        string $ident,
        string $name,
        int $type,
        string $profile = '',
        ?int $actionID = null,
        ?int $position = null,
        ?string $icon = null,
        bool $updateExistingPresentation = true
    ): int {
        SAEF_ValidateParentObject($parentID);
        SAEF_ValidateIdent($ident);
        SAEF_ValidateObjectName($name);
        SAEF_ValidateVariableType($type);

        if ($profile !== '' && !IPS_VariableProfileExists($profile)) {
            throw new RuntimeException('Variable profile does not exist: ' . $profile);
        }

        if ($actionID !== null && !IPS_ScriptExists($actionID)) {
            throw new RuntimeException('Action script does not exist: ' . $actionID);
        }

        $existingID = @IPS_GetObjectIDByIdent($ident, $parentID);

        $created = $existingID === false;
        if ($created) {
            $variableID = IPS_CreateVariable($type);
            IPS_SetParent($variableID, $parentID);
            IPS_SetIdent($variableID, $ident);
        } else {
            $object = IPS_GetObject($existingID);

            if ($object['ObjectType'] !== 2) {
                throw new RuntimeException(sprintf(
                    'Object with Ident "%s" below parent %d exists but is not a variable.',
                    $ident,
                    $parentID
                ));
            }

            $variableID = $existingID;
            $variable = IPS_GetVariable($variableID);

            if ($variable['VariableType'] !== $type) {
                throw new RuntimeException(sprintf(
                    'Variable "%s" has type %d, expected %d. Refusing to recreate variable to preserve history and links.',
                    $ident,
                    $variable['VariableType'],
                    $type
                ));
            }
        }

        if ($created || $updateExistingPresentation) {
            IPS_SetName($variableID, $name);

            if ($position !== null) {
                IPS_SetPosition($variableID, $position);
            }

            if ($icon !== null) {
                IPS_SetIcon($variableID, $icon);
            }
        }

        if ($profile !== '') {
            IPS_SetVariableCustomProfile($variableID, $profile);
        }

        if ($actionID !== null) {
            IPS_SetVariableCustomAction($variableID, $actionID);
        }

        return $variableID;
    }
}
