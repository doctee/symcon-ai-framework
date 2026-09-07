<?php

declare(strict_types=1);

const SAEF_DEPLOYMENT_APPROVAL_FORMAT_VERSION = 1;
const SAEF_DEPLOYMENT_APPROVAL_MAX_LIFETIME_SECONDS = 900;

/**
 * Creates a short-lived, scope-bound approval proof for one deployment plan.
 *
 * Raw user and host identities are hashed before they enter the proof. The
 * caller must obtain them from an authenticated local context and must keep the
 * HMAC secret outside repository and deployment evidence.
 *
 * @param array<string, mixed> $plan
 *
 * @return array<string, mixed>
 */
function createSaefDeploymentApproval(
    array $plan,
    string $approverIdentity,
    string $executionHostIdentity,
    string $secret,
    int $issuedAt,
    int $lifetimeSeconds = 300,
    ?string $nonce = null
): array {
    $normalizedPlan = normalizeSaefDeploymentApprovalPlan($plan);
    assertSaefDeploymentApprovalSecret($secret);
    if ($approverIdentity === '' || $executionHostIdentity === '') {
        throw new InvalidArgumentException('Approval user and host identities are required.');
    }
    if (
        $lifetimeSeconds < 1
        || $lifetimeSeconds > SAEF_DEPLOYMENT_APPROVAL_MAX_LIFETIME_SECONDS
    ) {
        throw new InvalidArgumentException('Approval lifetime is outside the allowed bound.');
    }
    $nonce ??= bin2hex(random_bytes(32));
    assertSaefDeploymentApprovalSha256($nonce, 'Approval nonce');

    $approval = [
        'formatVersion' => SAEF_DEPLOYMENT_APPROVAL_FORMAT_VERSION,
        'algorithm' => 'hmac-sha256',
        'planSha256' => hashSaefDeploymentApprovalValue($normalizedPlan),
        'targetId' => $normalizedPlan['targetId'],
        'adapterProfile' => $normalizedPlan['adapterProfile'],
        'allowedOperations' => $normalizedPlan['operations'],
        'expectedBaselineIdentities' => $normalizedPlan['expectedBaselineIdentities'],
        'channelHostBindingSha256' => $normalizedPlan['channelHostBindingSha256'],
        'approverIdentitySha256' => hash('sha256', $approverIdentity),
        'executionHostIdentitySha256' => hash('sha256', $executionHostIdentity),
        'issuedAt' => $issuedAt,
        'expiresAt' => $issuedAt + $lifetimeSeconds,
        'nonce' => $nonce,
    ];
    $approval['signature'] = hash_hmac(
        'sha256',
        encodeSaefDeploymentApprovalValue($approval),
        $secret
    );

    return $approval;
}

/**
 * Validates and normalizes a channel-v8 one-click deployment plan.
 *
 * High-risk channel mutations stay outside this contract. The optional reseal
 * phase is target-bound and must be followed by an independent final
 * postflight. Rollback is mandatory even though it is invoked only on failure.
 *
 * @param array<string, mixed> $plan
 *
 * @return array<string, mixed>
 */
