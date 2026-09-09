<?php

declare(strict_types=1);

const SAEF_HANDOVER_RECORD_MAX_BYTES = 65_536;
const SAEF_HANDOVER_MARKDOWN_MAX_BYTES = 131_072;

try {
    if (PHP_SAPI !== 'cli') {
        throw new RuntimeException('Workstream handover validation requires the CLI SAPI.');
    }

    $arguments = array_slice($argv, 1);
    if (count($arguments) !== 1) {
        throw new InvalidArgumentException(
            'Usage: tools/repository/check-workstream-handover.sh <workstream>'
        );
    }

    $workstream = $arguments[0];
    assertSaefHandoverSlug($workstream);
    $currentDirectory = getcwd();
    if ($currentDirectory === false) {
        throw new RuntimeException('Current working directory cannot be resolved.');
    }

    $repositoryRoot = saefHandoverGitOutput(
        $currentDirectory,
        ['rev-parse', '--show-toplevel'],
        'Current Git worktree cannot be resolved.'
    );
    $primaryCheckout = saefHandoverPrimaryCheckout($repositoryRoot);
    $privateRoot = $primaryCheckout . '/private/workstreams';
    $resolvedPrivateRoot = realpath($privateRoot);
    if ($resolvedPrivateRoot === false || !is_dir($resolvedPrivateRoot)) {
        throw new RuntimeException('Primary private workstream directory is missing.');
    }

    $handoverDirectory = $privateRoot . '/' . $workstream;
    $resolvedHandoverDirectory = realpath($handoverDirectory);
    if (
        $resolvedHandoverDirectory === false
        || !is_dir($resolvedHandoverDirectory)
        || is_link($handoverDirectory)
        || saefHandoverNormalizePath($resolvedHandoverDirectory)
            !== saefHandoverNormalizePath($handoverDirectory)
    ) {
        throw new RuntimeException('Canonical private handover directory is missing or unsafe.');
    }

    $recordPath = $handoverDirectory . '/workstream.local.json';
    $markdownPath = $handoverDirectory . '/HANDOVER.local.md';
    $record = saefHandoverReadJson($recordPath);
    assertSaefHandoverRecord($record, $workstream);
    $markdown = saefHandoverReadFile(
        $markdownPath,
        SAEF_HANDOVER_MARKDOWN_MAX_BYTES,
        'Handover Markdown'
    );
    assertSaefHandoverMarkdown($markdown, $record);
    assertSaefHandoverGitState($primaryCheckout, $record);

    $recordHash = hash_file('sha256', $recordPath);
    if (!is_string($recordHash)) {
        throw new RuntimeException('Workstream record hash cannot be calculated.');
    }
    fwrite(
        STDOUT,
        json_encode(
            [
                'formatVersion' => 1,
                'outcome' => 'passed',
                'workstream' => $workstream,
                'status' => $record['status'],
                'headCommit' => $record['headCommit'],
                'worktreeClean' => $record['worktreeClean'],
                'recordSha256' => $recordHash,
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ) . "\n"
    );
} catch (Throwable $throwable) {
    fwrite(STDERR, 'SAEF workstream handover failed: ' . $throwable->getMessage() . "\n");
    exit(1);
}

function assertSaefHandoverSlug(string $workstream): void
{
    if (preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/D', $workstream) !== 1) {
        throw new InvalidArgumentException(
            'Workstream name must use lowercase letters, digits and internal hyphens.'
        );
    }
}

/** @return array<string, mixed> */
function saefHandoverReadJson(string $path): array
{
    $contents = saefHandoverReadFile(
        $path,
        SAEF_HANDOVER_RECORD_MAX_BYTES,
        'Workstream record'
    );
    $decoded = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($decoded) || array_is_list($decoded)) {
        throw new RuntimeException('Workstream record must be a JSON object.');
    }

    return $decoded;
}

function saefHandoverReadFile(string $path, int $maximumBytes, string $label): string
{
    if (!is_file($path) || is_link($path) || !is_readable($path)) {
        throw new RuntimeException($label . ' is missing or unsafe.');
    }
    $size = filesize($path);
    if (!is_int($size) || $size < 1 || $size > $maximumBytes) {
        throw new RuntimeException($label . ' size is outside the allowed bound.');
    }
    $contents = file_get_contents($path);
    if (!is_string($contents) || strlen($contents) !== $size) {
        throw new RuntimeException($label . ' cannot be read completely.');
    }

    return $contents;
}

