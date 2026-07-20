<?php

declare(strict_types=1);

require_once __DIR__ . '/harness/SymconRuntime.php';
require_once __DIR__ . '/harness/FakeClock.php';
require_once __DIR__ . '/../distribution/NavimowAccount/module.php';
require_once __DIR__ . '/../distribution/NavimowDevice/module.php';

const HARNESS_COMMAND_ACCEPTED = 2;
const HARNESS_RESULT_PENDING = 4;
const HARNESS_RESULT_VERIFIED = 5;
const HARNESS_RESULT_TIMEOUT = 8;
const HARNESS_STATE_DOCKED = 2;
const HARNESS_STATE_RUNNING = 1;
const HARNESS_STATE_PAUSED = 4;
const HARNESS_STATE_DOCKING = 5;
const HARNESS_CONNECTION_CONNECTED = 3;
const HARNESS_CONNECTION_REAUTH_REQUIRED = 5;
const HARNESS_CONNECTION_OFFLINE = 6;

final class NavimowHarnessDevice extends NavimowDevice
{
    public function __construct(
        int $instanceId,
        private NavimowTestFakeClock $clock
    ) {
        parent::__construct($instanceId);
    }

    protected function currentTimestamp(): int
    {
        return $this->clock->now();
    }
}

final class NavimowHarnessAccount extends NavimowAccount
{
    public function __construct(
        int $instanceId,
        private NavimowTestFakeClock $clock,
        private Closure $transport
    ) {
        parent::__construct($instanceId);
    }

    protected function currentTimestamp(): int
    {
        return $this->clock->now();
    }

    protected function createApiClient(): Navimow\ApiClient
    {
        return new Navimow\ApiClient(
            'https://navimow.invalid',
            $this->transport
        );
    }
}

final class NavimowScriptedDeviceParent
{
    private array $commandResponses = [];
    private array $statusResponses = [];
    private array $calls = [];
    private array $messages = [];

    public function enqueueCommand(array|Closure $response): void
    {
        $this->commandResponses[] = $response;
    }

    public function enqueueStatus(array|Closure $response): void
    {
        $this->statusResponses[] = $response;
    }

    public function handle(string $json): string
    {
        $message = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($message)) {
            throw new RuntimeException('Parent message must be an object.');
        }

        $function = $message['Function'] ?? null;
        if (!is_string($function)) {
            throw new RuntimeException('Parent message function is missing.');
        }

        if (($message['DeviceId'] ?? null) !== 'DEVICE_001') {
            throw new RuntimeException('Harness received an unexpected device ID.');
        }

        $this->calls[] = $function;
        $this->messages[] = $message;
        if ($function === 'SendCommand') {
            if ($this->commandResponses === []) {
                throw new RuntimeException(
                    'Harness command response queue is empty.'
                );
            }
            $response = array_shift($this->commandResponses);
        } elseif ($function === 'GetStatus') {
            if ($this->statusResponses === []) {
                throw new RuntimeException(
                    'Harness status response queue is empty.'
                );
            }
            $response = array_shift($this->statusResponses);
        } else {
            throw new RuntimeException(
                'Harness received an unsupported parent function.'
            );
        }
        if ($response instanceof Closure) {
            $response = $response();
        }

        if (!is_array($response)) {
            throw new RuntimeException('Harness parent response is invalid.');
        }

        return json_encode($response, JSON_THROW_ON_ERROR);
    }

    public function handler(): Closure
    {
        return Closure::fromCallable([$this, 'handle']);
    }

    public function commandCount(): int
    {
        return count(array_filter(
            $this->calls,
            static fn (string $function): bool => $function === 'SendCommand'
        ));
    }

    public function readCount(): int
    {
        return count(array_filter(
            $this->calls,
            static fn (string $function): bool => $function === 'GetStatus'
        ));
    }

    public function calls(): array
    {
        return $this->calls;
    }

    public function messages(): array
    {
        return $this->messages;
    }
}

final class NavimowScriptedApiTransport
{
    private array $responses = [];
    private array $requests = [];

    public function enqueue(array|Throwable $response): void
    {
        $this->responses[] = $response;
    }

    public function handle(array $request): array
    {
        if ($this->responses === []) {
            throw new RuntimeException('Harness API response queue is empty.');
        }

        $headers = is_array($request['headers'] ?? null)
            ? $request['headers']
            : [];
        $hasAuthorization = count(array_filter(
            $headers,
            static fn (mixed $header): bool => is_string($header)
                && str_starts_with($header, 'Authorization: Bearer ')
        )) === 1;

        $this->requests[] = [
            'operation' => is_string($request['operation'] ?? null)
                ? $request['operation']
                : 'unknown',
            'method' => is_string($request['method'] ?? null)
                ? $request['method']
                : 'unknown',
            'path' => is_string($request['url'] ?? null)
                ? (string) parse_url($request['url'], PHP_URL_PATH)
                : '',
            'authorized' => $hasAuthorization,
        ];

        $response = array_shift($this->responses);
        if ($response instanceof Throwable) {
            throw $response;
        }

        return $response;
    }

    public function handler(): Closure
    {
        return Closure::fromCallable([$this, 'handle']);
    }

    public function requestCount(): int
    {
        return count($this->requests);
    }

    public function requests(): array
    {
        return $this->requests;
    }
}

$results = [];

runHarnessCase($results, 'OBS-01 timeout after Docking', static function (): void {
    $clock = new NavimowTestFakeClock(1000000);
    $parent = new NavimowScriptedDeviceParent();
    $device = createHarnessDevice($clock, $parent, 101);

    $parent->enqueueCommand(commandAccepted($clock));
    harnessAssertSame('Dock command was accepted.', $device->Dock(), 'Dock should be accepted.');
    harnessAssertSame(5000, $device->testTimerInterval('CommandVerification'), 'Initial timer should be five seconds.');

    $clock->advance(5);
    $parent->enqueueStatus(statusResult($clock, HARNESS_STATE_DOCKING));
    $device->VerifyCommand();
    harnessAssertSame(HARNESS_RESULT_PENDING, $device->testReadVariable('LastCommandResult'), 'Docking should remain pending.');
    harnessAssertSame(60000, $device->testTimerInterval('CommandVerification'), 'Docking should use 60 second polling.');

    $deadline = (int) $device->testReadAttribute('CommandDeadline');
    $clock->set($deadline);
    $parent->enqueueStatus(readFailure());
    $device->VerifyCommand();

    harnessAssertSame(HARNESS_RESULT_TIMEOUT, $device->testReadVariable('LastCommandResult'), 'Deadline with stale Docking must time out.');
    harnessAssertSame(0, $device->testTimerInterval('CommandVerification'), 'Timeout should stop the timer.');
    harnessAssertSame(1, $parent->commandCount(), 'Timeout must not replay Dock.');
});