function normalizeSaefDeploymentApprovalPlan(array $plan): array
{
    assertSaefDeploymentApprovalKeys(
        $plan,
        [
            'formatVersion',
            'channelVersion',
            'deploymentId',
            'targetId',
            'adapterProfile',
            'qualificationProfile',
            'postflightProfile',
            'package',
            'operations',
            'expectedBaselineIdentities',
            'channelHostBindingSha256',
            'riskScope',
        ],
        'Deployment approval plan'
    );
    if (($plan['formatVersion'] ?? null) !== 1 || ($plan['channelVersion'] ?? null) !== 8) {
        throw new InvalidArgumentException('Deployment approval plan version is unsupported.');
    }
    foreach (
        [
            'deploymentId' => 'deployment ID',
            'targetId' => 'target ID',
            'adapterProfile' => 'adapter profile',
            'qualificationProfile' => 'qualification profile',
            'postflightProfile' => 'postflight profile',
        ] as $key => $label
    ) {
        if (!is_string($plan[$key]) || !isSaefDeploymentApprovalIdentifier($plan[$key])) {
            throw new InvalidArgumentException('Deployment ' . $label . ' is invalid.');
        }
    }

    $package = $plan['package'];
    if (!is_array($package)) {
        throw new InvalidArgumentException('Deployment package identity is invalid.');
    }
    assertSaefDeploymentApprovalKeys($package, ['sha256', 'bytes'], 'Deployment package');
    assertSaefDeploymentApprovalSha256($package['sha256'] ?? null, 'Package identity');
    if (!is_int($package['bytes']) || $package['bytes'] < 1 || $package['bytes'] > 67_108_864) {
        throw new InvalidArgumentException('Deployment package size is invalid.');
    }

    $baseOperations = [
        'qualify',
        'stage',
        'preflight',
        'activate',
        'postflight',
        'rollback',
    ];
    $resealOperations = [
        'qualify',
        'stage',
        'preflight',
        'activate',
        'postflight',
        'reseal',
        'final_postflight',
        'rollback',
    ];
    if (!is_array($plan['operations']) || !array_is_list($plan['operations'])) {
        throw new InvalidArgumentException('Deployment operations are invalid.');
    }
    if ($plan['operations'] !== $baseOperations && $plan['operations'] !== $resealOperations) {
        throw new InvalidArgumentException('Deployment operations do not match a supported sequence.');
    }

    $identities = $plan['expectedBaselineIdentities'];
    if (!is_array($identities) || array_is_list($identities)) {
        throw new InvalidArgumentException('Expected baseline identities are invalid.');
    }
    $normalizedIdentities = [];
    foreach ($identities as $key => $value) {
        if (!is_string($key) || !isSaefDeploymentApprovalIdentifier($key)) {
            throw new InvalidArgumentException('Expected baseline identity name is invalid.');
        }
        assertSaefDeploymentApprovalSha256($value, 'Expected baseline identity');
        $normalizedIdentities[$key] = $value;
    }
    ksort($normalizedIdentities, SORT_STRING);

    assertSaefDeploymentApprovalSha256(
        $plan['channelHostBindingSha256'] ?? null,
        'Channel host binding'
    );
    $riskScope = $plan['riskScope'];
    if (!is_array($riskScope)) {
        throw new InvalidArgumentException('Deployment risk scope is invalid.');
    }
    assertSaefDeploymentApprovalKeys(
        $riskScope,
        [
            'allowlistChange',
            'serviceRestart',
            'providerContact',
            'publication',
            'retentionDeletion',
            'activeIdentityReseal',
        ],
        'Deployment risk scope'
    );
    foreach ($riskScope as $name => $value) {
        if (!is_bool($value)) {
            throw new InvalidArgumentException('Deployment risk scope values must be boolean.');
        }
        if ($name !== 'activeIdentityReseal' && $value) {
            throw new InvalidArgumentException(
                'High-risk operation is outside one-click approval: ' . $name
            );
        }
    }
    $hasReseal = in_array('reseal', $plan['operations'], true);
    if ($riskScope['activeIdentityReseal'] !== $hasReseal) {
        throw new InvalidArgumentException('Reseal risk and operation bindings differ.');
    }

    $normalized = $plan;
    $normalized['package'] = [
        'bytes' => $package['bytes'],
        'sha256' => $package['sha256'],
    ];
    $normalized['expectedBaselineIdentities'] = $normalizedIdentities;
    $normalized['riskScope'] = [
        'activeIdentityReseal' => $riskScope['activeIdentityReseal'],
        'allowlistChange' => $riskScope['allowlistChange'],
        'providerContact' => $riskScope['providerContact'],
        'publication' => $riskScope['publication'],
        'retentionDeletion' => $riskScope['retentionDeletion'],
        'serviceRestart' => $riskScope['serviceRestart'],
    ];

    return normalizeSaefDeploymentApprovalValue($normalized);
}

