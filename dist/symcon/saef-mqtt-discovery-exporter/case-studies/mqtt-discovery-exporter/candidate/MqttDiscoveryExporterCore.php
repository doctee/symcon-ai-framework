<?php
declare(strict_types=1);

namespace SAEF\CaseStudy\MqttDiscoveryExporter;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

/**
 * Deterministic core for the MQTT Discovery Exporter case-study candidate.
 *
 * This class is intentionally free of IP-Symcon and MQTT side effects. Runtime
 * integration belongs to MqttDiscoveryExporterRuntime.php.
 */
final class MqttDiscoveryExporterCore
{
    private const DEFAULT_UUID_NAMESPACE = '36d5dfd1-d837-4e5f-8d67-0f41e3f0f2a1';

    /**
     * Normalizes and validates the complete desired exporter configuration.
     *
     * @param array<string, mixed> $configuration
     *
     * @return array<string, mixed>
     */
    public static function normalizeConfiguration(array $configuration): array
    {
        $location = self::requireTechnicalID($configuration, 'location', 'configuration');

        if (!isset($configuration['mqtt']) || !is_array($configuration['mqtt'])) {
            throw new InvalidArgumentException('configuration.mqtt must be an array.');
        }

        $mqtt = $configuration['mqtt'];
        $transport = strtolower(self::optionalString($mqtt, 'transport') ?? 'server');
        if (!in_array($transport, ['client', 'server'], true)) {
            throw new InvalidArgumentException(
                'configuration.mqtt.transport must be either "client" or "server".'
            );
        }
        if (array_key_exists('gatewayID', $mqtt)) {
            $gatewayID = self::requirePositiveInteger($mqtt, 'gatewayID', 'configuration.mqtt');
        } elseif ($transport === 'server' && array_key_exists('serverID', $mqtt)) {
            // Backward-compatible input for the original server-only candidate.
            $gatewayID = self::requirePositiveInteger($mqtt, 'serverID', 'configuration.mqtt');
        } else {
            throw new InvalidArgumentException('configuration.mqtt.gatewayID must be a positive integer.');
        }
        $baseTopic = self::normalizeTopicPath(
            self::requireString($mqtt, 'baseTopic', 'configuration.mqtt'),
            'configuration.mqtt.baseTopic'
        );
        $discoveryPrefix = self::normalizeTopicPath(
            self::requireString($mqtt, 'discoveryPrefix', 'configuration.mqtt'),
            'configuration.mqtt.discoveryPrefix'
        );

        $uuidNamespace = $configuration['uuidNamespace'] ?? self::DEFAULT_UUID_NAMESPACE;
        if (!is_string($uuidNamespace)) {
            throw new InvalidArgumentException('configuration.uuidNamespace must be a UUID string.');
        }
        self::uuidV5($uuidNamespace, 'namespace-validation');

        $defaults = $configuration['defaults'] ?? [];
        if (!is_array($defaults)) {
            throw new InvalidArgumentException('configuration.defaults must be an array.');
        }

        if (!isset($configuration['devices']) || !is_array($configuration['devices'])) {
            throw new InvalidArgumentException('configuration.devices must be an array.');
        }

        $normalizedDevices = [];
        $deviceIDs = [];
        $entityKeys = [];
        $runtimeTopics = [];
        $discoveryTopics = [];

        foreach ($configuration['devices'] as $deviceIndex => $rawDevice) {
            $devicePath = sprintf('configuration.devices[%s]', (string)$deviceIndex);
            if (!is_array($rawDevice)) {
                throw new InvalidArgumentException(sprintf(
                    'configuration.devices[%s] must be an array.',
                    (string)$deviceIndex
                ));
            }

            $exportDevice = self::normalizeBoolean($rawDevice['export'] ?? true, $devicePath . '.export');
            if (!$exportDevice) {
                continue;
            }

            $deviceID = self::requireTechnicalID($rawDevice, 'id', $devicePath);

            if (isset($deviceIDs[$deviceID])) {
                throw new InvalidArgumentException('Duplicate device ID: ' . $deviceID);
            }
            $deviceIDs[$deviceID] = true;

            $deviceTopic = isset($rawDevice['topic'])
                ? self::requireTechnicalID($rawDevice, 'topic', $devicePath)
                : $deviceID;

            if (!isset($rawDevice['entities']) || !is_array($rawDevice['entities'])) {
                throw new InvalidArgumentException($devicePath . '.entities must be an array.');
            }

            $normalizedEntities = [];

            foreach ($rawDevice['entities'] as $entityIndex => $rawEntity) {
                $entityPath = sprintf('%s.entities[%s]', $devicePath, (string)$entityIndex);
                if (!is_array($rawEntity)) {
                    throw new InvalidArgumentException(sprintf(
                        '%s.entities[%s] must be an array.',
                        $devicePath,
                        (string)$entityIndex
                    ));
                }

                $exportEntity = self::normalizeBoolean($rawEntity['export'] ?? true, $entityPath . '.export');
                if (!$exportEntity) {
                    continue;
                }

                $entity = self::normalizeEntity($rawEntity, $deviceTopic, $entityPath);
                $entityKey = $deviceID . '.' . $entity['id'];

                if (isset($entityKeys[$entityKey])) {
                    throw new InvalidArgumentException('Duplicate entity key: ' . $entityKey);
                }
                $entityKeys[$entityKey] = true;

                $runtimeTopic = $baseTopic
                    . '/' . $location
                    . '/' . $entity['class']
                    . '/' . $entity['topicId'];
                $discoveryTopic = $discoveryPrefix
                    . '/' . $entity['class']
                    . '/' . $location
                    . '/' . self::objectID($location, $entity['class'], $entity['topicId'])
                    . '/config';

                if (isset($runtimeTopics[$runtimeTopic])) {
                    throw new InvalidArgumentException('Duplicate runtime topic: ' . $runtimeTopic);
                }
                if (isset($discoveryTopics[$discoveryTopic])) {
                    throw new InvalidArgumentException('Duplicate discovery topic: ' . $discoveryTopic);
                }

                $runtimeTopics[$runtimeTopic] = true;
                $discoveryTopics[$discoveryTopic] = true;
                $normalizedEntities[] = $entity;
            }

            if ($normalizedEntities === []) {
                throw new InvalidArgumentException($devicePath . ' has no active entities.');
            }

            $normalizedDevices[] = [
                'id' => $deviceID,
                'topicId' => $deviceTopic,
                'name' => self::requireString($rawDevice, 'name', $devicePath),
                'room' => self::optionalString($rawDevice, 'room'),
                'manufacturer' => self::optionalString($rawDevice, 'manufacturer'),
                'model' => self::optionalString($rawDevice, 'model'),
                'entities' => $normalizedEntities,
            ];
        }

        $retain = self::normalizeBoolean($defaults['retain'] ?? true, 'defaults.retain');
        if (!$retain) {
            throw new InvalidArgumentException('defaults.retain must be true for retained runtime state.');
        }

        return [
            'version' => self::optionalString($configuration, 'version') ?? 'candidate',
            'location' => $location,
            'uuidNamespace' => $uuidNamespace,
            'mqtt' => [
                'transport' => $transport,
                'gatewayID' => $gatewayID,
                'baseTopic' => $baseTopic,
                'discoveryPrefix' => $discoveryPrefix,
                'configurationURL' => self::optionalString($mqtt, 'configurationURL'),
            ],
            'defaults' => [
                'manufacturer' => self::optionalString($defaults, 'manufacturer') ?? 'IP-Symcon',
                'model' => self::optionalString($defaults, 'model') ?? 'Virtual Device',
                'qos' => self::normalizeQos($defaults['qos'] ?? 0),
                'retain' => $retain,
            ],
            'devices' => $normalizedDevices,
        ];
    }