runHarnessCase($results, 'OBS-01 final Docked read', static function (): void {
    $clock = new NavimowTestFakeClock(1100000);
    $parent = new NavimowScriptedDeviceParent();
    $device = createHarnessDevice($clock, $parent, 102);

    $parent->enqueueCommand(commandAccepted($clock));
    $device->Dock();
    $clock->set((int) $device->testReadAttribute('CommandDeadline'));
    $parent->enqueueStatus(statusResult($clock, HARNESS_STATE_DOCKED));
    $device->VerifyCommand();

    harnessAssertSame(HARNESS_RESULT_VERIFIED, $device->testReadVariable('LastCommandResult'), 'Docked at the deadline should verify.');
    harnessAssertSame(false, $device->testReadAttribute('CommandActive'), 'Verified command should become inactive.');
    harnessAssertSame(1, $parent->commandCount(), 'Final read must not replay Dock.');
});

runHarnessCase($results, 'OBS-01 missing deadline', static function (): void {
    $clock = new NavimowTestFakeClock(1200000);
    $parent = new NavimowScriptedDeviceParent();
    $device = createHarnessDevice($clock, $parent, 103);

    seedActiveCommand($device, $clock, 0, 1);
    $parent->enqueueStatus(readFailure());
    $device->VerifyCommand();

    harnessAssertSame(HARNESS_RESULT_TIMEOUT, $device->testReadVariable('LastCommandResult'), 'Missing deadline should fail closed.');
    harnessAssertSame(0, $device->testTimerInterval('CommandVerification'), 'Missing deadline should not loop.');
});

runHarnessCase($results, 'OBS-02 restart resume', static function (): void {
    $clock = new NavimowTestFakeClock(1300000);
    $parent = new NavimowScriptedDeviceParent();
    $before = createHarnessDevice($clock, $parent, 104);

    $parent->enqueueCommand(commandAccepted($clock));
    $before->Dock();
    $originalCommandAt = $before->testReadVariable('LastCommandAt');
    $originalDeadline = $before->testReadAttribute('CommandDeadline');

    $clock->advance(5);
    $parent->enqueueStatus(statusResult($clock, HARNESS_STATE_DOCKING));
    $before->VerifyCommand();
    $snapshot = $before->testSnapshotPersistentState();
    unset($before);

    $after = restoreHarnessDevice($clock, $parent, 104, $snapshot);
    harnessAssertSame(60000, $after->testTimerInterval('CommandVerification'), 'Restart should resume returning cadence.');
    harnessAssertSame($originalCommandAt, $after->testReadVariable('LastCommandAt'), 'Restart must preserve command timestamp.');
    harnessAssertSame($originalDeadline, $after->testReadAttribute('CommandDeadline'), 'Restart must preserve deadline.');
    harnessAssertSame(1, $parent->commandCount(), 'Restart must not replay Dock.');

    $clock->advance(60);
    $parent->enqueueStatus(statusResult($clock, HARNESS_STATE_DOCKED));
    $after->VerifyCommand();

    harnessAssertSame(HARNESS_RESULT_VERIFIED, $after->testReadVariable('LastCommandResult'), 'Docked after restart should verify.');
    harnessAssertSame(1, $parent->commandCount(), 'Verification after restart must stay read-only.');
});

runHarnessCase($results, 'OBS-02 expired restart', static function (): void {
    $clock = new NavimowTestFakeClock(1400000);
    $parent = new NavimowScriptedDeviceParent();
    $before = createHarnessDevice($clock, $parent, 105);

    $parent->enqueueCommand(commandAccepted($clock));
    $before->Dock();
    $clock->advance(5);
    $parent->enqueueStatus(statusResult($clock, HARNESS_STATE_DOCKING));
    $before->VerifyCommand();
    $deadline = (int) $before->testReadAttribute('CommandDeadline');
    $snapshot = $before->testSnapshotPersistentState();
    unset($before);

    $clock->set($deadline + 1);
    $after = restoreHarnessDevice($clock, $parent, 105, $snapshot);
    harnessAssertSame(1, $after->testTimerInterval('CommandVerification'), 'Expired restart should schedule an immediate tick.');
    harnessAssertSame(1, $parent->commandCount(), 'Expired restart must not replay Dock.');

    $after->VerifyCommand();
    harnessAssertSame(HARNESS_RESULT_TIMEOUT, $after->testReadVariable('LastCommandResult'), 'Expired restart should terminate after final failed read.');
    harnessAssertSame(1, $parent->readCount(), 'Expired restart must not perform a post-deadline read.');
});

runHarnessCase($results, 'OBS-03 transient recovery', static function (): void {
    $clock = new NavimowTestFakeClock(1500000);
    $parent = new NavimowScriptedDeviceParent();
    $device = createHarnessDevice($clock, $parent, 106);

    $parent->enqueueCommand(commandAccepted($clock));
    $device->Dock();

    foreach ([5, 5] as $advance) {
        $clock->advance($advance);
        $parent->enqueueStatus(readFailure());
        $device->VerifyCommand();
        harnessAssertSame(HARNESS_RESULT_PENDING, $device->testReadVariable('LastCommandResult'), 'Transient failure should stay pending.');
    }

    $clock->advance(5);
    $parent->enqueueStatus(statusResult($clock, HARNESS_STATE_DOCKING));
    $device->VerifyCommand();
    $clock->advance(60);
    $parent->enqueueStatus(statusResult($clock, HARNESS_STATE_DOCKED));
    $device->VerifyCommand();

    harnessAssertSame(HARNESS_RESULT_VERIFIED, $device->testReadVariable('LastCommandResult'), 'Reads should recover to Verified.');
    harnessAssertSame(1, $parent->commandCount(), 'Read recovery must not replay Dock.');
    harnessAssertSame(['SendCommand', 'GetStatus', 'GetStatus', 'GetStatus', 'GetStatus'], $parent->calls(), 'All recovery calls after Dock should be reads.');
});

