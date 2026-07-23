<?php

declare(strict_types=1);

namespace SAEF\Deployment;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Deployment-internal projection of the active SAEF helper source closure.
 *
 * The fileset remains authoritative. The generated Symcon script is inert and
 * exists only for source inspection and console search.
 */
final class SaefRuntimeSourceMirror
{
    private const FORMAT_VERSION = 1;
    private const MAXIMUM_SOURCE_COUNT = 256;
    private const MAXIMUM_SOURCE_BYTES = 1048576;
    private const MAXIMUM_TOTAL_BYTES = 4194304;
    private const PAYLOAD_MARKER = "__halt_compiler();\n";

    /**
     * @param array{
     *   filesetPath: string,
     *   parentID: int,
     *   ident: string,
     *   defaultName: string,
     *   defaultPosition?: int|null,
     *   expectedScriptID?: int|null
     * } $config
     * @return array{
     *   scriptID: int,
     *   outcome: 'created'|'updated'|'unchanged',
     *   filesetSha256: string,
     *   sourceIndexSha256: string,
     *   mirrorSha256: string,
     *   helperSourceCount: int
     * }
     */
    public static function reconcile(array $config): array
    {
        self::validateConfiguration($config);
        $fileset = self::readFileset($config['filesetPath']);
        if (!function_exists('SAEF_EnsureScript')) {
            require_once $fileset['ensureScriptPath'];
        }
        if (!function_exists('SAEF_EnsureScript')) {
            throw new RuntimeException('The active SAEF fileset does not provide SAEF_EnsureScript().');
        }
        $mirrorSource = self::render(
            $fileset['filesetSha256'],
            $fileset['sourceIndex'],
            $fileset['payload']
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
                    'Owned source mirror Ident "%s" exists below parent %d but is not a script.',
                    $config['ident'],
                    $config['parentID']
                ));
            }
            self::assertNoChildren($existingID);
            $previousSource = \IPS_GetScriptContent($existingID);
            if (hash_equals($mirrorSha256, hash('sha256', $previousSource))) {
                return self::result($existingID, 'unchanged', $fileset, $mirrorSource);
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
            null,
            false
        );

        try {
            self::assertNoChildren($scriptID);
            if (!\IPS_SetScriptContent($scriptID, $mirrorSource)) {
                throw new RuntimeException('IP-Symcon rejected the SAEF source mirror content update.');
            }
            $readback = \IPS_GetScriptContent($scriptID);
            if (!hash_equals($mirrorSha256, hash('sha256', $readback))) {
                throw new RuntimeException('SAEF source mirror readback hash mismatch.');
            }
        } catch (Throwable $exception) {
            self::rollback($scriptID, $created, $previousSource, $exception);
        }

        return self::result(
            $scriptID,
            $created ? 'created' : 'updated',
            $fileset,
            $mirrorSource
        );
    }

    /**
     * @param list<array{path: string, sha256: string}> $sourceIndex
     */
    public static function render(string $filesetSha256, array $sourceIndex, string $payload): string
    {
        self::assertSha256($filesetSha256, 'filesetSha256');
        if ($sourceIndex === []) {
            throw new InvalidArgumentException('sourceIndex must contain helper sources.');
        }
        $sourceIndexJson = json_encode(
            $sourceIndex,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
        );
        $sourceIndexSha256 = hash('sha256', $sourceIndexJson);

        $preamble = implode("\n", [
            '<?php',
            'declare(strict_types=1);',
            '',
            '/**',
            ' * GENERATED SAEF RUNTIME SOURCE MIRROR - DO NOT EDIT.',
            ' *',
            ' * Non-authoritative diagnostic projection. Not an action or autoload target.',
            ' * Format version: ' . self::FORMAT_VERSION,
            ' * Fileset SHA-256: ' . strtolower($filesetSha256),
            ' * Source index SHA-256: ' . $sourceIndexSha256,
            ' * Helper source count: ' . count($sourceIndex),
            ' */',
            '$saefRuntimeSourceMirrorMetadata = [',
            "    'filesetSha256' => '" . strtolower($filesetSha256) . "',",
            "    'sourceIndexSha256' => '" . $sourceIndexSha256 . "',",
            '    \'helperSourceCount\' => ' . count($sourceIndex) . ',',
            '];',
            'unset($saefRuntimeSourceMirrorMetadata);',
            '',
            rtrim(self::PAYLOAD_MARKER, "\n"),
            '',
        ]);

        return $preamble . $payload;
    }

    public static function extractPayload(string $mirrorSource): string
    {
        $offset = strpos($mirrorSource, self::PAYLOAD_MARKER);
        if ($offset === false) {
            throw new InvalidArgumentException('SAEF source mirror payload marker is missing.');
        }

        return substr($mirrorSource, $offset + strlen(self::PAYLOAD_MARKER));
    }

    /**
     * @return array{
     *   filesetSha256: string,
     *   sourceIndex: list<array{path: string, sha256: string}>,
     *   payload: string,
     *   ensureScriptPath: string
     * }
     */
    private static function readFileset(string $filesetPath): array
    {
        $resolvedRoot = realpath($filesetPath);
        if ($resolvedRoot === false || !is_dir($resolvedRoot)) {
            throw new InvalidArgumentException('filesetPath must identify an existing directory.');
        }
        $resolvedRoot = rtrim(str_replace('\\', '/', $resolvedRoot), '/') . '/';
        $sourceMapPath = $resolvedRoot . 'fileset.sources.json';
        $sidecarPath = $resolvedRoot . 'fileset.sha256';
        if (!is_file($sourceMapPath) || !is_file($sidecarPath)) {
            throw new RuntimeException('Fileset source map or hash sidecar is missing.');
        }
        if (filesize($sourceMapPath) > self::MAXIMUM_SOURCE_BYTES) {
            throw new RuntimeException('Fileset source map exceeds its byte limit.');
        }

        $sourceMapText = file_get_contents($sourceMapPath);
        $sidecarText = file_get_contents($sidecarPath);
        if ($sourceMapText === false || $sidecarText === false) {
            throw new RuntimeException('Cannot read fileset provenance.');
        }
        $sourceMap = json_decode($sourceMapText, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($sourceMap) || ($sourceMap['formatVersion'] ?? null) !== 1) {
            throw new RuntimeException('Fileset source map format is invalid.');
        }
        $filesetSha256 = $sourceMap['filesetSha256'] ?? null;
        if (!is_string($filesetSha256)) {
            throw new RuntimeException('Fileset source map identity is missing.');
        }
        self::assertSha256($filesetSha256, 'filesetSha256');
        if ($sidecarText !== strtolower($filesetSha256) . "  fileset\n") {
            throw new RuntimeException('Fileset hash sidecar differs from the source map.');
        }

        $orderedSources = $sourceMap['orderedSources'] ?? null;
        if (!is_array($orderedSources) || count($orderedSources) > self::MAXIMUM_SOURCE_COUNT) {
            throw new RuntimeException('Fileset ordered source list is invalid or unbounded.');
        }

        $sourceIndex = [];
        $payloadParts = [];
        $seenPaths = [];
        $totalBytes = 0;
        $ensureScriptPath = null;
        foreach ($orderedSources as $entry) {
            if (!is_array($entry)) {
                throw new RuntimeException('Fileset source entry is invalid.');
            }
            $path = $entry['path'] ?? null;
            $sha256 = $entry['sha256'] ?? null;
            if (!is_string($path) || !is_string($sha256) || !self::isSafeRelativePath($path)) {
                throw new RuntimeException('Fileset source path or hash is invalid.');
            }
            self::assertSha256($sha256, 'ordered source sha256');
            if (isset($seenPaths[$path])) {
                throw new RuntimeException('Fileset ordered source list contains a duplicate path.');
            }
            $seenPaths[$path] = true;
            if (!str_starts_with($path, 'helpers/')) {
                continue;
            }

            $absolutePath = realpath($resolvedRoot . $path);
            if (
                $absolutePath === false
                || !is_file($absolutePath)
                || !str_starts_with(str_replace('\\', '/', $absolutePath), $resolvedRoot)
            ) {
                throw new RuntimeException('Fileset helper source leaves the fileset root.');
            }
            $size = filesize($absolutePath);
            if ($size === false || $size > self::MAXIMUM_SOURCE_BYTES) {
                throw new RuntimeException('Fileset helper source exceeds its byte limit.');
            }
            $totalBytes += $size;
            if ($totalBytes > self::MAXIMUM_TOTAL_BYTES) {
                throw new RuntimeException('Fileset helper sources exceed the total byte limit.');
            }
            $source = file_get_contents($absolutePath);
            if ($source === false || !hash_equals(strtolower($sha256), hash('sha256', $source))) {
                throw new RuntimeException('Fileset helper source hash mismatch.');
            }
            $normalizedHash = strtolower($sha256);
            $sourceIndex[] = ['path' => $path, 'sha256' => $normalizedHash];
            if ($path === 'helpers/object/EnsureScript.php') {
                $ensureScriptPath = $absolutePath;
            }
            $payloadParts[] = sprintf(
                "/* ===== SAEF SOURCE: %s (%s) ===== */\n%s",
                $path,
                $normalizedHash,
                $source
            );
        }
        if ($sourceIndex === [] || $ensureScriptPath === null) {
            throw new RuntimeException('Fileset contains no complete helper source closure for the mirror.');
        }

        return [
            'filesetSha256' => strtolower($filesetSha256),
            'sourceIndex' => $sourceIndex,
            'payload' => implode("\n\n", $payloadParts),
            'ensureScriptPath' => $ensureScriptPath,
        ];
    }

    /** @param array<string, mixed> $config */
    private static function validateConfiguration(array $config): void
    {
        foreach (['filesetPath', 'parentID', 'ident', 'defaultName'] as $key) {
            if (!array_key_exists($key, $config)) {
                throw new InvalidArgumentException('SAEF source mirror configuration is missing: ' . $key);
            }
        }
        if (!is_string($config['filesetPath']) || !is_dir($config['filesetPath'])) {
            throw new InvalidArgumentException('filesetPath must identify an existing directory.');
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
    }

    /** @param array<string, mixed> $config */
    private static function assertExpectedOwnership(array $config, int|false $existingID): void
    {
        $expectedScriptID = $config['expectedScriptID'] ?? null;
        if ($expectedScriptID === null) {
            if ($existingID !== false) {
                throw new RuntimeException(
                    'Existing SAEF source mirror cannot be adopted without pinned deployment state.'
                );
            }
            return;
        }
        if (!\IPS_ObjectExists($expectedScriptID)) {
            throw new RuntimeException('Expected owned SAEF source mirror no longer exists.');
        }
        $object = \IPS_GetObject($expectedScriptID);
        $parentID = $object['ParentID'] ?? $object['ObjectParentID'] ?? null;
        if (
            ($object['ObjectType'] ?? null) !== 3
            || $parentID !== $config['parentID']
            || ($object['ObjectIdent'] ?? null) !== $config['ident']
            || $existingID !== $expectedScriptID
        ) {
            throw new RuntimeException('Expected SAEF source mirror has ownership drift.');
        }
    }

    private static function assertNoChildren(int $scriptID): void
    {
        if (\IPS_GetChildrenIDs($scriptID) !== []) {
            throw new RuntimeException('SAEF source mirror must not own child objects.');
        }
    }

    private static function isSafeRelativePath(string $path): bool
    {
        if ($path === '' || str_contains($path, '\\') || str_contains($path, ':') || str_starts_with($path, '/')) {
            return false;
        }
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
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
                    throw new RuntimeException('IP-Symcon rejected deletion of the new SAEF source mirror.');
                }
            } else {
                if ($previousSource === null || !\IPS_SetScriptContent($scriptID, $previousSource)) {
                    throw new RuntimeException('Previous SAEF source mirror is unavailable for rollback.');
                }
                $rollbackReadback = \IPS_GetScriptContent($scriptID);
                if (!hash_equals(hash('sha256', $previousSource), hash('sha256', $rollbackReadback))) {
                    throw new RuntimeException('SAEF source mirror rollback readback hash mismatch.');
                }
            }
        } catch (Throwable $rollbackException) {
            throw new RuntimeException(
                'SAEF source mirror update and rollback failed: ' . $rollbackException->getMessage(),
                0,
                $cause
            );
        }

        throw new RuntimeException(
            'SAEF source mirror update failed; its previous state was restored: ' . $cause->getMessage(),
            0,
            $cause
        );
    }

    /**
     * @param 'created'|'updated'|'unchanged' $outcome
     * @param array{
     *   filesetSha256: string,
     *   sourceIndex: list<array{path: string, sha256: string}>,
     *   payload: string
     * } $fileset
     * @return array{
     *   scriptID: int,
     *   outcome: 'created'|'updated'|'unchanged',
     *   filesetSha256: string,
     *   sourceIndexSha256: string,
     *   mirrorSha256: string,
     *   helperSourceCount: int
     * }
     */
    private static function result(int $scriptID, string $outcome, array $fileset, string $mirrorSource): array
    {
        $sourceIndexJson = json_encode(
            $fileset['sourceIndex'],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
        );

        return [
            'scriptID' => $scriptID,
            'outcome' => $outcome,
            'filesetSha256' => $fileset['filesetSha256'],
            'sourceIndexSha256' => hash('sha256', $sourceIndexJson),
            'mirrorSha256' => hash('sha256', $mirrorSource),
            'helperSourceCount' => count($fileset['sourceIndex']),
        ];
    }
}
