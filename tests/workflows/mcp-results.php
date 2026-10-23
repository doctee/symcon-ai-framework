<?php

declare(strict_types=1);

// Test-only executable specification. Never dispatches or authorizes an MCP call.
function evaluateExample(array $evidence): string
{
    $result = evaluateChangeExample($evidence);
    if ($evidence['phase'] === 'rollback') {
        return $result === 'verified' ? 'recovered' : 'recovery-required';
    }
    return $result;
}

function evaluateChangeExample(array $evidence): string
{
    if (
        $evidence['transportOk'] !== true
        || $evidence['complete'] !== true
        || $evidence['allContentReviewed'] !== true
        || $evidence['dispatched'] !== true
        || !is_bool($evidence['requiresLint'])
        || !is_bool($evidence['reloadRequired'])
        || !is_array($evidence['response'])
    ) {
        return 'uncertain';
    }
    $response = $evidence['response'];
    $toolFailed = false;
    $structuredSyntaxFailed = false;
    if ($evidence['channel'] === 'legacy') {
        foreach (['transportError', 'executionError', 'truncated'] as $field) {
            if (!array_key_exists($field, $response)) {
                return 'uncertain';
            }
        }
        if ($response['transportError'] !== null || $response['truncated'] !== false) {
            return 'uncertain';
        }
        $toolFailed = $response['executionError'] !== null;
    } elseif ($evidence['channel'] === 'native') {
        if (array_key_exists('isError', $response) && !is_bool($response['isError'])) {
            return 'uncertain';
        }
        if (empty($response['content']) && empty($response['structuredContent'])) {
            return 'uncertain';
        }
        $toolFailed = ($response['isError'] ?? false) === true;
        if ($evidence['nativeContract'] === 'module-write-structured') {
            $syntaxVerdict = evaluateStructuredSyntax($response, $evidence['requiresLint']);
            if ($syntaxVerdict === 'unknown') {
                return 'uncertain';
            }
            $structuredSyntaxFailed = $syntaxVerdict === 'fail';
        } elseif ($evidence['nativeContract'] !== 'text-reviewed') {
            return 'uncertain';
        }
    } else {
        return 'uncertain';
    }

    $lintFailed = $structuredSyntaxFailed || ($evidence['requiresLint'] === true
        && ($evidence['fileLint'] === 'fail' || $evidence['folderLint'] === 'fail'));
    if ($evidence['savedConfirmed'] === true && $lintFailed) {
        return 'written-invalid';
    }
    if (
        $toolFailed || $lintFailed || $evidence['semanticVerdict'] === 'fail'
        || $evidence['validation'] === 'fail' || $evidence['readback'] === 'mismatch'
        || $evidence['domainAcceptance'] === 'fail'
        || ($evidence['reloadRequired'] === true && $evidence['reloadVerdict'] === 'fail')
    ) {
        return 'failed';
    }
    if (
        $evidence['semanticVerdict'] !== 'pass'
        || $evidence['validation'] !== 'pass'
        || $evidence['readback'] !== 'match'
        || $evidence['domainAcceptance'] !== 'pass'
        || ($evidence['requiresLint'] === true
            && ($evidence['fileLint'] !== 'pass' || $evidence['folderLint'] !== 'pass'
                || $evidence['requiredFilesCovered'] !== true))
        || ($evidence['reloadRequired'] === true && $evidence['reloadVerdict'] !== 'pass')
    ) {
        return 'uncertain';
    }
    return 'verified';
}

// Synthetic write/edit syntax subset, not a live client or a write authorization.
function evaluateStructuredSyntax(array $response, bool $requiresLint): string
{
    $structured = $response['structuredContent'] ?? null;
    if (!is_array($structured) || !array_key_exists('syntax', $structured)) {
        return 'unknown';
    }
    $syntax = $structured['syntax'];
    if (!$requiresLint) {
        return $syntax === null ? 'pass' : 'unknown';
    }
    if (
        !is_array($syntax) || ($syntax['checked'] ?? null) !== true
        || !is_bool($syntax['ok'] ?? null) || ($syntax['unavailable'] ?? null) !== null
    ) {
        return 'unknown';
    }
    if ($syntax['ok'] === false) {
        return 'fail';
    }
    return ($syntax['error'] ?? null) === null && ($syntax['line'] ?? null) === null
        ? 'pass' : 'unknown';
}