/**
 * Coordinates one approved plan through fixed, non-extensible execution phases.
 *
 * The runner receives only a phase identifier and immutable structured state.
 * It must map these phases to already installed, hash-pinned platform actions.
 * No command line or executable path is accepted from the plan or approval.
 */
final class SaefDeploymentApprovalCoordinator
{
    private string $stateRoot;
    private string $secret;
    private string $approverIdentity;
    private string $executionHostIdentity;
    private int $maximumStateFiles;

    /** @var Closure(): int */
    private Closure $clock;

    /**
     * @param null|callable(): int $clock
     */
    public function __construct(
        string $stateRoot,
        string $secret,
        string $approverIdentity,
        string $executionHostIdentity,
        ?callable $clock = null,
        int $maximumStateFiles = 256
    ) {
        assertSaefDeploymentApprovalSecret($secret);
        if ($approverIdentity === '' || $executionHostIdentity === '') {
            throw new InvalidArgumentException('Coordinator user and host identities are required.');
        }
        if (!is_dir($stateRoot) || is_link($stateRoot) || !is_writable($stateRoot)) {
            throw new InvalidArgumentException('Coordinator state root must be an existing writable directory.');
        }
        if ($maximumStateFiles < 1 || $maximumStateFiles > 4096) {
            throw new InvalidArgumentException('Coordinator state capacity is invalid.');
        }
        $resolvedRoot = realpath($stateRoot);
        if ($resolvedRoot === false) {
            throw new InvalidArgumentException('Coordinator state root cannot be resolved.');
        }
        $this->stateRoot = $resolvedRoot;
        $this->secret = $secret;
        $this->approverIdentity = $approverIdentity;
        $this->executionHostIdentity = $executionHostIdentity;
        $this->maximumStateFiles = $maximumStateFiles;
        $this->clock = $clock !== null
            ? Closure::fromCallable($clock)
            : static fn (): int => time();
    }

