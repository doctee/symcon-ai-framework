<?php

declare(strict_types=1);

require_once __DIR__
    . '/../distribution/libs/Navimow/MqttContinuousOperationReducer.php';

use Navimow\MqttContinuousOperationReducer as Reducer;

function assertContinuous(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertContinuousThrows(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (InvalidArgumentException) {
        return;
    }

    throw new RuntimeException($message);
}

$now = 1700000000;
$hash = hash('sha256', 'continuous-configuration');
$initial = Reducer::initialState();
assertContinuous(
    Reducer::restore(Reducer::serialize($initial)) === $initial
        && Reducer::restore('{}') === $initial,
    'Initial registry did not round-trip.'
);

$start = Reducer::start($initial, $now, $hash);
$active = $start['registry'];
assertContinuous(
    $start['effect'] === 'schedule-initial-connect'
        && $active['state'] === 'Starting'
        && $active['sessionSequence'] === 1
        && $active['leaseExpiresAt'] === $now + 259200
        && $active['renewalEligibleAt'] === $now + 172800,
    'Continuous start did not establish the exact lease.'
);
$active = Reducer::observeRecoveryHealth(
    $active,
    $now + 60,
    true
)['registry'];
$early = Reducer::leaseDecision($active, $now + 172799, true);
assertContinuous(
    $early['effect'] === 'none'
        && $early['registry']['leaseExpiresAt'] === $now + 259200,
    'Early lease evaluation changed the deadline.'
);
$ineligible = Reducer::leaseDecision($active, $now + 172800, false);
assertContinuous(
    $ineligible['effect'] === 'none'
        && $ineligible['registry']['leaseExpiresAt'] === $now + 259200
        && $ineligible['registry']['lastLeaseEvaluationAt']
            === $now + 172800,
    'Ineligible renewal changed the lease.'
);
$renewed = Reducer::leaseDecision($active, $now + 172800, true);
assertContinuous(
    $renewed['effect'] === 'renew-lease'
        && $renewed['registry']['leaseStartedAt'] === $now + 172800
        && $renewed['registry']['leaseExpiresAt'] === $now + 431999 + 1
        && $renewed['registry']['renewalCount'] === 1,
    'Eligible renewal did not extend from the current time.'
);

$circuit = Reducer::openCircuit(
    $active,
    $now + 100,
    'inner-reconnect-exhausted'
);
assertContinuous(
    $circuit['effect'] === 'schedule-half-open'
        && $circuit['registry']['state'] === 'CircuitOpen'
        && $circuit['registry']['nextProbeAt'] === $now + 1900,
    'Circuit opening did not schedule the first cooldown.'
);
$waiting = Reducer::halfOpenDecision(
    $circuit['registry'],
    $now + 1900,
    false
);
assertContinuous(
    $waiting['effect'] === 'schedule-half-open'
        && $waiting['registry']['halfOpenProbeCount'] === 0
        && $waiting['registry']['nextProbeAt'] === $now + 2200,
    'Unready prerequisites consumed a half-open probe.'
);
$probe = Reducer::halfOpenDecision(
    $waiting['registry'],
    $now + 2200,
    true
);
assertContinuous(
    $probe['effect'] === 'start-half-open'
        && $probe['registry']['halfOpenProbeCount'] === 1
        && $probe['registry']['probeDeadlineAt'] === $now + 2380,
    'First half-open probe was not bounded.'
);
$confirming = Reducer::halfOpenConnected(
    $probe['registry'],
    $now + 2230
);
$confirmingEarly = Reducer::observeRecoveryHealth(
    $confirming['registry'],
    $now + 3129,
    true
);
assertContinuous(
    $confirmingEarly['registry']['state'] === 'RecoveryConfirming',
    'Recovery completed before 900 seconds of health.'
);
$recovered = Reducer::observeRecoveryHealth(
    $confirming['registry'],
    $now + 3130,
    true
);
assertContinuous(
    $recovered['registry']['state'] === 'Active'
        && $recovered['registry']['halfOpenProbeCount'] === 0,
    'Sustained health did not reset recovery.'
);

$state = $circuit['registry'];
$expectedCooldowns = [7200, 21600, 86400];
foreach ($expectedCooldowns as $index => $cooldown) {
    $due = $state['nextProbeAt'];
    $probe = Reducer::halfOpenDecision($state, $due, true);
    $failedAt = $due + 1;
    $failed = Reducer::halfOpenFailed(
        $probe['registry'],
        $failedAt,
        'probe-failed'
    );
    assertContinuous(
        $failed['registry']['state'] === 'CircuitOpen'
            && $failed['registry']['halfOpenProbeCount'] === $index + 1
            && $failed['registry']['nextProbeAt']
                === min(
                    $failed['registry']['leaseExpiresAt'],
                    $failedAt + $cooldown
                ),
        'Half-open cooldown sequence is incorrect.'
    );
    $state = $failed['registry'];
}
$fourthDue = $state['nextProbeAt'];
$fourth = Reducer::halfOpenDecision($state, $fourthDue, true);
$exhausted = Reducer::halfOpenFailed(
    $fourth['registry'],
    $fourthDue + 1,
    'probe-failed'
);
assertContinuous(
    $exhausted['effect'] === 'clear-credentials'
        && $exhausted['registry']['state'] === 'Stopping'
        && $exhausted['registry']['stopReason'] === 'half-open-exhausted',
    'Fourth failed probe did not request suspension.'
);
$cleared = Reducer::credentialsCleared(
    $exhausted['registry'],
    $fourthDue + 2
);
$suspended = Reducer::stopped($cleared['registry'], $fourthDue + 3);
assertContinuous(
    $suspended['registry']['state'] === 'Suspended'
        && $suspended['registry']['leaseExpiresAt'] === 0,
    'Half-open exhaustion did not finish credential-free and suspended.'
);

$stopping = Reducer::requestStop(
    $active,
    $now + 500,
    'operator-disabled'
);
$stoppingAgain = Reducer::requestStop(
    $stopping['registry'],
    $now + 501,
    'configuration-invalid'
);
assertContinuous(
    $stoppingAgain['registry']['stopReason'] === 'operator-disabled'
        && $stoppingAgain['registry']['stopRequestedAt'] === $now + 500,
    'First stop reason did not win.'
);

$expired = Reducer::leaseDecision(
    $active,
    $active['leaseExpiresAt'],
    true
);
assertContinuous(
    $expired['registry']['state'] === 'Stopping'
        && $expired['registry']['stopReason'] === 'lease-expired',
    'Exact lease expiry did not request cleanup.'
);

assertContinuousThrows(
    static fn (): array => Reducer::restore('{'),
    'Malformed JSON was accepted.'
);
$wrongVersion = $initial;
$wrongVersion['formatVersion'] = 2;
assertContinuousThrows(
    static fn (): string => Reducer::serialize($wrongVersion),
    'Unsupported registry version was accepted.'
);
$unknownState = $initial;
$unknownState['state'] = 'Forever';
assertContinuousThrows(
    static fn (): string => Reducer::serialize($unknownState),
    'Unknown registry state was accepted.'
);
assertContinuousThrows(
    static fn (): array => Reducer::start($active, $now + 1, $hash),
    'Duplicate active start was accepted.'
);

echo "MQTT continuous operation reducer tests passed.\n";
