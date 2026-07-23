<?php

declare(strict_types=1);

namespace SAEF\CaseStudy\MqttDiscoveryExporter;

/**
 * Fileset entry that composes the exporter runtime with deployment-owned
 * object creation needed after a successful fileset activation.
 */

require_once __DIR__ . '/../../../helpers/object/EnsureScript.php';
require_once __DIR__ . '/MqttDiscoveryExporterRuntime.php';
