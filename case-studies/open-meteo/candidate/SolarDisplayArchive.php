<?php

declare(strict_types=1);

require_once __DIR__ . '/SolarDisplayReader.php';
require_once __DIR__ . '/SolarDisplayJournal.php';

/** Explicit archive-only executor. Including this file performs no operations. */
final class SolarDisplayArchive
{
    public const OWNER_MARKER = 'SAEF SolarDisplay chart-only v1';

    /**
     * @param Closure(string, list<mixed>): mixed $call
     * @param Closure(): array<string, mixed> $readPlan
     * @param Closure(): int $clock
     * @param array<string, int> $targets Logical source key => dedicated chart variable.
     */
    public function __construct(
        private Closure $call,
        private Closure $readPlan,
        private Closure $clock,
        private SolarDisplayJournal $journal,
        private int $owner,
        private int $archive,
        private array $targets,
        private string $generation,
        private string $sourceContractHash
    ) {
        SolarDisplayReader::positiveId($owner);
        SolarDisplayReader::positiveId($archive);
        if (preg_match('/^[a-f0-9]{64}$/D', $sourceContractHash) !== 1) {
            throw new InvalidArgumentException('Pinned source contract hash required.');
        }
        if (preg_match('/^[a-f0-9]{32}$/D', $generation) !== 1) {
            throw new InvalidArgumentException('A pinned ownership generation is required.');
        }
        if (count($targets) !== 2 || count(array_unique(array_values($targets))) !== 2) {
            throw new InvalidArgumentException('Exactly two distinct chart targets required.');
        }
        foreach ($targets as $key => $id) {
            if (preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $key) !== 1) {
                throw new InvalidArgumentException('Invalid chart key.');
            }
            SolarDisplayReader::positiveId($id);
        }
    }

    /**
     * Preview is read-only, including the journal.
     * @return array<string, mixed>
     */
    public function preview(): array
    {
        $this->validateTargets();
        if ($this->journal->read('pending') !== null) {
            return ['success' => false, 'code' => 'recovery_required'];
        }
        return ($this->readPlan)();
    }

    /**
     * Calling apply/resume is a separately authorized live operation.
     * This never sets variables, logging, configuration, schedules or links.
     * @return array<string, mixed>
     */
    public function apply(): array
    {
        return $this->locked(false);
    }

    /** Restore only the still-pending transaction. Separate rollback authority required.
     * @return array<string, mixed>
     */
    public function rollbackPending(): array
    {
        return $this->locked(true);
    }

    /** @return array<string, mixed> */
    private function locked(bool $rollback): array
    {
        $lock = 'SAEF.SolarDisplay.' . $this->owner;
        if (($this->call)('IPS_SemaphoreEnter', [$lock, 100]) !== true) {
            return ['success' => false, 'code' => 'writer_busy'];
        }
        try {
            $identities = $this->validateTargets();
            $pending = $this->journal->read('pending');
            if ($pending === null) {
                if ($rollback) {
                    return ['success' => false, 'code' => 'no_pending_transaction'];
                }
                $plan = ($this->readPlan)();
                if (($plan['success'] ?? null) !== true) {
                    return $plan;
                }
                $this->validatePlan($plan);
                $before = [];
                foreach ($this->targets as $key => $id) {
                    $before[$key] = $this->raw($id, $plan['validFrom'], $plan['validTo']);
                    $this->projectionRows($before[$key], $plan['validFrom']);
                }
                $fresh = ($this->readPlan)();
                if (($fresh['success'] ?? null) !== true || ($fresh['sources'] ?? null) !== $plan['sources'] || ($fresh['localDay'] ?? null) !== $plan['localDay']) {
                    return ['success' => false, 'code' => 'plan_changed'];
                }
                $this->validatePlan($plan);
                if ($identities !== $this->validateTargets()) {
                    throw new RuntimeException('Target identity changed before journal.');
                }
                $desired = $plan['chartProjection']['records'];
                if ($before === $desired && $this->aggregatesMatch($desired, $plan['validFrom'], $plan['validTo'])) {
                    return ['success' => true, 'code' => 'unchanged'];
                }
                $intent = [
                    'version' => 1, 'owner' => $this->owner, 'archive' => $this->archive,
                    'targets' => $this->targets, 'identities' => $identities,
                    'sourceContractHash' => $this->sourceContractHash,
                    'from' => $plan['validFrom'], 'to' => $plan['validTo'],
                    'createdAt' => ($this->clock)(), 'before' => $before, 'desired' => $desired,
                    'sources' => $plan['sources'],
                ];
                $bytes = json_encode($intent, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
                $pending = hash('sha256', $bytes);
                $this->journal->put($pending . '.json', $bytes);
                $this->journal->put('pending', $pending);
            }
            if (preg_match('/^[a-f0-9]{64}$/D', $pending) !== 1) {
                throw new RuntimeException('Invalid pending identity.');
            }
            $bytes = $this->journal->read($pending . '.json');
            if ($bytes === null || !hash_equals($pending, hash('sha256', $bytes))) {
                throw new RuntimeException('Journal hash mismatch.');
            }
            $intent = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($intent) || ($intent['sourceContractHash'] ?? null) !== $this->sourceContractHash) {
                throw new RuntimeException('Source contract changed during recovery.');
            }
            if (($intent['version'] ?? null) !== 1 || ($intent['owner'] ?? null) !== $this->owner || ($intent['archive'] ?? null) !== $this->archive || ($intent['targets'] ?? null) !== $this->targets || ($intent['identities'] ?? null) !== $identities) {
                throw new RuntimeException('Journal ownership/configuration drift.');
            }
            $from = $intent['from'];
            $to = $intent['to'];
            if (!is_int($from) || !is_int($to) || $from <= 0 || $to <= $from || $to - $from > 26 * 3600) {
                throw new RuntimeException('Journal range invalid.');
            }
            if ($this->journal->read($pending . '.done') !== null) {
                $receipt = $this->journal->read($pending . '.done');
                if (!in_array($receipt, ['restored', 'applied'], true) || !$this->aggregatesMatch($receipt === 'restored' ? $intent['before'] : $intent['desired'], $from, $to)) {
                    throw new RuntimeException('Completed receipt readback differs.');
                }
                $this->journal->finish($pending);
                return ['success' => true, 'code' => 'receipt_recovered'];
            }
            if ($rollback) {
                $this->journal->put($pending . '.rollback', 'restore-before');
            }
            $restoring = $this->journal->read($pending . '.rollback') !== null;
            if ($restoring && $this->journal->read($pending . '.rollback') !== 'restore-before') {
                throw new RuntimeException('Rollback marker invalid.');
            }
            $desired = $restoring ? $intent['before'] : $intent['desired'];
            // Inspect both plants before changing either; recheck each again below.
            foreach ($this->targets as $key => $id) {
                $current = $this->raw($id, $from, $to);
                if ($current !== $intent['before'][$key] && !$this->subset($current, $intent['desired'][$key]) && !($restoring && $this->subset($current, $desired[$key]))) {
                    throw new RuntimeException('Archive conflict; manual review required.');
                }
            }
            foreach ($this->targets as $key => $id) {
                $current = $this->raw($id, $from, $to);
                $goal = $desired[$key];
                // Unknown concurrent data is never deleted. Interrupted inserts can be subsets.
                if ($current !== $intent['before'][$key] && !$this->subset($current, $intent['desired'][$key]) && !($restoring && $this->subset($current, $goal))) {
                    throw new RuntimeException('Archive conflict; manual review required.');
                }
                if ($current !== $goal) {
                    if ($identities !== $this->validateTargets()) {
                        throw new RuntimeException('Target drift before archive mutation.');
                    }
                    $deleted = ($this->call)('AC_DeleteVariableData', [$this->archive, $id, $from, $to - 1]);
                    if (!is_int($deleted) || $deleted !== count($current) || $this->raw($id, $from, $to) !== []) {
                        throw new RuntimeException('Archive deletion readback failed.');
                    }
                    if ($identities !== $this->validateTargets()) {
                        throw new RuntimeException('Target drift before insertion.');
                    }
                    if ($goal !== [] && ($this->call)('AC_AddLoggedValues', [$this->archive, $id, $goal]) !== true) {
                        throw new RuntimeException('Archive insertion failed; pending journal retained.');
                    }
                    if ($this->raw($id, $from, $to) !== $goal) {
                        throw new RuntimeException('Archive insertion readback failed.');
                    }
                }
                $marker = $pending . '.aggregate-' . $key;
                // Rollback needs a new aggregation, distinguished without overwriting its receipt.
                if ($restoring) {
                    $marker = $pending . '.aggregate-restore_' . $key;
                }
                if ($this->journal->read($marker) === null) {
                    $this->validateTargets();
                    if (($this->call)('AC_ReAggregateVariable', [$this->archive, $id]) !== true) {
                        throw new RuntimeException('Reaggregation request failed.');
                    }
                    $this->journal->put($marker, 'requested');
                }
            }
            if (!$this->aggregatesMatch($desired, $from, $to)) {
                return ['success' => false, 'code' => (($this->clock)() - $intent['createdAt'] > 600 ? 'aggregation_timeout' : 'aggregation_pending'), 'transaction' => $pending];
            }
            $this->journal->put($pending . '.done', $restoring ? 'restored' : 'applied');
            $this->journal->finish($pending);
            return ['success' => true, 'code' => $restoring ? 'restored' : 'applied', 'transaction' => $pending];
        } finally {
            if (($this->call)('IPS_SemaphoreLeave', [$lock]) !== true) {
                throw new RuntimeException('Writer lock release failed.');
            }
        }
    }

    /** @param array<string, mixed> $plan */
    private function validatePlan(array $plan): void
    {
        $now = ($this->clock)();
        if ($now < $plan['evaluatedAt'] || $now >= $plan['expiresAt'] || $now <= $plan['validFrom'] + 1 || $now >= $plan['validTo'] || array_keys($plan['chartProjection']['records']) !== array_keys($this->targets)) {
            throw new RuntimeException('Plan expired or target keys differ.');
        }
        foreach ($plan['chartProjection']['records'] as $rows) {
            $this->projectionRows($rows, $plan['validFrom']);
            if (count($rows) !== 2) {
                throw new RuntimeException('New projection cannot be empty.');
            }
        }
    }

    /** @param list<array{TimeStamp: int, Value: float}> $rows */
    private function projectionRows(array $rows, int $from): void
    {
        if ($rows === []) {
            return;
        }
        if (count($rows) !== 2 || $rows[0]['TimeStamp'] !== $from || $rows[1]['TimeStamp'] !== $from + 1 || $rows[0]['Value'] !== 0.0 || !is_finite($rows[1]['Value']) || $rows[1]['Value'] < 0) {
            throw new RuntimeException('Archive is not a dedicated two-point projection.');
        }
    }

    /** @return array<string, mixed> */
    private function validateTargets(): array
    {
        $owner = ($this->call)('IPS_GetObject', [$this->owner]);
        $archive = ($this->call)('IPS_GetInstance', [$this->archive]);
        $scriptType = defined('OBJECTTYPE_SCRIPT') ? constant('OBJECTTYPE_SCRIPT') : 3;
        $variableType = defined('OBJECTTYPE_VARIABLE') ? constant('OBJECTTYPE_VARIABLE') : 2;
        $floatType = defined('VARIABLETYPE_FLOAT') ? constant('VARIABLETYPE_FLOAT') : 2;
        if (!is_array($owner) || ($owner['ObjectInfo'] ?? null) !== 'SAEF SolarDisplay owner v1 ' . $this->generation) {
            throw new RuntimeException('Owner generation mismatch.');
        }
        if (($owner['ObjectType'] ?? null) !== $scriptType || !is_array($archive) || ($archive['ModuleInfo']['ModuleID'] ?? null) !== '{43192F0B-135B-4CE7-A0A7-1475603F3060}') {
            throw new RuntimeException('Invalid owner or Archive Control.');
        }
        $identities = [];
        foreach ($this->targets as $key => $id) {
            if (($this->call)('IPS_ObjectExists', [$id]) !== true) {
                throw new RuntimeException('Chart target missing.');
            }
            $object = ($this->call)('IPS_GetObject', [$id]);
            $variable = ($this->call)('IPS_GetVariable', [$id]);
            if (
                !is_array($object)
                || !is_array($variable)
                || ($object['ObjectType'] ?? null) !== $variableType
                || ($object['ParentID'] ?? null) !== $this->owner
                || ($object['ObjectIdent'] ?? null) !== 'SolarDisplay_Chart_' . $key
                || ($object['ObjectInfo'] ?? null) !== self::OWNER_MARKER . ' ' . $this->generation
                || ($variable['VariableType'] ?? null) !== $floatType
                || ($variable['VariableCustomProfile'] ?? null) !== '~Electricity'
                || ($variable['VariableAction'] ?? null) !== 0
                || ($variable['VariableCustomAction'] ?? null) !== 0
                || ($this->call)('AC_GetLoggingStatus', [$this->archive, $id]) !== true
                || ($this->call)('AC_GetAggregationType', [$this->archive, $id]) !== 1
                || ($this->call)('AC_GetCounterIgnoreZeros', [$this->archive, $id]) !== false
                || ($this->call)('AC_GetCompaction', [$this->archive, $id]) !== []
            ) {
                throw new RuntimeException('Chart ownership/type/archive contract differs.');
            }
            // Official IPS_GetVariable has no creation timestamp. Pin a generation
            // on the actual object instead; runtime never creates or changes it.
            $identities[$key] = ['id' => $id, 'generation' => $object['ObjectInfo']];
        }
        return $identities;
    }

    /** @return list<array{TimeStamp: int, Value: float}> */
    private function raw(int $id, int $from, int $to): array
    {
        $rows = ($this->call)('AC_GetLoggedValues', [$this->archive, $id, $from, $to - 1, 3]);
        if (!is_array($rows) || !array_is_list($rows) || count($rows) > 2) {
            throw new RuntimeException('Chart-only archive row bound exceeded.');
        }
        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !is_int($row['TimeStamp'] ?? null) || $row['TimeStamp'] < $from || $row['TimeStamp'] >= $to || (!is_int($row['Value'] ?? null) && !is_float($row['Value'] ?? null)) || !is_finite((float) $row['Value']) || $row['Value'] < 0) {
                throw new RuntimeException('Invalid chart raw record.');
            }
            $result[] = ['TimeStamp' => $row['TimeStamp'], 'Value' => (float) $row['Value']];
        }
        usort($result, static fn(array $a, array $b): int => $a['TimeStamp'] <=> $b['TimeStamp']);
        if (count($result) === 2 && $result[0]['TimeStamp'] === $result[1]['TimeStamp']) {
            throw new RuntimeException('Duplicate raw timestamp.');
        }
        return $result;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<array<string, mixed>> $allowed
     */
    private function subset(array $rows, array $allowed): bool
    {
        foreach ($rows as $row) {
            if (!in_array($row, $allowed, true)) {
                return false;
            }
        }
        return true;
    }

    /** @param array<string, list<array{TimeStamp: int, Value: float}>> $desired */
    private function aggregatesMatch(array $desired, int $from, int $to): bool
    {
        foreach ($this->targets as $key => $id) {
            if ($this->raw($id, $from, $to) !== $desired[$key]) {
                return false;
            }
            $rows = ($this->call)('AC_GetAggregatedValues', [$this->archive, $id, 1, $from, $to - 1, 2]);
            if ($desired[$key] === [] && $rows === []) {
                continue;
            }
            $expected = 0.0;
            for ($i = 1; $i < count($desired[$key]); $i++) {
                $expected += max(0.0, $desired[$key][$i]['Value'] - $desired[$key][$i - 1]['Value']);
            }
            if (!is_array($rows) || count($rows) !== 1 || ($rows[0]['TimeStamp'] ?? null) !== $from || (!is_int($rows[0]['Avg'] ?? null) && !is_float($rows[0]['Avg'] ?? null)) || !is_finite((float) $rows[0]['Avg']) || abs($rows[0]['Avg'] - $expected) > 0.000001) {
                return false;
            }
        }
        return true;
    }
}