    /**
     * Executes or safely resumes one approved plan.
     *
     * @param array<string, mixed> $plan
     * @param array<string, mixed> $approval
     * @param callable(string, array<string, mixed>): array<string, mixed> $runner
     *
     * @return array<string, mixed>
     */
    public function apply(array $plan, array $approval, callable $runner): array
    {
        $plan = normalizeSaefDeploymentApprovalPlan($plan);
        $planSha256 = hashSaefDeploymentApprovalValue($plan);
        $approval = $this->verifyApproval($plan, $approval);
        $lock = $this->acquireLock();

        try {
            $state = $this->loadState($planSha256);
            if ($state === null) {
                $this->assertStateCapacity();
                $state = $this->initialState($plan, $approval);
                $this->writeState($planSha256, $state);
            } else {
                $this->assertResumableState($state, $approval);
            }

            if (($state['stepState'] ?? null) === 'started') {
                $state = $this->reconcileInterruptedStep($plan, $state, $runner);
            }
            if (($state['outcome'] ?? null) !== 'running') {
                return $state;
            }

            $steps = $this->executionSteps($plan);
            $start = (int) ($state['completedStepCount'] ?? 0);
            for ($index = $start; $index < count($steps); $index++) {
                $step = $steps[$index];
                $state = $this->runStep($plan, $state, $step, $runner);
                if (($state['outcome'] ?? null) !== 'running') {
                    return $state;
                }
            }
            $state['phase'] = 'complete';
            $state['outcome'] = 'completed';
            $state['stepState'] = 'completed';
            $state['updatedAt'] = ($this->clock)();
            $this->writeState($planSha256, $state);

            return $state;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return array<string, mixed>|null */
    public function status(string $planSha256): ?array
    {
        assertSaefDeploymentApprovalSha256($planSha256, 'Plan identity');

        return $this->loadState($planSha256);
    }

    /**
     * @param array<string, mixed> $plan
     * @param array<string, mixed> $approval
     *
     * @return array<string, mixed>
     */
    private function verifyApproval(array $plan, array $approval): array
    {
        $signature = $approval['signature'] ?? null;
        assertSaefDeploymentApprovalSha256($signature, 'Approval signature');
        $unsigned = $approval;
        unset($unsigned['signature']);
        $expected = hash_hmac(
            'sha256',
            encodeSaefDeploymentApprovalValue($unsigned),
            $this->secret
        );
        if (!hash_equals($expected, $signature)) {
            throw new RuntimeException('Deployment approval signature differs.');
        }
        assertSaefDeploymentApprovalKeys(
            $approval,
            [
                'formatVersion',
                'algorithm',
                'planSha256',
                'targetId',
                'adapterProfile',
                'allowedOperations',
                'expectedBaselineIdentities',
                'channelHostBindingSha256',
                'approverIdentitySha256',
                'executionHostIdentitySha256',
                'issuedAt',
                'expiresAt',
                'nonce',
                'signature',
            ],
            'Deployment approval'
        );
        $now = ($this->clock)();
        if (
            ($approval['formatVersion'] ?? null) !== 1
            || ($approval['algorithm'] ?? null) !== 'hmac-sha256'
            || !is_int($approval['issuedAt'])
            || !is_int($approval['expiresAt'])
            || $approval['issuedAt'] > $now + 60
            || $approval['expiresAt'] < $now
            || $approval['expiresAt'] - $approval['issuedAt'] < 1
            || $approval['expiresAt'] - $approval['issuedAt']
                > SAEF_DEPLOYMENT_APPROVAL_MAX_LIFETIME_SECONDS
        ) {
            throw new RuntimeException('Deployment approval is expired or has invalid timing.');
        }
        assertSaefDeploymentApprovalSha256($approval['nonce'] ?? null, 'Approval nonce');

        $bindings = [
            'planSha256' => hashSaefDeploymentApprovalValue($plan),
            'targetId' => $plan['targetId'],
            'adapterProfile' => $plan['adapterProfile'],
            'allowedOperations' => $plan['operations'],
            'expectedBaselineIdentities' => $plan['expectedBaselineIdentities'],
            'channelHostBindingSha256' => $plan['channelHostBindingSha256'],
            'approverIdentitySha256' => hash('sha256', $this->approverIdentity),
            'executionHostIdentitySha256' => hash('sha256', $this->executionHostIdentity),
        ];
        foreach ($bindings as $key => $value) {
            if (($approval[$key] ?? null) !== $value) {
                throw new RuntimeException('Deployment approval binding differs: ' . $key);
            }
        }

        return $approval;
    }

    /** @return resource */
    private function acquireLock()
    {
        $path = $this->stateRoot . DIRECTORY_SEPARATOR . '.coordinator.lock';
        if (is_link($path)) {
            throw new RuntimeException('Deployment approval lock cannot be a symbolic link.');
        }
        $lock = fopen($path, 'c+b');
        if ($lock === false) {
            throw new RuntimeException('Deployment approval lock cannot be opened.');
        }
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            throw new RuntimeException('Deployment approval is already being processed.');
        }

        return $lock;
    }

    private function assertStateCapacity(): void
    {
        $stateFiles = glob($this->stateRoot . DIRECTORY_SEPARATOR . '*.json');
        if ($stateFiles === false || count($stateFiles) >= $this->maximumStateFiles) {
            throw new RuntimeException('Deployment approval state capacity is exhausted.');
        }
    }

    /**
     * @param array<string, mixed> $plan
     * @param array<string, mixed> $approval
     *
     * @return array<string, mixed>
     */
    private function initialState(array $plan, array $approval): array
    {
        $now = ($this->clock)();

        return [
            'formatVersion' => 1,
            'planSha256' => $approval['planSha256'],
            'targetId' => $plan['targetId'],
            'adapterProfile' => $plan['adapterProfile'],
            'nonceSha256' => hash('sha256', (string) $approval['nonce']),
            'approvalExpiresAt' => $approval['expiresAt'],
            'phase' => 'claimed',
            'stepState' => 'completed',
            'completedStepCount' => 0,
            'mutationPossible' => false,
            'outcome' => 'running',
            'evidence' => [],
            'createdAt' => $now,
            'updatedAt' => $now,
        ];
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $approval
     */
    private function assertResumableState(array $state, array $approval): void
    {
        if (
            ($state['nonceSha256'] ?? null) !== hash('sha256', (string) $approval['nonce'])
            || ($state['planSha256'] ?? null) !== $approval['planSha256']
        ) {
            throw new RuntimeException('Deployment approval was already consumed by another claim.');
        }
        if (($state['outcome'] ?? null) !== 'running') {
            throw new RuntimeException('Deployment approval has already reached a terminal outcome.');
        }
    }

    /** @param array<string, mixed> $plan @return list<string> */
    private function executionSteps(array $plan): array
    {
        return array_values(array_filter(
            $plan['operations'],
            static fn (string $operation): bool => $operation !== 'rollback'
        ));
    }

    /**
     * @param array<string, mixed> $plan
     * @param array<string, mixed> $state
     * @param callable(string, array<string, mixed>): array<string, mixed> $runner
     *
     * @return array<string, mixed>
     */
    private function runStep(
        array $plan,
        array $state,
        string $step,
        callable $runner
    ): array {
        $state['phase'] = $step;
        $state['stepState'] = 'started';
        if (in_array($step, ['stage', 'activate', 'reseal'], true)) {
            $state['mutationPossible'] = true;
        }
        $state['updatedAt'] = ($this->clock)();
        $this->writeState((string) $state['planSha256'], $state);

        try {
            $result = $runner($step, $this->runnerContext($plan, $state));
            $this->assertStepResult($plan, $step, $result);
            $state['evidence'][$step] = $result['evidenceSha256'];
            $state['completedStepCount'] = (int) $state['completedStepCount'] + 1;
            $state['stepState'] = 'completed';
            $state['updatedAt'] = ($this->clock)();
            $this->writeState((string) $state['planSha256'], $state);

            return $state;
        } catch (Throwable $exception) {
            return $this->handleFailure($plan, $state, $runner, $exception);
        }
    }

    /**
     * @param array<string, mixed> $plan
     * @param array<string, mixed> $state
     * @param callable(string, array<string, mixed>): array<string, mixed> $runner
     *
     * @return array<string, mixed>
     */
    private function reconcileInterruptedStep(array $plan, array $state, callable $runner): array
    {
        $result = $runner('inspect', $this->runnerContext($plan, $state));
        assertSaefDeploymentApprovalKeys(
            $result,
            ['outcome', 'evidenceSha256', 'mutationAttempted'],
            'Interrupted deployment inspection'
        );
        assertSaefDeploymentApprovalSha256(
            $result['evidenceSha256'] ?? null,
            'Inspection evidence'
        );
        if (($result['mutationAttempted'] ?? null) !== false) {
            throw new RuntimeException('Interrupted deployment inspection attempted mutation.');
        }
        $state['evidence']['inspect'] = $result['evidenceSha256'];
        if (($result['outcome'] ?? null) === 'step_completed') {
            $state['completedStepCount'] = (int) $state['completedStepCount'] + 1;
            $state['stepState'] = 'completed';
            $state['updatedAt'] = ($this->clock)();
            $this->writeState((string) $state['planSha256'], $state);

            return $state;
        }
        if (($result['outcome'] ?? null) === 'rolled_back') {
            $state['phase'] = 'rollback';
            $state['stepState'] = 'completed';
            $state['outcome'] = 'rolled_back';
            $state['updatedAt'] = ($this->clock)();
            $this->writeState((string) $state['planSha256'], $state);

            return $state;
        }

        $state['phase'] = 'manual_recovery';
        $state['stepState'] = 'uncertain';
        $state['outcome'] = 'manual_recovery_required';
        $state['updatedAt'] = ($this->clock)();
        $this->writeState((string) $state['planSha256'], $state);

        return $state;
    }

    /**
     * @param array<string, mixed> $plan
     * @param array<string, mixed> $state
     * @param callable(string, array<string, mixed>): array<string, mixed> $runner
     *
     * @return array<string, mixed>
     */
    private function handleFailure(
        array $plan,
        array $state,
        callable $runner,
        Throwable $exception
    ): array {
        $state['failureType'] = get_debug_type($exception);
        if (!$state['mutationPossible']) {
            $state['phase'] = 'aborted';
            $state['outcome'] = 'aborted';
            $state['stepState'] = 'failed';
            $state['updatedAt'] = ($this->clock)();
            $this->writeState((string) $state['planSha256'], $state);

            return $state;
        }

        $state['phase'] = 'rollback';
        $state['stepState'] = 'started';
        $state['updatedAt'] = ($this->clock)();
        $this->writeState((string) $state['planSha256'], $state);
        try {
            $result = $runner('rollback', $this->runnerContext($plan, $state));
            assertSaefDeploymentApprovalKeys(
                $result,
                [
                    'outcome',
                    'evidenceSha256',
                    'mutationAttempted',
                    'rollbackAttempted',
                    'rollbackSucceeded',
                ],
                'Deployment rollback'
            );
            assertSaefDeploymentApprovalSha256(
                $result['evidenceSha256'] ?? null,
                'Rollback evidence'
            );
            $state['evidence']['rollback'] = $result['evidenceSha256'];
            if (
                ($result['outcome'] ?? null) !== 'rolled_back'
                || ($result['mutationAttempted'] ?? null) !== true
                || ($result['rollbackAttempted'] ?? null) !== true
                || ($result['rollbackSucceeded'] ?? null) !== true
            ) {
                throw new RuntimeException('Deployment rollback was not proven.');
            }
            $state['outcome'] = 'rolled_back';
            $state['stepState'] = 'completed';
        } catch (Throwable) {
            $state['outcome'] = 'manual_recovery_required';
            $state['stepState'] = 'uncertain';
        }
        $state['updatedAt'] = ($this->clock)();
        $this->writeState((string) $state['planSha256'], $state);

        return $state;
    }

    /**
     * @param array<string, mixed> $plan
     * @param array<string, mixed> $state
     *
     * @return array<string, mixed>
     */
    private function runnerContext(array $plan, array $state): array
    {
        return [
            'plan' => $plan,
            'planSha256' => $state['planSha256'],
            'phase' => $state['phase'],
            'completedStepCount' => $state['completedStepCount'],
            'expectedBaselineIdentities' => $plan['expectedBaselineIdentities'],
        ];
    }

    /** @param array<string, mixed> $plan @param array<string, mixed> $result */
    private function assertStepResult(array $plan, string $step, array $result): void
    {
        $required = ['outcome', 'evidenceSha256', 'mutationAttempted'];
        if ($step === 'preflight') {
            $required[] = 'baselineIdentities';
        }
        assertSaefDeploymentApprovalKeys($result, $required, 'Deployment phase result');
        assertSaefDeploymentApprovalSha256(
            $result['evidenceSha256'] ?? null,
            'Deployment phase evidence'
        );
        $expectedOutcome = match ($step) {
            'stage' => 'staged',
            'activate' => 'activated',
            'reseal' => 'resealed',
            default => 'passed',
        };
        if (($result['outcome'] ?? null) !== $expectedOutcome) {
            throw new RuntimeException('Deployment phase did not reach its required outcome.');
        }
        $expectedMutation = in_array($step, ['stage', 'activate', 'reseal'], true);
        if (($result['mutationAttempted'] ?? null) !== $expectedMutation) {
            throw new RuntimeException('Deployment phase mutation evidence differs.');
        }
        if (
            $step === 'preflight'
            && ($result['baselineIdentities'] ?? null)
                !== $plan['expectedBaselineIdentities']
        ) {
            throw new RuntimeException('Deployment baseline identities drifted before mutation.');
        }
    }

    /** @return array<string, mixed>|null */
    private function loadState(string $planSha256): ?array
    {
        $path = $this->statePath($planSha256);
        if (is_link($path)) {
            throw new RuntimeException('Deployment approval state cannot be a symbolic link.');
        }
        if (!is_file($path)) {
            return null;
        }
        $contents = file_get_contents($path);
        if ($contents === false || strlen($contents) > 262_144) {
            throw new RuntimeException('Deployment approval state cannot be read safely.');
        }
        $state = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($state) || ($state['formatVersion'] ?? null) !== 1) {
            throw new RuntimeException('Deployment approval state is invalid.');
        }
        $signature = $state['stateSignature'] ?? null;
        assertSaefDeploymentApprovalSha256($signature, 'Deployment state signature');
        unset($state['stateSignature']);
        $expected = hash_hmac(
            'sha256',
            encodeSaefDeploymentApprovalValue($state),
            $this->secret
        );
        if (!hash_equals($expected, $signature)) {
            throw new RuntimeException('Deployment approval state integrity differs.');
        }
        $state['stateSignature'] = $signature;

        return $state;
    }

    /** @param array<string, mixed> $state */
    private function writeState(string $planSha256, array $state): void
    {
        $path = $this->statePath($planSha256);
        if (is_link($path)) {
            throw new RuntimeException('Deployment approval state cannot be a symbolic link.');
        }
        unset($state['stateSignature']);
        $state['stateSignature'] = hash_hmac(
            'sha256',
            encodeSaefDeploymentApprovalValue($state),
            $this->secret
        );
        $temporary = $path . '.tmp-' . bin2hex(random_bytes(8));
        $contents = json_encode(
            $state,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ) . "\n";
        if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Deployment approval state cannot be written.');
        }
        if (!rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Deployment approval state cannot be committed atomically.');
        }
    }