    /**
     * @param array<string, mixed> $device
     * @param array<string, mixed> $entity
     */
    public static function entityKey(array $device, array $entity): string
    {
        return (string)$device['id'] . '.' . (string)$entity['id'];
    }

    /**
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $device
     * @param array<string, mixed> $entity
     */
    public static function runtimeBaseTopic(array $configuration, array $device, array $entity): string
    {
        return (string)$configuration['mqtt']['baseTopic']
            . '/' . (string)$configuration['location']
            . '/' . (string)$entity['class']
            . '/' . (string)$entity['topicId'];
    }

    /**
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $device
     * @param array<string, mixed> $entity
     */
    public static function discoveryTopic(array $configuration, array $device, array $entity): string
    {
        return (string)$configuration['mqtt']['discoveryPrefix']
            . '/' . (string)$entity['class']
            . '/' . (string)$configuration['location']
            . '/' . self::objectID(
                (string)$configuration['location'],
                (string)$entity['class'],
                (string)$entity['topicId']
            )
            . '/config';
    }

    /**
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $device
     * @param array<string, mixed> $entity
     *
     * @return array<string, mixed>
     */
    public static function buildDiscoveryPayload(array $configuration, array $device, array $entity): array
    {
        $runtimeTopic = self::runtimeBaseTopic($configuration, $device, $entity);
        $entityKey = self::entityKey($device, $entity);
        $uuid = self::uuidV5(
            (string)$configuration['uuidNamespace'],
            (string)$configuration['location'] . ':' . $entityKey
        );

        $payload = [
            'name' => $entity['name'],
            'unique_id' => $uuid,
            'default_entity_id' => $entity['class'] . '.' . self::objectID(
                (string)$configuration['location'],
                (string)$entity['class'],
                (string)$entity['topicId']
            ),
            'state_topic' => $runtimeTopic . '/state',
            'command_topic' => $runtimeTopic . '/set',
            'qos' => $configuration['defaults']['qos'],
            'optimistic' => false,
            'device' => [
                'identifiers' => ['ips_' . $configuration['location'] . '_' . $device['id']],
                'name' => $device['name'],
                'manufacturer' => $device['manufacturer'] ?? $configuration['defaults']['manufacturer'],
                'model' => $device['model'] ?? $configuration['defaults']['model'],
                'serial_number' => self::uuidV5(
                    (string)$configuration['uuidNamespace'],
                    (string)$configuration['location'] . ':device:' . $device['id']
                ),
                'sw_version' => 'SAEF MQTT Discovery Exporter ' . $configuration['version'],
                'suggested_area' => $device['room'],
                'configuration_url' => $configuration['mqtt']['configurationURL'],
            ],
            'origin' => [
                'name' => 'SAEF IP-Symcon MQTT Discovery Exporter',
                'sw_version' => $configuration['version'],
            ],
            'payload_on' => 'ON',
            'payload_off' => 'OFF',
            'icon' => $entity['icon'] ?? ($entity['class'] === 'light' ? 'mdi:lightbulb' : 'mdi:toggle-switch'),
        ];

        if ($entity['class'] === 'light') {
            $payload['color_mode_state_topic'] = $runtimeTopic . '/color_mode/state';

            if (isset($entity['capabilities']['brightness'])) {
                $payload['brightness_state_topic'] = $runtimeTopic . '/brightness/state';
                $payload['brightness_command_topic'] = $runtimeTopic . '/brightness/set';
                $payload['brightness_scale'] = 100;
            }

            if (isset($entity['capabilities']['rgb'])) {
                $payload['rgb_state_topic'] = $runtimeTopic . '/rgb/state';
                $payload['rgb_command_topic'] = $runtimeTopic . '/rgb/set';
            }

            if (isset($entity['capabilities']['colorTemperature'])) {
                $colorTemperature = $entity['capabilities']['colorTemperature'];
                $payload['color_temp_state_topic'] = $runtimeTopic . '/color_temp/state';
                $payload['color_temp_command_topic'] = $runtimeTopic . '/color_temp/set';
                $payload['color_temp_kelvin'] = true;
                $payload['min_kelvin'] = $colorTemperature['minimumKelvin'];
                $payload['max_kelvin'] = $colorTemperature['maximumKelvin'];
            }
        }

        return self::removeNullValues($payload);
    }

