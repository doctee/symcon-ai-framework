<?php

declare(strict_types=1);

use Opis\JsonSchema\Validator;

$root = dirname(__DIR__, 2);
$configuredVendor = getenv('COMPOSER_VENDOR_DIR') ?: 'vendor';
$vendor = str_starts_with($configuredVendor, DIRECTORY_SEPARATOR)
    ? $configuredVendor
    : $root . '/' . $configuredVendor;
require $vendor . '/autoload.php';

$schema = json_decode(
    file_get_contents($root . '/standards/schemas/artifact-record.schema.json'),
    false,
    64,
    JSON_THROW_ON_ERROR
);
$example = json_decode(
    file_get_contents($root . '/templates/artifacts/ARTIFACT_RECORD.example.json'),
    false,
    64,
    JSON_THROW_ON_ERROR
);
$validator = new Validator();
$checks = 0;

// Every case is an in-memory shape check, never a probe of its recorded path.
$check = static function (object $record, bool $expected, string $label) use ($validator, $schema, &$checks): void {
    if ($validator->validate($record, $schema)->isValid() !== $expected) {
        throw new RuntimeException('Artifact record regression failed: ' . $label);
    }
    $checks++;
};
$copy = static fn (): object => json_decode(json_encode($example, JSON_THROW_ON_ERROR), false, 64, JSON_THROW_ON_ERROR);
$check($example, true, 'synthetic retained example');
$record = $copy();
$record->role = 'package';
$check($record, true, 'prepared package distinct from runtime and transfer');
$record->artifactId = '20260101T000000Z-012345abcdef';
$record->workstream = 'media-carousel-package';
$record->state = 'review-required';
$record->producer = (object) ['name' => 'Start-SaefMediaCarouselSchemaPackage.ps1', 'revision' => str_repeat('a', 64)];
$record->location = (object) ['path' => 'C:\\Synthetic\\media-carousel-package\\' . $record->artifactId, 'basis' => 'absolute'];
$record->evidence->basis = 'local-observation';
$record->evidence->checkedAtUtc = '2026-01-01T00:00:00.1234567Z';
$record->evidence->references = ['source-zip: C:\\Synthetic\\package.zip', 'source-zip-sha256: ' . str_repeat('b', 64)];
$record->copyOf = 'C:\\Synthetic\\package.zip';
$check($record, true, 'Windows extraction provenance shape, not a native execution test');

foreach (array_keys(get_object_vars($example)) as $key) {
    $record = $copy();
    unset($record->{$key});
    $check($record, false, 'missing ' . $key);
}
foreach (['active', 'retained', 'review-required', 'completed'] as $state) {
    $record = $copy();
    $record->state = $state;
    $check($record, true, 'state with retained hold: ' . $state);
}
$record = $copy();
$record->state = 'removed-reported';
$check($record, false, 'removed without historical evidence');
$record->evidence->basis = 'historical-report';
$check($record, true, 'historically reported removal is not a live observation');

$record = $copy();
$record->role = 'transfer';
$check($record, false, 'copy without source');
$record->copyOf = 'existing-source-manifest';
$check($record, true, 'copy linked to source');

foreach (['delete', 'expired', 'safe-to-delete'] as $decision) {
    $record = $copy();
    $record->retention->decision = $decision;
    $check($record, false, 'unsupported destructive decision');
}
$record = $copy();
$record->deletionAuthorized = true;
$check($record, false, 'unknown authority field');
$record = $copy();
$record->retention->deleteAfterUtc = '2026-01-01T00:00:00Z';
$check($record, false, 'nested deletion trigger');
$record = $copy();
$record->owner = '   ';
$check($record, false, 'empty owner');
$record = $copy();
$record->workstream = '../other';
$check($record, false, 'invalid workstream slug');
$record = $copy();
$record->identity->sha256 = 'not-a-hash';
$check($record, false, 'invalid hash');
$record->identity->sha256 = str_repeat('a', 64);
$check($record, true, 'syntactically valid hash is not content verification');
$record = $copy();
$record->evidence->references = [];
$check($record, false, 'missing evidence references');
$record = $copy();
$record->retention->holds = [];
$check($record, false, 'unverified without hold');
$record = $copy();
$record->retention->holds = ['hold', 'hold'];
$check($record, false, 'duplicate holds');
$record = $copy();
$record->retention->reviewAfterUtc = '2026-01-02T00:00:00Z';
$check($record, true, 'review date does not release hold');
foreach (['2026-01-01', '2026-01-01T00:00:00+01:00', '2026-99-99T00:00:00Z'] as $date) {
    $record = $copy();
    $record->evidence->checkedAtUtc = $date;
    $check($record, false, 'invalid UTC timestamp');
}
fwrite(STDOUT, 'PASS: ' . $checks . " artifact lifecycle record checks (offline, no filesystem mutations).\n");
