<?php

declare(strict_types=1);

/**
 * Builds deterministic, self-contained IP-Symcon helper bundles.
 *
 * Canonical helper files remain the only handwritten implementation source.
 * This tool only resolves their static local dependencies and removes the
 * file-loading syntax that cannot be used inside a single Symcon script.
 */

const SAEF_SYMCON_BUNDLE_BUILDER_VERSION = '1.0.0';
const SAEF_SYMCON_BUNDLE_FRAMEWORK_VERSION = '0.3.0';
const SAEF_SYMCON_BUNDLE_LICENSE = 'PolyForm-Noncommercial-1.0.0';
const SAEF_SYMCON_BUNDLE_LICENSE_URL = 'https://polyformproject.org/licenses/noncommercial/1.0.0/';

final class SaefSymconBundleBuilder
{
    private string $projectRoot;

    /** @var array<string, string> */
    private array $sources = [];

    /** @var array<string, list<string>> */
    private array $dependencies = [];

    /** @var array<string, int> */
    private array $visitState = [];

    /** @var list<string> */
    private array $orderedSources = [];

    public function __construct(string $projectRoot)
    {
        $resolvedRoot = realpath($projectRoot);

        if ($resolvedRoot === false) {
            throw new RuntimeException('Project root does not exist: ' . $projectRoot);
        }

        $this->projectRoot = str_replace('\\', '/', $resolvedRoot);
    }

    /**
     * @return array<string, string> Repository-relative output path to content.
     */
    public function build(string $manifestArgument): array
    {
        $this->sources = [];
        $this->dependencies = [];
        $this->visitState = [];
        $this->orderedSources = [];

        [$manifest, $manifestPath] = $this->loadManifest($manifestArgument);

        foreach ($manifest['entries'] as $entry) {
            $this->visit($entry);
        }

        $orderedSources = $this->orderedSources;
        $actualExports = [];

        foreach ($orderedSources as $sourcePath) {
            $actualExports = array_merge(
                $actualExports,
                $this->extractFunctions($this->sources[$sourcePath])
            );
        }

        sort($actualExports, SORT_STRING);
        $expectedExports = $manifest['exports'];
        sort($expectedExports, SORT_STRING);

        if ($actualExports !== $expectedExports) {
            throw new RuntimeException(sprintf(
                "Manifest export mismatch.\nExpected: %s\nActual: %s",
                implode(', ', $expectedExports),
                implode(', ', $actualExports)
            ));
        }

        $canonicalManifest = $this->canonicalJson($manifest);
        $guardConstants = [];

        foreach ($orderedSources as $sourcePath) {
            $guardConstants = array_merge(
                $guardConstants,
                $this->extractGuardConstants($this->sources[$sourcePath], $sourcePath)
            );
        }

        $guardConstants = array_values(array_unique($guardConstants));
        sort($guardConstants, SORT_STRING);
        $sourceInputHash = $this->createSourceInputHash(
            $canonicalManifest,
            $orderedSources
        );
        $artifact = $this->renderArtifact(
            $orderedSources,
            $sourceInputHash,
            $actualExports,
            $guardConstants
        );
        $artifactHash = hash('sha256', $artifact);
        $outputPath = $manifest['output'];
        $sidecarBase = substr($outputPath, 0, -4);

        $sourceMap = [
            'formatVersion' => 1,
            'manifest' => $manifestPath,
            'manifestFormatVersion' => $manifest['formatVersion'],
            'name' => $manifest['name'],
            'phpMinimum' => $manifest['phpMinimum'],
            'orderedSources' => array_map(
                fn (string $path): array => [
                    'path' => $path,
                    'sha256' => hash('sha256', $this->sources[$path]),
                ],
                $orderedSources
            ),
            'sourceInputHash' => $sourceInputHash,
            'artifact' => [
                'path' => $outputPath,
                'sha256' => $artifactHash,
            ],
            'frameworkVersion' => SAEF_SYMCON_BUNDLE_FRAMEWORK_VERSION,
            'builderVersion' => SAEF_SYMCON_BUNDLE_BUILDER_VERSION,
            'license' => [
                'identifier' => SAEF_SYMCON_BUNDLE_LICENSE,
                'url' => SAEF_SYMCON_BUNDLE_LICENSE_URL,
            ],
        ];

        return [
            $outputPath => $artifact,
            $outputPath . '.sha256' => $artifactHash . '  ' . basename($outputPath) . "\n",
            $sidecarBase . '.sources.json' => $this->prettyJson($sourceMap),
        ];
    }