runHarnessCase($results, 'OBS-03 failed-read cadence', static function (): void {
    $clock = new NavimowTestFakeClock(1600000);
    $parent = new NavimowScriptedDeviceParent();
    $device = createHarnessDevice($clock, $parent, 107);

    $parent->enqueueCommand(commandAccepted($clock));
    $device->Dock();
    $clock->advance(5);
    $parent->enqueueStatus(readFailure());
    $device->VerifyCommand();

    harnessAssertSame(60000, $device->testTimerInterval('CommandVerification'), 'A failed initial read must use the bounded 60 second cadence.');
});

runHarnessCase($results, 'OBS-03 continuous failure timeout', static function (): void {
    $clock = new NavimowTestFakeClock(1700000);
    $parent = new NavimowScriptedDeviceParent();
    $device = createHarnessDevice($clock, $parent, 108);

    $parent->enqueueCommand(commandAccepted($clock));
    $device->Dock();
    $clock->set((int) $device->testReadAttribute('CommandDeadline'));
    $parent->enqueueStatus(readFailure());
    $device->VerifyCommand();

    harnessAssertSame(HARNESS_RESULT_TIMEOUT, $device->testReadVariable('LastCommandResult'), 'Continuous failure must end at the deadline.');
    harnessAssertSame(1, $parent->commandCount(), 'Continuous read failure must not replay Dock.');
});

runHarnessCase($results, 'OBS-03 deadline-aligned cadence', static function (): void {
    $clock = new NavimowTestFakeClock(1800000);
    $parent = new NavimowScriptedDeviceParent();
    $device = createHarnessDevice($clock, $parent, 109);

    $parent->enqueueCommand(commandAccepted($clock));
    $device->Dock();
    $deadline = (int) $device->testReadAttribute('CommandDeadline');
    $clock->set($deadline - 30);
    $parent->enqueueStatus(readFailure());
    $device->VerifyCommand();

    harnessAssertSame(30000, $device->testTimerInterval('CommandVerification'), 'Final timer should align with the remaining deadline.');
    harnessAssertSame(1, $parent->commandCount(), 'Deadline alignment must stay read-only.');
});

runHarnessCase($results, 'Pause fresh Running and verify', static function (): void {
    $clock = new NavimowTestFakeClock(1900000);
    $parent = new NavimowScriptedDeviceParent();
    $device = createHarnessDevice($clock, $parent, 110);

    $parent->enqueueStatus(statusResult($clock, HARNESS_STATE_RUNNING));
    $parent->enqueueCommand(commandAccepted($clock));
    harnessAssertSame('Pause command was accepted.', $device->Pause(), 'Pause should be accepted after a fresh Running read.');
    harnessAssertSame(['GetStatus', 'SendCommand'], $parent->calls(), 'Pause must read current state before its single write.');
    harnessAssertSame('Pause', $parent->messages()[1]['Command'] ?? null, 'Pause must use the symbolic Pause command.');
    harnessAssertSame(2000, $device->testTimerInterval('CommandVerification'), 'Pause should schedule its first read after two seconds.');
    harnessAssertSame(4, $device->testReadVariable('LastCommand'), 'Pause must use the stable command profile value.');

    $clock->advance(2);
    $parent->enqueueStatus(statusResult($clock, HARNESS_STATE_PAUSED));
    $device->VerifyCommand();

    harnessAssertSame(HARNESS_RESULT_VERIFIED, $device->testReadVariable('LastCommandResult'), 'A later Paused read should verify Pause.');
    harnessAssertSame(1, $parent->commandCount(), 'Pause verification must not repeat the command.');
    harnessAssertSame(0, $device->testTimerInterval('CommandVerification'), 'Verified Pause should stop its timer.');
});

runHarnessCase($results, 'Pause rejects non-Running state', static function (): void {
    $clock = new NavimowTestFakeClock(1910000);
    $parent = new NavimowScriptedDeviceParent();
    $device = createHarnessDevice($clock, $parent, 111);

    $parent->enqueueStatus(statusResult($clock, HARNESS_STATE_DOCKED));
    harnessAssertSame('Pause is only available while the mower is running.', $device->Pause(), 'Docked state must reject Pause.');
    harnessAssertSame(0, $parent->commandCount(), 'Rejected Pause must not send a command.');
});

runHarnessCase($results, 'Pause rejects failed pre-read', static function (): void {
    $clock = new NavimowTestFakeClock(1920000);
    $parent = new NavimowScriptedDeviceParent();
    $device = createHarnessDevice($clock, $parent, 112);

    $parent->enqueueStatus(readFailure());
    harnessAssertSame('Pause requires a current successful status read.', $device->Pause(), 'Failed current read must reject Pause.');
    harnessAssertSame(0, $parent->commandCount(), 'Failed pre-read must not send a command.');
});

runHarnessCase($results, 'Pause bounded read schedule and timeout', static function (): void {
    $clock = new NavimowTestFakeClock(1930000);
    $parent = new NavimowScriptedDeviceParent();
    $device = createHarnessDevice($clock, $parent, 113);

    $parent->enqueueStatus(statusResult($clock, HARNESS_STATE_RUNNING));
    $parent->enqueueCommand(commandAccepted($clock));
    $device->Pause();

    foreach ([2, 3, 5, 10, 10] as $advance) {
        $clock->advance($advance);
        $parent->enqueueStatus(statusResult($clock, HARNESS_STATE_RUNNING));
        $device->VerifyCommand();
    }
    harnessAssertSame(30000, $device->testTimerInterval('CommandVerification'), 'The 30 second read should schedule the 60 second deadline read.');

    $clock->advance(30);
    $parent->enqueueStatus(statusResult($clock, HARNESS_STATE_RUNNING));
    $device->VerifyCommand();
    harnessAssertSame(HARNESS_RESULT_TIMEOUT, $device->testReadVariable('LastCommandResult'), 'Running through 60 seconds should time out.');
    harnessAssertSame(1, $parent->commandCount(), 'Pause timeout must not repeat the command.');
});

