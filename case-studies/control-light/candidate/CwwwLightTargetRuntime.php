<?php
declare(strict_types=1);

namespace SAEF\CaseStudy\ControlLight;

use InvalidArgumentException;
use RuntimeException;

require_once __DIR__ . '/CwwwLightAdapter.php';
require_once __DIR__ . '/../../../helpers/common/Validation.php';
require_once __DIR__ . '/../../../helpers/object/EnsureVariable.php';
require_once __DIR__ . '/../../../helpers/object/EnsureEvent.php';

/**
 * Candidate I/O boundary. Reconciliation is explicit, never part of dispatch.
 * All IDs refer to independently adopted resources, never facade variables.
 * ControlLight retains alarm policy and confirmation. Its wrapper must call
 * assertReady before run (including the already-confirmed shortcut).
 */
final class CwwwLightTargetRuntime
{
    private const TYPES = ['raw' => 3, 'command' => 3, 'online' => 0,
        'ready' => 0, 'state' => 0, 'brightness' => 1, 'colorTemperature' => 1];
    private const OWNED = ['ready' => 'available', 'state' => 'state',
        'brightness' => 'brightness', 'colorTemperature' => 'color_temp_kelvin'];

    /** @param array<string, int> $ids */
    public function __construct(
        private int $ownerScriptID,
        private array $ids,
        private CwwwLightAdapter $adapter
    ) {
        $this->validate();
    }

    /**
     * Explicit installation phase, after transport ownership has been verified.
     * Does not create/change MQTT instances or touch existing facade objects.
     * New receive events stay inactive until separately approved activation.
     * @param array<string, mixed> $external Unvalidated installation configuration.
     * @return array<string, int>
     */
    public static function reconcile(int $ownerScriptID, array $external): array
    {
        \SAEF_ValidateMutableObject($ownerScriptID, 3);
        if (count($external) !== 3) {
            throw new InvalidArgumentException('Invalid CWWW transport bindings.');
        }
        $ids = [];
        foreach (['raw' => 3, 'command' => 3, 'online' => 0] as $key => $type) {
            $id = $external[$key] ?? null;
            if (!is_int($id) || $id <= 0) {
                throw new InvalidArgumentException('Missing positive CWWW transport ID.');
            }
            \SAEF_ValidateMutableObject($id, 2);
            if (\IPS_GetVariable($id)['VariableType'] !== $type) {
                throw new RuntimeException('CWWW transport type mismatch.');
            }
            $ids[$key] = $id;
        }
        if (count(array_unique($ids)) !== 3) {
            throw new InvalidArgumentException('CWWW transport bindings must be distinct.');
        }
        $output = \IPS_GetVariable($ids['command']);
        if ($output['VariableCustomAction'] !== 0 || $output['VariableAction'] <= 0) {
            throw new RuntimeException('CWWW output action unavailable.');
        }
        // Preflight all occupied Idents before changing any existing resources.
        foreach (self::OWNED as $key => $ident) {
            $existing = @\IPS_GetObjectIDByIdent($ident, $ownerScriptID);
            if ($existing !== false) {
                \SAEF_ValidateMutableObject($existing, 2);
                $metadata = \IPS_GetVariable($existing);
                if (
                    $metadata['VariableType'] !== self::TYPES[$key] || $metadata['VariableAction'] !== 0
                    || $metadata['VariableCustomAction'] !== ($key === 'ready' ? 0 : $ownerScriptID)
                ) {
                    throw new RuntimeException('Occupied CWWW Ident is not owned: ' . $ident);
                }
            }
        }
        foreach (['raw' => 0, 'online' => 1] as $key => $triggerType) {
            $existing = @\IPS_GetObjectIDByIdent('EV_CWWW_' . strtoupper($key), $ownerScriptID);
            if ($existing !== false) {
                \SAEF_ValidateMutableObject($existing, 4);
                $event = \IPS_GetEvent($existing);
                if (
                    $event['EventType'] !== 0 || $event['EventActive'] !== false
                    || $event['TriggerType'] !== $triggerType || $event['TriggerVariableID'] !== $external[$key]
                    || $event['EventActionID'] !== SAEF_RUN_AUTOMATION_ACTION_GUID
                    || $event['EventActionParameters'] !== []
                ) {
                    throw new RuntimeException('CWWW event is active or has ownership/configuration drift.');
                }
            }
        }
        foreach (self::OWNED as $key => $ident) {
            $ids[$key] = \SAEF_EnsureVariable(
                $ownerScriptID,
                $ident,
                $ident,
                self::TYPES[$key],
                '',
                $key === 'ready' ? null : $ownerScriptID,
                null,
                null,
                false
            );
        }
        if (!\SetValue($ids['ready'], false)) {
            throw new RuntimeException('Unable to invalidate CWWW readiness.');
        }
        foreach (['raw' => 0, 'online' => 1] as $key => $triggerType) {
            \SAEF_EnsureTriggeredScriptEvent(
                $ownerScriptID,
                'EV_CWWW_' . strtoupper($key),
                'CWWW ' . $key,
                $ownerScriptID,
                $ids[$key],
                $triggerType,
                false,
                null,
                true,
                false
            );
        }
        return $ids;
    }

    /** @param array<string, mixed> $context Validated script dispatch, not an installer. */
    public function handle(array $context): void
    {
        $variableID = $context['VARIABLE'] ?? null;
        if (!is_int($variableID) || $variableID <= 0) {
            throw new InvalidArgumentException('Missing CWWW event/action variable.');
        }
        if (($context['SENDER'] ?? null) === 'Variable') {
            $this->receive($variableID);
        } elseif (($context['SENDER'] ?? null) === 'Action' && array_key_exists('VALUE', $context)) {
            $this->dispatch($variableID, $context['VALUE']);
        } else {
            throw new InvalidArgumentException('Unsupported CWWW invocation.');
        }
    }

