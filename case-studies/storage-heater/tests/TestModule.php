<?php

declare(strict_types=1);

class TestModule extends StorageHeaterForecast
{
    protected function now(): int
    {
        return $GLOBALS['now'];
    }
}
