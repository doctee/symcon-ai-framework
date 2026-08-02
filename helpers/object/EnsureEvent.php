<?php
declare(strict_types=1);

/**
 * SAEF Helper: EnsureEvent
 *
 * Idempotently creates or updates event objects below a known parent object.
 *
 * Supported scope:
 * - cyclic script events;
 * - variable-triggered script events for updates and changes.
 *
 * Related SAEF artifacts:
 * - RS-001 Symcon Engineering Standards
 * - EK-005 Idempotent Configuration
 * - RI-001 Idempotent Configuration Script
 */

require_once __DIR__ . '/../common/Validation.php';

if (!defined('SAEF_HELPER_ENSURE_EVENT')) {
    define('SAEF_HELPER_ENSURE_EVENT', true);

    /**
     * IP-Symcon "Run Automation" action GUID.
     *
     * Required for automatically created script-executing events in IP-Symcon 6.0+.
     */
    if (!defined('SAEF_RUN_AUTOMATION_ACTION_GUID')) {
        define('SAEF_RUN_AUTOMATION_ACTION_GUID', '{7938A5A2-0981-5FE0-BE6C-8AA610D654EB}');
    }

    /**
     * Ensures that a cyclic event exists and executes a target script.
     *
     * The event is identified by Ident below the given parent object.
     * Existing compatible events are updated, not recreated.
     *
     * @param int         $parentID        Parent object ID for the event.
     * @param string      $ident           Stable technical Ident.
     * @param string      $name            User-facing event name.
     * @param int         $targetScriptID  Script ID executed by the event.
     * @param int         $intervalSeconds Execution interval in seconds.
     * @param bool        $active          Whether the event should be active.
     * @param int|null    $position        Optional object position.
     * @param bool|null   $hidden          Optional hidden flag.
     * @param bool        $updateExistingPresentation Whether name, position and visibility are managed after creation.
     *
     * @return int Event ID.
     *
     * @throws InvalidArgumentException On invalid configuration.
     * @throws RuntimeException On incompatible existing object or event type.
     */
    function SAEF_EnsureCyclicScriptEvent(
        int $parentID,
        string $ident,
        string $name,
        int $targetScriptID,
        int $intervalSeconds,
        bool $active = false,
        ?int $position = null,
        ?bool $hidden = true,
        bool $updateExistingPresentation = true
    ): int {
        SAEF_ValidateParentObject($parentID);
        SAEF_ValidateIdent($ident);
        SAEF_ValidateObjectName($name);
        if ($targetScriptID <= 0 || !IPS_ScriptExists($targetScriptID)) {
            throw new InvalidArgumentException('Target script does not exist: ' . $targetScriptID);
        }

        if ($parentID !== $targetScriptID) {
            throw new InvalidArgumentException(sprintf(
                'Script event parent %d must equal target script %d.',
                $parentID,
                $targetScriptID
            ));
        }

        if ($intervalSeconds <= 0) {
            throw new InvalidArgumentException('intervalSeconds must be greater than zero.');
        }

        $existingID = @IPS_GetObjectIDByIdent($ident, $parentID);

        $created = $existingID === false;
        if ($created) {
            $eventID = IPS_CreateEvent(1); // 1 = cyclic event
            SAEF_ValidateMutableObject($eventID, 4);
            IPS_SetParent($eventID, $parentID);
            IPS_SetIdent($eventID, $ident);
        } else {
            $object = IPS_GetObject($existingID);

            if ($object['ObjectType'] !== 4) {
                throw new RuntimeException(sprintf(
                    'Object with Ident "%s" below parent %d exists but is not an event.',
                    $ident,
                    $parentID
                ));
            }

            $eventID = $existingID;
            $event = IPS_GetEvent($eventID);

            if ($event['EventType'] !== 1) {
                throw new RuntimeException(sprintf(
                    'Event "%s" has type %d, expected cyclic event type 1.',
                    $ident,
                    $event['EventType']
                ));
            }
        }

        SAEF_ValidateMutableObject($eventID, 4);
        if ($created || $updateExistingPresentation) {
            IPS_SetName($eventID, $name);

            if ($position !== null) {
                IPS_SetPosition($eventID, $position);
            }

            if ($hidden !== null) {
                IPS_SetHidden($eventID, $hidden);
            }
        }

        /*
         * Cyclic event:
         * - DateType 0: no date restriction
         * - TimeType 1: interval in seconds
         */
        IPS_SetEventCyclic($eventID, 0, 0, 0, 0, 1, $intervalSeconds);

        /*
         * Execute the parent script through the Run Automation action.
         * This is required for generated script events on IP-Symcon 6.0+.
         *
         * IPS_SetEventScript() is intentionally not used here. That function
         * expects PHP source text and changes the event action to "Execute PHP
         * Code"; it does not assign a target script ID.
         */
        IPS_SetEventAction($eventID, SAEF_RUN_AUTOMATION_ACTION_GUID, []);

        IPS_SetEventActive($eventID, $active);

        return $eventID;
    }

    /**
     * Ensures that a variable-triggered event exists and executes its parent script.
     *
     * Initial trigger scope is deliberately limited to variable updates and
     * variable changes. Threshold and value triggers require an additional
     * trigger-value contract and are outside this helper version.
     *
     * @param int         $parentID         Parent script ID for the event.
     * @param string      $ident            Stable technical Ident.
     * @param string      $name             User-facing event name.
     * @param int         $targetScriptID   Script executed by the event. Must equal parentID.
     * @param int         $triggerVariableID Variable observed by the event.
     * @param int         $triggerType      Trigger type: 0 update, 1 change.
     * @param bool        $active           Whether the event should be active.
     * @param int|null    $position         Optional object position.
     * @param bool|null   $hidden           Optional hidden flag.
     * @param bool        $updateExistingPresentation Whether name, position and visibility are managed after creation.
     *
     * @return int Event ID.
     *
     * @throws InvalidArgumentException On invalid configuration.
     * @throws RuntimeException On incompatible existing object or event type.
     */
    function SAEF_EnsureTriggeredScriptEvent(
        int $parentID,
        string $ident,
        string $name,
        int $targetScriptID,
        int $triggerVariableID,
        int $triggerType,
        bool $active = false,
        ?int $position = null,
        ?bool $hidden = true,
        bool $updateExistingPresentation = true
    ): int {
        SAEF_ValidateParentObject($parentID);
        SAEF_ValidateIdent($ident);
        SAEF_ValidateObjectName($name);
        if ($targetScriptID <= 0 || !IPS_ScriptExists($targetScriptID)) {
            throw new InvalidArgumentException('Target script does not exist: ' . $targetScriptID);
        }

        if ($parentID !== $targetScriptID) {
            throw new InvalidArgumentException(sprintf(
                'Script event parent %d must equal target script %d.',
                $parentID,
                $targetScriptID
            ));
        }

        if ($triggerVariableID <= 0 || !IPS_VariableExists($triggerVariableID)) {
            throw new InvalidArgumentException('Trigger variable does not exist: ' . $triggerVariableID);
        }

        if (!in_array($triggerType, [0, 1], true)) {
            throw new InvalidArgumentException(
                'Unsupported trigger type: ' . $triggerType . '. Expected 0 (update) or 1 (change).'
            );
        }

        /*
         * A missing Ident is an expected branch during idempotent creation.
         * IP-Symcon reports that absence as false plus a warning, so the narrow
         * lookup suppression follows the exception documented in PHP_STANDARDS.
         */
        $existingID = @IPS_GetObjectIDByIdent($ident, $parentID);

        $created = $existingID === false;
        if ($created) {
            $eventID = IPS_CreateEvent(0); // 0 = triggered event
            SAEF_ValidateMutableObject($eventID, 4);
            IPS_SetParent($eventID, $parentID);
            IPS_SetIdent($eventID, $ident);
        } else {
            $object = IPS_GetObject($existingID);

            if ($object['ObjectType'] !== 4) {
                throw new RuntimeException(sprintf(
                    'Object with Ident "%s" below parent %d exists but is not an event.',
                    $ident,
                    $parentID
                ));
            }

            $eventID = $existingID;
            $event = IPS_GetEvent($eventID);

            if ($event['EventType'] !== 0) {
                throw new RuntimeException(sprintf(
                    'Event "%s" has type %d, expected triggered event type 0.',
                    $ident,
                    $event['EventType']
                ));
            }
        }

        SAEF_ValidateMutableObject($eventID, 4);
        if ($created || $updateExistingPresentation) {
            IPS_SetName($eventID, $name);

            if ($position !== null) {
                IPS_SetPosition($eventID, $position);
            }

            if ($hidden !== null) {
                IPS_SetHidden($eventID, $hidden);
            }
        }

        IPS_SetEventTrigger($eventID, $triggerType, $triggerVariableID);
        IPS_SetEventAction($eventID, SAEF_RUN_AUTOMATION_ACTION_GUID, []);
        IPS_SetEventActive($eventID, $active);

        return $eventID;
    }
}