    /**
     * @return array{0: array{
     *     formatVersion: int,
     *     name: string,
     *     entries: list<string>,
     *     phpMinimum: string,
     *     output: string,
     *     exports: list<string>
     * }, 1: string}
     */
    private function loadManifest(string $manifestArgument): array
    {
        $manifestAbsolute = $this->absoluteInputPath($manifestArgument);
        $manifestPath = $this->relativeProjectPath($manifestAbsolute);
        $contents = file_get_contents($manifestAbsolute);

        if ($contents === false) {
            throw new RuntimeException('Cannot read manifest: ' . $manifestPath);
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Invalid bundle manifest JSON: ' . $exception->getMessage(),
                0,
                $exception
            );
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Bundle manifest must contain a JSON object.');
        }

        $requiredFields = [
            'formatVersion',
            'name',
            'entries',
            'phpMinimum',
            'output',
            'exports',
        ];

        if (array_keys($decoded) !== $requiredFields) {
            throw new RuntimeException(
                'Bundle manifest fields or their order do not match the formatVersion 1 contract.'
            );
        }

        if ($decoded['formatVersion'] !== 1) {
            throw new RuntimeException('Unsupported bundle manifest formatVersion.');
        }

        if (!is_string($decoded['name']) || !preg_match('/^[a-z][a-z0-9-]*$/', $decoded['name'])) {
            throw new RuntimeException('Bundle name must be a lowercase kebab-case identifier.');
        }

        if (!is_string($decoded['phpMinimum']) || !preg_match('/^\d+\.\d+$/', $decoded['phpMinimum'])) {
            throw new RuntimeException('phpMinimum must use major.minor format.');
        }

        if (!is_array($decoded['entries']) || $decoded['entries'] === []) {
            throw new RuntimeException('Bundle entries must be a non-empty list.');
        }

        $entries = [];

        foreach ($decoded['entries'] as $entry) {
            if (!is_string($entry)) {
                throw new RuntimeException('Every bundle entry must be a string.');
            }

            $entry = $this->validateRelativePath($entry, 'helpers/', '.php');
            $entries[] = $entry;
        }

        if (count($entries) !== count(array_unique($entries))) {
            throw new RuntimeException('Bundle entries must not contain duplicates.');
        }

        if (!is_string($decoded['output'])) {
            throw new RuntimeException('Bundle output must be a string.');
        }

        $output = $this->validateRelativePath($decoded['output'], 'dist/symcon/', '.php');

        if (!is_array($decoded['exports']) || $decoded['exports'] === []) {
            throw new RuntimeException('Bundle exports must be a non-empty list.');
        }

        $exports = [];

        foreach ($decoded['exports'] as $export) {
            if (!is_string($export) || !preg_match('/^SAEF_[A-Za-z0-9_]+$/', $export)) {
                throw new RuntimeException('Every bundle export must be a valid SAEF function name.');
            }

            $exports[] = $export;
        }

        if (count($exports) !== count(array_unique($exports))) {
            throw new RuntimeException('Bundle exports must not contain duplicates.');
        }

        $sortedExports = $exports;
        sort($sortedExports, SORT_STRING);

        if ($exports !== $sortedExports) {
            throw new RuntimeException('Bundle exports must be sorted and unique.');
        }

