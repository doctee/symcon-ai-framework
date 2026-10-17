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