runHarnessCase($results, 'Pause unexpected state fails closed', static function (): void {
    $clock = new NavimowTestFakeClock(1940000);
    $parent = new NavimowScriptedDeviceParent();
    $device = createHarnessDevice($clock, $parent, 114);

    $parent->enqueueStatus(statusResult($clock, HARNESS_STATE_RUNNING));
    $parent->enqueueCommand(commandAccepted($clock));
    $device->Pause();
    $clock->advance(2);
    $parent->enqueueStatus(statusResult($clock, HARNESS_STATE_DOCKING));
    $device->VerifyCommand();

    harnessAssertSame(7, $device->testReadVariable('LastCommandResult'), 'Unexpected Pause state should fail closed.');
    harnessAssertSame(1, $parent->commandCount(), 'Unexpected state must not repeat Pause.');
});

runHarnessCase($results, 'Pause restart resumes read-only verification', static function (): void {
    $clock = new NavimowTestFakeClock(1950000);
    $parent = new NavimowScriptedDeviceParent();
    $before = createHarnessDevice($clock, $parent, 115);

    $parent->enqueueStatus(statusResult($clock, HARNESS_STATE_RUNNING));
    $parent->enqueueCommand(commandAccepted($clock));
    $before->Pause();
    $snapshot = $before->testSnapshotPersistentState();
    unset($before);

    $after = restoreHarnessDevice($clock, $parent, 115, $snapshot);
    harnessAssertSame(2000, $after->testTimerInterval('CommandVerification'), 'Restart should restore the next Pause read.');
    harnessAssertSame(1, $parent->commandCount(), 'Restart must not replay Pause.');
    $clock->advance(2);
    $parent->enqueueStatus(statusResult($clock, HARNESS_STATE_PAUSED));
    $after->VerifyCommand();
    harnessAssertSame(HARNESS_RESULT_VERIFIED, $after->testReadVariable('LastCommandResult'), 'Paused after restart should verify.');
});

runHarnessCase($results, 'Resume fresh Paused and verify', static function (): void {
    $clock = new NavimowTestFakeClock(1960000);
    $parent = new NavimowScriptedDeviceParent();
    $device = createHarnessDevice($clock, $parent, 116);

    $parent->enqueueStatus(statusResult($clock, HARNESS_STATE_PAUSED));
    $parent->enqueueCommand(commandAccepted($clock));
    harnessAssertSame('Resume command was accepted.', $device->Resume(), 'Resume should be accepted after a fresh Paused read.');
    harnessAssertSame(['GetStatus', 'SendCommand'], $parent->calls(), 'Resume must read current state before its single write.');
    harnessAssertSame('Resume', $parent->messages()[1]['Command'] ?? null, 'Resume must use the symbolic Resume command.');
    harnessAssertSame(2000, $device->testTimerInterval('CommandVerification'), 'Resume should schedule its first read after two seconds.');
    harnessAssertSame(5, $device->testReadVariable('LastCommand'), 'Resume must use the stable command profile value.');

    $clock->advance(2);
    $parent->enqueueStatus(statusResult($clock, HARNESS_STATE_RUNNING));
    $device->VerifyCommand();

    harnessAssertSame(HARNESS_RESULT_VERIFIED, $device->testReadVariable('LastCommandResult'), 'A later Running read should verify Resume.');
    harnessAssertSame(1, $parent->commandCount(), 'Resume verification must not repeat the command.');
    harnessAssertSame(0, $device->testTimerInterval('CommandVerification'), 'Verified Resume should stop its timer.');
});

runHarnessCase($results, 'Resume rejects non-Paused state', static function (): void {
    $clock = new NavimowTestFakeClock(1970000);
    $parent = new NavimowScriptedDeviceParent();
    $device = createHarnessDevice($clock, $parent, 117);

    $parent->enqueueStatus(statusResult($clock, HARNESS_STATE_RUNNING));
    harnessAssertSame('Resume is only available while the mower is paused.', $device->Resume(), 'Running state must reject Resume.');
    harnessAssertSame(0, $parent->commandCount(), 'Rejected Resume must not send a command.');
});

runHarnessCase($results, 'Resume rejects failed pre-read', static function (): void {
    $clock = new NavimowTestFakeClock(1980000);
    $parent = new NavimowScriptedDeviceParent();
    $device = createHarnessDevice($clock, $parent, 118);

    $parent->enqueueStatus(readFailure());
    harnessAssertSame('Resume requires a current successful status read.', $device->Resume(), 'Failed current read must reject Resume.');
    harnessAssertSame(0, $parent->commandCount(), 'Failed Resume pre-read must not send a command.');
});

runHarnessCase($results, 'Resume bounded read schedule and timeout', static function (): void {
    $clock = new NavimowTestFakeClock(1990000);
    $parent = new NavimowScriptedDeviceParent();
    $device = createHarnessDevice($clock, $parent, 119);

    $parent->enqueueStatus(statusResult($clock, HARNESS_STATE_PAUSED));
    $parent->enqueueCommand(commandAccepted($clock));
    $device->Resume();

    foreach ([2, 3, 5, 10, 10] as $advance) {
        $clock->advance($advance);
        $parent->enqueueStatus(statusResult($clock, HARNESS_STATE_PAUSED));
        $device->VerifyCommand();
    }
    harnessAssertSame(30000, $device->testTimerInterval('CommandVerification'), 'The 30 second Resume read should schedule the deadline read.');

    $clock->advance(30);
    $parent->enqueueStatus(statusResult($clock, HARNESS_STATE_PAUSED));
    $device->VerifyCommand();
    harnessAssertSame(HARNESS_RESULT_TIMEOUT, $device->testReadVariable('LastCommandResult'), 'Paused through 60 seconds should time out Resume.');
    harnessAssertSame(1, $parent->commandCount(), 'Resume timeout must not repeat the command.');
});

runHarnessCase($results, 'Resume unexpected state fails closed', static function (): void {
    $clock = new NavimowTestFakeClock(2000000);
    $parent = new NavimowScriptedDeviceParent();
    $device = createHarnessDevice($clock, $parent, 120);

    $parent->enqueueStatus(statusResult($clock, HARNESS_STATE_PAUSED));
    $parent->enqueueCommand(commandAccepted($clock));
    $device->Resume();
    $clock->advance(2);
    $parent->enqueueStatus(statusResult($clock, HARNESS_STATE_DOCKING));
    $device->VerifyCommand();

    harnessAssertSame(7, $device->testReadVariable('LastCommandResult'), 'Unexpected Resume state should fail closed.');
    harnessAssertSame(1, $parent->commandCount(), 'Unexpected state must not repeat Resume.');
});

