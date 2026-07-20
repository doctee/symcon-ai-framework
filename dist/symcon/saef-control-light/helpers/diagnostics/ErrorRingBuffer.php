<?php
declare(strict_types=1);

/**
 * SAEF Helper: ErrorRingBuffer
 *
 * Stores a bounded script-owned error history as JSON in a string variable.
 *
 * Related SAEF artifacts:
 * - RS-001 Symcon Engineering Standards
 * - EK-004 Internal State Management
 */

require_once __DIR__ . '/../object/EnsureVariable.php';

if (!defined('SAEF_HELPER_ERROR_RING_BUFFER')) {
    define('SAEF_HELPER_ERROR_RING_BUFFER', true);

    /**
     * Maximum number of error entries kept in one ring buffer.
     */
    if (!defined('SAEF_ERROR_RING_BUFFER_MAX_CAPACITY')) {
        define('SAEF_ERROR_RING_BUFFER_MAX_CAPACITY', 100);
    }

    /**
     * Ensures that an error ring buffer string variable exists below a parent object.
     *
     * Object creation and type compatibility checks are delegated to
     * SAEF_EnsureVariable(). The variable stores a bounded JSON array of small
     * error metadata entries. Discovery payloads or large data sets must not be
     * stored in error ring buffers.
     *
     * @param int         $parentID Parent object ID.
     * @param string      $ident    Stable technical Ident.
     * @param string      $name     User-facing variable name.
     * @param int|null    $position Optional object position.
     * @param string|null $icon     Optional object icon.
     * @param bool        $updateExistingPresentation Whether name, position and icon are managed after creation.
     *
     * @return int Error ring buffer variable ID.
     */
    function SAEF_EnsureErrorRingBufferVariable(
        int $parentID,
        string $ident,
        string $name,
        ?int $position = null,
        ?string $icon = null,
        bool $updateExistingPresentation = true
    ): int {
        return SAEF_EnsureVariable(
            $parentID,
            $ident,
            $name,
            3,
            '',
            null,
            $position,
            $icon,
            $updateExistingPresentation
        );
    }

    /**
     * Reads an error ring buffer from a string variable.
     *
     * Empty values are treated as an empty error history. Invalid JSON fails
     * loudly so corrupted diagnostic state does not go unnoticed.
     *
     * @param int $variableID Error ring buffer variable ID.
     *
     * @return array<int, array<string, mixed>> Error entries.
     *
     * @throws InvalidArgumentException If the variable does not exist.
     * @throws RuntimeException If the variable or stored bounded-entry structure is incompatible.
     */
    function SAEF_ReadErrorRingBuffer(int $variableID): array
    {
        SAEF_ValidateErrorRingBufferVariable($variableID);

        $value = GetValue($variableID);

        if ($value === null || $value === '') {
            return [];
        }

        if (!is_string($value)) {
            throw new RuntimeException('Error ring buffer variable does not contain a string value: ' . $variableID);
        }

        $trimmedValue = trim($value);

        if ($trimmedValue === '') {
            return [];
        }

        try {
            $entries = json_decode($trimmedValue, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Error ring buffer variable contains invalid JSON: ' . $variableID . ' (' . $exception->getMessage() . ')',
                0,
                $exception
            );
        }

        if (!is_array($entries)) {
            throw new RuntimeException('Error ring buffer JSON must decode to an array: ' . $variableID);
        }

        if (!array_is_list($entries)) {
            throw new RuntimeException('Error ring buffer JSON must be a list: ' . $variableID);
        }

        if (count($entries) > SAEF_ERROR_RING_BUFFER_MAX_CAPACITY) {
            throw new RuntimeException(
                'Error ring buffer exceeds maximum capacity: ' . $variableID
            );
        }

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                throw new RuntimeException('Error ring buffer entries must be arrays: ' . $variableID);
            }

            if (
                !isset($entry['timestamp'], $entry['message'], $entry['context'])
                || !is_int($entry['timestamp'])
                || !is_string($entry['message'])
                || $entry['message'] === ''
                || !is_array($entry['context'])
            ) {
                throw new RuntimeException('Error ring buffer entry has an invalid structure: ' . $variableID);
            }
        }

        return $entries;
    }

    /**
     * Appends an error entry and trims the buffer to the configured capacity.
     *
     * The newest entry is appended at the end of the returned array. Context is
     * intended for small diagnostic metadata only.
     *
     * @param int    $variableID Error ring buffer variable ID.
     * @param string $message    Error message.
     * @param int    $capacity   Maximum number of entries to keep.
     * @param array  $context    Small diagnostic context metadata.
     *
     * @return array<int, array<string, mixed>> Updated error entries.
     *
     * @throws InvalidArgumentException If message or capacity is invalid.
     * @throws RuntimeException If the variable is incompatible or contains invalid JSON.
     * @throws JsonException If the buffer cannot be encoded as JSON.
     */
    function SAEF_AppendErrorRingBufferEntry(
        int $variableID,
        string $message,
        int $capacity = 20,
        array $context = []
    ): array {
        if ($message === '') {
            throw new InvalidArgumentException('Error ring buffer message must not be empty.');
        }

        SAEF_ValidateErrorRingBufferCapacity($capacity);

        $entries = SAEF_ReadErrorRingBuffer($variableID);
        $entries[] = [
            'timestamp' => time(),
            'message' => $message,
            'context' => $context,
        ];

        $entries = array_slice($entries, -$capacity);

        $encodedEntries = json_encode(
            $entries,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        SetValue($variableID, $encodedEntries);

        return $entries;
    }

    /**
     * Clears an error ring buffer.
     *
     * @param int $variableID Error ring buffer variable ID.
     *
     * @throws InvalidArgumentException If the variable does not exist.
     * @throws RuntimeException If the variable is incompatible.
     */
    function SAEF_ClearErrorRingBuffer(int $variableID): void
    {
        SAEF_ValidateErrorRingBufferVariable($variableID);

        SetValue($variableID, '[]');
    }

    /**
     * Validates that an object is a string variable usable as an error ring buffer.
     *
     * @internal Compatibility implementation detail; use the public ring buffer APIs.
     *
     * @param int $variableID Error ring buffer variable ID.
     */
    function SAEF_ValidateErrorRingBufferVariable(int $variableID): void
    {
        if ($variableID <= 0 || !IPS_VariableExists($variableID)) {
            throw new InvalidArgumentException('Error ring buffer variable does not exist: ' . $variableID);
        }

        $variable = IPS_GetVariable($variableID);

        if ($variable['VariableType'] !== 3) {
            throw new RuntimeException('Error ring buffer variable must be a string variable: ' . $variableID);
        }
    }

    /**
     * Validates ring buffer capacity.
     *
     * @internal Compatibility implementation detail; use the public ring buffer APIs.
     *
     * @param int $capacity Maximum number of entries.
     */
    function SAEF_ValidateErrorRingBufferCapacity(int $capacity): void
    {
        if ($capacity <= 0) {
            throw new InvalidArgumentException('Error ring buffer capacity must be greater than zero.');
        }

        if ($capacity > SAEF_ERROR_RING_BUFFER_MAX_CAPACITY) {
            throw new InvalidArgumentException(
                'Error ring buffer capacity must not exceed ' . (string)SAEF_ERROR_RING_BUFFER_MAX_CAPACITY . '.'
            );
        }
    }
}
