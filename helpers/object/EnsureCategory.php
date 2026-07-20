<?php
declare(strict_types=1);

/**
 * SAEF Helper: EnsureCategory
 *
 * Idempotently creates or updates a category below a known parent object.
 *
 * Related SAEF artifacts:
 * - RS-001 Symcon Engineering Standards
 * - EK-005 Idempotent Configuration
 */

require_once __DIR__ . '/../common/Validation.php';

if (!defined('SAEF_HELPER_ENSURE_CATEGORY')) {
    define('SAEF_HELPER_ENSURE_CATEGORY', true);

    /**
     * Ensures that a category exists below a parent object.
     *
     * @param int         $parentID Parent object ID.
     * @param string      $ident    Stable technical Ident.
     * @param string      $name     User-facing category name.
     * @param int|null    $position Optional object position.
     * @param string|null $icon     Optional object icon.
     * @param bool        $updateExistingPresentation Whether name, position and icon are managed after creation.
     *
     * @return int Category ID.
     *
     * @throws InvalidArgumentException On invalid configuration.
     * @throws RuntimeException On incompatible existing object.
     */
    function SAEF_EnsureCategory(
        int $parentID,
        string $ident,
        string $name,
        ?int $position = null,
        ?string $icon = null,
        bool $updateExistingPresentation = true
    ): int {
        SAEF_ValidateParentObject($parentID);
        SAEF_ValidateIdent($ident);
        SAEF_ValidateObjectName($name);

        $existingID = @IPS_GetObjectIDByIdent($ident, $parentID);

        $created = $existingID === false;
        if ($created) {
            $categoryID = IPS_CreateCategory();
            IPS_SetParent($categoryID, $parentID);
            IPS_SetIdent($categoryID, $ident);
        } else {
            $object = IPS_GetObject($existingID);

            if ($object['ObjectType'] !== 0) {
                throw new RuntimeException(sprintf(
                    'Object with Ident "%s" below parent %d exists but is not a category.',
                    $ident,
                    $parentID
                ));
            }

            $categoryID = $existingID;
        }

        if ($created || $updateExistingPresentation) {
            IPS_SetName($categoryID, $name);

            if ($position !== null) {
                IPS_SetPosition($categoryID, $position);
            }

            if ($icon !== null) {
                IPS_SetIcon($categoryID, $icon);
            }
        }

        return $categoryID;
    }
}