// Coverage is a separate review conclusion, not a field supplied by the server.
function evaluateLintExample(array $response, bool $coverageVerified): string
{
    if (array_key_exists('isError', $response) && !is_bool($response['isError'])) {
        return 'unknown';
    }
    if (($response['isError'] ?? false) === true) {
        return 'fail';
    }
    $data = $response['structuredContent'] ?? null;
    if (
        !is_array($data) || !is_int($data['checked'] ?? null) || $data['checked'] < 1
        || !is_int($data['skipped'] ?? null) || $data['skipped'] < 0
        || !is_array($data['errors'] ?? null) || !array_is_list($data['errors'])
    ) {
        return 'unknown';
    }
    if ($data['errors'] !== []) {
        return 'fail';
    }
    return $coverageVerified ? 'pass' : 'unknown';
}

// Synthetic fixed-contract read model, not a production parser or MCP client.
function evaluateDirectReadExample(array $evidence): array
{
    $unknown = ['verdict' => 'uncertain'];
    foreach (['transportOk', 'complete', 'allContentReviewed', 'contractVerified'] as $gate) {
        if (($evidence[$gate] ?? null) !== true) {
            return $unknown;
        }
    }
    $response = $evidence['response'] ?? null;
    if (!is_array($response) || array_diff(array_keys($response), ['content', 'isError']) !== []) {
        return $unknown;
    }
    if (array_key_exists('isError', $response) && !is_bool($response['isError'])) {
        return $unknown;
    }
    if (($response['isError'] ?? false) === true) {
        return ['verdict' => 'failed'];
    }
    $content = $response['content'] ?? null;
    if (!is_array($content) || array_keys($content) !== [0] || !is_array($content[0])) {
        return $unknown;
    }
    $block = $content[0];
    if (
        count($block) !== 2 || ($block['type'] ?? null) !== 'text'
        || !is_string($block['text'] ?? null) || strlen($block['text']) > 2048
    ) {
        return $unknown;
    }
    $text = $block['text'];
    if (($evidence['contract'] ?? null) === 'known-string-text') {
        return ['verdict' => 'observed', 'value' => $text];
    }
    if (($evidence['contract'] ?? null) !== 'variable-metadata-json') {
        return $unknown;
    }
    try {
        $variable = json_decode($text, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return $unknown;
    }
    $expectedID = $evidence['expectedID'] ?? null;
    $expectedType = $evidence['expectedType'] ?? null;
    if (
        !is_int($expectedID) || $expectedID <= 0 || !is_int($expectedType)
        || !is_array($variable) || ($variable['VariableID'] ?? null) !== $expectedID
        || ($variable['VariableType'] ?? null) !== $expectedType
        || !array_key_exists('VariableValue', $variable)
    ) {
        return $unknown;
    }
    foreach (['VariableUpdated', 'VariableChanged'] as $field) {
        if (!is_int($variable[$field] ?? null) || $variable[$field] < 0) {
            return $unknown;
        }
    }
    $value = $variable['VariableValue'];
    // Frozen synthetic VariableType encodings from the documented contract.
    // JSON numbers may decode as int even for an integral float variable.
    $matches = match ($expectedType) {
        0 => is_bool($value),
        1 => is_int($value),
        2 => (is_int($value) || is_float($value)) && is_finite((float)$value),
        3 => is_string($value),
        default => false,
    };
    if (!$matches) {
        return $unknown;
    }
    return [
        'verdict' => 'observed', 'value' => $value,
        'updated' => $variable['VariableUpdated'], 'changed' => $variable['VariableChanged'],
    ];
}

$data = json_decode(
    file_get_contents(__DIR__ . '/mcp-result-cases.json'),
    true,
    512,
    JSON_THROW_ON_ERROR
);
$seen = [];
$checks = 0;
foreach ($data['cases'] as $case) {
    if (isset($seen[$case['id']])) {
        throw new RuntimeException('Duplicate fixture: ' . $case['id']);
    }
    $seen[$case['id']] = true;
    $evidence = array_replace($data['baseline'], $case['changes']);
    $actual = evaluateExample($evidence);
    if ($actual !== $case['expected']) {
        throw new RuntimeException($case['id'] . ': expected ' . $case['expected'] . ', got ' . $actual);
    }
    $checks++;
}

foreach ($data['lintCases'] as $case) {
    if (isset($seen[$case['id']])) {
        throw new RuntimeException('Duplicate fixture: ' . $case['id']);
    }
    $seen[$case['id']] = true;
    $actual = evaluateLintExample($case['response'], $case['coverageVerified']);
    if ($actual !== $case['expected']) {
        throw new RuntimeException($case['id'] . ': expected ' . $case['expected'] . ', got ' . $actual);
    }
    $checks++;
}

// Missing independent evidence must never become success through PHP truthiness.
foreach (
    ['dispatched', 'transportOk', 'complete', 'allContentReviewed', 'fileLint', 'folderLint',
    'semanticVerdict', 'validation', 'readback', 'domainAcceptance', 'requiredFilesCovered'] as $field
) {
    foreach ([null, false, 0, '', [], 'unknown'] as $value) {
        $evidence = array_replace($data['baseline'], [$field => $value]);
        if (evaluateExample($evidence) === 'verified') {
            throw new RuntimeException('Missing/invalid evidence admitted: ' . $field);
        }
        $checks++;
    }
}

foreach (['requiresLint', 'reloadRequired'] as $field) {
    foreach ([null, 0, '', [], 'false', 'true'] as $value) {
        $evidence = array_replace($data['baseline'], [$field => $value]);
        if (evaluateExample($evidence) === 'verified') {
            throw new RuntimeException('Invalid applicability flag admitted: ' . $field);
        }
        $checks++;
    }
}

// No installation data: IDs below are synthetic and never sent to a server.
$direct = [
    'transportOk' => true, 'complete' => true, 'allContentReviewed' => true,
    'contractVerified' => true, 'contract' => 'variable-metadata-json',
    'expectedID' => 7, 'expectedType' => 0,
];
$metadata = [
    'VariableID' => 7, 'VariableType' => 0, 'VariableValue' => false,
    'VariableUpdated' => 10, 'VariableChanged' => 0,
];
$textResponse = static fn(string $text): array => [
    'content' => [['type' => 'text', 'text' => $text]], 'isError' => false,
];
$direct['response'] = $textResponse(json_encode($metadata, JSON_THROW_ON_ERROR));
$expectRead = static function (array $evidence, array $expected) use (&$checks): void {
    if (evaluateDirectReadExample($evidence) !== $expected) {
        throw new RuntimeException('Direct-read fixture mismatch at check ' . $checks);
    }
    $checks++;
};
$observation = ['verdict' => 'observed', 'value' => false, 'updated' => 10, 'changed' => 0];
$expectRead($direct, $observation); // Old/zero times remain evidence, never health.
$withoutFlag = $direct;
unset($withoutFlag['response']['isError']);
$expectRead($withoutFlag, $observation);
foreach (['transportOk', 'complete', 'allContentReviewed', 'contractVerified'] as $gate) {
    foreach ([false, null, 'true', 1] as $invalid) {
        $expectRead(array_replace($direct, [$gate => $invalid]), ['verdict' => 'uncertain']);
    }
}
foreach ([true, false, 0, 0.0, '', '[]', 'false', '0'] as $value) {
    $type = is_bool($value) ? 0 : (is_int($value) ? 1 : (is_float($value) ? 2 : 3));
    $sample = array_replace($metadata, ['VariableType' => $type, 'VariableValue' => $value]);
    $expectRead(array_replace($direct, [
        'expectedType' => $type,
        'response' => $textResponse(json_encode($sample, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION)),
    ]), array_replace($observation, ['value' => $value]));
}
foreach (array_keys($metadata) as $missing) {
    $sample = $metadata;
    unset($sample[$missing]);
    $expectRead(array_replace($direct, [
        'response' => $textResponse(json_encode($sample, JSON_THROW_ON_ERROR)),
    ]), ['verdict' => 'uncertain']);
}
foreach ([[1, 0.5], [2, '0'], [2, false], [3, 0], [4, []]] as [$type, $value]) {
    $sample = array_replace($metadata, ['VariableType' => $type, 'VariableValue' => $value]);
    $expectRead(array_replace($direct, [
        'expectedType' => $type,
        'response' => $textResponse(json_encode($sample, JSON_THROW_ON_ERROR)),
    ]), ['verdict' => 'uncertain']);
}
$integralFloat = array_replace($metadata, ['VariableType' => 2, 'VariableValue' => 0.0]);
$expectRead(array_replace($direct, [
    'expectedType' => 2,
    'response' => $textResponse(json_encode($integralFloat, JSON_THROW_ON_ERROR)),
]), array_replace($observation, ['value' => 0]));
foreach (
    [
    ['VariableID' => 8], ['VariableID' => '7'], ['VariableType' => '0'],
    ['VariableValue' => null], ['VariableValue' => 'false'], ['VariableValue' => 0],
    ['VariableUpdated' => -1], ['VariableUpdated' => '10'], ['VariableChanged' => null],
    ] as $changes
) {
    $expectRead(array_replace($direct, [
        'response' => $textResponse(json_encode(array_replace($metadata, $changes), JSON_THROW_ON_ERROR)),
    ]), ['verdict' => 'uncertain']);
}
foreach ([0, null, '7'] as $id) {
    $expectRead(array_replace($direct, ['expectedID' => $id]), ['verdict' => 'uncertain']);
}
$json = $direct['response']['content'][0]['text'];
foreach (['', 'Warning: fixture ' . $json, $json . ' warning', substr($json, 0, -1), str_repeat('x', 2049)] as $text) {
    $expectRead(array_replace($direct, ['response' => $textResponse($text)]), ['verdict' => 'uncertain']);
}
foreach (
    [
    [], ['content' => []], ['content' => [['type' => 'text']]],
    ['content' => [['type' => 'image', 'text' => 'not text']]],
    ['content' => [['type' => 'text', 'text' => false]]],
    array_replace($direct['response'], ['structuredContent' => ['unreviewed' => true]]),
    ['content' => [$direct['response']['content'][0], ['type' => 'text', 'text' => 'warning']]],
    array_replace($direct['response'], ['isError' => 'false']),
    ] as $response
) {
    $expectRead(array_replace($direct, ['response' => $response]), ['verdict' => 'uncertain']);
}
$expectRead(array_replace($direct, [
    'response' => array_replace($direct['response'], ['isError' => true]),
]), ['verdict' => 'failed']);
foreach (['', '[]', 'false', '0', 'Warning: literal user data'] as $text) {
    $expectRead(array_replace($direct, [
        'contract' => 'known-string-text', 'response' => $textResponse($text),
    ]), ['verdict' => 'observed', 'value' => $text]);
    $expectRead(array_replace($direct, [
        'contract' => 'unknown', 'response' => $textResponse($text),
    ]), ['verdict' => 'uncertain']);
}

// Document links are part of this workflow's integration, not a platform test.
$root = dirname(__DIR__, 2);
foreach (
    [
    'project/SYMCON_CHANGE_WORKFLOW.md',
    'project/SYMCON_MCP_SCRIPT_READBACK.md',
    'project/NATIVE_MCP_CAPABILITIES_20261017.md',
    'project/SYMCON_MODULE_INSTALLATION.md',
    'adr/ADR-0012-use-risk-tiered-symcon-change-workflows.md',
    'templates/CHANGE_RECORD.md',
    ] as $relative
) {
    $path = $root . '/' . $relative;
    $document = file_get_contents($path);
    preg_match_all('/\]\(([^)]+)\)/', $document, $matches);
    foreach ($matches[1] as $target) {
        if (str_contains($target, '://') || str_starts_with($target, '#')) {
            continue;
        }
        $target = explode('#', $target)[0];
        if (!is_file(dirname($path) . '/' . $target)) {
            throw new RuntimeException('Broken workflow link: ' . $relative . ' -> ' . $target);
        }
        $checks++;
    }
}

printf("MCP workflow: %d offline checks passed; no live calls or mutations.\n", $checks);
