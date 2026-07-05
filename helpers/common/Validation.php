<?php
declare(strict_types=1);

/**
 * SAEF Common Helper: Validation
 *
 * Shared validation functions for SAEF helper files.
 *
 * These functions are intentionally small and strict. They validate configuration
 * before helper functions modify IP-Symcon object state.
 */

if (!defined('SAEF_HELPER_VALIDATION')) {
    define('SAEF_HELPER_VALIDATION', true);

    /**
     * Validates that an object exists and can be used as parent.
     *
     * @throws InvalidArgumentException
     */
    function SAEF_ValidateParentObject(int $parentID): void
    {
        if ($parentID <= 0 || !IPS_ObjectExists($parentID)) {
            throw new InvalidArgumentException('Parent object does not exist: ' . $parentID);
        }
    }

    /**
     * Validates an IP-Symcon Ident used by SAEF helpers.
     *
     * SAEF requires explicit Idents instead of silently deriving them from names.
     *
     * @throws InvalidArgumentException
     */
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

    /**
     * Validates an IP-Symcon variable type.
     *
     * @throws InvalidArgumentException
     */
    function SAEF_ValidateVariableType(int $type): void
    {
        if (!in_array($type, [0, 1, 2, 3], true)) {
            throw new InvalidArgumentException('Invalid variable type: ' . $type);
        }
    }

    /**
     * Validates that a user-facing object name is not empty.
     *
     * @throws InvalidArgumentException
     */
    function SAEF_ValidateObjectName(string $name): void
    {
        if ($name === '') {
            throw new InvalidArgumentException('Object name must not be empty.');
        }
    }
}
