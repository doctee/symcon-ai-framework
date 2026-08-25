# Latest-Command-Wins Offline Implementation

**Status:** Deterministic offline implementation complete; live activation not admitted
**Implementation date:** 2026-08-25
**Architecture:** Report 38
**Live inventory:** Report 39
**Live mutation:** None

## Purpose

This workstream implements the bounded V05-001 command-supersession model in
the repository candidate and its generated filesystem distribution. It does
not stage or activate a fileset, restart Symcon, migrate an owner, publish an
MQTT message or invoke a device action.

## Reuse Decision

No SAEF helper or global public API was added. The exporter composes the
existing Diagnostics responsibilities:

- `Registry` stores one hashed generation slot per configured command channel;
- `Statistics` exposes the bounded `SUPERSEDED_COMMANDS` counter;
- `ErrorRingBuffer` continues to record genuine runtime failures only; and
- `ConfigurationHash` continues to reject dispatch against unreconciled
  configuration.

The generation rules remain private to `MqttDiscoveryExporterRuntime` because
they model this exporter's command lifecycle rather than a recurring framework
abstraction.

## Implemented Contract

The runtime accepts an optional event-time payload in
`dispatchTriggeredVariable()`. Calls without that argument retain the previous
read-at-dispatch behavior for a controlled owner migration. Calls with the
snapshot register a normalized target generation before acquiring the longer
dispatch semaphore.

The dedicated `COMMAND_ARBITRATION_REGISTRY` contains only schema version and
hashed channel slots. Channel and target hashes contain no raw MQTT payload,
topic, ObjectID or installation data. Reconcile prunes channels that are no
longer present in the finite command index. Registry metadata never replays a
command after restart.

Repeated equal targets keep their generation and remain independent
dispatches. A changed target supersedes older invocations for the same entity
and capability only. The runtime rechecks the slot before `RequestAction()`,
after authoritative feedback and before affected-entity publication.

Superseded work increments `SUPERSEDED_COMMANDS` without incrementing failures
or appending to ErrorRingBuffer. Current lock timeouts, confirmation timeouts,
action failures and publication failures retain their genuine failure paths.
An action rejection is not hidden by later supersession. Existing confirmed
feedback remains authoritative when `RequestAction()` returns false after the
device has nevertheless applied the target.

## Timing Bound

Configuration normalization rejects confirmation timeouts above 15 seconds.
The command semaphore wait is derived from the greatest configured
confirmation timeout plus five seconds and is capped at 20 seconds. State
trigger coalescing retains its one-millisecond lock attempt.

## Verification

Deterministic tests cover immutable event input, repeated-equal and independent
channels, supersession before action and publication, old and current lock
timeouts, genuine action failures, bounded and prunable Registry state, invalid
JSON, no replay, and compatible timing bounds.

The complete repository check passed with 28 dispatch scenarios. PHPStan,
PHPCS, generated-fileset verification and `git diff --check` are green. The
deterministic offline fileset hash is
`c5dbcdd1a608bf72119990bfca49d680ba829486ac19dc5add740870edaa8144`.

## Remaining Live Gates

The generated fileset is an offline candidate only. A later live workstream
must separately authorize and verify:

1. recoverable fileset staging and exact hash preconditions;
2. initialization of the new Registry and statistic before command events run;
3. owner migration to pass the immutable event-time payload;
4. controlled activation and restart with rollback evidence;
5. bounded same-channel rapid-command scenarios and immediate compensation;
6. passive observation of both exporter owners and existing consumers; and
7. retention decisions for the previous owner and fileset.

Until those gates pass, the active Symcon installation remains intentionally
unchanged.
