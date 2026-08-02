<?php
declare(strict_types=1);

/**
 * SAEF Helper: EnsureInstance
 *
 * Idempotently creates or updates an instance below a known parent object.
 */

require_once __DIR__ . '/../common/Validation.php';

if (!defined('SAEF_HELPER_ENSURE_INSTANCE')) {
    define('SAEF_HELPER_ENSURE_INSTANCE', true);

    /**
     * Ensures that an instance of the expected module exists below a parent.
     *
     * @param bool $updateExistingPresentation Whether name, position, icon and visibility are managed after creation.
     */
    function SAEF_EnsureInstance(
        int $parentID,
        string $ident,
        string $name,
        string $moduleGuid,
        ?int $position = null,
        ?string $icon = null,
        ?bool $hidden = null,
        bool $updateExistingPresentation = true
    ): int {
        SAEF_ValidateParentObject($parentID);
        SAEF_ValidateIdent($ident);
        SAEF_ValidateObjectName($name);
        SAEF_ValidateModuleGuid($moduleGuid);

        $existingID = @IPS_GetObjectIDByIdent($ident, $parentID);

        $created = $existingID === false;
        if ($created) {
            $instanceID = IPS_CreateInstance($moduleGuid);
            SAEF_ValidateMutableObject($instanceID, 1);
            IPS_SetParent($instanceID, $parentID);
            IPS_SetIdent($instanceID, $ident);
        } else {
            $object = IPS_GetObject($existingID);

            if ($object['ObjectType'] !== 1) {
                throw new RuntimeException(sprintf(
                    'Object with Ident "%s" below parent %d exists but is not an instance.',
                    $ident,
                    $parentID
                ));
            }

            $instanceID = $existingID;
            $instance = IPS_GetInstance($instanceID);

            if ($instance['ModuleInfo']['ModuleID'] !== $moduleGuid) {
                throw new RuntimeException(sprintf(
                    'Instance "%s" uses module %s, expected %s. Refusing to recreate instance.',
                    $ident,
                    $instance['ModuleInfo']['ModuleID'],
                    $moduleGuid
                ));
            }
        }

        SAEF_ValidateMutableObject($instanceID, 1);
        if ($created || $updateExistingPresentation) {
            IPS_SetName($instanceID, $name);

            if ($position !== null) {
                IPS_SetPosition($instanceID, $position);
            }

            if ($icon !== null) {
                IPS_SetIcon($instanceID, $icon);
            }

            if ($hidden !== null) {
                IPS_SetHidden($instanceID, $hidden);
            }
        }

        return $instanceID;
    }
}