    /** Only a raw update admits data; any online transition invalidates readiness. */
    public function receive(int $sourceVariableID): void
    {
        if (!in_array($sourceVariableID, [$this->ids['raw'], $this->ids['online']], true)) {
            throw new InvalidArgumentException('Unexpected CWWW receive source.');
        }
        $this->locked(function () use ($sourceVariableID): void {
            $this->write('ready', false);
            if ($sourceVariableID === $this->ids['online'] || \GetValue($this->ids['online']) !== true) {
                return;
            }
            // Read after locking; never replay an older event VALUE.
            $payload = \GetValue($this->ids['raw']);
            if (!is_string($payload)) {
                throw new RuntimeException('CWWW raw value is not a string.');
            }
            $feedback = $this->adapter->initialFeedback($payload);
            // Validate everything first. Publish power last; ready is the commit marker.
            foreach (['brightness', 'colorTemperature', 'state'] as $key) {
                $this->write($key, $feedback[$key]);
            }
            if (\GetValue($this->ids['raw']) !== $payload || \GetValue($this->ids['online']) !== true) {
                return; // Newer data is pending; never loop or claim the old packet current.
            }
            $this->write('ready', true);
        });
    }

    public function assertReady(): void
    {
        $this->locked(fn() => $this->requireReady());
    }

    /** No target/facade write, retry or wait on dispatch; only MQTT command output. */
    public function dispatch(int $targetVariableID, mixed $value): void
    {
        $capability = null;
        foreach (['state', 'brightness', 'colorTemperature'] as $key) {
            if ($this->ids[$key] === $targetVariableID) {
                $capability = $key;
                break;
            }
        }
        if ($capability === null) {
            throw new InvalidArgumentException('Unexpected CWWW action variable.');
        }
        $payload = json_encode($this->adapter->command($capability, $value), JSON_THROW_ON_ERROR);
        $this->locked(function () use ($payload): void {
            $this->requireReady();
            if (!\RequestAction($this->ids['command'], $payload)) {
                throw new RuntimeException('CWWW command transport rejected request.');
            }
        });
    }

    private function requireReady(): void
    {
        if (\GetValue($this->ids['online']) !== true || \GetValue($this->ids['ready']) !== true) {
            throw new RuntimeException('CWWW target offline or not initialized.');
        }
        $raw = \GetValue($this->ids['raw']);
        if (!is_string($raw)) {
            throw new RuntimeException('CWWW raw value is not a string.');
        }
        foreach ($this->adapter->initialFeedback($raw) as $key => $value) {
            if (\GetValue($this->ids[$key]) !== $value) {
                throw new RuntimeException('CWWW feedback processing is pending.');
            }
        }
    }

    private function write(string $key, bool|int $value): void
    {
        // Ownership/type validation also precedes each write, not only construction.
        $this->validate();
        if (!\SetValue($this->ids[$key], $value)) {
            throw new RuntimeException('CWWW feedback write failed: ' . $key);
        }
    }

    private function locked(callable $operation): void
    {
        $this->validate();
        $lock = 'SAEF_CWWW_TARGET_' . $this->ownerScriptID;
        if (!\IPS_SemaphoreEnter($lock, 1000)) {
            throw new RuntimeException('CWWW target lock timed out.');
        }
        try {
            $this->validate();
            $operation();
        } finally {
            if (!\IPS_SemaphoreLeave($lock)) {
                throw new RuntimeException('CWWW target lock release failed.');
            }
        }
    }

    private function validate(): void
    {
        \SAEF_ValidateMutableObject($this->ownerScriptID, 3);
        if (count($this->ids) !== count(self::TYPES) || count(array_unique($this->ids)) !== count(self::TYPES)) {
            throw new InvalidArgumentException('CWWW bindings must be complete and distinct.');
        }
        foreach (self::TYPES as $key => $type) {
            $id = $this->ids[$key] ?? null;
            if (!is_int($id) || $id <= 0 || $id === $this->ownerScriptID) {
                throw new InvalidArgumentException('Invalid CWWW binding: ' . $key);
            }
            \SAEF_ValidateMutableObject($id, 2);
            $variable = \IPS_GetVariable($id);
            if ($variable['VariableType'] !== $type) {
                throw new RuntimeException('CWWW binding type mismatch: ' . $key);
            }
            if (isset(self::OWNED[$key])) {
                $object = \IPS_GetObject($id);
                if ($object['ParentID'] !== $this->ownerScriptID || $object['ObjectIdent'] !== self::OWNED[$key]) {
                    throw new RuntimeException('CWWW target ownership mismatch: ' . $key);
                }
                if ($key !== 'ready' && $variable['VariableCustomAction'] !== $this->ownerScriptID) {
                    throw new RuntimeException('CWWW target action ownership mismatch.');
                }
                if ($variable['VariableAction'] !== 0 || ($key === 'ready' && $variable['VariableCustomAction'] !== 0)) {
                    throw new RuntimeException('CWWW feedback must be script-owned.');
                }
            }
            if ($key === 'command' && ($variable['VariableCustomAction'] !== 0 || $variable['VariableAction'] <= 0)) {
                throw new RuntimeException('CWWW output requires a native MQTT variable action.');
            }
        }
    }
}
