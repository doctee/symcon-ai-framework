<?php
declare(strict_types=1);

/**
 * SAEF Helper: WaitForVariable
 *
 * Waits for an IP-Symcon variable to change or update.
 *
 * Purpose:
 * - wait for asynchronous device/module feedback,
 * - distinguish value changes from updates,
 * - support short lookback windows for events that already happened,
 * - optionally validate the resulting value.
 *
 * Related SAEF artifacts:
 * - RS-001 Symcon Engineering Standards
 * - EK-002 Retry Mechanisms
 * - EK-004 Internal State Management
 */

if (!defined('SAEF_HELPER_WAIT_FOR_VARIABLE')) {
    define('SAEF_HELPER_WAIT_FOR_VARIABLE', true);

    /**
     * Wait mode: wait for a real value change.
     *
     * Uses IPS variable metadata field `VariableChanged`.
     */
    const SAEF_WAIT_CHANGED = 1;

    /**
     * Wait mode: wait for any variable update, including updates with unchanged value.
     *
     * Uses IPS variable metadata field `VariableUpdated`.
     */
    const SAEF_WAIT_UPDATED = 2;

    /**
     * Waits for a variable to change or update.
     *
     * @param int           $variableID    Variable ID to observe.
     * @param int           $timeoutMs     Maximum wait time in milliseconds.
     * @param int           $intervalMs    Polling interval in milliseconds.
     * @param mixed|null    $expectedValue Optional exact value check using strict comparison.
     * @param int           $mode          SAEF_WAIT_CHANGED or SAEF_WAIT_UPDATED.
     * @param int           $lookbackMs    Lookback window in milliseconds.
     * @param callable|null $predicate     Optional predicate receiving current value and variable metadata.
     *
     * @return bool True if the wait condition was met, otherwise false.
     *
     * @throws InvalidArgumentException On invalid parameters.
     */
    function SAEF_WaitForVariable(
        int $variableID,
        int $timeoutMs = 5000,
        int $intervalMs = 100,
        mixed $expectedValue = null,
        int $mode = SAEF_WAIT_CHANGED,
        int $lookbackMs = 0,
        ?callable $predicate = null
    ): bool {
        SAEF_ValidateWaitForVariableArguments(
            $variableID,
            $timeoutMs,
            $intervalMs,
            $mode,
            $lookbackMs
        );

        $metadataKey = SAEF_GetWaitMetadataKey($mode);
        $info = IPS_GetVariable($variableID);

        if ($lookbackMs > 0 && SAEF_WaitLookbackMatches(
            $variableID,
            $info,
            $metadataKey,
            $lookbackMs,
            $expectedValue,
            $predicate
        )) {
            return true;
        }

        $startTimestamp = $info[$metadataKey];
        $elapsedMs = 0;

        while ($elapsedMs < $timeoutMs) {
            IPS_Sleep($intervalMs);
            $elapsedMs += $intervalMs;

            $info = IPS_GetVariable($variableID);
            $currentTimestamp = $info[$metadataKey];

            if ($currentTimestamp > $startTimestamp) {
                if (SAEF_WaitValueMatches($variableID, $info, $expectedValue, $predicate)) {
                    return true;
                }

                /*
                 * An event was detected but the value condition did not match.
                 * Continue waiting for the next change/update.
                 */
                $startTimestamp = $currentTimestamp;
            }
        }

        return false;
    }

    /**
     * Validates WaitForVariable parameters.
     *
     * @throws InvalidArgumentException
     */
    function SAEF_ValidateWaitForVariableArguments(
        int $variableID,
        int $timeoutMs,
        int $intervalMs,
        int $mode,
        int $lookbackMs
    ): void {
        if ($variableID <= 0 || !IPS_VariableExists($variableID)) {
            throw new InvalidArgumentException('Variable does not exist: ' . $variableID);
        }

        if ($timeoutMs < 0) {
            throw new InvalidArgumentException('timeoutMs must not be negative.');
        }

        if ($intervalMs <= 0) {
            throw new InvalidArgumentException('intervalMs must be greater than zero.');
        }

        if (!in_array($mode, [SAEF_WAIT_CHANGED, SAEF_WAIT_UPDATED], true)) {
            throw new InvalidArgumentException('Invalid wait mode: ' . $mode);
        }

        if ($lookbackMs < 0) {
            throw new InvalidArgumentException('lookbackMs must not be negative.');
        }
    }

    /**
     * Returns the IP-Symcon variable metadata key for the selected wait mode.
     */
    function SAEF_GetWaitMetadataKey(int $mode): string
    {
        return match ($mode) {
            SAEF_WAIT_CHANGED => 'VariableChanged',
            SAEF_WAIT_UPDATED => 'VariableUpdated',
            default => throw new InvalidArgumentException('Invalid wait mode: ' . $mode),
        };
    }

    /**
     * Checks whether the lookback window already satisfies the wait condition.
     */
    function SAEF_WaitLookbackMatches(
        int $variableID,
        array $info,
        string $metadataKey,
        int $lookbackMs,
        mixed $expectedValue,
        ?callable $predicate
    ): bool {
        /*
         * IP-Symcon timestamps are second-based. Add one second tolerance so that
         * sub-second lookback windows still work reliably.
         */
        $lookbackSeconds = max(1, (int)ceil($lookbackMs / 1000));
        $threshold = time() - $lookbackSeconds - 1;

        if (($info[$metadataKey] ?? 0) < $threshold) {
            return false;
        }

        return SAEF_WaitValueMatches($variableID, $info, $expectedValue, $predicate);
    }

    /**
     * Checks the current variable value against optional exact value and predicate.
     */
    function SAEF_WaitValueMatches(
        int $variableID,
        array $info,
        mixed $expectedValue,
        ?callable $predicate
    ): bool {
        $value = GetValue($variableID);

        if ($expectedValue !== null && $value !== $expectedValue) {
            return false;
        }

        if ($predicate !== null) {
            return (bool)$predicate($value, $info);
        }

        return true;
    }
}
