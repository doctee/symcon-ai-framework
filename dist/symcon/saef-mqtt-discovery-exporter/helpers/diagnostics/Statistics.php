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
     * @param bool  $updateExistingPresentation Whether names, positions and icons are managed after creation.
     *
     * @return array<string, int> Variable IDs indexed by Ident.
     *
     * @throws InvalidArgumentException On incomplete or invalid definitions.
     * @throws RuntimeException On incompatible existing objects or invalid profiles.
     */
    function SAEF_EnsureStatisticsVariables(
        int $parentID,
        array $definitions,
        bool $updateExistingPresentation = true
    ): array {
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
                $icon,
                $updateExistingPresentation
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
     * @throws InvalidArgumentException If the variable or increment is invalid.
     * @throws RuntimeException If the variable type, stored value or arithmetic result is invalid.
     */
    function SAEF_IncrementStatistic(int $variableID, int|float $increment = 1): int|float
    {
        if (is_float($increment) && !is_finite($increment)) {
            throw new InvalidArgumentException('Statistic increment must be finite: ' . $variableID);
        }

        $variableType = SAEF_GetStatisticVariableType($variableID);

        if (!in_array($variableType, [1, 2], true)) {
            throw new RuntimeException('Statistic variable must be integer or float: ' . $variableID);
        }

        $semaphoreName = 'SAEF_STATISTIC_' . $variableID;
        $semaphoreTimeoutMilliseconds = 1000;
        if (!IPS_SemaphoreEnter($semaphoreName, $semaphoreTimeoutMilliseconds)) {
            throw new RuntimeException('Statistic variable is busy: ' . $variableID);
        }

        try {
            if ($variableType === 1) {
                if (!is_int($currentValue = GetValue($variableID))) {
                    throw new RuntimeException('Integer statistic variable must contain an integer: ' . $variableID);
                }

                if (is_float($increment)) {
                    if (floor($increment) !== $increment) {
                        throw new InvalidArgumentException(
                            'Integer statistic increment must be a finite whole number: ' . $variableID
                        );
                    }

                    if ($increment < PHP_INT_MIN || $increment >= PHP_INT_MAX) {
                        throw new InvalidArgumentException('Integer statistic increment is out of range: ' . $variableID);
                    }

                    $increment = (int)$increment;
                }

                if (
                    ($increment > 0 && $currentValue > PHP_INT_MAX - $increment)
                    || ($increment < 0 && $currentValue < PHP_INT_MIN - $increment)
                ) {
                    throw new RuntimeException('Integer statistic increment would overflow: ' . $variableID);
                }

                $updatedValue = $currentValue + $increment;
                SetValue($variableID, $updatedValue);

                return $updatedValue;
            }

            $currentValue = GetValue($variableID);
            if (!is_int($currentValue) && !is_float($currentValue)) {
                throw new RuntimeException('Float statistic variable must contain a numeric value: ' . $variableID);
            }

            $updatedValue = (float)$currentValue + (float)$increment;
            if (!is_finite($updatedValue)) {
                throw new RuntimeException('Float statistic increment must produce a finite value: ' . $variableID);
            }

            SetValue($variableID, $updatedValue);

            return $updatedValue;
        } finally {
            IPS_SemaphoreLeave($semaphoreName);
        }
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
     * @internal Compatibility implementation detail; use the public statistics APIs.
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
