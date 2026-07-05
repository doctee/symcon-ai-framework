<?php
declare(strict_types=1);

/**
 * SAEF Helper: Statistics
 *
 * Provides small script-owned diagnostic statistics variables.
 *
 * Related SAEF artifacts:
 * - RS-001 Symcon Engineering Standards
 * - EK-004 Internal State Management
 */

require_once __DIR__ . '/../object/EnsureVariable.php';

if (!defined('SAEF_HELPER_STATISTICS')) {
    define('SAEF_HELPER_STATISTICS', true);

    /**
     * Ensures statistic variables below a parent object.
     *
     * Object creation and type compatibility checks are delegated to
     * SAEF_EnsureVariable(). Existing type conflicts are not repaired silently.
     *
     * Expected definition shape:
     *
     * [
     *     'ident' => 'EXECUTIONS',
     *     'name' => 'Executions',
     *     'type' => 1,
     *     'profile' => '',
     *     'position' => 10,
     *     'icon' => null,
     * ]
     *
     * @param int   $parentID    Parent object ID.
     * @param array $definitions Statistic variable definitions.
     *
     * @return array<string, int> Variable IDs indexed by Ident.
     *
     * @throws InvalidArgumentException On incomplete or invalid definitions.
     * @throws RuntimeException On incompatible existing objects or invalid profiles.
     */
    function SAEF_EnsureStatisticsVariables(int $parentID, array $definitions): array
    {
        $variableIDs = [];

        foreach ($definitions as $definition) {
            if (!is_array($definition)) {
                throw new InvalidArgumentException('Statistic definition must be an array.');
            }

            foreach (['ident', 'name', 'type'] as $requiredKey) {
                if (!array_key_exists($requiredKey, $definition)) {
                    throw new InvalidArgumentException('Statistic definition is missing key: ' . $requiredKey);
                }
            }

            $ident = $definition['ident'];
            $name = $definition['name'];
            $type = $definition['type'];

            if (!is_string($ident) || !is_string($name) || !is_int($type)) {
                throw new InvalidArgumentException('Statistic definition ident and name must be strings; type must be integer.');
            }

            $profile = $definition['profile'] ?? '';
            $position = $definition['position'] ?? null;
            $icon = $definition['icon'] ?? null;

            if (!is_string($profile)) {
                throw new InvalidArgumentException('Statistic definition profile must be a string.');
            }

            if ($position !== null && !is_int($position)) {
                throw new InvalidArgumentException('Statistic definition position must be an integer or null.');
            }

            if ($icon !== null && !is_string($icon)) {
                throw new InvalidArgumentException('Statistic definition icon must be a string or null.');
            }

            $variableIDs[$ident] = SAEF_EnsureVariable(
                $parentID,
                $ident,
                $name,
                $type,
                $profile,
                null,
                $position,
                $icon
            );
        }

        return $variableIDs;
    }

    /**
     * Increments an integer or float statistic variable.
     *
     * Only the provided variable is changed. Boolean and string variables are
     * rejected because they do not have counter semantics.
     *
     * @param int       $variableID Statistic variable ID.
     * @param int|float $increment  Increment value.
     *
     * @return int|float Updated statistic value.
     *
     * @throws InvalidArgumentException If the variable does not exist.
     * @throws RuntimeException If the variable type is not integer or float.
     */
    function SAEF_IncrementStatistic(int $variableID, int|float $increment = 1): int|float
    {
        $variableType = SAEF_GetStatisticVariableType($variableID);

        if (!in_array($variableType, [1, 2], true)) {
            throw new RuntimeException('Statistic variable must be integer or float: ' . $variableID);
        }

        $currentValue = GetValue($variableID);

        if (!is_int($currentValue) && !is_float($currentValue)) {
            throw new RuntimeException('Statistic variable value must be numeric: ' . $variableID);
        }

        $updatedValue = $currentValue + $increment;

        if ($variableType === 1) {
            $updatedValue = (int)$updatedValue;
        }

        SetValue($variableID, $updatedValue);

        return $updatedValue;
    }

    /**
     * Sets an integer timestamp statistic variable.
     *
     * If no timestamp is provided, the current Unix timestamp is used.
     *
     * @param int      $variableID Statistic timestamp variable ID.
     * @param int|null $timestamp  Unix timestamp.
     *
     * @throws InvalidArgumentException If the variable does not exist.
     * @throws RuntimeException If the variable type is not integer.
     */
    function SAEF_SetStatisticTimestamp(int $variableID, ?int $timestamp = null): void
    {
        $variableType = SAEF_GetStatisticVariableType($variableID);

        if ($variableType !== 1) {
            throw new RuntimeException('Statistic timestamp variable must be integer: ' . $variableID);
        }

        SetValue($variableID, $timestamp ?? time());
    }

    /**
     * Returns the Symcon variable type for a statistic variable.
     *
     * @param int $variableID Statistic variable ID.
     *
     * @return int Variable type.
     */
    function SAEF_GetStatisticVariableType(int $variableID): int
    {
        if ($variableID <= 0 || !IPS_VariableExists($variableID)) {
            throw new InvalidArgumentException('Statistic variable does not exist: ' . $variableID);
        }

        $variable = IPS_GetVariable($variableID);

        return $variable['VariableType'];
    }
}
