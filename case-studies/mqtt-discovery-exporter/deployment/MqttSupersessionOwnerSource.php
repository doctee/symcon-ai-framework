<?php

declare(strict_types=1);

namespace SAEF\CaseStudy\MqttDiscoveryExporter\Deployment;

use InvalidArgumentException;
use RuntimeException;

/**
 * Builds the deliberately small owner-source delta required by the
 * latest-command-wins runtime. This is a case-study-local migration helper,
 * not a general PHP rewriter.
 */
final class MqttSupersessionOwnerSource
{
    private const RUNTIME_CLASS = 'MqttDiscoveryExporterRuntime';
    private const CORE_CLASS = 'MqttDiscoveryExporterCore';

    /**
     * @return array{
     *     originalSource: string,
     *     originalSourceSha256: string,
     *     candidateSource: string,
     *     candidateSourceSha256: string,
     *     configurationLoaderBody: string,
     *     configurationLoaderSha256: string
     * }
     */
    public static function prepare(string $source): array
    {
        if ($source === '' || !str_starts_with($source, '<?php')) {
            throw new InvalidArgumentException('Owner source must be a complete PHP file.');
        }
        if (str_contains($source, "\0")) {
            throw new InvalidArgumentException('Owner source contains a NUL byte.');
        }

        $candidate = self::addEventValueSnapshot($source);
        $loader = self::extractConfigurationLoaderBody($source);

        return [
            'originalSource' => $source,
            'originalSourceSha256' => hash('sha256', $source),
            'candidateSource' => $candidate,
            'candidateSourceSha256' => hash('sha256', $candidate),
            'configurationLoaderBody' => $loader,
            'configurationLoaderSha256' => hash('sha256', $loader),
        ];
    }

    public static function addEventValueSnapshot(string $source): string
    {
        $tokens = self::tokensWithOffsets($source);
        $calls = [];

        for ($index = 0; $index < count($tokens); ++$index) {
            if (!self::tokenMatches($tokens[$index], T_STRING, self::RUNTIME_CLASS)) {
                continue;
            }

            $doubleColon = self::nextSignificantIndex($tokens, $index + 1);
            $method = self::nextSignificantIndex($tokens, $doubleColon + 1);
            $open = self::nextSignificantIndex($tokens, $method + 1);
            if (
                !self::tokenMatches($tokens[$doubleColon], T_DOUBLE_COLON)
                || !self::tokenMatches($tokens[$method], T_STRING, 'dispatchTriggeredVariable')
                || $tokens[$open]['text'] !== '('
            ) {
                continue;
            }

            $calls[] = self::inspectCall($tokens, $open);
        }

        if (count($calls) !== 1) {
            throw new RuntimeException('Owner source must contain exactly one exporter dispatch call.');
        }

        $call = $calls[0];
        if ($call['argumentCount'] !== 3) {
            throw new RuntimeException('Owner dispatch call is not the historical three-argument contract.');
        }

        $lastArgument = $tokens[$call['lastArgumentToken']];
        $indent = self::lineIndentAt($source, $lastArgument['offset']);
        $insertion = ",\n" . $indent . "\$_IPS['VALUE'] ?? null";
        $candidate = substr($source, 0, $lastArgument['end'])
            . $insertion
            . substr($source, $lastArgument['end']);

        self::assertCandidateCall($candidate);

        return $candidate;
    }

