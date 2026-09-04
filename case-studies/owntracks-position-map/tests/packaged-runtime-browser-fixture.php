<?php

declare(strict_types=1);

define(
    'OWNTRACKS_RUNTIME_MODULE_FILE',
    dirname(__DIR__, 3)
    . '/dist/symcon/saef-owntracks-position-map-module/'
    . 'OwnTracksPositionMap/module.php'
);
define(
    'OWNTRACKS_TEST_CORE_DIRECTORY',
    dirname(__DIR__, 3)
    . '/dist/symcon/saef-owntracks-position-map-module/'
    . 'OwnTracksPositionMap/libs/OwnTracks'
);

require __DIR__ . '/runtime-browser-fixture.php';
