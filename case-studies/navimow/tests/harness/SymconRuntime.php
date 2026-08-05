<?php

declare(strict_types=1);

if (!defined('IS_CREATING')) {
    define('IS_CREATING', 101);
}

if (!defined('IS_ACTIVE')) {
    define('IS_ACTIVE', 102);
}

if (!class_exists('IPSModule')) {
    class IPSModule
    {
        public int $InstanceID;

        private array $properties = [];
        private array $attributes = [];
        private array $variables = [];
        private array $timers = [];
        private array $registeredMessages = [];
        private array $debugEntries = [];
        private array $childMessages = [];
        private ?Closure $parentHandler = null;
        private int $status = IS_CREATING;

        public function __construct(int $InstanceID = 1)
        {
            $this->InstanceID = $InstanceID;
        }

        public function Create()
        {
        }

        public function ApplyChanges()
        {
            $this->status = IS_CREATING;
        }

        protected function SetStatus(int $status): void
        {
            $this->status = $status;
        }

        protected function RegisterPropertyString(string $ident, string $default): void
        {
            $this->properties[$ident] ??= $default;
        }

        protected function RegisterPropertyInteger(string $ident, int $default): void
        {
            $this->properties[$ident] ??= $default;
        }

        protected function RegisterPropertyBoolean(string $ident, bool $default): void
        {
            $this->properties[$ident] ??= $default;
        }

        protected function ReadPropertyString(string $ident): string
        {
            return (string) $this->requireEntry($this->properties, $ident, 'property');
        }

        protected function ReadPropertyInteger(string $ident): int
        {
            return (int) $this->requireEntry($this->properties, $ident, 'property');
        }

        protected function ReadPropertyBoolean(string $ident): bool
        {
            return (bool) $this->requireEntry($this->properties, $ident, 'property');
        }

        protected function RegisterAttributeString(string $ident, string $default): void
        {
            $this->attributes[$ident] ??= $default;
        }

        protected function RegisterAttributeInteger(string $ident, int $default): void
        {
            $this->attributes[$ident] ??= $default;
        }

        protected function RegisterAttributeBoolean(string $ident, bool $default): void
        {
            $this->attributes[$ident] ??= $default;
        }

        protected function ReadAttributeString(string $ident): string
        {
            return (string) $this->requireEntry($this->attributes, $ident, 'attribute');
        }

        protected function ReadAttributeInteger(string $ident): int
        {
            return (int) $this->requireEntry($this->attributes, $ident, 'attribute');
        }

        protected function ReadAttributeBoolean(string $ident): bool
        {
            return (bool) $this->requireEntry($this->attributes, $ident, 'attribute');
        }

        protected function WriteAttributeString(string $ident, string $value): void
        {
            $this->requireEntry($this->attributes, $ident, 'attribute');
            $this->attributes[$ident] = $value;
        }

        protected function WriteAttributeInteger(string $ident, int $value): void
        {
            $this->requireEntry($this->attributes, $ident, 'attribute');
            $this->attributes[$ident] = $value;
        }

        protected function WriteAttributeBoolean(string $ident, bool $value): void
        {
            $this->requireEntry($this->attributes, $ident, 'attribute');
            $this->attributes[$ident] = $value;
        }

        protected function RegisterVariableString(
            string $ident,
            string $name,
            string $profile,
            int $position
        ): void {
            $this->variables[$ident] ??= '';
        }

        protected function RegisterVariableInteger(
            string $ident,
            string $name,
            string $profile,
            int $position
        ): void {
            $this->variables[$ident] ??= 0;
        }

        protected function RegisterVariableBoolean(
            string $ident,
            string $name,
            string $profile,
            int $position
        ): void {
            $this->variables[$ident] ??= false;
        }

        protected function SetValue(string $ident, mixed $value): void
        {
            $this->requireEntry($this->variables, $ident, 'variable');
            $this->variables[$ident] = $value;
        }

        protected function GetValue(string $ident): mixed
        {
            return $this->requireEntry($this->variables, $ident, 'variable');
        }

        protected function GetIDForIdent(string $ident): int
        {
            $keys = array_keys($this->variables);
            $position = array_search($ident, $keys, true);
            if ($position === false) {
                throw new RuntimeException('Unknown variable ident.');
            }

            return 1000 + $position;
        }

        protected function RegisterTimer(
            string $ident,
            int $interval,
            string $script
        ): void {
            $this->timers[$ident] ??= [
                'interval' => $interval,
                'script' => $script,
            ];
        }

        protected function SetTimerInterval(string $ident, int $interval): void
        {
            $timer = $this->requireEntry($this->timers, $ident, 'timer');
            $timer['interval'] = $interval;
            $this->timers[$ident] = $timer;
        }

        protected function RegisterMessage(int $senderId, int $messageId): bool
        {
            $key = $senderId . ':' . $messageId;
            $this->registeredMessages[$key] = [
                'senderId' => $senderId,
                'messageId' => $messageId,
            ];

            return true;
        }

        protected function UnregisterMessage(
            int $senderId,
            int $messageId
        ): bool {
            unset($this->registeredMessages[
                $senderId . ':' . $messageId
            ]);

            return true;
        }

        protected function GetMessageList(): array
        {
            $result = [];
            foreach ($this->registeredMessages as $registration) {
                $result[$registration['senderId']][] =
                    $registration['messageId'];
            }

            return $result;
        }

        protected function SendDataToParent(string $json): string
        {
            if ($this->parentHandler === null) {
                throw new RuntimeException('No scripted parent handler is configured.');
            }

            return ($this->parentHandler)($json);
        }

        protected function SendDataToChildren(string $json): void
        {
            $this->childMessages[] = $json;
        }

        protected function SendDebug(string $message, mixed $data, int $format): void
        {
            if (count($this->debugEntries) >= 50) {
                array_shift($this->debugEntries);
            }

            $this->debugEntries[] = [
                'message' => $message,
                'data' => is_scalar($data) ? (string) $data : get_debug_type($data),
                'format' => $format,
            ];
        }

        public function testSetProperty(string $ident, mixed $value): void
        {
            $this->requireEntry($this->properties, $ident, 'property');
            $this->properties[$ident] = $value;
        }

        public function testSetAttribute(string $ident, mixed $value): void
        {
            $this->requireEntry($this->attributes, $ident, 'attribute');
            $this->attributes[$ident] = $value;
        }

        public function testReadAttribute(string $ident): mixed
        {
            return $this->requireEntry($this->attributes, $ident, 'attribute');
        }

        public function testReadVariable(string $ident): mixed
        {
            return $this->requireEntry($this->variables, $ident, 'variable');
        }

        public function testSetVariable(string $ident, mixed $value): void
        {
            $this->requireEntry($this->variables, $ident, 'variable');
            $this->variables[$ident] = $value;
        }

        public function testTimerInterval(string $ident): int
        {
            $timer = $this->requireEntry($this->timers, $ident, 'timer');
            return (int) $timer['interval'];
        }

        public function testStatus(): int
        {
            return $this->status;
        }

        public function testRegisteredMessages(): array
        {
            return array_values($this->registeredMessages);
        }

        public function testSetParentHandler(Closure $handler): void
        {
            $this->parentHandler = $handler;
        }

        public function testDebugEntries(): array
        {
            return $this->debugEntries;
        }

        public function testChildMessages(): array
        {
            return $this->childMessages;
        }

        public function testSnapshotPersistentState(): array
        {
            return [
                'properties' => $this->properties,
                'attributes' => $this->attributes,
                'variables' => $this->variables,
            ];
        }

        public function testRestorePersistentState(array $snapshot): void
        {
            foreach (['properties', 'attributes', 'variables'] as $key) {
                if (!isset($snapshot[$key]) || !is_array($snapshot[$key])) {
                    throw new InvalidArgumentException(
                        'Persistent state snapshot is invalid.'
                    );
                }
            }

            $this->properties = $snapshot['properties'];
            $this->attributes = $snapshot['attributes'];
            $this->variables = $snapshot['variables'];
        }

        private function requireEntry(array $store, string $ident, string $kind): mixed
        {
            if (!array_key_exists($ident, $store)) {
                throw new RuntimeException(sprintf('Unknown %s ident.', $kind));
            }

            return $store[$ident];
        }
    }
}

if (!defined('IPS_KERNELSTARTED')) {
    define('IPS_KERNELSTARTED', 10001);
}

if (!defined('IM_CHANGESTATUS')) {
    define('IM_CHANGESTATUS', 10505);
}

if (!function_exists('IPS_VariableProfileExists')) {
    function IPS_VariableProfileExists(string $name): bool
    {
        return true;
    }
}

if (!function_exists('IPS_CreateVariableProfile')) {
    function IPS_CreateVariableProfile(string $name, int $type): void
    {
    }
}

if (!function_exists('IPS_SetVariableProfileAssociation')) {
    function IPS_SetVariableProfileAssociation(
        string $name,
        int $value,
        string $label,
        string $icon,
        int $color
    ): void {
    }
}

if (!function_exists('IPS_SemaphoreEnter')) {
    function IPS_SemaphoreEnter(string $name, int $timeout): bool
    {
        return true;
    }
}

if (!function_exists('IPS_SemaphoreLeave')) {
    function IPS_SemaphoreLeave(string $name): void
    {
    }
}