/** @param array<string, mixed> $record */
function assertSaefHandoverRecord(array $record, string $workstream): void
{
    $expectedKeys = [
        'formatVersion',
        'updatedAtUtc',
        'workstream',
        'status',
        'branch',
        'worktree',
        'baseCommit',
        'headCommit',
        'worktreeClean',
        'publicScope',
        'privateScope',
        'authorization',
        'verification',
        'openGates',
        'rollbackAndRetention',
        'nextAction',
    ];
    assertSaefHandoverExactKeys($record, $expectedKeys, 'Workstream record');
    if ($record['formatVersion'] !== 1) {
        throw new RuntimeException('Workstream record format version is unsupported.');
    }
    assertSaefHandoverTimestamp($record['updatedAtUtc']);
    if ($record['workstream'] !== $workstream) {
        throw new RuntimeException('Workstream record name differs from its directory.');
    }
    $statuses = ['active', 'ready_for_handover', 'blocked', 'closed'];
    if (!is_string($record['status']) || !in_array($record['status'], $statuses, true)) {
        throw new RuntimeException('Workstream status is unsupported.');
    }
    if ($record['branch'] !== 'codex/' . $workstream) {
        throw new RuntimeException('Workstream branch must match the canonical codex branch.');
    }
    if ($record['worktree'] !== 'private/worktrees/' . $workstream) {
        throw new RuntimeException('Worktree path must match the canonical private path.');
    }
    assertSaefHandoverCommit($record['baseCommit'], 'Base commit');
    assertSaefHandoverCommit($record['headCommit'], 'Head commit');
    if (!is_bool($record['worktreeClean'])) {
        throw new RuntimeException('Worktree clean state must be boolean.');
    }
    assertSaefHandoverText($record['publicScope'], 'Public scope', 4_096);
    assertSaefHandoverText($record['privateScope'], 'Private scope', 4_096);
    assertSaefHandoverText($record['nextAction'], 'Next action', 4_096);
    assertSaefHandoverAuthorization($record['authorization']);
    assertSaefHandoverStringList($record['verification'], 'Verification');
    assertSaefHandoverStringList($record['openGates'], 'Open gates');
    assertSaefHandoverStringList($record['rollbackAndRetention'], 'Rollback and retention');
}

/**
 * @param array<string, mixed> $value
 * @param list<string> $expectedKeys
 */
function assertSaefHandoverExactKeys(array $value, array $expectedKeys, string $label): void
{
    $actualKeys = array_keys($value);
    sort($actualKeys, SORT_STRING);
    sort($expectedKeys, SORT_STRING);
    if ($actualKeys !== $expectedKeys) {
        throw new RuntimeException($label . ' fields are invalid.');
    }
}

function assertSaefHandoverTimestamp(mixed $value): void
{
    if (!is_string($value)) {
        throw new RuntimeException('Workstream update timestamp must be a UTC string.');
    }
    $timestamp = DateTimeImmutable::createFromFormat(
        '!Y-m-d\TH:i:s\Z',
        $value,
        new DateTimeZone('UTC')
    );
    if ($timestamp === false || $timestamp->format('Y-m-d\TH:i:s\Z') !== $value) {
        throw new RuntimeException('Workstream update timestamp must use YYYY-MM-DDTHH:MM:SSZ.');
    }
}

function assertSaefHandoverCommit(mixed $value, string $label): void
{
    if (!is_string($value) || preg_match('/^[0-9a-f]{40}$/D', $value) !== 1) {
        throw new RuntimeException($label . ' must be a full lowercase Git commit.');
    }
}

function assertSaefHandoverText(mixed $value, string $label, int $maximumBytes): void
{
    if (!is_string($value) || trim($value) === '' || strlen($value) > $maximumBytes) {
        throw new RuntimeException($label . ' must be a bounded non-empty string.');
    }
    if (str_contains($value, '{{') || str_contains($value, '}}')) {
        throw new RuntimeException($label . ' contains an unresolved template placeholder.');
    }
}

