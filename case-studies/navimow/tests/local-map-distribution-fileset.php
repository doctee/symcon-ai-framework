<?php

declare(strict_types=1);

function assertLocalMapFileset(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__, 3);
$manifest = json_decode(
    (string) file_get_contents(
        $root . '/deployments/symcon/navimow-module.fileset.json'
    ),
    true,
    32,
    JSON_THROW_ON_ERROR
);
$publication = json_decode(
    (string) file_get_contents(
        $root . '/deployments/symcon/navimow-publication.json'
    ),
    true,
    32,
    JSON_THROW_ON_ERROR
);
$required = [
    'libs/Navimow/MapGeometryReducer.php',
    'libs/Navimow/MqttPathSegmenter.php',
    'libs/Navimow/ZoneStatisticsReducer.php',
    'libs/Navimow/LocalMapSceneProjector.php',
    'libs/Navimow/RevisionBoundedTrackStore.php',
    'libs/Navimow/LocalMapSvgRenderer.php',
    'libs/SAEF/helpers/diagnostics/ConfigurationHash.php',
];
$targets = array_column($manifest['files'], 'target');
foreach ($required as $target) {
    assertLocalMapFileset(
        in_array($target, $targets, true)
            && in_array($target, $publication['inventory'], true),
        'Local-map publication target is missing: ' . $target
    );
}
assertLocalMapFileset(
    count($targets) === count(array_unique($targets))
        && count($publication['inventory'])
            === count(array_unique($publication['inventory'])),
    'Local-map publication inventory contains duplicates.'
);

echo "Navimow local-map distribution fileset checks passed.\n";