    /**
     * Parses one untrusted MQTT command without permissive scalar coercion.
     *
     * @param array<string, mixed> $entity
     *
     * @return array{capability: string, value: bool|int}
     */
    public static function parseCommand(array $entity, string $commandType, string $payload): array
    {
        $payload = trim($payload);
        $capabilities = $entity['capabilities'] ?? [];

        if (!is_array($capabilities) || !isset($capabilities[$commandType])) {
            throw new InvalidArgumentException('Entity does not expose command type: ' . $commandType);
        }

        return match ($commandType) {
            'power' => [
                'capability' => 'power',
                'value' => self::parsePowerPayload($payload, (bool)$capabilities['power']['invert']),
            ],
            'brightness' => [
                'capability' => 'brightness',
                'value' => self::parseIntegerPayload($payload, 0, 100, 'brightness'),
            ],
            'rgb' => [
                'capability' => 'rgb',
                'value' => self::parseRgbPayload($payload),
            ],
            'colorTemperature' => [
                'capability' => 'colorTemperature',
                'value' => self::parseIntegerPayload(
                    $payload,
                    (int)$capabilities['colorTemperature']['minimumKelvin'],
                    (int)$capabilities['colorTemperature']['maximumKelvin'],
                    'color temperature'
                ),
            ],
            default => throw new InvalidArgumentException('Unsupported command type: ' . $commandType),
        };
    }