function assertSaefHandoverAuthorization(mixed $value): void
{
    if (!is_array($value) || array_is_list($value)) {
        throw new RuntimeException('Authorization state must be a JSON object.');
    }
    $expectedKeys = [
        'commit',
        'push',
        'pullRequest',
        'merge',
        'liveSymcon',
        'serviceRestart',
        'retentionCleanup',
    ];
    assertSaefHandoverExactKeys($value, $expectedKeys, 'Authorization state');
    foreach ($value as $state) {
        if (!is_bool($state)) {
            throw new RuntimeException('Every authorization state must be boolean.');
        }
    }
}

function assertSaefHandoverStringList(mixed $value, string $label): void
{
    if (!is_array($value) || !array_is_list($value) || count($value) > 64) {
        throw new RuntimeException($label . ' must be a bounded JSON list.');
    }
    foreach ($value as $entry) {
        assertSaefHandoverText($entry, $label . ' entry', 4_096);
    }
}

/** @param array<string, mixed> $record */
function assertSaefHandoverMarkdown(string $markdown, array $record): void
{
    $normalized = str_replace("\r\n", "\n", $markdown);
    if (str_contains($normalized, '{{') || str_contains($normalized, '}}')) {
        throw new RuntimeException('Handover Markdown contains an unresolved template placeholder.');
    }
    if (!str_starts_with($normalized, "# SAEF Workstream Handover\n")) {
        throw new RuntimeException('Handover Markdown title is invalid.');
    }
    if (substr_count($normalized, '<!-- SAEF_WORKSTREAM_HANDOVER_V1 -->') !== 1) {
        throw new RuntimeException('Handover Markdown version marker is missing or repeated.');
    }

    $headings = [
        '## Identity',
        '## Scope',
        '## Repository State',
        '## Verification',
        '## Authorization Gates',
        '## Rollback And Retention',
        '## Next Action',
    ];
    $previousPosition = -1;
    $headingPositions = [];
    foreach ($headings as $heading) {
        $headingMatches = [];
        $headingCount = preg_match_all(
            '/^' . preg_quote($heading, '/') . '$/m',
            $normalized,
            $headingMatches,
            PREG_OFFSET_CAPTURE
        );
        if ($headingCount !== 1) {
            throw new RuntimeException('Required handover heading is missing or repeated: ' . $heading);
        }
        $position = $headingMatches[0][0][1];
        if ($position <= $previousPosition) {
            throw new RuntimeException('Handover headings are not in canonical order.');
        }
        $headingPositions[] = $position;
        $previousPosition = $position;
    }

    foreach ($headings as $index => $heading) {
        $contentStart = $headingPositions[$index] + strlen($heading);
        $contentEnd = $headingPositions[$index + 1] ?? strlen($normalized);
        $section = substr($normalized, $contentStart, $contentEnd - $contentStart);
        if (trim($section) === '') {
            throw new RuntimeException('Required handover section is empty: ' . $heading);
        }
    }

    $expectedFields = [
        'Workstream' => $record['workstream'],
        'Status' => $record['status'],
        'Branch' => $record['branch'],
        'Worktree' => $record['worktree'],
        'Base commit' => $record['baseCommit'],
        'Head commit' => $record['headCommit'],
        'Worktree clean' => $record['worktreeClean'] ? 'true' : 'false',
        'Authoritative record' => 'workstream.local.json',
    ];
    foreach ($expectedFields as $label => $expectedValue) {
        $actualValue = saefHandoverMarkdownField($normalized, $label);
        if ($actualValue !== $expectedValue) {
            throw new RuntimeException('Handover Markdown field differs from record: ' . $label);
        }
    }
    foreach (['Source task', 'Destination task'] as $label) {
        assertSaefHandoverText(
            saefHandoverMarkdownField($normalized, $label),
            'Handover ' . strtolower($label),
            1_024
        );
    }
}

function saefHandoverMarkdownField(string $markdown, string $label): string
{
    $pattern = '/^- ' . preg_quote($label, '/') . ': `([^`\r\n]+)`$/m';
    $matches = [];
    $count = preg_match_all($pattern, $markdown, $matches);
    if ($count !== 1 || !isset($matches[1][0])) {
        throw new RuntimeException('Handover Markdown field is missing or repeated: ' . $label);
    }

    return $matches[1][0];
}

