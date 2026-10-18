<?php

declare(strict_types=1);

namespace SAEF\StorageHeater;

/** Offline archive guards and module observer share the same simulated wall clock. */
function time(): int
{
    return $GLOBALS['now'];
}
