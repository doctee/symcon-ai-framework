<?php
declare(strict_types=1);

/**
 * SAEF Helper: EnsureDummy
 *
 * Idempotently creates or updates an IP-Symcon Dummy Module instance.
 */

require_once __DIR__ . '/EnsureInstance.php';

if (!defined('SAEF_HELPER_ENSURE_DUMMY')) {
    define('SAEF_HELPER_ENSURE_DUMMY', true);

    /**
     * IP-Symcon Dummy Module GUID.
     */
    const SAEF_DUMMY_MODULE_GUID = '{485D0419-BE97-4548-AA9C-C083EB82E61E}';

    function SAEF_EnsureDummy(
        int $parentID,
        string $ident,
        string $name,
        ?int $position = null,
        ?string $icon = null,
        ?bool $hidden = null
    ): int {
        return SAEF_EnsureInstance(
            $parentID,
            $ident,
            $name,
            SAEF_DUMMY_MODULE_GUID,
            $position,
            $icon,
            $hidden
        );
    }
}
