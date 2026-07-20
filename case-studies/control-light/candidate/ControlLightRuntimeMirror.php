<?php
declare(strict_types=1);

namespace SAEF\CaseStudy\ControlLight;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * ControlLight-local managed runtime mirror generator and provisioner.
 *
 * This is deliberately not a public SAEF helper. A second independent use case
 * is required before the provisioner contract may be promoted.
 */
final class ControlLightRuntimeMirror
{
    private const FORMAT_VERSION = 1;
    private const PAYLOAD_MARKER = "__halt_compiler();\n";

    /**
     * @param array<int, mixed> $referenceIDs
     */
    public static function render(string $runtimeSource, string $expectedRuntimeSha256, array $referenceIDs): string
    {
        self::assertSha256($expectedRuntimeSha256, 'expectedRuntimeSha256');

        $runtimeSha256 = hash('sha256', $runtimeSource);
        if (!hash_equals(strtolower($expectedRuntimeSha256), $runtimeSha256)) {
            throw new RuntimeException(sprintf(
                'Authoritative ControlLight runtime hash mismatch: expected %s, got %s.',
                strtolower($expectedRuntimeSha256),
                $runtimeSha256
            ));
        }

        $normalizedReferenceIDs = self::normalizeReferenceIDs($referenceIDs);
        $referenceJson = json_encode($normalizedReferenceIDs, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $referenceSha256 = hash('sha256', $referenceJson);
        $referenceLines = array_map(
            static fn (int $objectID): string => '    ' . $objectID . ',',
            $normalizedReferenceIDs
        );

        $preamble = implode("\n", [
            '<?php',
            'declare(strict_types=1);',
            '',
            '/**',
            ' * GENERATED SAEF CONTROLIGHT RUNTIME MIRROR — DO NOT EDIT.',
            ' *',
            ' * Non-authoritative diagnostic projection. Not an action or autoload target.',
            ' * Format version: ' . self::FORMAT_VERSION,
            ' * Runtime SHA-256: ' . $runtimeSha256,
            ' * Reference index SHA-256: ' . $referenceSha256,
            ' */',
            '$saefControlLightRuntimeMirrorReferenceIDs = [',
            ...$referenceLines,
            '];',
            'unset($saefControlLightRuntimeMirrorReferenceIDs);',
            '',
            rtrim(self::PAYLOAD_MARKER, "\n"),
            '',
        ]);

        return $preamble . $runtimeSource;
    }

    /**
     * @param array{
     *   parentID: int,
     *   ident: string,
     *   defaultName: string,
     *   defaultPosition?: int|null,
     *   expectedScriptID?: int|null,
     *   runtimePath: string,
     *   expectedRuntimeSha256: string,
     *   referenceIDs: array<int, mixed>
     * } $config
     * @return array{
     *   scriptID: int,
     *   outcome: 'created'|'updated'|'unchanged',
     *   runtimeSha256: string,
     *   referenceIndexSha256: string,
     *   mirrorSha256: string
     * }
     */
    public static function reconcile(array $config): array
    {
        self::validateConfiguration($config);

        $runtimeSource = file_get_contents($config['runtimePath']);
        if ($runtimeSource === false) {
            throw new RuntimeException('Cannot read authoritative ControlLight runtime file.');
        }

        $mirrorSource = self::render(
            $runtimeSource,
            $config['expectedRuntimeSha256'],
            $config['referenceIDs']
        );
        $mirrorSha256 = hash('sha256', $mirrorSource);

        $existingID = @\IPS_GetObjectIDByIdent($config['ident'], $config['parentID']);
        self::assertExpectedOwnership($config, $existingID);
        $created = $existingID === false;
        $previousSource = null;

        if (!$created) {
            $object = \IPS_GetObject($existingID);
            if (($object['ObjectType'] ?? null) !== 3) {
                throw new RuntimeException(sprintf(
                    'Owned mirror Ident "%s" exists below parent %d but is not a script.',
                    $config['ident'],
                    $config['parentID']
                ));
            }

            $previousSource = \IPS_GetScriptContent($existingID);
            if (hash_equals($mirrorSha256, hash('sha256', $previousSource))) {
                return self::result(
                    $existingID,
                    'unchanged',
                    $runtimeSource,
                    $config['referenceIDs'],
                    $mirrorSource
                );
            }

            $nameForEnsure = (string) ($object['ObjectName'] ?? $config['defaultName']);
            $positionForEnsure = null;
        } else {
            $nameForEnsure = $config['defaultName'];
            $positionForEnsure = $config['defaultPosition'] ?? null;
        }

        $scriptID = \SAEF_EnsureScript(
            $config['parentID'],
            $config['ident'],
            $nameForEnsure,
            0,
            $positionForEnsure,
            null,
            null
        );

        try {
            if (!\IPS_SetScriptContent($scriptID, $mirrorSource)) {
                throw new RuntimeException('IP-Symcon rejected the managed runtime mirror content update.');
            }
            $readback = \IPS_GetScriptContent($scriptID);
            if (!hash_equals($mirrorSha256, hash('sha256', $readback))) {
                throw new RuntimeException('Managed runtime mirror readback hash mismatch.');
            }
        } catch (Throwable $exception) {
            self::rollback($scriptID, $created, $previousSource, $exception);
        }

        return self::result(
            $scriptID,
            $created ? 'created' : 'updated',
            $runtimeSource,
            $config['referenceIDs'],
            $mirrorSource
        );
    }

    public static function extractRuntimePayload(string $mirrorSource): string
    {
        $offset = strpos($mirrorSource, self::PAYLOAD_MARKER);
        if ($offset === false) {
            throw new InvalidArgumentException('Managed runtime mirror payload marker is missing.');
        }

        return substr($mirrorSource, $offset + strlen(self::PAYLOAD_MARKER));
    }

    /** @param array<int, mixed> $referenceIDs @return list<int> */
    private static function normalizeReferenceIDs(array $referenceIDs): array
    {
        if ($referenceIDs === []) {
            throw new InvalidArgumentException('referenceIDs must contain at least one ObjectID.');
        }

        $normalized = [];
        foreach ($referenceIDs as $index => $objectID) {
            if (!is_int($objectID) || $objectID <= 0) {
                throw new InvalidArgumentException(sprintf(
                    'referenceIDs[%d] must be a positive integer.',
                    $index
                ));
            }
            $normalized[$objectID] = $objectID;
        }

        sort($normalized, SORT_NUMERIC);
        return $normalized;
    }

    /** @param array<string, mixed> $config */
    private static function validateConfiguration(array $config): void
    {
        foreach (['parentID', 'ident', 'defaultName', 'runtimePath', 'expectedRuntimeSha256', 'referenceIDs'] as $key) {
            if (!array_key_exists($key, $config)) {
                throw new InvalidArgumentException('Managed runtime mirror configuration is missing: ' . $key);
            }
        }

        if (!is_int($config['parentID']) || $config['parentID'] <= 0 || !\IPS_ObjectExists($config['parentID'])) {
            throw new InvalidArgumentException('parentID must identify an existing Symcon object.');
        }
        if (!is_string($config['ident']) || preg_match('/^[A-Za-z0-9_]+$/', $config['ident']) !== 1) {
            throw new InvalidArgumentException('ident must be a non-empty Symcon Ident.');
        }
        if (!is_string($config['defaultName']) || trim($config['defaultName']) === '') {
            throw new InvalidArgumentException('defaultName must be a non-empty string.');
        }
        if (
            array_key_exists('defaultPosition', $config)
            && $config['defaultPosition'] !== null
            && !is_int($config['defaultPosition'])
        ) {
            throw new InvalidArgumentException('defaultPosition must be an integer or null.');
        }
        if (
            array_key_exists('expectedScriptID', $config)
            && $config['expectedScriptID'] !== null
            && (!is_int($config['expectedScriptID']) || $config['expectedScriptID'] <= 0)
        ) {
            throw new InvalidArgumentException('expectedScriptID must be a positive integer or null.');
        }
        if (!is_string($config['runtimePath']) || !is_file($config['runtimePath'])) {
            throw new InvalidArgumentException('runtimePath must identify an existing file.');
        }
        if (!is_string($config['expectedRuntimeSha256'])) {
            throw new InvalidArgumentException('expectedRuntimeSha256 must be a string.');
        }
        self::assertSha256($config['expectedRuntimeSha256'], 'expectedRuntimeSha256');
        if (!is_array($config['referenceIDs'])) {
            throw new InvalidArgumentException('referenceIDs must be an array.');
        }
        self::normalizeReferenceIDs($config['referenceIDs']);
    }

    /** @param array<string, mixed> $config */
    private static function assertExpectedOwnership(array $config, int|false $existingID): void
    {
        $expectedScriptID = $config['expectedScriptID'] ?? null;
        if ($expectedScriptID === null) {
            return;
        }

        if (!\IPS_ObjectExists($expectedScriptID)) {
            throw new RuntimeException(sprintf(
                'Expected owned mirror script %d no longer exists.',
                $expectedScriptID
            ));
        }

        $expectedObject = \IPS_GetObject($expectedScriptID);
        if (
            ($expectedObject['ObjectType'] ?? null) !== 3
            || ($expectedObject['ObjectParentID'] ?? null) !== $config['parentID']
            || ($expectedObject['ObjectIdent'] ?? null) !== $config['ident']
            || $existingID !== $expectedScriptID
        ) {
            throw new RuntimeException(sprintf(
                'Expected mirror script %d has ownership drift in type, parent or Ident.',
                $expectedScriptID
            ));
        }
    }

    private static function assertSha256(string $value, string $name): void
    {
        if (preg_match('/^[a-fA-F0-9]{64}$/', $value) !== 1) {
            throw new InvalidArgumentException($name . ' must be a hexadecimal SHA-256 value.');
        }
    }

    private static function rollback(
        int $scriptID,
        bool $created,
        ?string $previousSource,
        Throwable $cause
    ): never {
        try {
            if ($created) {
                if (!\IPS_DeleteScript($scriptID, true)) {
                    throw new RuntimeException('IP-Symcon rejected deletion of the newly created mirror script.');
                }
            } else {
                if ($previousSource === null) {
                    throw new RuntimeException('Previous mirror source is unavailable for rollback.');
                }
                if (!\IPS_SetScriptContent($scriptID, $previousSource)) {
                    throw new RuntimeException('IP-Symcon rejected the managed runtime mirror rollback content.');
                }
                $rollbackReadback = \IPS_GetScriptContent($scriptID);
                if (!hash_equals(hash('sha256', $previousSource), hash('sha256', $rollbackReadback))) {
                    throw new RuntimeException('Managed runtime mirror rollback readback hash mismatch.');
                }
            }
        } catch (Throwable $rollbackException) {
            throw new RuntimeException(
                'Managed runtime mirror update failed and rollback failed: ' . $rollbackException->getMessage(),
                0,
                $cause
            );
        }

        throw new RuntimeException(
            'Managed runtime mirror update failed; the previous state was restored: ' . $cause->getMessage(),
            0,
            $cause
        );
    }

    /**
     * @param 'created'|'updated'|'unchanged' $outcome
     * @param array<int, mixed> $referenceIDs
     * @return array{
     *   scriptID: int,
     *   outcome: 'created'|'updated'|'unchanged',
     *   runtimeSha256: string,
     *   referenceIndexSha256: string,
     *   mirrorSha256: string
     * }
     */
    private static function result(
        int $scriptID,
        string $outcome,
        string $runtimeSource,
        array $referenceIDs,
        string $mirrorSource
    ): array {
        $normalizedReferenceIDs = self::normalizeReferenceIDs($referenceIDs);
        $referenceJson = json_encode($normalizedReferenceIDs, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return [
            'scriptID' => $scriptID,
            'outcome' => $outcome,
            'runtimeSha256' => hash('sha256', $runtimeSource),
            'referenceIndexSha256' => hash('sha256', $referenceJson),
            'mirrorSha256' => hash('sha256', $mirrorSource),
        ];
    }
}