        /** @var array{
         *     formatVersion: int,
         *     name: string,
         *     entries: list<string>,
         *     phpMinimum: string,
         *     output: string,
         *     exports: list<string>
         * } $manifest
         */
        $manifest = [
            'formatVersion' => 1,
            'name' => $decoded['name'],
            'entries' => $entries,
            'phpMinimum' => $decoded['phpMinimum'],
            'output' => $output,
            'exports' => $exports,
        ];

        return [$manifest, $manifestPath];
    }

    private function visit(string $sourcePath): void
    {
        $state = $this->visitState[$sourcePath] ?? 0;

        if ($state === 2) {
            return;
        }

        if ($state === 1) {
            throw new RuntimeException('Cyclic helper dependency detected at: ' . $sourcePath);
        }

        $this->visitState[$sourcePath] = 1;
        $absolutePath = $this->projectRoot . '/' . $sourcePath;
        $resolvedPath = realpath($absolutePath);

        if (
            $resolvedPath === false
            || $this->relativeProjectPath($resolvedPath) !== $sourcePath
        ) {
            throw new RuntimeException(
                'Helper source must resolve to its canonical path inside the project root: ' . $sourcePath
            );
        }

        $source = file_get_contents($resolvedPath);

        if ($source === false) {
            throw new RuntimeException('Cannot read helper source: ' . $sourcePath);
        }

        $this->assertLfSource($source, $sourcePath);
        $dependencies = $this->discoverDependencies($source, $sourcePath);
        sort($dependencies, SORT_STRING);
        $this->sources[$sourcePath] = $source;
        $this->dependencies[$sourcePath] = $dependencies;

        foreach ($dependencies as $dependency) {
            $this->visit($dependency);
        }

        $this->visitState[$sourcePath] = 2;
        $this->orderedSources[] = $sourcePath;
    }

    /** @return list<string> */
    private function discoverDependencies(string $source, string $sourcePath): array
    {
        $tokens = token_get_all($source, TOKEN_PARSE);
        $dependencies = [];
        $depth = 0;

        foreach ($tokens as $index => $token) {
            if ($token === '{') {
                $depth++;
                continue;
            }

            if ($token === '}') {
                $depth--;
                continue;
            }

            if (!is_array($token)) {
                continue;
            }

            if (in_array($token[0], [T_INCLUDE, T_INCLUDE_ONCE], true)) {
                throw new RuntimeException('Includes are not supported in bundle source: ' . $sourcePath);
            }

            if (!in_array($token[0], [T_REQUIRE, T_REQUIRE_ONCE], true)) {
                continue;
            }

            if ($depth !== 0) {
                throw new RuntimeException('Nested requires are not supported in bundle source: ' . $sourcePath);
            }

            [$dependency, $endIndex] = $this->parseDependencyStatement($tokens, $index, $sourcePath);
            unset($endIndex);
            $dependencies[] = $dependency;
        }

        return array_values(array_unique($dependencies));
    }

    /**
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     *
     * @return array{0: string, 1: int}
     */
    private function parseDependencyStatement(array $tokens, int $startIndex, string $sourcePath): array
    {
        $significant = [];
        $endIndex = $startIndex;

        for ($index = $startIndex; $index < count($tokens); $index++) {
            $token = $tokens[$index];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $significant[] = $token;
            $endIndex = $index;

            if ($token === ';') {
                break;
            }
        }

        if (count($significant) !== 5 || $significant[4] !== ';') {
            throw new RuntimeException('Dynamic require is not supported in bundle source: ' . $sourcePath);
        }

        if (
            !is_array($significant[1])
            || $significant[1][0] !== T_DIR
            || $significant[2] !== '.'
            || !is_array($significant[3])
            || $significant[3][0] !== T_CONSTANT_ENCAPSED_STRING
        ) {
            throw new RuntimeException('Require must use __DIR__ and one literal path: ' . $sourcePath);
        }

        $literal = $this->decodeSingleQuotedPhpString($significant[3][1], $sourcePath);
        $dependency = $this->normalizeRelativePath(dirname($sourcePath) . '/' . $literal);
        $dependency = $this->validateRelativePath($dependency, 'helpers/', '.php');

        if (!is_file($this->projectRoot . '/' . $dependency)) {
            throw new RuntimeException('Required helper source does not exist: ' . $dependency);
        }

        return [$dependency, $endIndex];
    }

    /**
     * @param list<string> $orderedSources
     * @param list<string> $exports
     * @param list<string> $guardConstants
     */
    private function renderArtifact(
        array $orderedSources,
        string $sourceInputHash,
        array $exports,
        array $guardConstants
    ): string {
        $sourceLines = array_map(
            static fn (string $path): string => ' * - ' . $path,
            $orderedSources
        );

        $functionList = implode(
            "\n",
            array_map(static fn (string $name): string => "    '" . $name . "',", $exports)
        );
        $constantList = implode(
            "\n",
            array_map(static fn (string $name): string => "    '" . $name . "',", $guardConstants)
        );

        $header = implode("\n", [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            '/**',
            ' * GENERATED FILE — DO NOT EDIT.',
            ' *',
            ' * Canonical SAEF sources:',
            ...$sourceLines,
            ' *',
            ' * Source input SHA-256: ' . $sourceInputHash,
            ' * SAEF version: ' . SAEF_SYMCON_BUNDLE_FRAMEWORK_VERSION,
            ' * Builder version: ' . SAEF_SYMCON_BUNDLE_BUILDER_VERSION,
            ' * SPDX-License-Identifier: ' . SAEF_SYMCON_BUNDLE_LICENSE,
            ' * License: ' . SAEF_SYMCON_BUNDLE_LICENSE_URL,
            ' */',
            '',
            '$saefBundleConflicts = [];',
            '',
            'foreach ([',
            $functionList,
            '] as $saefBundleFunction) {',
            '    if (function_exists($saefBundleFunction)) {',
            "        \$saefBundleConflicts[] = 'function ' . \$saefBundleFunction;",
            '    }',
            '}',
            '',
            'foreach ([',
            $constantList,
            '] as $saefBundleConstant) {',
            '    if (defined($saefBundleConstant)) {',
            "        \$saefBundleConflicts[] = 'constant ' . \$saefBundleConstant;",
            '    }',
            '}',
            '',
            'if ($saefBundleConflicts !== []) {',
            "    throw new RuntimeException('SAEF bundle namespace conflict: ' . implode(', ', \$saefBundleConflicts));",
            '}',
            '',
            'unset($saefBundleConflicts, $saefBundleFunction, $saefBundleConstant);',
            '',
        ]);

        $parts = [];

        foreach ($orderedSources as $sourcePath) {
            $parts[] = $this->transformSource(
                $this->sources[$sourcePath],
                $sourcePath,
                $this->dependencies[$sourcePath]
            );
        }

        return rtrim($header . implode("\n\n", $parts)) . "\n";
    }

    /** @param list<string> $expectedDependencies */
    private function transformSource(string $source, string $sourcePath, array $expectedDependencies): string
    {
        $tokens = token_get_all($source, TOKEN_PARSE);
        $this->assertApprovedTopLevel($tokens, $sourcePath);
        $skip = [];
        $foundDependencies = [];
        $depth = 0;

        foreach ($tokens as $index => $token) {
            if ($token === '{') {
                $depth++;
                continue;
            }

            if ($token === '}') {
                $depth--;
                continue;
            }

            if (is_array($token) && $token[0] === T_CLOSE_TAG) {
                throw new RuntimeException('Closing PHP tags are not supported: ' . $sourcePath);
            }

            if (
                is_array($token)
                && $token[0] === T_INLINE_HTML
                && trim($token[1]) !== ''
            ) {
                throw new RuntimeException('Inline non-PHP content is not supported: ' . $sourcePath);
            }

            if (!is_array($token) || $depth !== 0) {
                continue;
            }

            if ($token[0] === T_OPEN_TAG) {
                $skip[$index] = true;
                continue;
            }

            if ($token[0] === T_DECLARE) {
                $endIndex = $this->findStatementEnd($tokens, $index, $sourcePath);
                $statement = $this->tokensToString(array_slice($tokens, $index, $endIndex - $index + 1));

                if (preg_replace('/\s+/', '', $statement) !== 'declare(strict_types=1);') {
                    throw new RuntimeException('Only declare(strict_types=1) is supported: ' . $sourcePath);
                }

                for ($remove = $index; $remove <= $endIndex; $remove++) {
                    $skip[$remove] = true;
                }

                continue;
            }

            if (in_array($token[0], [T_REQUIRE, T_REQUIRE_ONCE], true)) {
                [$dependency, $endIndex] = $this->parseDependencyStatement($tokens, $index, $sourcePath);
                $foundDependencies[] = $dependency;

                for ($remove = $index; $remove <= $endIndex; $remove++) {
                    $skip[$remove] = true;
                }
            }
        }

        sort($foundDependencies, SORT_STRING);
        sort($expectedDependencies, SORT_STRING);

        if ($foundDependencies !== $expectedDependencies) {
            throw new RuntimeException('Dependency transformation mismatch in: ' . $sourcePath);
        }

        $result = '';

        foreach ($tokens as $index => $token) {
            if (isset($skip[$index])) {
                continue;
            }

            $result .= is_array($token) ? $token[1] : $token;
        }

        return trim($result);
    }

    /**
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function assertApprovedTopLevel(array $tokens, string $sourcePath): void
    {
        $depth = 0;
        $allowedTrivia = [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];

        for ($index = 0; $index < count($tokens); $index++) {
            $token = $tokens[$index];

            if ($token === '{') {
                $depth++;
                continue;
            }

            if ($token === '}') {
                $depth--;

                if ($depth < 0) {
                    throw new RuntimeException('Unbalanced top-level block in: ' . $sourcePath);
                }

                continue;
            }

            if ($depth !== 0) {
                continue;
            }

            if (!is_array($token)) {
                if (trim($token) !== '') {
                    throw new RuntimeException('Unexpected top-level syntax in: ' . $sourcePath);
                }

                continue;
            }

            if (in_array($token[0], $allowedTrivia, true)) {
                continue;
            }

            if ($token[0] === T_DECLARE) {
                $index = $this->findStatementEnd($tokens, $index, $sourcePath);
                continue;
            }

            if (in_array($token[0], [T_REQUIRE, T_REQUIRE_ONCE], true)) {
                [, $index] = $this->parseDependencyStatement($tokens, $index, $sourcePath);
                continue;
            }

            if ($token[0] !== T_IF) {
                throw new RuntimeException('Unexpected executable top-level logic in: ' . $sourcePath);
            }

            $headerEnd = $this->findOpeningBrace($tokens, $index, $sourcePath);
            $blockEnd = $this->findClosingBrace($tokens, $headerEnd, $sourcePath);
            $header = $this->tokensToString(array_slice($tokens, $index, $headerEnd - $index + 1));
            $normalizedHeader = preg_replace('/\s+/', '', $header);
            $matches = [];

            if (
                $normalizedHeader === null
                || !preg_match("/^if\(!defined\('([A-Z][A-Z0-9_]*)'\)\)\{$/", $normalizedHeader, $matches)
            ) {
                throw new RuntimeException('Top-level blocks must be literal SAEF guard blocks: ' . $sourcePath);
            }

            $this->assertApprovedGuardBody(
                $tokens,
                $headerEnd,
                $blockEnd,
                $matches[1],
                $sourcePath
            );
            $index = $blockEnd;
        }

        if ($depth !== 0) {
            throw new RuntimeException('Unbalanced top-level block in: ' . $sourcePath);
        }
    }

    /**
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function assertApprovedGuardBody(
        array $tokens,
        int $blockStart,
        int $blockEnd,
        string $guardConstant,
        string $sourcePath
    ): void {
        $guardDefined = false;
        $functionCount = 0;

        for ($index = $blockStart + 1; $index < $blockEnd; $index++) {
            $token = $tokens[$index];

            if (
                is_array($token)
                && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
            ) {
                continue;
            }

            if (is_array($token) && $token[0] === T_STRING && strtolower($token[1]) === 'define') {
                $statementEnd = $this->findStatementEnd($tokens, $index, $sourcePath);
                $statement = $this->tokensToString(
                    array_slice($tokens, $index, $statementEnd - $index + 1)
                );
                $normalizedStatement = preg_replace('/\s+/', '', $statement);

                if (
                    $guardDefined
                    || $normalizedStatement !== "define('" . $guardConstant . "',true);"
                ) {
                    throw new RuntimeException(
                        'Guard block must define its own constant exactly once: ' . $sourcePath
                    );
                }

                $guardDefined = true;
                $index = $statementEnd;
                continue;
            }

            if (is_array($token) && $token[0] === T_FUNCTION) {
                $this->assertNamedFunction($tokens, $index, $sourcePath);
                $functionStart = $this->findOpeningBrace($tokens, $index, $sourcePath);
                $index = $this->findClosingBrace($tokens, $functionStart, $sourcePath);
                $functionCount++;
                continue;
            }

            throw new RuntimeException(
                'Guard blocks may contain only their guard definition and named functions: ' . $sourcePath
            );
        }

        if (!$guardDefined || $functionCount === 0) {
            throw new RuntimeException(
                'Guard block must define its constant and at least one named function: ' . $sourcePath
            );
        }
    }

    /**
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function assertNamedFunction(array $tokens, int $startIndex, string $sourcePath): void
    {
        for ($index = $startIndex + 1; $index < count($tokens); $index++) {
            $token = $tokens[$index];

            if (is_array($token) && $token[0] === T_STRING) {
                return;
            }

            if ($token === '(') {
                break;
            }
        }

        throw new RuntimeException('Anonymous functions are not allowed in a guard block: ' . $sourcePath);
    }

    /** @return list<string> */
    private function extractFunctions(string $source): array
    {
        $tokens = token_get_all($source, TOKEN_PARSE);
        $functions = [];

        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_FUNCTION) {
                continue;
            }

            for ($next = $index + 1; $next < count($tokens); $next++) {
                $candidate = $tokens[$next];

                if (is_array($candidate) && $candidate[0] === T_STRING) {
                    $functions[] = $candidate[1];
                    break;
                }

                if ($candidate === '(') {
                    break;
                }
            }
        }

        return $functions;
    }

    /** @return list<string> */
    private function extractGuardConstants(string $source, string $sourcePath): array
    {
        $tokens = token_get_all($source, TOKEN_PARSE);
        $constants = [];
        $depth = 0;

        for ($index = 0; $index < count($tokens); $index++) {
            $token = $tokens[$index];

            if ($token === '{') {
                $depth++;
                continue;
            }

            if ($token === '}') {
                $depth--;
                continue;
            }

            if (!is_array($token) || $depth !== 0 || $token[0] !== T_IF) {
                continue;
            }

            $headerEnd = $this->findOpeningBrace($tokens, $index, $sourcePath);
            $header = $this->tokensToString(array_slice($tokens, $index, $headerEnd - $index + 1));
            $normalizedHeader = preg_replace('/\s+/', '', $header);
            $matches = [];

            if (
                $normalizedHeader === null
                || !preg_match("/^if\(!defined\('([A-Z][A-Z0-9_]*)'\)\)\{$/", $normalizedHeader, $matches)
            ) {
                throw new RuntimeException('Cannot extract top-level guard constant from: ' . $sourcePath);
            }

            $constants[] = $matches[1];
            $depth = 1;
            $index = $headerEnd;
        }

        return $constants;
    }

    private function createSourceInputHash(string $canonicalManifest, array $orderedSources): string
    {
        $context = hash_init('sha256');
        hash_update($context, "SAEF-SYMCON-BUNDLE\0" . SAEF_SYMCON_BUNDLE_BUILDER_VERSION . "\0");
        hash_update($context, strlen($canonicalManifest) . "\0" . $canonicalManifest);

        foreach ($orderedSources as $path) {
            $source = $this->sources[$path];
            hash_update($context, strlen($path) . "\0" . $path);
            hash_update($context, strlen($source) . "\0" . $source);
        }

        return hash_final($context);
    }

    private function canonicalJson(array $value): string
    {
        $normalized = $this->sortJsonValue($value);

        return json_encode(
            $normalized,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    private function prettyJson(array $value): string
    {
        return json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) . "\n";
    }

    private function sortJsonValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        foreach ($value as $key => $child) {
            $value[$key] = $this->sortJsonValue($child);
        }

        return $value;
    }

    private function absoluteInputPath(string $path): string
    {
        $candidate = str_starts_with($path, '/') ? $path : getcwd() . '/' . $path;
        $resolved = realpath($candidate);

        if ($resolved === false || !is_file($resolved)) {
            throw new RuntimeException('Manifest does not exist: ' . $path);
        }

        $this->relativeProjectPath($resolved);

        return str_replace('\\', '/', $resolved);
    }

    private function relativeProjectPath(string $absolutePath): string
    {
        $normalized = str_replace('\\', '/', $absolutePath);
        $prefix = $this->projectRoot . '/';

        if (!str_starts_with($normalized, $prefix)) {
            throw new RuntimeException('Path is outside the SAEF project root.');
        }

        return substr($normalized, strlen($prefix));
    }

    private function validateRelativePath(string $path, string $prefix, string $suffix): string
    {
        if (
            $path === ''
            || str_contains($path, '\\')
            || str_contains($path, '://')
            || str_starts_with($path, '/')
        ) {
            throw new RuntimeException('Invalid repository-relative path: ' . $path);
        }

        $normalized = $this->normalizeRelativePath($path);

        if (
            $normalized !== $path
            || !str_starts_with($normalized, $prefix)
            || !str_ends_with($normalized, $suffix)
        ) {
            throw new RuntimeException('Path is outside its allowed bundle area: ' . $path);
        }

        return $normalized;
    }

    private function normalizeRelativePath(string $path): string
    {
        $parts = [];

        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                if ($parts === []) {
                    throw new RuntimeException('Path traversal escapes the project root: ' . $path);
                }

                array_pop($parts);
                continue;
            }

            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    private function decodeSingleQuotedPhpString(string $literal, string $sourcePath): string
    {
        if (
            strlen($literal) < 2
            || $literal[0] !== "'"
            || $literal[strlen($literal) - 1] !== "'"
        ) {
            throw new RuntimeException('Dependency path must use a single-quoted literal: ' . $sourcePath);
        }

        $value = substr($literal, 1, -1);

        return str_replace(["\\\\", "\\'"], ["\\", "'"], $value);
    }

    private function assertLfSource(string $source, string $sourcePath): void
    {
        if (str_contains($source, "\r")) {
            throw new RuntimeException('Helper source must use LF line endings: ' . $sourcePath);
        }
    }

    /**
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function findStatementEnd(array $tokens, int $startIndex, string $sourcePath): int
    {
        for ($index = $startIndex; $index < count($tokens); $index++) {
            if ($tokens[$index] === ';') {
                return $index;
            }
        }

        throw new RuntimeException('Unterminated statement in: ' . $sourcePath);
    }

    /**
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function findOpeningBrace(array $tokens, int $startIndex, string $sourcePath): int
    {
        for ($index = $startIndex; $index < count($tokens); $index++) {
            if ($tokens[$index] === '{') {
                return $index;
            }

            if ($tokens[$index] === ';') {
                break;
            }
        }

        throw new RuntimeException('Expected guarded top-level block in: ' . $sourcePath);
    }

    /**
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function findClosingBrace(array $tokens, int $startIndex, string $sourcePath): int
    {
        $depth = 0;

        for ($index = $startIndex; $index < count($tokens); $index++) {
            if ($tokens[$index] === '{') {
                $depth++;
                continue;
            }

            if ($tokens[$index] !== '}') {
                continue;
            }

            $depth--;

            if ($depth === 0) {
                return $index;
            }
        }

        throw new RuntimeException('Unterminated guarded block in: ' . $sourcePath);
    }

    /**
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function tokensToString(array $tokens): string
    {
        $result = '';

        foreach ($tokens as $token) {
            $result .= is_array($token) ? $token[1] : $token;
        }

        return $result;
    }
}

/** @return array{manifest: string, check: bool, outputRoot: string} */
function parseArguments(array $arguments, string $projectRoot): array
{
    array_shift($arguments);
    $manifest = '';
    $check = false;
    $outputRoot = $projectRoot;

    foreach ($arguments as $argument) {
        if ($argument === '--check') {
            $check = true;
            continue;
        }

        if (str_starts_with($argument, '--output-root=')) {
            $outputRoot = substr($argument, strlen('--output-root='));
            continue;
        }

        if (str_starts_with($argument, '--')) {
            throw new InvalidArgumentException('Unknown option: ' . $argument);
        }

        if ($manifest !== '') {
            throw new InvalidArgumentException('Only one bundle manifest may be built at a time.');
        }

        $manifest = $argument;
    }

    if ($manifest === '') {
        throw new InvalidArgumentException(
            'Usage: php tools/build-symcon-bundle.php [--check] [--output-root=PATH] MANIFEST'
        );
    }

    if ($check && $outputRoot !== $projectRoot) {
        throw new InvalidArgumentException('--check cannot be combined with --output-root.');
    }

    if ($outputRoot === '' || !str_starts_with($outputRoot, '/')) {
        throw new InvalidArgumentException('--output-root must be an absolute path.');
    }

    return [
        'manifest' => $manifest,
        'check' => $check,
        'outputRoot' => rtrim($outputRoot, '/'),
    ];
}