    private function statePath(string $planSha256): string
    {
        return $this->stateRoot . DIRECTORY_SEPARATOR . $planSha256 . '.json';
    }
}

/** @param array<string, mixed> $value */
function hashSaefDeploymentApprovalValue(array $value): string
{
    return hash('sha256', encodeSaefDeploymentApprovalValue($value));
}

/** @param array<string, mixed> $value */
function encodeSaefDeploymentApprovalValue(array $value): string
{
    return json_encode(
        normalizeSaefDeploymentApprovalValue($value),
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
}

/** @return mixed */
function normalizeSaefDeploymentApprovalValue(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }
    if (array_is_list($value)) {
        return array_map('normalizeSaefDeploymentApprovalValue', $value);
    }
    ksort($value, SORT_STRING);
    foreach ($value as $key => $item) {
        $value[$key] = normalizeSaefDeploymentApprovalValue($item);
    }

    return $value;
}

/** @param list<string> $expected */
function assertSaefDeploymentApprovalKeys(array $value, array $expected, string $label): void
{
    $actual = array_keys($value);
    sort($actual, SORT_STRING);
    sort($expected, SORT_STRING);
    if ($actual !== $expected) {
        throw new InvalidArgumentException($label . ' fields are invalid.');
    }
}

function assertSaefDeploymentApprovalSecret(string $secret): void
{
    if (strlen($secret) < 32) {
        throw new InvalidArgumentException('Deployment approval secret is too short.');
    }
}

function assertSaefDeploymentApprovalSha256(mixed $value, string $label): void
{
    if (!is_string($value) || preg_match('/^[a-f0-9]{64}$/D', $value) !== 1) {
        throw new InvalidArgumentException($label . ' must be a lowercase SHA-256 value.');
    }
}

function isSaefDeploymentApprovalIdentifier(string $value): bool
{
    return preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/D', $value) === 1;
}
