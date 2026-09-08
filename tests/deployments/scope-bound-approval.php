<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/tools/deployment/ScopeBoundApproval.php';

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR
    . 'saef-deployment-approval-test-' . bin2hex(random_bytes(8));

try {
    if (!mkdir($root, 0700, true) && !is_dir($root)) {
        throw new RuntimeException('Cannot create deployment approval test root.');
    }

    $now = 1_800_000_000;
    $secret = str_repeat('scope-bound-test-secret-', 2);
    $plan = deploymentApprovalTestPlan();
    $approval = createSaefDeploymentApproval(
        $plan,
        'test-approver',
        'test-execution-host',
        $secret,
        $now,
        300,
        hash('sha256', 'happy-path-nonce')
    );

    deploymentApprovalTestExamples();
    deploymentApprovalTestNormalization($plan);
    deploymentApprovalTestHappyPath($root, $secret, $now, $plan, $approval);
    deploymentApprovalTestProofFailures($root, $secret, $now, $plan, $approval);
    deploymentApprovalTestDriftAndRollback($root, $secret, $now, $plan);
    deploymentApprovalTestRollbackFailure($root, $secret, $now, $plan);
    deploymentApprovalTestPreMutationAbort($root, $secret, $now, $plan);
    deploymentApprovalTestParallelClaim($root, $secret, $now, $plan);
    deploymentApprovalTestStateIntegrity($root, $secret, $now, $plan);
    deploymentApprovalTestStateCapacity($root, $secret, $now, $plan);
    deploymentApprovalTestInterruptedResume($root, $secret, $now, $plan);
    deploymentApprovalTestUncertainResume($root, $secret, $now, $plan);
    deploymentApprovalTestReseal($root, $secret, $now, $plan);
    deploymentApprovalTestRiskBoundaries($plan);

    fwrite(STDOUT, "scope-bound-deployment-approval: ok\n");
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        'scope-bound-deployment-approval: failed: ' . $exception->getMessage() . "\n"
    );
    exit(1);
} finally {
    deploymentApprovalTestRemoveTree($root);
}

function deploymentApprovalTestExamples(): void
{
    $root = dirname(__DIR__, 2) . '/deployments/symcon/windows';
    $paths = [
        $root . '/deployment-approval-plan.example.json',
        $root . '/adapters/owntracks-position-map-approval-plan.example.json',
    ];
    foreach ($paths as $path) {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Cannot read deployment approval example.');
        }
        $plan = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($plan)) {
            throw new RuntimeException('Deployment approval example is invalid.');
        }
        normalizeSaefDeploymentApprovalPlan($plan);
    }
}

/** @return array<string, mixed> */
function deploymentApprovalTestPlan(): array
{
    return [
        'formatVersion' => 1,
        'channelVersion' => 8,
        'deploymentId' => 'saef-test-module-20260907-01',
        'targetId' => 'test-module',
        'adapterProfile' => 'saef-test-module-v1',
        'qualificationProfile' => 'saef-windows-powershell-5.1-test-v1',
        'postflightProfile' => 'saef-test-module-health-v1',
        'package' => [
            'sha256' => hash('sha256', 'package'),
            'bytes' => 1024,
        ],
        'operations' => [
            'qualify',
            'stage',
            'preflight',
            'activate',
            'postflight',
            'rollback',
        ],
        'expectedBaselineIdentities' => [
            'channelPolicySha256' => hash('sha256', 'channel-policy'),
            'adapterPolicySha256' => hash('sha256', 'adapter-policy'),
            'activePackageSha256' => hash('sha256', 'active-package'),
        ],
        'channelHostBindingSha256' => hash('sha256', 'channel-host'),
        'riskScope' => [
            'allowlistChange' => false,
            'serviceRestart' => false,
            'providerContact' => false,
            'publication' => false,
            'retentionDeletion' => false,
            'activeIdentityReseal' => false,
        ],
    ];
}

