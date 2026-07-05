<?php
declare(strict_types=1);

/**
 * SAEF Helper: Registry
 *
 * Stores small internal registry metadata as JSON in a script-owned string variable.
 *
 * Related SAEF artifacts:
 * - RS-001 Symcon Engineering Standards
 * - EK-004 Internal State Management
 * - EK-005 Idempotent Configuration
 */

require_once __DIR__ . '/../object/EnsureVariable.php';

if (!defined('SAEF_HELPER_REGISTRY')) {
    define('SAEF_HELPER_REGISTRY', true);

    /**
     * Ensures that a registry string variable exists below a parent object.
     *
     * The registry variable is script-owned internal state. Its value stores a
     * small JSON object with metadata required by the owning automation.
     *
     * @param int         $parentID Parent object ID.
     * @param string      $ident    Stable technical Ident.
     * @param string      $name     User-facing variable name.
     * @param int|null    $position Optional object position.
     * @param string|null $icon     Optional object icon.
     *
     * @return int Registry variable ID.
     */
    function SAEF_EnsureRegistryVariable(
        int $parentID,
        string $ident,
        string $name,
        ?int $position = null,
        ?string $icon = null
    ): int {
        return SAEF_EnsureVariable(
            $parentID,
            $ident,
            $name,
            3,
            '',
            null,
            $position,
            $icon
        );
    }

    /**
     * Reads a registry from a string variable.
     *
     * Empty values are treated as an empty registry. Invalid JSON fails loudly so
     * corrupted internal state is visible during engineering review and runtime.
     *
     * @param int $variableID Registry variable ID.
     *
     * @return array Registry data.
     *
     * @throws InvalidArgumentException If the variable does not exist.
     * @throws RuntimeException If the variable is not a string variable or contains invalid JSON.
     */
    function SAEF_ReadRegistry(int $variableID): array
    {
        SAEF_ValidateRegistryVariable($variableID);

        $value = GetValue($variableID);

        if ($value === null || $value === '') {
            return [];
        }

        if (!is_string($value)) {
            throw new RuntimeException('Registry variable does not contain a string value: ' . $variableID);
        }

        $trimmedValue = trim($value);

        if ($trimmedValue === '') {
            return [];
        }

        try {
            $registry = json_decode($trimmedValue, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Registry variable contains invalid JSON: ' . $variableID . ' (' . $exception->getMessage() . ')',
                0,
                $exception
            );
        }

        if (!is_array($registry)) {
            throw new RuntimeException('Registry JSON must decode to an array: ' . $variableID);
        }

        return $registry;
    }

    /**
     * Writes a registry array to a string variable as JSON.
     *
     * Registries must remain small and should contain metadata only. Discovery
     * payloads or large data sets must not be stored in registry variables.
     *
     * @param int   $variableID Registry variable ID.
     * @param array $registry   Registry data.
     *
     * @throws InvalidArgumentException If the variable does not exist.
     * @throws RuntimeException If the variable is incompatible.
     * @throws JsonException If the registry cannot be encoded as JSON.
     */
    function SAEF_WriteRegistry(int $variableID, array $registry): void
    {
        SAEF_ValidateRegistryVariable($variableID);

        $encodedRegistry = json_encode(
            $registry,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        SetValue($variableID, $encodedRegistry);
    }

    /**
     * Updates one registry entry and writes the registry back to the variable.
     *
     * @param int    $variableID Registry variable ID.
     * @param string $key        Registry key to update.
     * @param mixed  $value      Registry value.
     *
     * @return array Updated registry data.
     *
     * @throws InvalidArgumentException If the key is empty or the variable does not exist.
     * @throws RuntimeException If the registry variable is incompatible or contains invalid JSON.
     * @throws JsonException If the registry cannot be encoded as JSON.
     */
    function SAEF_UpdateRegistryEntry(int $variableID, string $key, mixed $value): array
    {
        if ($key === '') {
            throw new InvalidArgumentException('Registry key must not be empty.');
        }

        $registry = SAEF_ReadRegistry($variableID);
        $registry[$key] = $value;

        SAEF_WriteRegistry($variableID, $registry);

        return $registry;
    }

    /**
     * Validates that an object is a string variable usable as a registry.
     *
     * @param int $variableID Registry variable ID.
     */
    function SAEF_ValidateRegistryVariable(int $variableID): void
    {
        if ($variableID <= 0 || !IPS_VariableExists($variableID)) {
            throw new InvalidArgumentException('Registry variable does not exist: ' . $variableID);
        }

        $variable = IPS_GetVariable($variableID);

        if ($variable['VariableType'] !== 3) {
            throw new RuntimeException('Registry variable must be a string variable: ' . $variableID);
        }
    }
}
