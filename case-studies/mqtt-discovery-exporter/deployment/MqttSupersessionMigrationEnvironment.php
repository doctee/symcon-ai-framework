<?php

declare(strict_types=1);

namespace SAEF\CaseStudy\MqttDiscoveryExporter\Deployment;

interface MqttSupersessionMigrationEnvironment
{
    public function now(): \DateTimeImmutable;

    public function nonce(): string;

    public function semaphoreEnter(string $name, int $timeoutMilliseconds): bool;

    public function semaphoreLeave(string $name): bool;

    public function scriptExists(int $scriptID): bool;

    public function getScriptContent(int $scriptID): string;

    public function setScriptContent(int $scriptID, string $source): void;

    public function objectExists(int $objectID): bool;

    /** @return array<string, mixed> */
    public function getObject(int $objectID): array;

    /** @return array<string, mixed> */
    public function getEvent(int $eventID): array;

    public function setEventActive(int $eventID, bool $active): void;

    public function getObjectIDByIdent(string $ident, int $parentID): int|false;

    /** @return array<string, mixed> */
    public function getVariable(int $variableID): array;

    public function getValue(int $variableID): bool|int|float|string;

    public function createClaim(string $planSha256, string $nonce): bool;

    public function getClaimState(string $planSha256, string $nonce): ?string;

    public function transitionClaim(
        string $planSha256,
        string $nonce,
        string $expectedState,
        string $newState
    ): void;
}
