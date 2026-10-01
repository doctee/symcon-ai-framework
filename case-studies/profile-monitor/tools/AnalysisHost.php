<?php

declare(strict_types=1);

namespace SAEF\ProfileMonitor;

/** Static-analysis host only. Never loaded into the generated module. */
abstract class AnalysisHost extends \IPSModule
{
    use MonitorRuntime;
}