    /**
     * Builds retained runtime topic payloads from observed values.
     *
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $device
     * @param array<string, mixed> $entity
     * @param array<string, mixed> $observedValues
     *
     * @return array{topics: array<string, string>, values: array<string, bool|int|string>}
     */
    public static function buildRuntimePayloads(
        array $configuration,
        array $device,
        array $entity,
        array $observedValues
    ): array {
        $baseTopic = self::runtimeBaseTopic($configuration, $device, $entity);
        $capabilities = $entity['capabilities'];
        $topics = [];
        $values = [];

        if (!array_key_exists('power', $observedValues) || !is_bool($observedValues['power'])) {
            throw new InvalidArgumentException('Observed power value must be Boolean.');
        }

        $power = $observedValues['power'];
        if ($capabilities['power']['invert'] === true) {
            $power = !$power;
        }
        $topics[$baseTopic . '/state'] = $power ? 'ON' : 'OFF';
        $values['power'] = $power;

        if ($entity['class'] === 'light') {
            $colorMode = self::lightColorMode($entity);
            $topics[$baseTopic . '/color_mode/state'] = $colorMode;
            $values['colorMode'] = $colorMode;
        }

        if (isset($capabilities['brightness'])) {
            $brightness = self::requireObservedInteger($observedValues, 'brightness', 0, 100);
            $topics[$baseTopic . '/brightness/state'] = (string)$brightness;
            $values['brightness'] = $brightness;
        }

        if (isset($capabilities['rgb'])) {
            if (!array_key_exists('rgb', $observedValues)) {
                throw new InvalidArgumentException('Observed RGB value is missing.');
            }
            $rgb = self::normalizeObservedRgb($observedValues['rgb']);
            $topics[$baseTopic . '/rgb/state'] = $rgb['payload'];
            $values['rgb'] = $rgb['value'];
        }

        if (isset($capabilities['colorTemperature'])) {
            $colorTemperature = $capabilities['colorTemperature'];
            $kelvin = self::requireObservedInteger(
                $observedValues,
                'colorTemperature',
                (int)$colorTemperature['minimumKelvin'],
                (int)$colorTemperature['maximumKelvin']
            );
            $topics[$baseTopic . '/color_temp/state'] = (string)$kelvin;
            $values['colorTemperature'] = $kelvin;
        }

        ksort($topics);
        ksort($values);

        return ['topics' => $topics, 'values' => $values];
    }

    /**
     * @param array<string, mixed> $entity
     */
    public static function lightColorMode(array $entity): string
    {
        $capabilities = $entity['capabilities'] ?? [];

        if (isset($capabilities['rgb'])) {
            return 'rgb';
        }
        if (isset($capabilities['colorTemperature'])) {
            return 'color_temp';
        }
        if (isset($capabilities['brightness'])) {
            return 'brightness';
        }

        return 'onoff';
    }

    /**
     * Returns exact previously managed entries that no longer exist in desired state.
     *
     * @param array<string, mixed> $previousEntries
     * @param list<string>         $desiredEntityKeys
     *
     * @return array<string, array<string, mixed>>
     */
    public static function planRemovedEntries(array $previousEntries, array $desiredEntityKeys): array
    {
        $desiredMap = array_fill_keys($desiredEntityKeys, true);
        $removed = [];

        foreach ($previousEntries as $entityKey => $entry) {
            if (!is_array($entry)) {
                throw new InvalidArgumentException('Managed registry entries must map string keys to arrays.');
            }

            if (!isset($desiredMap[$entityKey])) {
                $removed[$entityKey] = $entry;
            }
        }

        ksort($removed);

        return $removed;
    }

