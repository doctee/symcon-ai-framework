<?php

declare(strict_types=1);

require_once __DIR__ . '/SolarDisplayArchive.php';

/** Explicit composition entrypoint; no installation, automatic execution or publication. */
final class SolarDisplayRuntime
{
    /**
     * @param list<array{key: string, instanceId: int, expectedConfigurationHash: string}> $sources
     * @param array<string, int> $targets
     * @return array{reader: Closure(): array<string, mixed>, writer: SolarDisplayArchive}
     */
    public static function connect(
        array $sources,
        string $timezone,
        int $maximumAge,
        int $owner,
        int $archive,
        array $targets,
        string $generation,
        string $journalDirectory
    ): array {
        if (date_default_timezone_get() !== $timezone) {
            throw new InvalidArgumentException('Forecast timezone must equal the Symcon PHP/archive timezone.');
        }
        // Functions are selected exclusively by the adapters, never by external payloads.
        $call = static function (string $function, array $arguments): mixed {
            if (!function_exists($function)) {
                throw new RuntimeException('Required Symcon function unavailable: ' . $function);
            }
            return call_user_func_array($function, $arguments);
        };
        $clock = static fn(): int => time();
        $reader = new SolarDisplayReader($call, $clock);
        $read = static fn(): array => $reader->read($sources, $timezone, $maximumAge);
        $contractHash = hash('sha256', json_encode([$sources, $timezone, $maximumAge], JSON_THROW_ON_ERROR));
        $writer = new SolarDisplayArchive($call, $read, $clock, new SolarDisplayJournal($journalDirectory), $owner, $archive, $targets, $generation, $contractHash);
        return ['reader' => $read, 'writer' => $writer];
    }
}
