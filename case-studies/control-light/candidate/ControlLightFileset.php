<?php

declare(strict_types=1);

namespace SAEF\CaseStudy\ControlLight;

/**
 * Fileset entry that composes the runtime with deployment-owned object
 * creation needed after a successful fileset activation.
 */

require_once __DIR__ . '/../../../helpers/object/EnsureScript.php';
require_once __DIR__ . '/ControlLightRuntime.php';
require_once __DIR__ . '/HueWallSwitchRuntime.php';
require_once __DIR__ . '/ManualOnPulseOffRuntime.php';