    public static function extractConfigurationLoaderBody(string $source): string
    {
        $tokens = self::tokensWithOffsets($source);
        $assignmentIndex = null;
        $callOpenIndex = null;

        for ($index = 0; $index < count($tokens); ++$index) {
            if (!self::tokenMatches($tokens[$index], T_VARIABLE, '$configuration')) {
                continue;
            }
            $equals = self::nextSignificantIndexOrNull($tokens, $index + 1);
            if ($equals === null) {
                continue;
            }
            $core = self::nextSignificantIndexOrNull($tokens, $equals + 1);
            if ($core === null) {
                continue;
            }
            $doubleColon = self::nextSignificantIndexOrNull($tokens, $core + 1);
            if ($doubleColon === null) {
                continue;
            }
            $method = self::nextSignificantIndexOrNull($tokens, $doubleColon + 1);
            if ($method === null) {
                continue;
            }
            $open = self::nextSignificantIndexOrNull($tokens, $method + 1);
            if ($open === null) {
                continue;
            }
            if (
                $tokens[$equals]['text'] === '='
                && self::tokenMatches($tokens[$core], T_STRING, self::CORE_CLASS)
                && self::tokenMatches($tokens[$doubleColon], T_DOUBLE_COLON)
                && self::tokenMatches($tokens[$method], T_STRING, 'normalizeConfiguration')
                && $tokens[$open]['text'] === '('
            ) {
                if ($assignmentIndex !== null) {
                    throw new RuntimeException('Owner source contains more than one configuration assignment.');
                }
                $assignmentIndex = $index;
                $callOpenIndex = $open;
            }
        }

        if ($assignmentIndex === null || $callOpenIndex === null) {
            throw new RuntimeException('Owner configuration assignment was not found.');
        }

        $call = self::inspectCall($tokens, $callOpenIndex);
        $semicolon = self::nextSignificantIndex($tokens, $call['closeToken'] + 1);
        if ($tokens[$semicolon]['text'] !== ';') {
            throw new RuntimeException('Owner configuration assignment is not terminated.');
        }

        $bodyStart = self::loaderBodyStart($tokens, $assignmentIndex);
        $body = substr(
            $source,
            $bodyStart,
            $tokens[$semicolon]['end'] - $bodyStart
        );
        $body = ltrim($body, "\r\n");
        if ($body === '') {
            throw new RuntimeException('Owner configuration loader is empty.');
        }

        self::assertReadOnlyLoader($body);

        return $body . "\nreturn \$configuration;";
    }

    /**
     * @param list<array{id: int|null, text: string, offset: int, end: int}> $tokens
     *
     * @return array{argumentCount: int, closeToken: int, lastArgumentToken: int}
     */
    private static function inspectCall(array $tokens, int $openIndex): array
    {
        $parentheses = 1;
        $brackets = 0;
        $braces = 0;
        $commas = 0;
        $lastSignificant = $openIndex;

        for ($index = $openIndex + 1; $index < count($tokens); ++$index) {
            $text = $tokens[$index]['text'];
            if ($text === '(') {
                ++$parentheses;
            } elseif ($text === ')') {
                --$parentheses;
                if ($parentheses === 0) {
                    if ($lastSignificant === $openIndex) {
                        return [
                            'argumentCount' => 0,
                            'closeToken' => $index,
                            'lastArgumentToken' => $openIndex,
                        ];
                    }

                    return [
                        'argumentCount' => $commas + 1,
                        'closeToken' => $index,
                        'lastArgumentToken' => $lastSignificant,
                    ];
                }
            } elseif ($text === '[') {
                ++$brackets;
            } elseif ($text === ']') {
                --$brackets;
            } elseif ($text === '{') {
                ++$braces;
            } elseif ($text === '}') {
                --$braces;
            } elseif (
                $text === ','
                && $parentheses === 1
                && $brackets === 0
                && $braces === 0
            ) {
                ++$commas;
            }

            if (!self::isIgnoredToken($tokens[$index])) {
                $lastSignificant = $index;
            }
            if ($brackets < 0 || $braces < 0) {
                break;
            }
        }

        throw new RuntimeException('Owner source contains an unterminated method call.');
    }

    private static function assertCandidateCall(string $source): void
    {
        $tokens = self::tokensWithOffsets($source);
        $matchingCalls = 0;

        for ($index = 0; $index < count($tokens); ++$index) {
            if (!self::tokenMatches($tokens[$index], T_STRING, self::RUNTIME_CLASS)) {
                continue;
            }
            $doubleColon = self::nextSignificantIndex($tokens, $index + 1);
            $method = self::nextSignificantIndex($tokens, $doubleColon + 1);
            $open = self::nextSignificantIndex($tokens, $method + 1);
            if (
                !self::tokenMatches($tokens[$doubleColon], T_DOUBLE_COLON)
                || !self::tokenMatches($tokens[$method], T_STRING, 'dispatchTriggeredVariable')
                || $tokens[$open]['text'] !== '('
            ) {
                continue;
            }
            $call = self::inspectCall($tokens, $open);
            if ($call['argumentCount'] !== 4) {
                throw new RuntimeException('Candidate owner dispatch call does not have four arguments.');
            }
            ++$matchingCalls;
        }

        if ($matchingCalls !== 1 || substr_count($source, "\$_IPS['VALUE'] ?? null") !== 1) {
            throw new RuntimeException('Candidate owner event-value snapshot contract is invalid.');
        }
    }