runHarnessCase($results, 'Resume already-in-state fails closed', static function (): void {
    $clock = new NavimowTestFakeClock(2010000);
    $parent = new NavimowScriptedDeviceParent();
    $device = createHarnessDevice($clock, $parent, 121);

    $parent->enqueueStatus(statusResult($clock, HARNESS_STATE_PAUSED));
    $parent->enqueueCommand(commandAlreadyInState($clock));
    harnessAssertSame('Resume already-in-state response is unsupported.', $device->Resume(), 'Resume already-in-state must fail closed.');
    harnessAssertSame(7, $device->testReadVariable('LastCommandResult'), 'Unsupported Resume response should become Failed.');
    harnessAssertSame(0, $device->testTimerInterval('CommandVerification'), 'Unsupported Resume response should not schedule verification.');
    harnessAssertSame(1, $parent->commandCount(), 'Unsupported response must not repeat Resume.');
});

runHarnessCase($results, 'Resume restart resumes read-only verification', static function (): void {
    $clock = new NavimowTestFakeClock(2020000);
    $parent = new NavimowScriptedDeviceParent();
    $before = createHarnessDevice($clock, $parent, 122);

    $parent->enqueueStatus(statusResult($clock, HARNESS_STATE_PAUSED));
    $parent->enqueueCommand(commandAccepted($clock));
    $before->Resume();
    $snapshot = $before->testSnapshotPersistentState();
    unset($before);

    $after = restoreHarnessDevice($clock, $parent, 122, $snapshot);
    harnessAssertSame(2000, $after->testTimerInterval('CommandVerification'), 'Restart should restore the next Resume read.');
    harnessAssertSame(1, $parent->commandCount(), 'Restart must not replay Resume.');
    $clock->advance(2);
    $parent->enqueueStatus(statusResult($clock, HARNESS_STATE_RUNNING));
    $after->VerifyCommand();
    harnessAssertSame(HARNESS_RESULT_VERIFIED, $after->testReadVariable('LastCommandResult'), 'Running after restart should verify Resume.');
});

runHarnessCase($results, 'OBS-04 refresh success', static function (): void {
    $clock = new NavimowTestFakeClock(2000000);
    $transport = new NavimowScriptedApiTransport();
    $account = createHarnessAccount($clock, $transport, 201, 3600);

    harnessAssertSame(3300000, $account->testTimerInterval('RefreshToken'), 'Initial refresh timer should use the five minute margin.');
    harnessAssertSame(300000, $account->testTimerInterval('PollStatus'), 'Polling should use the configured interval.');

    $transport->enqueue(tokenResponse(7200));
    harnessAssertSame('Token refresh succeeded.', $account->RefreshAuthentication(), 'Refresh should succeed.');
    harnessAssertSame(2007200, $account->testReadVariable('TokenExpiresAt'), 'Refresh should use fake time for expiry.');
    harnessAssertSame(6900000, $account->testTimerInterval('RefreshToken'), 'Refreshed token should reschedule before expiry.');
    harnessAssertSame(HARNESS_CONNECTION_CONNECTED, $account->testReadVariable('ConnectionState'), 'Successful refresh should remain connected.');
    harnessAssertSame(false, $account->testReadVariable('ReauthRequired'), 'Successful refresh should not require reauthorization.');
    harnessAssertSame(1, $transport->requestCount(), 'Refresh should perform exactly one token request.');
});

runHarnessCase($results, 'Adaptive polling bounded wake window', static function (): void {
    $clock = new NavimowTestFakeClock(2050000);
    $transport = new NavimowScriptedApiTransport();
    $account = createHarnessAccount($clock, $transport, 221, 3600);

    harnessAssertSame(300000, $account->testTimerInterval('PollStatus'), 'Docked baseline should use the normal interval.');
    harnessAssertSame('Status polling wake requested.', $account->WakePolling(), 'Wake should be accepted with a usable token.');
    harnessAssertSame(60000, $account->testTimerInterval('PollStatus'), 'Wake should select the active interval.');
    harnessAssertSame(1, count($account->testChildMessages()), 'Wake should broadcast exactly one immediate read-only poll.');
    harnessAssertSame(2050180, $account->testReadAttribute('WakePollingUntil'), 'Wake window should be bounded to three minutes.');

    $transport->enqueue(apiVehicleStatusResponse('isDocked'));
    forwardAccountStatus($account);
    harnessAssertSame(60000, $account->testTimerInterval('PollStatus'), 'A possibly stale Docked read must not cancel a fresh departure wake.');

    $clock->advance(181);
    $transport->enqueue(apiVehicleStatusResponse('isDocked'));
    forwardAccountStatus($account);
    harnessAssertSame(300000, $account->testTimerInterval('PollStatus'), 'Expired wake with Docked status should restore the normal interval.');
});

runHarnessCase($results, 'Adaptive polling rejects unauthenticated wake', static function (): void {
    $clock = new NavimowTestFakeClock(2051000);
    $transport = new NavimowScriptedApiTransport();
    $account = createHarnessAuthorizationAccount($clock, $transport, 224);
    $account->testSetAttribute('WakePollingUntil', $clock->now() + 120);
    $account->testSetAttribute(
        'ActiveDeviceObservations',
        json_encode([hash('sha256', 'DEVICE_001') => $clock->now()], JSON_THROW_ON_ERROR)
    );

    harnessAssertSame(
        'Status polling wake requires a usable access token.',
        $account->WakePolling(),
        'Wake without authentication should be rejected.'
    );
    harnessAssertSame(0, $account->testTimerInterval('PollStatus'), 'Rejected wake should disable polling.');
    harnessAssertSame(0, $account->testReadAttribute('WakePollingUntil'), 'Rejected wake retained its wake deadline.');
    harnessAssertSame('[]', $account->testReadAttribute('ActiveDeviceObservations'), 'Rejected wake retained active evidence.');
    harnessAssertSame([], $account->testChildMessages(), 'Rejected wake broadcast a status poll.');
});