    /**
     * @throws JsonException
     */
    public static function canonicalJson(mixed $value): string
    {
        return json_encode(
            self::sortRecursive($value),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * @throws JsonException
     */
    public static function payloadHash(mixed $value): string
    {
        return hash('sha256', self::canonicalJson($value));
    }

    /**
     * @param array<string, mixed> $rawEntity
     *
     * @return array<string, mixed>
     */
    private static function normalizeEntity(array $rawEntity, string $deviceTopic, string $path): array
    {
        $entityID = self::requireTechnicalID($rawEntity, 'id', $path);
        $class = self::requireString($rawEntity, 'class', $path);

        if (!in_array($class, ['light', 'switch'], true)) {
            throw new InvalidArgumentException($path . '.class must be light or switch.');
        }

        $topicID = isset($rawEntity['topic'])
            ? self::requireTechnicalID($rawEntity, 'topic', $path)
            : $deviceTopic;

        $capabilities = [
            'power' => self::normalizeCapabilityPair(
                $rawEntity,
                'powerID',
                'stateID',
                'actionID',
                $path . '.power',
                true
            ),
        ];
        $capabilities['power']['invert'] = self::normalizeBoolean($rawEntity['invert'] ?? false, $path . '.invert');

        $brightness = self::normalizeCapabilityPair(
            $rawEntity,
            'brightnessID',
            'brightnessStateID',
            'brightnessActionID',
            $path . '.brightness',
            false
        );
        if ($brightness !== null) {
            $capabilities['brightness'] = $brightness;
        }

        if (isset($rawEntity['colorID'], $rawEntity['rgbID'])) {
            throw new InvalidArgumentException($path . ' must not define both colorID and rgbID.');
        }

        $colorCombinedKey = isset($rawEntity['rgbID']) ? 'rgbID' : 'colorID';
        $rgb = self::normalizeCapabilityPair(
            $rawEntity,
            $colorCombinedKey,
            'colorStateID',
            'colorActionID',
            $path . '.rgb',
            false
        );
        if ($rgb !== null) {
            $capabilities['rgb'] = $rgb;
        }

        $colorTemperature = self::normalizeCapabilityPair(
            $rawEntity,
            'colorTempID',
            'colorTempStateID',
            'colorTempActionID',
            $path . '.colorTemperature',
            false
        );
        if ($colorTemperature !== null) {
            $minimumKelvin = self::normalizeIntegerValue(
                $rawEntity['minKelvin'] ?? 2000,
                $path . '.minKelvin'
            );
            $maximumKelvin = self::normalizeIntegerValue(
                $rawEntity['maxKelvin'] ?? 6500,
                $path . '.maxKelvin'
            );
            if ($minimumKelvin < 1000 || $maximumKelvin > 10000 || $minimumKelvin >= $maximumKelvin) {
                throw new InvalidArgumentException($path . ' has an invalid Kelvin range.');
            }
            $colorTemperature['minimumKelvin'] = $minimumKelvin;
            $colorTemperature['maximumKelvin'] = $maximumKelvin;
            $capabilities['colorTemperature'] = $colorTemperature;
        }

        if ($class === 'switch' && count($capabilities) !== 1) {
            throw new InvalidArgumentException($path . ' switch entities support power only.');
        }

        if (
            (isset($capabilities['rgb']) || isset($capabilities['colorTemperature']))
            && !isset($capabilities['brightness'])
        ) {
            throw new InvalidArgumentException($path . ' color capabilities require brightness.');
        }

        $confirmation = $rawEntity['confirmation'] ?? [];
        if (!is_array($confirmation)) {
            throw new InvalidArgumentException($path . '.confirmation must be an array.');
        }

        $timeout = self::normalizeIntegerValue($confirmation['timeoutMilliseconds'] ?? 2000, $path . '.confirmation.timeoutMilliseconds');
        $poll = self::normalizeIntegerValue($confirmation['pollIntervalMilliseconds'] ?? 50, $path . '.confirmation.pollIntervalMilliseconds');
        if ($timeout <= 0 || $poll <= 0 || $poll > $timeout) {
            throw new InvalidArgumentException($path . ' has invalid confirmation timing.');
        }

        return [
            'id' => $entityID,
            'class' => $class,
            'name' => self::requireString($rawEntity, 'name', $path),
            'topicId' => $topicID,
            'icon' => self::optionalString($rawEntity, 'icon'),
            'capabilities' => $capabilities,
            'confirmation' => [
                'timeoutMilliseconds' => $timeout,
                'pollIntervalMilliseconds' => $poll,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $entity
     *
     * @return array{stateVariableID: int, actionVariableID: int}|null
     */
    private static function normalizeCapabilityPair(
        array $entity,
        string $combinedKey,
        string $stateKey,
        string $actionKey,
        string $path,
        bool $required
    ): ?array {
        $combined = array_key_exists($combinedKey, $entity)
            ? self::normalizeIntegerValue($entity[$combinedKey], $path . '.' . $combinedKey)
            : null;
        $state = array_key_exists($stateKey, $entity)
            ? self::normalizeIntegerValue($entity[$stateKey], $path . '.' . $stateKey)
            : $combined;
        $action = array_key_exists($actionKey, $entity)
            ? self::normalizeIntegerValue($entity[$actionKey], $path . '.' . $actionKey)
            : $combined;

        if ($state === null && $action === null) {
            if ($required) {
                throw new InvalidArgumentException($path . ' capability is required.');
            }

            return null;
        }

        if ($state === null || $action === null) {
            throw new InvalidArgumentException($path . ' requires complete state and action variable IDs.');
        }

        if ($state <= 0 || $action <= 0) {
            throw new InvalidArgumentException($path . ' variable IDs must be positive integers.');
        }

        return [
            'stateVariableID' => $state,
            'actionVariableID' => $action,
        ];
    }

    /** @param array<string, mixed> $data */
    private static function requireTechnicalID(array $data, string $key, string $path): string
    {
        $value = self::requireString($data, $key, $path);
        $slug = self::slug($value);

        if ($slug !== $value) {
            throw new InvalidArgumentException($path . '.' . $key . ' must already be a safe lowercase technical ID.');
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private static function requireString(array $data, string $key, string $path): string
    {
        if (!array_key_exists($key, $data) || !is_string($data[$key]) || trim($data[$key]) === '') {
            throw new InvalidArgumentException($path . '.' . $key . ' must be a non-empty string.');
        }

        return trim($data[$key]);
    }

    /** @param array<string, mixed> $data */
    private static function optionalString(array $data, string $key): ?string
    {
        if (!array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }
        if (!is_string($data[$key])) {
            throw new InvalidArgumentException($key . ' must be a string or null.');
        }

        $value = trim($data[$key]);

        return $value === '' ? null : $value;
    }

    /** @param array<string, mixed> $data */
    private static function requirePositiveInteger(array $data, string $key, string $path): int
    {
        if (!array_key_exists($key, $data)) {
            throw new InvalidArgumentException($path . '.' . $key . ' is required.');
        }

        $value = self::normalizeIntegerValue($data[$key], $path . '.' . $key);
        if ($value <= 0) {
            throw new InvalidArgumentException($path . '.' . $key . ' must be positive.');
        }

        return $value;
    }

    private static function normalizeIntegerValue(mixed $value, string $path): int
    {
        if (!is_int($value)) {
            throw new InvalidArgumentException($path . ' must be an integer.');
        }

        return $value;
    }

    private static function normalizeBoolean(mixed $value, string $path): bool
    {
        if (!is_bool($value)) {
            throw new InvalidArgumentException($path . ' must be Boolean.');
        }

        return $value;
    }

    private static function normalizeQos(mixed $value): int
    {
        if (!is_int($value) || !in_array($value, [0, 1, 2], true)) {
            throw new InvalidArgumentException('defaults.qos must be 0, 1 or 2.');
        }

        return $value;
    }

    private static function normalizeTopicPath(string $topic, string $path): string
    {
        $topic = trim($topic, '/');
        if ($topic === '' || preg_match('/[#+\\\\\s]/', $topic) === 1 || str_contains($topic, '//')) {
            throw new InvalidArgumentException($path . ' contains unsupported MQTT topic characters.');
        }

        return $topic;
    }

    private static function slug(string $value): string
    {
        $value = str_replace(
            ['ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß'],
            ['ae', 'oe', 'ue', 'ae', 'oe', 'ue', 'ss'],
            $value
        );
        $value = strtolower($value);
        $value = (string)preg_replace('/[^a-z0-9]+/', '_', $value);

        return trim($value, '_');
    }

    private static function objectID(string $location, string $class, string $topicID): string
    {
        return self::slug('symcon_' . $location . '_' . $class . '_' . $topicID);
    }

    private static function parsePowerPayload(string $payload, bool $invert): bool
    {
        if (!in_array($payload, ['ON', 'OFF'], true)) {
            throw new InvalidArgumentException('Power payload must be exactly ON or OFF.');
        }

        $value = $payload === 'ON';

        return $invert ? !$value : $value;
    }

    private static function parseIntegerPayload(string $payload, int $minimum, int $maximum, string $label): int
    {
        if (preg_match('/^(0|[1-9][0-9]*)$/', $payload) !== 1) {
            throw new InvalidArgumentException('Invalid ' . $label . ' payload: ' . $payload);
        }

        $value = filter_var($payload, FILTER_VALIDATE_INT);
        if (!is_int($value) || $value < $minimum || $value > $maximum) {
            throw new InvalidArgumentException(sprintf(
                '%s payload must be between %d and %d.',
                ucfirst($label),
                $minimum,
                $maximum
            ));
        }

        return $value;
    }

    private static function parseRgbPayload(string $payload): int
    {
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $payload) === 1) {
            return (int)hexdec(substr($payload, 1));
        }

        if (preg_match('/^(0|[1-9][0-9]{0,2}),(0|[1-9][0-9]{0,2}),(0|[1-9][0-9]{0,2})$/', $payload, $matches) !== 1) {
            throw new InvalidArgumentException('RGB payload must be #RRGGBB or R,G,B.');
        }

        $red = (int)$matches[1];
        $green = (int)$matches[2];
        $blue = (int)$matches[3];
        if ($red > 255 || $green > 255 || $blue > 255) {
            throw new InvalidArgumentException('RGB channels must be between 0 and 255.');
        }

        return ($red << 16) | ($green << 8) | $blue;
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function requireObservedInteger(array $values, string $key, int $minimum, int $maximum): int
    {
        if (!array_key_exists($key, $values) || !is_int($values[$key])) {
            throw new InvalidArgumentException('Observed ' . $key . ' value must be an integer.');
        }

        if ($values[$key] < $minimum || $values[$key] > $maximum) {
            throw new InvalidArgumentException(sprintf(
                'Observed %s value must be between %d and %d.',
                $key,
                $minimum,
                $maximum
            ));
        }

        return $values[$key];
    }

    /** @return array{payload: string, value: int} */
    private static function normalizeObservedRgb(mixed $value): array
    {
        if (is_int($value) && $value >= 0 && $value <= 0xFFFFFF) {
            $integer = $value;
        } elseif (is_string($value)) {
            $integer = self::parseRgbPayload(trim($value));
        } else {
            throw new InvalidArgumentException('Observed RGB value must be an integer or supported RGB string.');
        }

        return [
            'payload' => (($integer >> 16) & 255) . ',' . (($integer >> 8) & 255) . ',' . ($integer & 255),
            'value' => $integer,
        ];
    }

    /** @param array<string, mixed> $array */
    private static function removeNullValues(array $array): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = self::removeNullValues($value);
            } elseif ($value === null) {
                unset($array[$key]);
            }
        }

        return $array;
    }

    private static function sortRecursive(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = self::sortRecursive($item);
        }

        if (!array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    private static function uuidV5(string $namespace, string $name): string
    {
        if (preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $namespace) !== 1) {
            throw new InvalidArgumentException('Invalid UUID namespace: ' . $namespace);
        }

        $namespaceBytes = hex2bin(str_replace('-', '', strtolower($namespace)));
        if ($namespaceBytes === false) {
            throw new RuntimeException('Unable to decode UUID namespace.');
        }

        $hash = sha1($namespaceBytes . $name, true);
        $bytes = substr($hash, 0, 16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x50);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