    /**
     * @param list<array{id: int|null, text: string, offset: int, end: int}> $tokens
     */
    private static function loaderBodyStart(array $tokens, int $assignmentIndex): int
    {
        $start = 0;
        for ($index = 0; $index < $assignmentIndex; ++$index) {
            $id = $tokens[$index]['id'];
            if ($id !== T_OPEN_TAG && $id !== T_DECLARE && $id !== T_USE) {
                continue;
            }
            if ($id === T_OPEN_TAG) {
                $start = $tokens[$index]['end'];
                continue;
            }

            for ($cursor = $index + 1; $cursor < $assignmentIndex; ++$cursor) {
                if ($tokens[$cursor]['text'] === ';') {
                    $start = $tokens[$cursor]['end'];
                    $index = $cursor;
                    break;
                }
            }
        }

        return $start;
    }

    private static function assertReadOnlyLoader(string $body): void
    {
        $tokens = self::tokensWithOffsets("<?php\n" . $body);
        $allowedCalls = [
            'IPS_GetObjectIDByIdent',
            'IPS_VariableExists',
            'MqttDiscoveryExporterCore',
            'normalizeConfiguration',
            'RuntimeException',
        ];

        foreach ($tokens as $index => $token) {
            if ($token['id'] !== T_STRING) {
                continue;
            }
            $next = self::nextSignificantIndexOrNull($tokens, $index + 1);
            if ($next === null) {
                continue;
            }
            $isCall = $tokens[$next]['text'] === '(' || $tokens[$next]['id'] === T_DOUBLE_COLON;
            if ($isCall && !in_array($token['text'], $allowedCalls, true)) {
                throw new RuntimeException('Owner configuration loader contains an unapproved call.');
            }
        }

        foreach (
            [
                'RequestAction',
                'SetValue',
                'MQTT_Publish',
                'IPS_Create',
                'IPS_Delete',
                'IPS_Set',
                'IPS_Run',
                'IPS_Execute',
                'file_put_contents',
                'unlink',
                'rename',
            ] as $forbidden
        ) {
            if (stripos($body, $forbidden) !== false) {
                throw new RuntimeException('Owner configuration loader contains a mutating token.');
            }
        }
    }

    private static function lineIndentAt(string $source, int $offset): string
    {
        $lineStart = strrpos(substr($source, 0, $offset), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        $linePrefix = substr($source, $lineStart, $offset - $lineStart);
        if (preg_match('/^[ \t]*/', $linePrefix, $matches) !== 1) {
            return '';
        }

        return $matches[0];
    }

    /**
     * @return list<array{id: int|null, text: string, offset: int, end: int}>
     */
    private static function tokensWithOffsets(string $source): array
    {
        $rawTokens = token_get_all($source, TOKEN_PARSE);
        $tokens = [];
        $offset = 0;
        foreach ($rawTokens as $rawToken) {
            if (is_array($rawToken)) {
                $id = $rawToken[0];
                $text = $rawToken[1];
            } else {
                $id = null;
                $text = $rawToken;
            }
            $tokens[] = [
                'id' => $id,
                'text' => $text,
                'offset' => $offset,
                'end' => $offset + strlen($text),
            ];
            $offset += strlen($text);
        }

        return $tokens;
    }

    /**
     * @param array{id: int|null, text: string, offset: int, end: int} $token
     */
    private static function tokenMatches(array $token, int $id, ?string $text = null): bool
    {
        return $token['id'] === $id && ($text === null || $token['text'] === $text);
    }

    /**
     * @param list<array{id: int|null, text: string, offset: int, end: int}> $tokens
     */
    private static function nextSignificantIndex(array $tokens, int $start): int
    {
        $index = self::nextSignificantIndexOrNull($tokens, $start);
        if ($index === null) {
            throw new RuntimeException('Owner source ended unexpectedly.');
        }

        return $index;
    }

    /**
     * @param list<array{id: int|null, text: string, offset: int, end: int}> $tokens
     */
    private static function nextSignificantIndexOrNull(array $tokens, int $start): ?int
    {
        for ($index = $start; $index < count($tokens); ++$index) {
            if (!self::isIgnoredToken($tokens[$index])) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param array{id: int|null, text: string, offset: int, end: int} $token
     */
    private static function isIgnoredToken(array $token): bool
    {
        return in_array($token['id'], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
    }
}