/** @param array<string, mixed> $plan */
function deploymentApprovalTestNormalization(array $plan): void
{
    $reordered = array_reverse($plan, true);
    $reordered['expectedBaselineIdentities'] = array_reverse(
        $plan['expectedBaselineIdentities'],
        true
    );
    deploymentApprovalTestSame(
        hashSaefDeploymentApprovalValue(normalizeSaefDeploymentApprovalPlan($plan)),
        hashSaefDeploymentApprovalValue(normalizeSaefDeploymentApprovalPlan($reordered)),
        'Plan hash is not stable for equivalent maps.'
    );
}

/**
 * @param array<string, mixed> $plan
 * @param array<string, mixed> $approval
 */
function deploymentApprovalTestHappyPath(
    string $root,
    string $secret,
    int $now,
    array $plan,
    array $approval
): void {
    $caseRoot = deploymentApprovalTestCaseRoot($root, 'happy');
    $steps = [];
    $coordinator = deploymentApprovalTestCoordinator($caseRoot, $secret, $now);
    $result = $coordinator->apply(
        $plan,
        $approval,
        deploymentApprovalTestRunner($plan, $steps)
    );
    deploymentApprovalTestSame('completed', $result['outcome'], 'Happy path did not complete.');
    deploymentApprovalTestSame(
        ['qualify', 'stage', 'preflight', 'activate', 'postflight'],
        $steps,
        'Happy path phase order differs.'
    );
    $status = $coordinator->status((string) $approval['planSha256']);
    deploymentApprovalTestSame('completed', $status['outcome'] ?? null, 'Terminal status was lost.');
    deploymentApprovalTestThrows(
        static fn (): array => $coordinator->apply(
            $plan,
            $approval,
            deploymentApprovalTestRunner($plan, $steps)
        ),
        'terminal outcome'
    );

    $secondApproval = createSaefDeploymentApproval(
        $plan,
        'test-approver',
        'test-execution-host',
        $secret,
        $now,
        300,
        hash('sha256', 'second-click-nonce')
    );
    deploymentApprovalTestThrows(
        static fn (): array => $coordinator->apply(
            $plan,
            $secondApproval,
            deploymentApprovalTestRunner($plan, $steps)
        ),
        'another claim'
    );
}

/**
 * @param array<string, mixed> $plan
 * @param array<string, mixed> $approval
 */
function deploymentApprovalTestProofFailures(
    string $root,
    string $secret,
    int $now,
    array $plan,
    array $approval
): void {
    $tampered = $approval;
    $tampered['targetId'] = 'other-target';
    deploymentApprovalTestThrows(
        static fn (): array => deploymentApprovalTestCoordinator(
            deploymentApprovalTestCaseRoot($root, 'tamper'),
            $secret,
            $now
        )->apply($plan, $tampered, static fn (): array => []),
        'signature differs'
    );

    $expired = createSaefDeploymentApproval(
        $plan,
        'test-approver',
        'test-execution-host',
        $secret,
        $now - 400,
        300,
        hash('sha256', 'expired-nonce')
    );
    deploymentApprovalTestThrows(
        static fn (): array => deploymentApprovalTestCoordinator(
            deploymentApprovalTestCaseRoot($root, 'expired'),
            $secret,
            $now
        )->apply($plan, $expired, static fn (): array => []),
        'expired'
    );

    deploymentApprovalTestThrows(
        static fn (): array => (new SaefDeploymentApprovalCoordinator(
            deploymentApprovalTestCaseRoot($root, 'wrong-user'),
            $secret,
            'other-approver',
            'test-execution-host',
            static fn (): int => $now
        ))->apply($plan, $approval, static fn (): array => []),
        'approverIdentitySha256'
    );

    $changedPlan = $plan;
    $changedPlan['package']['bytes'] = 2048;
    deploymentApprovalTestThrows(
        static fn (): array => deploymentApprovalTestCoordinator(
            deploymentApprovalTestCaseRoot($root, 'changed-plan'),
            $secret,
            $now
        )->apply($changedPlan, $approval, static fn (): array => []),
        'planSha256'
    );
}

