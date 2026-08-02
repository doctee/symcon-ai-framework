<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);
$allowedCreateFiles = [
    'helpers/object/EnsureCategory.php',
    'helpers/object/EnsureEvent.php',
    'helpers/object/EnsureInstance.php',
    'helpers/object/EnsureLink.php',
    'helpers/object/EnsureScript.php',
    'helpers/object/EnsureVariable.php',
];
$allowedCreateMap = array_fill_keys($allowedCreateFiles, true);
$scanRoots = ['helpers', 'case-studies', 'deployments', 'tools', 'templates', 'examples'];
$violations = [];

foreach ($scanRoots as $scanRoot) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $projectRoot . '/' . $scanRoot,
            FilesystemIterator::SKIP_DOTS
        )
    );
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $path = str_replace('\\', '/', $file->getPathname());
        $relative = substr($path, strlen(str_replace('\\', '/', $projectRoot)) + 1);
        if (str_contains($relative, '/tests/') || str_contains($relative, '/tools/symcon-')) {
            continue;
        }
        $source = file_get_contents($path);
        if ($source === false) {
            throw new RuntimeException('Cannot read mutation-safety source: ' . $relative);
        }
        if (preg_match('/\\?IPS_Create(?:Category|Event|Instance|Link|Media|Script|Variable)\s*\(/', $source) !== 1) {
            continue;
        }
        if (!isset($allowedCreateMap[$relative])) {
            $violations[] = $relative . ': direct IPS_Create*() outside an approved Ensure helper';
            continue;
        }
        if (
            preg_match_all(
                '/\$(\w+)\s*=\s*IPS_Create(?:Category|Event|Instance|Link|Media|Script|Variable)\s*\([^;]*\);/',
                $source,
                $matches,
                PREG_OFFSET_CAPTURE
            ) === false
        ) {
            throw new RuntimeException('Cannot inspect object creation assignments: ' . $relative);
        }
        foreach ($matches[1] as $index => [$variable, $variableOffset]) {
            $assignmentOffset = $matches[0][$index][1];
            $nextCreateOffset = $matches[0][$index + 1][1] ?? strlen($source);
            $segment = substr($source, $assignmentOffset, $nextCreateOffset - $assignmentOffset);
            if (!str_contains($segment, 'SAEF_ValidateMutableObject($' . $variable . ',')) {
                $line = substr_count(substr($source, 0, $variableOffset), "\n") + 1;
                $violations[] = sprintf(
                    '%s:%d: IPS_Create*() result is not guarded before mutation',
                    $relative,
                    $line
                );
            }
        }
    }
}

if ($violations !== []) {
    throw new RuntimeException("Object mutation safety violations:\n" . implode("\n", $violations));
}

fwrite(STDOUT, "PASS: Production object creation is confined to guarded Ensure helpers.\n");