/** @param array<string, mixed> $record */
function assertSaefHandoverGitState(string $primaryCheckout, array $record): void
{
    $worktreePath = $primaryCheckout . '/' . $record['worktree'];
    $resolvedWorktreePath = realpath($worktreePath);
    if (
        $resolvedWorktreePath === false
        || !is_dir($resolvedWorktreePath)
        || is_link($worktreePath)
        || saefHandoverNormalizePath($resolvedWorktreePath)
            !== saefHandoverNormalizePath($worktreePath)
    ) {
        throw new RuntimeException('Referenced worktree is missing or unsafe.');
    }
    $actualRoot = saefHandoverGitOutput(
        $worktreePath,
        ['rev-parse', '--show-toplevel'],
        'Referenced path is not a Git worktree.'
    );
    if (saefHandoverNormalizePath($actualRoot) !== saefHandoverNormalizePath($worktreePath)) {
        throw new RuntimeException('Referenced Git worktree root differs from the record.');
    }
    $actualBranch = saefHandoverGitOutput(
        $worktreePath,
        ['symbolic-ref', '--quiet', '--short', 'HEAD'],
        'Referenced worktree must have its canonical branch checked out.'
    );
    if ($actualBranch !== $record['branch']) {
        throw new RuntimeException('Referenced worktree branch differs from the record.');
    }
    $actualHead = saefHandoverGitOutput(
        $worktreePath,
        ['rev-parse', 'HEAD'],
        'Referenced worktree HEAD cannot be resolved.'
    );
    if ($actualHead !== $record['headCommit']) {
        throw new RuntimeException('Referenced worktree HEAD differs from the record.');
    }
    $baseCheck = saefHandoverRunGit(
        $worktreePath,
        ['merge-base', '--is-ancestor', $record['baseCommit'], $record['headCommit']]
    );
    if ($baseCheck['exitCode'] !== 0) {
        throw new RuntimeException('Base commit is not an ancestor of the handover head.');
    }
    $status = saefHandoverGitOutput(
        $worktreePath,
        ['status', '--porcelain=v1', '--untracked-files=normal'],
        'Referenced worktree status cannot be read.'
    );
    $actualClean = trim($status) === '';
    if ($actualClean !== $record['worktreeClean']) {
        throw new RuntimeException('Referenced worktree clean state differs from the record.');
    }
}

function saefHandoverPrimaryCheckout(string $repositoryRoot): string
{
    $output = saefHandoverGitOutput(
        $repositoryRoot,
        ['worktree', 'list', '--porcelain'],
        'Git worktrees cannot be enumerated.'
    );
    foreach (explode("\n", $output) as $line) {
        if (str_starts_with($line, 'worktree ')) {
            $path = substr($line, strlen('worktree '));
            $resolved = realpath($path);
            if ($resolved === false || !is_dir($resolved)) {
                break;
            }

            return $resolved;
        }
    }

    throw new RuntimeException('Primary checkout cannot be resolved.');
}

function saefHandoverNormalizePath(string $path): string
{
    return rtrim(str_replace('\\', '/', $path), '/');
}

/**
 * @param list<string> $arguments
 *
 * @return array{exitCode: int, stdout: string, stderr: string}
 */
function saefHandoverRunGit(string $directory, array $arguments): array
{
    $command = array_merge(['git', '-C', $directory], $arguments);
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $pipes = [];
    $process = proc_open($command, $descriptors, $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($process)) {
        throw new RuntimeException('Git process cannot be started.');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if (!is_string($stdout) || !is_string($stderr)) {
        throw new RuntimeException('Git process output cannot be read.');
    }

    return [
        'exitCode' => $exitCode,
        'stdout' => $stdout,
        'stderr' => $stderr,
    ];
}

/** @param list<string> $arguments */
function saefHandoverGitOutput(
    string $directory,
    array $arguments,
    string $failureMessage
): string {
    $result = saefHandoverRunGit($directory, $arguments);
    if ($result['exitCode'] !== 0) {
        throw new RuntimeException($failureMessage);
    }

    return trim($result['stdout']);
}
