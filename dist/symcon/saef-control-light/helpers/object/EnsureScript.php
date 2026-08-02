<?php
declare(strict_types=1);

/**
 * SAEF Helper: EnsureScript
 *
 * Idempotently creates or updates a script below a known parent object.
 */

require_once __DIR__ . '/../common/Validation.php';

if (!defined('SAEF_HELPER_ENSURE_SCRIPT')) {
    define('SAEF_HELPER_ENSURE_SCRIPT', true);

    /**
     * Ensures that a script exists below a parent object.
     *
     * @param bool $updateExistingPresentation Whether name, position, icon and visibility are managed after creation.
     */
    function SAEF_EnsureScript(
        int $parentID,
        string $ident,
        string $name,
        int $scriptType = 0,
        ?int $position = null,
        ?string $icon = null,
        ?bool $hidden = null,
        bool $updateExistingPresentation = true
    ): int {
        SAEF_ValidateParentObject($parentID);
        SAEF_ValidateIdent($ident);
        SAEF_ValidateObjectName($name);
        SAEF_ValidateScriptType($scriptType);

        $existingID = @IPS_GetObjectIDByIdent($ident, $parentID);

        $created = $existingID === false;
        if ($created) {
            $scriptID = IPS_CreateScript($scriptType);
            SAEF_ValidateMutableObject($scriptID, 3);
            IPS_SetParent($scriptID, $parentID);
            IPS_SetIdent($scriptID, $ident);
        } else {
            $object = IPS_GetObject($existingID);

            if ($object['ObjectType'] !== 3) {
                throw new RuntimeException(sprintf(
                    'Object with Ident "%s" below parent %d exists but is not a script.',
                    $ident,
                    $parentID
                ));
            }

            $scriptID = $existingID;
        }

        SAEF_ValidateMutableObject($scriptID, 3);
        if ($created || $updateExistingPresentation) {
            IPS_SetName($scriptID, $name);

            if ($position !== null) {
                IPS_SetPosition($scriptID, $position);
            }

            if ($icon !== null) {
                IPS_SetIcon($scriptID, $icon);
            }

            if ($hidden !== null) {
                IPS_SetHidden($scriptID, $hidden);
            }
        }

        return $scriptID;
    }
}
