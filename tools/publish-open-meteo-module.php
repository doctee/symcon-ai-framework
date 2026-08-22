<?php

declare(strict_types=1);

require_once __DIR__ . '/publication/ModulePublication.php';

const SAEF_OPEN_METEO_PUBLICATION_CONTRACT =
    'deployments/symcon/open-meteo-publication.json';

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    exit(runModulePublicationCli(
        $_SERVER,
        SAEF_OPEN_METEO_PUBLICATION_CONTRACT,
        'tools/publish-open-meteo-module.php',
        true
    ));
}