runHarnessCase($results, 'Adaptive polling active state and Docked recovery', static function (): void {
    $clock = new NavimowTestFakeClock(2060000);
    $transport = new NavimowScriptedApiTransport();
    $account = createHarnessAccount($clock, $transport, 222, 3600);

    $transport->enqueue(apiVehicleStatusResponse('isRunning'));
    forwardAccountStatus($account);
    harnessAssertSame(60000, $account->testTimerInterval('PollStatus'), 'Running should select the active interval.');

    $snapshot = $account->testSnapshotPersistentState();
    unset($account);
    $account = restoreHarnessAccount($clock, $transport, 222, $snapshot);
    harnessAssertSame(60000, $account->testTimerInterval('PollStatus'), 'Restart should preserve fresh active evidence.');

    $transport->enqueue(apiVehicleStatusResponse('isDocking'));
    forwardAccountStatus($account);
    harnessAssertSame(60000, $account->testTimerInterval('PollStatus'), 'Docking should retain the active interval.');

    $transport->enqueue(apiVehicleStatusResponse('isDocked'));
    forwardAccountStatus($account);
    harnessAssertSame(300000, $account->testTimerInterval('PollStatus'), 'Confirmed Docked should restore the normal interval.');
    harnessAssertSame('[]', $account->testReadAttribute('ActiveDeviceObservations'), 'Docked should remove active evidence.');
});

runHarnessCase($results, 'Adaptive polling stale and malformed evidence', static function (): void {
    $clock = new NavimowTestFakeClock(2070000);
    $transport = new NavimowScriptedApiTransport();
    $account = createHarnessAccount($clock, $transport, 223, 3600);

    $transport->enqueue(apiVehicleStatusResponse('isRunning'));
    forwardAccountStatus($account);
    harnessAssertSame(60000, $account->testTimerInterval('PollStatus'), 'Running should create active evidence.');

    $snapshot = $account->testSnapshotPersistentState();
    $clock->advance(1201);
    unset($account);
    $account = restoreHarnessAccount($clock, $transport, 223, $snapshot);
    harnessAssertSame(300000, $account->testTimerInterval('PollStatus'), 'Stale active evidence should expire after restart.');
    harnessAssertSame('[]', $account->testReadAttribute('ActiveDeviceObservations'), 'Expired evidence should be normalized away.');

    $account->testSetAttribute('ActiveDeviceObservations', '{invalid');
    $account->ApplyChanges();
    harnessAssertSame(300000, $account->testTimerInterval('PollStatus'), 'Malformed evidence must fail closed to normal polling.');
    harnessAssertSame('[]', $account->testReadAttribute('ActiveDeviceObservations'), 'Malformed evidence should be discarded.');

    $oversized = [];
    for ($index = 0; $index < 66; $index++) {
        $oversized[hash('sha256', 'DEVICE_' . (string)$index)] = $clock->now() - $index;
    }
    $account->testSetAttribute(
        'ActiveDeviceObservations',
        json_encode($oversized, JSON_THROW_ON_ERROR)
    );
    $account->ApplyChanges();
    $bounded = json_decode(
        $account->testReadAttribute('ActiveDeviceObservations'),
        true,
        8,
        JSON_THROW_ON_ERROR
    );
    harnessAssertSame(64, count($bounded), 'Active evidence exceeded its fixed capacity.');
    harnessAssertSame(
        true,
        isset($bounded[hash('sha256', 'DEVICE_0')]),
        'Newest active evidence was discarded.'
    );
    harnessAssertSame(
        false,
        isset($bounded[hash('sha256', 'DEVICE_65')]),
        'Oldest active evidence was retained past capacity.'
    );
});

runHarnessCase($results, 'OBS-04 rejected token', static function (): void {
    $clock = new NavimowTestFakeClock(2100000);
    $transport = new NavimowScriptedApiTransport();
    $account = createHarnessAccount($clock, $transport, 202, 3600);

    $transport->enqueue(new RuntimeException('Synthetic transport failure.'));
    $account->RefreshAuthentication();
    harnessAssertSame(1, $account->testReadAttribute('RefreshRetryCount'), 'Transport failure should start the bounded retry sequence.');

    $transport->enqueue([
        'status' => 200,
        'body' => json_encode([
            'code' => 4005,
            'desc' => 'CODE_OAUTH_INFO_ILLEGAL',
            'data' => null,
        ], JSON_THROW_ON_ERROR),
    ]);
    $account->RefreshAuthentication();

    harnessAssertSame(HARNESS_CONNECTION_REAUTH_REQUIRED, $account->testReadVariable('ConnectionState'), 'Rejected refresh token should require reauthorization.');
    harnessAssertSame(true, $account->testReadVariable('ReauthRequired'), 'Rejected refresh token should set the reauth flag.');
    harnessAssertSame(0, $account->testReadAttribute('RefreshRetryCount'), 'Authentication rejection should clear transport retry state.');
    harnessAssertSame(0, $account->testTimerInterval('RefreshToken'), 'Rejected token should stop automatic refresh.');
});

runHarnessCase($results, 'OBS-04 expired access token', static function (): void {
    $clock = new NavimowTestFakeClock(2200000);
    $transport = new NavimowScriptedApiTransport();
    $account = createHarnessAccount($clock, $transport, 203, 0);

    $result = json_decode(
        $account->ForwardData(json_encode([
            'DataID' => '{54620029-127D-470D-97C7-44265496FAA0}',
            'SchemaVersion' => 1,
            'Function' => 'GetStatus',
            'DeviceId' => 'DEVICE_001',
        ], JSON_THROW_ON_ERROR)),
        true,
        32,
        JSON_THROW_ON_ERROR
    );

    harnessAssertSame('error', $result['status'] ?? null, 'Expired token should reject status reads.');
    harnessAssertSame('authentication', $result['kind'] ?? null, 'Expired token should be classified as authentication.');
    harnessAssertSame(0, $transport->requestCount(), 'Expired token should fail before transport.');
    harnessAssertSame(true, $account->testReadVariable('ReauthRequired'), 'Expired token should require reauthorization after a read attempt.');
});