/** @param array<string, mixed> $plan */
function deploymentApprovalTestDriftAndRollback(
    string $root,
    string $secret,
    int $now,
    array $plan
): void {
    $approval = deploymentApprovalTestApproval($plan, $secret, $now, 'drift');
    $steps = [];
    $runner = static function (string $step, array $context) use (&$steps): array {
        $steps[] = $step;
        if ($step === 'preflight') {
            return [
                'outcome' => 'passed',
                'evidenceSha256' => hash('sha256', $step),
                'mutationAttempted' => false,
                'baselineIdentities' => [
                    'activePackageSha256' => hash('sha256', 'drifted'),
                ],
            ];
        }

        return deploymentApprovalTestResult($step, $context['plan']);
    };
    $result = deploymentApprovalTestCoordinator(
        deploymentApprovalTestCaseRoot($root, 'drift'),
        $secret,
        $now
    )->apply($plan, $approval, $runner);
    deploymentApprovalTestSame('rolled_back', $result['outcome'], 'Drift did not roll back.');
    deploymentApprovalTestSame(
        ['qualify', 'stage', 'preflight', 'rollback'],
        $steps,
        'Drift rollback sequence differs.'
    );
}

/** @param array<string, mixed> $plan */
function deploymentApprovalTestRollbackFailure(
    string $root,
    string $secret,
    int $now,
    array $plan
): void {
    $approval = deploymentApprovalTestApproval($plan, $secret, $now, 'rollback-failure');
    $runner = static function (string $step, array $context): array {
        if ($step === 'activate') {
            return [
                'outcome' => 'failed',
                'evidenceSha256' => hash('sha256', 'partial-activation'),
                'mutationAttempted' => true,
            ];
        }
        if ($step === 'rollback') {
            return [
                'outcome' => 'manual_recovery_required',
                'evidenceSha256' => hash('sha256', 'unproven-rollback'),
                'mutationAttempted' => true,
                'rollbackAttempted' => true,
                'rollbackSucceeded' => false,
            ];
        }

        return deploymentApprovalTestResult($step, $context['plan']);
    };
    $result = deploymentApprovalTestCoordinator(
        deploymentApprovalTestCaseRoot($root, 'rollback-failure'),
        $secret,
        $now
    )->apply($plan, $approval, $runner);
    deploymentApprovalTestSame(
        'manual_recovery_required',
        $result['outcome'],
        'Unproven rollback did not stop for manual recovery.'
    );
}

/** @param array<string, mixed> $plan */
function deploymentApprovalTestPreMutationAbort(
    string $root,
    string $secret,
    int $now,
    array $plan
): void {
    $approval = deploymentApprovalTestApproval($plan, $secret, $now, 'qualification-failure');
    $steps = [];
    $result = deploymentApprovalTestCoordinator(
        deploymentApprovalTestCaseRoot($root, 'qualification-failure'),
        $secret,
        $now
    )->apply(
        $plan,
        $approval,
        static function (string $step) use (&$steps): array {
            $steps[] = $step;
            throw new RuntimeException('Synthetic qualification failure.');
        }
    );
    deploymentApprovalTestSame('aborted', $result['outcome'], 'Early failure did not abort.');
    deploymentApprovalTestSame(['qualify'], $steps, 'Early failure attempted another phase.');
}

