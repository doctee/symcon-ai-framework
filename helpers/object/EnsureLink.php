<?php
declare(strict_types=1);

/**
 * SAEF Helper: EnsureLink
 *
 * Idempotently creates or updates a link below a known parent object.
 */

require_once __DIR__ . '/../common/Validation.php';

if (!defined('SAEF_HELPER_ENSURE_LINK')) {
    define('SAEF_HELPER_ENSURE_LINK', true);

    function SAEF_EnsureLink(
        int $parentID,
        string $ident,
        string $name,
        int $targetID,
        ?int $position = null,
        ?string $icon = null,
        ?bool $hidden = null
    ): int {
        SAEF_ValidateParentObject($parentID);
        SAEF_ValidateIdent($ident);
        SAEF_ValidateObjectName($name);

        if ($targetID <= 0 || !IPS_ObjectExists($targetID)) {
            throw new InvalidArgumentException('Link target object does not exist: ' . $targetID);
        }

        $existingID = @IPS_GetObjectIDByIdent($ident, $parentID);

        if ($existingID === false) {
            $linkID = IPS_CreateLink();
            IPS_SetParent($linkID, $parentID);
            IPS_SetIdent($linkID, $ident);
        } else {
            $object = IPS_GetObject($existingID);

            if ($object['ObjectType'] !== 6) {
                throw new RuntimeException(sprintf(
                    'Object with Ident "%s" below parent %d exists but is not a link.',
                    $ident,
                    $parentID
                ));
            }

            $linkID = $existingID;
        }

        IPS_SetName($linkID, $name);
        IPS_SetLinkTargetID($linkID, $targetID);

        if ($position !== null) {
            IPS_SetPosition($linkID, $position);
        }

        if ($icon !== null) {
            IPS_SetIcon($linkID, $icon);
        }

        if ($hidden !== null) {
            IPS_SetHidden($linkID, $hidden);
        }

        return $linkID;
    }
}