runHarnessCase($results, 'OBS-04 refresh transport recovery', static function (): void {
    $clock = new NavimowTestFakeClock(2300000);
    $transport = new NavimowScriptedApiTransport();
    $account = createHarnessAccount($clock, $transport, 204, 3600);

    $transport->enqueue(new RuntimeException('Synthetic transport failure.'));
    $account->RefreshAuthentication();

    harnessAssertSame(HARNESS_CONNECTION_OFFLINE, $account->testReadVariable('ConnectionState'), 'Transport failure should mark account offline.');
    harnessAssertSame(false, $account->testReadVariable('ReauthRequired'), 'Transport failure should not reject credentials.');
    $debug = json_encode($account->testDebugEntries(), JSON_THROW_ON_ERROR);
    harnessAssertNotContains('ACCESS_TEST_VALUE', $debug, 'Debug output must not expose access token.');
    harnessAssertNotContains('REFRESH_TEST_VALUE', $debug, 'Debug output must not expose refresh token.');
    harnessAssertNotContains('SECRET_TEST_VALUE', $debug, 'Debug output must not expose client secret.');
    harnessAssertSame(1, $account->testReadAttribute('RefreshRetryCount'), 'Transport failure should persist the retry count.');
    harnessAssertSame(60000, $account->testTimerInterval('RefreshToken'), 'Transport failure should schedule a bounded refresh retry.');
});

runHarnessCase($results, 'OBS-04 refresh retry bound', static function (): void {
    $clock = new NavimowTestFakeClock(2400000);
    $transport = new NavimowScriptedApiTransport();
    $account = createHarnessAccount($clock, $transport, 205, 3600);

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $transport->enqueue(new RuntimeException('Synthetic transport failure.'));
        $account->RefreshAuthentication();
        harnessAssertSame($attempt, $account->testReadAttribute('RefreshRetryCount'), 'Retry count should match the failed attempt.');
        harnessAssertSame(
            $attempt < 5 ? 60000 : 0,
            $account->testTimerInterval('RefreshToken'),
            'Refresh retry timer should stop after attempt five.'
        );
    }

    harnessAssertSame(5, $transport->requestCount(), 'Retry bound should allow exactly five failed refresh requests.');
    harnessAssertSame(HARNESS_CONNECTION_OFFLINE, $account->testReadVariable('ConnectionState'), 'Retry exhaustion should remain offline.');
    harnessAssertSame(false, $account->testReadVariable('ReauthRequired'), 'Transport retry exhaustion should not reject credentials.');

    $snapshot = $account->testSnapshotPersistentState();
    unset($account);
    $after = restoreHarnessAccount($clock, $transport, 205, $snapshot);
    harnessAssertSame(5, $after->testReadAttribute('RefreshRetryCount'), 'Restart should preserve exhausted retry count.');
    harnessAssertSame(0, $after->testTimerInterval('RefreshToken'), 'Restart after retry exhaustion should keep the timer stopped.');
    harnessAssertSame(HARNESS_CONNECTION_OFFLINE, $after->testReadVariable('ConnectionState'), 'Restart after retry exhaustion should remain offline.');
});

runHarnessCase($results, 'OBS-04 refresh retry restart', static function (): void {
    $clock = new NavimowTestFakeClock(2500000);
    $transport = new NavimowScriptedApiTransport();
    $before = createHarnessAccount($clock, $transport, 206, 3600);

    $transport->enqueue(new RuntimeException('Synthetic transport failure.'));
    $before->RefreshAuthentication();
    $snapshot = $before->testSnapshotPersistentState();
    unset($before);

    $after = restoreHarnessAccount($clock, $transport, 206, $snapshot);
    harnessAssertSame(1, $after->testReadAttribute('RefreshRetryCount'), 'Restart should preserve refresh retry count.');
    harnessAssertSame(60000, $after->testTimerInterval('RefreshToken'), 'Restart should resume bounded refresh retry.');
    harnessAssertSame(HARNESS_CONNECTION_OFFLINE, $after->testReadVariable('ConnectionState'), 'Restart during retry should remain offline.');

    $transport->enqueue(tokenResponse(7200));
    $after->RefreshAuthentication();
    harnessAssertSame(0, $after->testReadAttribute('RefreshRetryCount'), 'Successful retry should clear the retry count.');
    harnessAssertSame(HARNESS_CONNECTION_CONNECTED, $after->testReadVariable('ConnectionState'), 'Successful retry should reconnect.');
    harnessAssertSame(6900000, $after->testTimerInterval('RefreshToken'), 'Successful retry should restore normal refresh scheduling.');
});

runHarnessCase($results, 'OBS-04 authorization code no retry', static function (): void {
    $clock = new NavimowTestFakeClock(2600000);
    $transport = new NavimowScriptedApiTransport();
    $account = createHarnessAuthorizationAccount($clock, $transport, 207);

    $transport->enqueue(new RuntimeException('Synthetic transport failure.'));
    $account->ExchangeAuthorizationCode('CODE_TEST_VALUE');

    harnessAssertSame(0, $account->testReadAttribute('RefreshRetryCount'), 'Authorization-code failure must not start refresh retry.');
    harnessAssertSame(0, $account->testTimerInterval('RefreshToken'), 'Authorization-code failure must leave refresh timer stopped.');
    harnessAssertSame(1, $transport->requestCount(), 'Authorization-code exchange should not be replayed.');
});

foreach ($results as $result) {
    fwrite(
        $result['status'] === 'PASS' ? STDOUT : STDERR,
        sprintf('%s: %s%s', $result['name'], $result['status'], PHP_EOL)
    );
    if ($result['status'] === 'FAIL') {
        fwrite(STDERR, '  ' . $result['message'] . PHP_EOL);
    }
}

$failed = array_values(array_filter(
    $results,
    static fn (array $result): bool => $result['status'] === 'FAIL'
));
if ($failed !== []) {
    fwrite(
        STDERR,
        sprintf(
            'Navimow pilot observation harness found %d failing case(s).%s',
            count($failed),
            PHP_EOL
        )
    );
    exit(1);
}

fwrite(STDOUT, "Navimow pilot observation harness checks passed.\n");

function createHarnessDevice(
    NavimowTestFakeClock $clock,
    NavimowScriptedDeviceParent $parent,
    int $instanceId
): NavimowHarnessDevice {
    $device = new NavimowHarnessDevice($instanceId, $clock);
    $device->testSetParentHandler($parent->handler());
    $device->Create();
    $device->testSetProperty('DeviceId', 'DEVICE_001');
    $device->ApplyChanges();
    return $device;
}

function restoreHarnessDevice(
    NavimowTestFakeClock $clock,
    NavimowScriptedDeviceParent $parent,
    int $instanceId,
    array $snapshot
): NavimowHarnessDevice {
    $device = new NavimowHarnessDevice($instanceId, $clock);
    $device->testSetParentHandler($parent->handler());
    $device->testRestorePersistentState($snapshot);
    $device->Create();
    $device->ApplyChanges();
    return $device;
}