/** @param array<string, mixed> $plan */
function deploymentApprovalTestParallelClaim(
    string $root,
    string $secret,
    int $now,
    array $plan
): void {
    $caseRoot = deploymentApprovalTestCaseRoot($root, 'parallel');
    $approval = deploymentApprovalTestApproval($plan, $secret, $now, 'parallel');
    $parallelRejected = false;
    $steps = [];
    $runner = static function (
        string $step,
        array $context
    ) use (
        &$parallelRejected,
        &$steps,
        $caseRoot,
        $secret,
        $now,
        $plan,
        $approval
    ): array {
        $steps[] = $step;
        if ($step === 'qualify') {
            try {
                deploymentApprovalTestCoordinator($caseRoot, $secret, $now)->apply(
                    $plan,
                    $approval,
                    static fn (): array => []
                );
            } catch (RuntimeException $exception) {
                $parallelRejected = str_contains($exception->getMessage(), 'already being processed');
            }
        }

        return deploymentApprovalTestResult($step, $context['plan']);
    };
    $result = deploymentApprovalTestCoordinator($caseRoot, $secret, $now)->apply(
        $plan,
        $approval,
        $runner
    );
    deploymentApprovalTestSame(true, $parallelRejected, 'Parallel execution was not rejected.');
    deploymentApprovalTestSame('completed', $result['outcome'], 'Primary execution did not complete.');
}

/** @param array<string, mixed> $plan */
function deploymentApprovalTestStateIntegrity(
    string $root,
    string $secret,
    int $now,
    array $plan
): void {
    $caseRoot = deploymentApprovalTestCaseRoot($root, 'state-integrity');
    $approval = deploymentApprovalTestApproval($plan, $secret, $now, 'state-integrity');
    $steps = [];
    $coordinator = deploymentApprovalTestCoordinator($caseRoot, $secret, $now);
    $coordinator->apply($plan, $approval, deploymentApprovalTestRunner($plan, $steps));
    $path = $caseRoot . DIRECTORY_SEPARATOR . $approval['planSha256'] . '.json';
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException('Cannot read state-integrity fixture.');
    }
    $state = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($state)) {
        throw new RuntimeException('State-integrity fixture is invalid.');
    }
    $state['outcome'] = 'running';
    $changed = json_encode(
        $state,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . "\n";
    if (file_put_contents($path, $changed) === false) {
        throw new RuntimeException('Cannot alter state-integrity fixture.');
    }
    deploymentApprovalTestThrows(
        static fn (): ?array => $coordinator->status((string) $approval['planSha256']),
        'state integrity differs'
    );
}

/** @param array<string, mixed> $plan */
function deploymentApprovalTestStateCapacity(
    string $root,
    string $secret,
    int $now,
    array $plan
): void {
    $caseRoot = deploymentApprovalTestCaseRoot($root, 'state-capacity');
    $coordinator = new SaefDeploymentApprovalCoordinator(
        $caseRoot,
        $secret,
        'test-approver',
        'test-execution-host',
        static fn (): int => $now,
        1
    );
    $firstApproval = deploymentApprovalTestApproval($plan, $secret, $now, 'capacity-first');
    $steps = [];
    $coordinator->apply(
        $plan,
        $firstApproval,
        deploymentApprovalTestRunner($plan, $steps)
    );

    $secondPlan = $plan;
    $secondPlan['deploymentId'] = 'saef-test-module-20260907-02';
    $secondApproval = deploymentApprovalTestApproval(
        $secondPlan,
        $secret,
        $now,
        'capacity-second'
    );
    deploymentApprovalTestThrows(
        static fn (): array => $coordinator->apply(
            $secondPlan,
            $secondApproval,
            deploymentApprovalTestRunner($secondPlan, $steps)
        ),
        'capacity is exhausted'
    );
}

