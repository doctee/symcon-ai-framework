<?php

declare(strict_types=1);

require_once __DIR__ . '/../candidate/Evaluator.php';

use SAEF\ProfileMonitor\Evaluator;

if ($argc !== 2) {
    throw new InvalidArgumentException('Usage: php compare-snapshot.php private-snapshot.json');
}
$data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$legacy = (new Evaluator($data['profiles'], $data['ignored'], [], false))->evaluate($data['variables']);
$new = (new Evaluator($data['profiles'], $data['ignored'], Evaluator::presets(true, true, 10, 2)))->evaluate($data['variables']);
$report = ['storedCheckedIdentical' => $legacy['checked'] === $data['existingChecked'],
    'storedWarningsIdentical' => $legacy['warnings'] === $data['existingWarnings'],
    'legacyChecked' => count($legacy['checked']), 'candidateChecked' => count($new['checked']),
    'legacyWarnings' => count($legacy['warnings']), 'candidateWarnings' => count($new['warnings']),
    'additional' => array_values(array_diff($new['checked'], $legacy['checked'])),
    'removed' => array_values(array_diff($legacy['checked'], $new['checked'])),
    'unknown' => $new['unknown'], 'details' => array_values(array_filter($new['details'], static function (array $r) use ($legacy): bool {
        return !in_array($r['id'], $legacy['checked'], true);
    })),
    'note' => 'Offline calculation from one read-only snapshot; no live changes.'];
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