function createHarnessAccount(
    NavimowTestFakeClock $clock,
    NavimowScriptedApiTransport $transport,
    int $instanceId,
    int $expiresIn
): NavimowHarnessAccount {
    $account = new NavimowHarnessAccount(
        $instanceId,
        $clock,
        $transport->handler()
    );
    $account->Create();
    $account->testSetProperty('BaseUrl', 'https://navimow.invalid');
    $account->testSetProperty('ClientId', 'CLIENT_TEST_VALUE');
    $account->testSetProperty('ClientSecret', 'SECRET_TEST_VALUE');
    $account->testSetProperty('RedirectUri', 'https://callback.invalid');
    $account->testSetAttribute('AccessToken', 'ACCESS_TEST_VALUE');
    $account->testSetAttribute('RefreshToken', 'REFRESH_TEST_VALUE');
    $account->testSetAttribute(
        'TokenExpiresAtInternal',
        $clock->now() + $expiresIn
    );
    $account->ApplyChanges();
    return $account;
}

function restoreHarnessAccount(
    NavimowTestFakeClock $clock,
    NavimowScriptedApiTransport $transport,
    int $instanceId,
    array $snapshot
): NavimowHarnessAccount {
    $account = new NavimowHarnessAccount(
        $instanceId,
        $clock,
        $transport->handler()
    );
    $account->testRestorePersistentState($snapshot);
    $account->Create();
    $account->ApplyChanges();
    return $account;
}

function createHarnessAuthorizationAccount(
    NavimowTestFakeClock $clock,
    NavimowScriptedApiTransport $transport,
    int $instanceId
): NavimowHarnessAccount {
    $account = new NavimowHarnessAccount(
        $instanceId,
        $clock,
        $transport->handler()
    );
    $account->Create();
    $account->testSetProperty('BaseUrl', 'https://navimow.invalid');
    $account->testSetProperty('ClientId', 'CLIENT_TEST_VALUE');
    $account->testSetProperty('ClientSecret', 'SECRET_TEST_VALUE');
    $account->testSetProperty('RedirectUri', 'https://callback.invalid');
    $account->ApplyChanges();
    return $account;
}

function seedActiveCommand(
    NavimowHarnessDevice $device,
    NavimowTestFakeClock $clock,
    int $deadline,
    int $state
): void {
    $device->testSetAttribute('CommandActive', true);
    $device->testSetAttribute('CommandCloudResult', HARNESS_COMMAND_ACCEPTED);
    $device->testSetAttribute('CommandStatusBaseline', 0);
    $device->testSetAttribute('CommandStartedAt', $clock->now());
    $device->testSetAttribute('CommandDeadline', $deadline);
    $device->testSetAttribute('CommandVerificationState', $state);
}

function commandAccepted(NavimowTestFakeClock $clock): array
{
    return [
        'status' => 'ok',
        'result' => HARNESS_COMMAND_ACCEPTED,
        'receivedAt' => $clock->now(),
    ];
}

function commandAlreadyInState(NavimowTestFakeClock $clock): array
{
    return [
        'status' => 'ok',
        'result' => 3,
        'receivedAt' => $clock->now(),
    ];
}

function statusResult(
    NavimowTestFakeClock $clock,
    int $vehicleState
): Closure {
    return static fn (): array => [
        'status' => 'ok',
        'deviceId' => 'DEVICE_001',
        'data' => [
            'vehicleState' => $vehicleState,
            'vehicleStateSource' => match ($vehicleState) {
                HARNESS_STATE_DOCKED => 'isDocked',
                HARNESS_STATE_RUNNING => 'isRunning',
                HARNESS_STATE_PAUSED => 'isPaused',
                default => 'isDocking',
            },
            'batteryLevel' => 80,
            'online' => true,
        ],
        'receivedAt' => $clock->now(),
        'staleAfter' => 300,
    ];
}

function readFailure(): array
{
    return [
        'status' => 'error',
        'kind' => 'transport',
        'message' => 'Synthetic status transport failure.',
        'staleAfter' => 300,
    ];
}

function tokenResponse(int $expiresIn): array
{
    return [
        'status' => 200,
        'body' => json_encode([
            'access_token' => 'ACCESS_REFRESHED_VALUE',
            'token_type' => 'Bearer',
            'expires_in' => $expiresIn,
        ], JSON_THROW_ON_ERROR),
    ];
}

function apiVehicleStatusResponse(string $vehicleState): array
{
    return [
        'status' => 200,
        'body' => json_encode([
            'code' => 1,
            'desc' => 'Operation successful',
            'data' => [
                'payload' => [
                    'devices' => [[
                        'id' => 'DEVICE_001',
                        'capacityRemaining' => [[
                            'unit' => 'PERCENTAGE',
                            'rawValue' => 80,
                        ]],
                        'vehicleState' => $vehicleState,
                    ]],
                ],
            ],
        ], JSON_THROW_ON_ERROR),
    ];
}

function forwardAccountStatus(NavimowHarnessAccount $account): array
{
    return json_decode(
        $account->ForwardData(json_encode([
            'DataID' => '{54620029-127D-470D-97C7-44265496FAA0}',
            'SchemaVersion' => 1,
            'Function' => 'GetStatus',
            'DeviceId' => 'DEVICE_001',
        ], JSON_THROW_ON_ERROR)),
        true,
        32,
        JSON_THROW_ON_ERROR
    );
}

function runHarnessCase(array &$results, string $name, Closure $case): void
{
    try {
        $case();
        $results[] = ['name' => $name, 'status' => 'PASS', 'message' => ''];
    } catch (Throwable $exception) {
        $message = preg_replace('/[[:cntrl:]]/', '', $exception->getMessage())
            ?? 'Harness case failed.';
        $results[] = [
            'name' => $name,
            'status' => 'FAIL',
            'message' => substr($message, 0, 240),
        ];
    }
}

function harnessAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . sprintf(
                ' Expected %s, got %s.',
                var_export($expected, true),
                var_export($actual, true)
            )
        );
    }
}

function harnessAssertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function harnessAssertNotContains(
    string $needle,
    string $haystack,
    string $message
): void {
    if (str_contains($haystack, $needle)) {
        throw new RuntimeException($message);
    }
}