/** @param array<string, mixed> $plan */
function deploymentApprovalTestInterruptedResume(
    string $root,
    string $secret,
    int $now,
    array $plan
): void {
    $caseRoot = deploymentApprovalTestCaseRoot($root, 'resume');
    $approval = deploymentApprovalTestApproval($plan, $secret, $now, 'resume');
    deploymentApprovalTestWriteInterruptedState(
        $caseRoot,
        $approval,
        $secret,
        $now,
        'activate',
        3
    );
    $steps = [];
    $runner = static function (string $step, array $context) use (&$steps): array {
        $steps[] = $step;
        if ($step === 'inspect') {
            return [
                'outcome' => 'step_completed',
                'evidenceSha256' => hash('sha256', 'activation-inspection'),
                'mutationAttempted' => false,
            ];
        }

        return deploymentApprovalTestResult($step, $context['plan']);
    };
    $result = deploymentApprovalTestCoordinator($caseRoot, $secret, $now)->apply(
        $plan,
        $approval,
        $runner
    );
    deploymentApprovalTestSame('completed', $result['outcome'], 'Safe resume did not complete.');
    deploymentApprovalTestSame(
        ['inspect', 'postflight'],
        $steps,
        'Interrupted activation was blindly repeated.'
    );
}

/** @param array<string, mixed> $plan */
function deploymentApprovalTestUncertainResume(
    string $root,
    string $secret,
    int $now,
    array $plan
): void {
    $caseRoot = deploymentApprovalTestCaseRoot($root, 'uncertain');
    $approval = deploymentApprovalTestApproval($plan, $secret, $now, 'uncertain');
    deploymentApprovalTestWriteInterruptedState(
        $caseRoot,
        $approval,
        $secret,
        $now,
        'activate',
        3
    );
    $steps = [];
    $result = deploymentApprovalTestCoordinator($caseRoot, $secret, $now)->apply(
        $plan,
        $approval,
        static function (string $step) use (&$steps): array {
            $steps[] = $step;

            return [
                'outcome' => 'uncertain',
                'evidenceSha256' => hash('sha256', 'uncertain-inspection'),
                'mutationAttempted' => false,
            ];
        }
    );
    deploymentApprovalTestSame(
        'manual_recovery_required',
        $result['outcome'],
        'Uncertain resume did not stop.'
    );
    deploymentApprovalTestSame(['inspect'], $steps, 'Uncertain phase was repeated.');
}

/** @param array<string, mixed> $plan */
function deploymentApprovalTestReseal(
    string $root,
    string $secret,
    int $now,
    array $plan
): void {
    $plan['operations'] = [
        'qualify',
        'stage',
        'preflight',
        'activate',
        'postflight',
        'reseal',
        'final_postflight',
        'rollback',
    ];
    $plan['riskScope']['activeIdentityReseal'] = true;
    $approval = deploymentApprovalTestApproval($plan, $secret, $now, 'reseal');
    $steps = [];
    $result = deploymentApprovalTestCoordinator(
        deploymentApprovalTestCaseRoot($root, 'reseal'),
        $secret,
        $now
    )->apply($plan, $approval, deploymentApprovalTestRunner($plan, $steps));
    deploymentApprovalTestSame('completed', $result['outcome'], 'Reseal plan did not complete.');
    deploymentApprovalTestSame(
        [
            'qualify',
            'stage',
            'preflight',
            'activate',
            'postflight',
            'reseal',
            'final_postflight',
        ],
        $steps,
        'Reseal phase sequence differs.'
    );
}

/** @param array<string, mixed> $plan */
function deploymentApprovalTestRiskBoundaries(array $plan): void
{
    $risks = [
        'allowlistChange',
        'serviceRestart',
        'providerContact',
        'publication',
        'retentionDeletion',
    ];
    foreach ($risks as $risk) {
        $unsafe = $plan;
        $unsafe['riskScope'][$risk] = true;
        deploymentApprovalTestThrows(
            static fn (): array => normalizeSaefDeploymentApprovalPlan($unsafe),
            'outside one-click approval'
        );
    }
}

/**
 * @param array<string, mixed> $plan
 * @param list<string> $steps
 *
 * @return Closure(string, array<string, mixed>): array<string, mixed>
 */
function deploymentApprovalTestRunner(array $plan, array &$steps): Closure
{
    return static function (string $step, array $context) use (&$steps): array {
        $steps[] = $step;

        return deploymentApprovalTestResult($step, $context['plan']);
    };
}