/** @param array<string, string> $outputs */
function writeOutputs(array $outputs, string $outputRoot): void
{
    foreach ($outputs as $relativePath => $contents) {
        $absolutePath = $outputRoot . '/' . $relativePath;
        $directory = dirname($absolutePath);

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('Cannot create output directory: ' . $directory);
        }

        $temporaryPath = $directory . '/.' . basename($absolutePath) . '.tmp.' . getmypid();

        if (file_put_contents($temporaryPath, $contents) !== strlen($contents)) {
            throw new RuntimeException('Cannot write generated output: ' . $relativePath);
        }

        if (!rename($temporaryPath, $absolutePath)) {
            throw new RuntimeException('Cannot replace generated output: ' . $relativePath);
        }
    }
}

/** @param array<string, string> $outputs */
function checkOutputs(array $outputs, string $projectRoot): void
{
    $drift = [];

    foreach ($outputs as $relativePath => $contents) {
        $absolutePath = $projectRoot . '/' . $relativePath;
        $trackedContents = is_file($absolutePath) ? file_get_contents($absolutePath) : false;

        if ($trackedContents !== $contents) {
            $drift[] = $relativePath;
        }
    }

    if ($drift !== []) {
        throw new RuntimeException(
            "Generated bundle artifacts are missing or stale:\n- " . implode("\n- ", $drift)
        );
    }
}

try {
    $projectRoot = str_replace('\\', '/', dirname(__DIR__));
    $options = parseArguments($argv, $projectRoot);
    $builder = new SaefSymconBundleBuilder($projectRoot);
    $outputs = $builder->build($options['manifest']);

    if ($options['check']) {
        checkOutputs($outputs, $projectRoot);
        fwrite(STDOUT, "Symcon bundle artifacts are current.\n");
    } else {
        writeOutputs($outputs, $options['outputRoot']);
        fwrite(STDOUT, 'Built ' . count($outputs) . " Symcon bundle artifacts.\n");
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Bundle build failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
