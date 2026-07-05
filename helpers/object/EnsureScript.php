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

    function SAEF_EnsureScript(
        int $parentID,
        string $ident,
        string $name,
        int $scriptType = 0,
        ?int $position = null,
        ?string $icon = null,
        ?bool $hidden = null
    ): int {
        SAEF_ValidateParentObject($parentID);
        SAEF_ValidateIdent($ident);
        SAEF_ValidateObjectName($name);
        SAEF_ValidateScriptType($scriptType);

        $existingID = @IPS_GetObjectIDByIdent($ident, $parentID);

        if ($existingID === false) {
            $scriptID = IPS_CreateScript($scriptType);
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

        return $scriptID;
    }
}