/** @param array<string, mixed> $plan @return array<string, mixed> */
function deploymentApprovalTestResult(string $step, array $plan): array
{
    $outcome = match ($step) {
        'stage' => 'staged',
        'activate' => 'activated',
        'reseal' => 'resealed',
        'rollback' => 'rolled_back',
        default => 'passed',
    };
    $result = [
        'outcome' => $outcome,
        'evidenceSha256' => hash('sha256', 'evidence-' . $step),
        'mutationAttempted' => in_array(
            $step,
            ['stage', 'activate', 'reseal', 'rollback'],
            true
        ),
    ];
    if ($step === 'rollback') {
        $result['rollbackAttempted'] = true;
        $result['rollbackSucceeded'] = true;
    }
    if ($step === 'preflight') {
        $result['baselineIdentities'] = $plan['expectedBaselineIdentities'];
    }

    return $result;
}

/** @param array<string, mixed> $plan @return array<string, mixed> */
function deploymentApprovalTestApproval(
    array $plan,
    string $secret,
    int $now,
    string $nonce
): array {
    return createSaefDeploymentApproval(
        $plan,
        'test-approver',
        'test-execution-host',
        $secret,
        $now,
        300,
        hash('sha256', $nonce)
    );
}

function deploymentApprovalTestCoordinator(
    string $root,
    string $secret,
    int $now
): SaefDeploymentApprovalCoordinator {
    return new SaefDeploymentApprovalCoordinator(
        $root,
        $secret,
        'test-approver',
        'test-execution-host',
        static fn (): int => $now
    );
}

function deploymentApprovalTestCaseRoot(string $root, string $name): string
{
    $path = $root . DIRECTORY_SEPARATOR . $name;
    if (!mkdir($path, 0700, true) && !is_dir($path)) {
        throw new RuntimeException('Cannot create test case root.');
    }

    return $path;
}

/** @param array<string, mixed> $approval */
function deploymentApprovalTestWriteInterruptedState(
    string $root,
    array $approval,
    string $secret,
    int $now,
    string $phase,
    int $completedStepCount
): void {
    $state = [
        'formatVersion' => 1,
        'planSha256' => $approval['planSha256'],
        'targetId' => $approval['targetId'],
        'adapterProfile' => $approval['adapterProfile'],
        'nonceSha256' => hash('sha256', (string) $approval['nonce']),
        'approvalExpiresAt' => $approval['expiresAt'],
        'phase' => $phase,
        'stepState' => 'started',
        'completedStepCount' => $completedStepCount,
        'mutationPossible' => true,
        'outcome' => 'running',
        'evidence' => [],
        'createdAt' => $now,
        'updatedAt' => $now,
    ];
    $state['stateSignature'] = hash_hmac(
        'sha256',
        encodeSaefDeploymentApprovalValue($state),
        $secret
    );
    $path = $root . DIRECTORY_SEPARATOR . $approval['planSha256'] . '.json';
    $contents = json_encode(
        $state,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . "\n";
    if (file_put_contents($path, $contents) === false) {
        throw new RuntimeException('Cannot write interrupted state fixture.');
    }
}

function deploymentApprovalTestSame(mixed $expected, mixed $actual, string $message): void
{
    if ($actual !== $expected) {
        throw new RuntimeException(
            $message . ' Expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true) . '.'
        );
    }
}

function deploymentApprovalTestThrows(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (Throwable $exception) {
        if (str_contains($exception->getMessage(), $message)) {
            return;
        }
        throw new RuntimeException(
            'Unexpected exception: ' . $exception->getMessage(),
            0,
            $exception
        );
    }
    throw new RuntimeException('Expected exception containing: ' . $message);
}

function deploymentApprovalTestRemoveTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $items = scandir($path);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $child = $path . DIRECTORY_SEPARATOR . $item;
        if (is_dir($child) && !is_link($child)) {
            deploymentApprovalTestRemoveTree($child);
        } else {
            unlink($child);
        }
    }
    rmdir($path);
}
