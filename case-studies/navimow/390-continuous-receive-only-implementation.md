# SAEF Step 390: Continuous Receive-Only Implementation

**Case study:** Navimow native IP-Symcon module

**Status:** Productive offline candidate implemented; functional and artifact
validation pass, lock-identical static analysis remains open; publication and
all live gates remain closed

**Date:** 2026-09-04

## 1. Purpose

Step 389 froze the implementation contract for a private, continuously
monitored and strictly receive-only MQTT operating mode. This step implements
that contract in a current, isolated Navimow worktree.

The implementation does not publish, update or access Symcon, activate MQTT,
retrieve credentials, perform OAuth, restart Symcon or send a mower command.

## 2. Worktree And Scope

The candidate was developed on:

```text
branch: codex/navimow-continuous-receive-only
base:   78f5c62
```

The worktree started from current `origin/main` and contained only the retained
Navimow documentation from steps 387 to 389. No mixed checkout, unrelated
working-tree state or external runtime state was used.

The productive change remains inside the Navimow case study plus its existing
manifest-driven deployment and generated distribution roots.

## 3. Implemented Operating Contract

The existing master property remains unchanged and defaults to disabled:

```text
EnableMqttShadow = false
```

The new policy property is additive:

```text
MqttOperatingMode = 1
```

Its exact values are:

| Value | Mode | Behavior |
|---:|---|---|
| `1` | `BoundedPilot` | Existing duration-bound pilot |
| `2` | `ContinuousReceiveOnly` | Renewable receive-only operation |

Existing installations therefore retain the bounded-pilot behavior. No
existing configuration is implicitly migrated into continuous mode. An
unknown mode is a configuration error and initiates credential-first cleanup;
it never falls back to an enabled mode.

REST remains authoritative for public mower state and commands. MQTT remains
an allowlisted receive-only evidence channel and gains no publish or device
command path.

## 4. Continuous State Reducer

`MqttContinuousOperationReducer` implements the state transitions independently
from Symcon, transport and credentials. It provides:

- strict versioned restore and bounded serialization;
- exact predecessor-state validation;
- monotonic timestamp and saturating counter checks;
- a 72-hour safety lease with renewal eligibility after 48 hours;
- five-minute renewal rechecks without crossing lease expiry;
- credential-free CircuitOpen state after inner reconnect exhaustion;
- at most four outer half-open probes per lease;
- cooldowns of 1800, 7200, 21600 and 86400 seconds;
- a 180-second probe deadline and 900-second recovery confirmation;
- first-stop-reason preservation; and
- explicit `Stopping`, `CredentialsCleared`, `Suspended` and `Stopped`
  transitions.

The reducer returns only a validated registry and one symbolic effect. The
Account module remains responsible for all Core and credential operations.

## 5. Account Integration

The Account module now owns three additional timers:

```text
MqttContinuousLease
MqttContinuousRecovery
MqttContinuousClosure
```

It composes the existing ownership validation, exact subscription set,
`60/300/900`-second inner reconnect sequence, credential mapper, passive token
refresh and `ConfigurationHash` helper.

Continuous start and recovery require:

- the exact continuous mode;
- valid owned Core topology and subscription shape;
- no active or closing bounded pilot;
- connected REST authentication without reauthentication requirement;
- at least 1200 seconds remaining token horizon;
- a credential-free transport before a new connection attempt; and
- a matching privacy-safe configuration fingerprint.

Lease renewal additionally requires Active transport health, no pending token
rotation, at least 900 seconds of sustained Core health and a REST success not
older than 900 seconds.

Inner reconnect exhaustion opens the outer circuit only in continuous mode.
Bounded-pilot exhaustion and closure semantics remain unchanged. A half-open
probe consumes exactly one probe count and one connection attempt. Persistent
failure suspends the operation without an endless retry timer.

Operator disable, mode change, invalid registry, configuration drift and
ownership failure all use the credential-first closure path. WebSocket
authorization and MQTT username/password must be absent before a stop can be
finalized. Manual disconnect in continuous mode means explicit suspension;
manual resume creates one new bounded lease.

## 6. Additive Variables And Profiles

The Account adds five variables without changing existing Idents, positions,
profiles, Archive settings or aggregation:

| Position | Ident | Meaning |
|---:|---|---|
| `70` | `MqttOperatingState` | Disabled, starting, active, degraded, recovery or terminal state |
| `80` | `MqttLastMessageAt` | Last accepted MQTT evidence timestamp |
| `90` | `MqttLastPositionAt` | Last accepted position timestamp |
| `100` | `MqttPositionFreshness` | Unavailable, fresh, delayed or stale |
| `110` | `MqttLeaseExpiresAt` | Current continuous safety-lease deadline |

The profiles `NAVIMOW.MqttOperatingState` and
`NAVIMOW.MqttPositionFreshness` are registered idempotently. Historical MQTT
timestamps survive terminal cleanup, while freshness becomes Unavailable and
the active lease timestamp is cleared.

## 7. Position Freshness And Local Map

Position evidence is classified exactly as:

```text
age <= 120 seconds       Fresh
age 121..600 seconds     Delayed
age > 600 seconds        Stale
no accepted position     Unavailable
```

Fresh positions retain the existing REST-derived mower state color and
direction. Delayed positions retain that marker and add an amber dashed halo.
Stale and unavailable positions suppress the current-position marker rather
than presenting an outdated location as current.

Retained paths, task evidence and zone statistics are not deleted when the
current marker becomes stale. Docked state remains represented by station
occupancy. Candidate and distribution renderers are behaviorally identical
apart from their established namespaces.

## 8. Diagnostics Contract

MQTT diagnostics advance from format version 2 to 3. The bounded projection
adds:

- effective operating mode;
- continuous state and lease projection; and
- nine saturating continuous-operation counters.

The projection remains bounded and excludes credentials, Authorization
headers, endpoints, topics, coordinates, device identifiers, ObjectIDs,
hostnames and private configuration values.

The checked publication inventory contains 43 files. Its current hashes are:

```text
filesetSha256:      852ae5939981f5a578305c9e9ac37b591b7e536c693bd3f4afea6bbaa94eebbb
publicationSha256: d65f3c49d81e79cac393a222eb7c360c6384d8ab9208d7795a2de0885a08a9b5
```

These hashes identify the local candidate only. They authorize no publication.

## 9. Implementation Variance

Step 389 listed the productive files and primary tests. Implementation exposed
additional stale regression expectations caused by the additive variables,
diagnostic format 3 and the new exact freshness boundaries.

The following non-productive test artifacts were therefore updated as a
traceable variance:

- the bounded diagnostics fixture and fixture README;
- Account ingestion and reconciliation assertions;
- pilot-checkpoint variable-count assertions; and
- Local Map runtime freshness assertions.

No parser, REST endpoint, command behavior, topic allowlist, module GUID,
interface GUID or existing variable contract changed. Updating these tests is
required to prove compatibility rather than an expansion of productive scope.

## 10. Offline Validation

| Check | Result |
|---|---|
| 30 Navimow functional PHP checks | PASS |
| Continuous reducer transitions | PASS |
| Continuous Account integration with synthetic Core | PASS |
| Existing bounded-pilot lifecycle and cleanup | PASS |
| Exact 120/600-second freshness boundaries | PASS |
| Local Map and retained statistics regression suite | PASS |
| Distribution structure validator | PASS |
| 21 changed PHP files with `php -l` | PASS |
| 8 changed JSON files parsed with exceptions enabled | PASS |
| MQTT check script shell syntax | PASS |
| Candidate/distribution renderer equality apart from namespace | PASS |
| Deterministic module fileset build and byte check | PASS |
| Generic publication contract check, mutation disabled | PASS |
| `git diff --check` | PASS |
| PHPCS and PHPStan through the repository resolver | OPEN |
| Full `composer check` | OPEN |

At the conclusion of this implementation step, the complete MQTT check runner
stopped before execution because this isolated
worktree has no `vendor/` directory. The available vendor tree belongs to a
different `composer.lock` and is correctly rejected by the SAEF resolver.
Dependencies were not copied, downloaded or substituted. Functional checks
were executed directly with PHP; lock-identical PHPCS, PHPStan and the full
Composer gate remained mandatory before review or publication. Step 391
resolves this toolchain gate without changing dependencies.

## 11. Architecture Decisions

### AD-NAV-390-01: Keep the new reducer implementation-local

Lease and circuit behavior is now deterministic and independently testable,
but no recurring cross-case-study use has been demonstrated. A new public SAEF
helper would therefore be premature.

### AD-NAV-390-02: Preserve the existing pilot controller

Continuous operation composes the transport primitives but has its own
registry and timers. Existing pilots keep their duration, checkpoint and
automatic cleanup semantics.

### AD-NAV-390-03: Make all continuous starts explicit and bounded

The master switch, operating mode, fresh prerequisites and renewable safety
lease are all required. Restart or configuration migration cannot silently
turn a bounded pilot into ongoing operation.

### AD-NAV-390-04: Keep credential cleanup ahead of terminal state

No closure becomes Suspended or Stopped until Core credential absence has been
verified. Cleanup failure remains retryable and visible instead of being
reported as a successful stop.

### AD-NAV-390-05: Separate state authority from position freshness

REST state color and MQTT location age represent different facts. The map uses
an overlay or hides only the current marker, preserving historical evidence.

### AD-NAV-390-06: Update dependent regression expectations explicitly

Diagnostic format and additive variable changes necessarily affect existing
fixtures and assertions. Recording those updates is safer than weakening or
omitting the affected tests.

## 12. Risks And Open Evidence

- Symcon timer scheduling, Core reconnect ordering and profile registration are
  covered synthetically but not yet verified on the live installation.
- At the end of this step, the candidate had not yet passed lock-identical
  PHPCS, PHPStan or the complete Composer gate; step 391 closes this evidence.
- No disabled-update migration evidence exists for this exact candidate.
- No 24-hour continuous lease, renewal, outage recovery or restart evidence
  exists for this exact candidate.
- The vendor protocol remains undocumented; REST authority and receive-only
  MQTT scope therefore remain mandatory.

## 13. Gate Result

| Gate | Scope | Status |
|---|---|---|
| I1 productive implementation | isolated local candidate | PASS |
| I2 complete offline validation | lock-identical full toolchain | HOLD |
| R1 focused code review | exact candidate | CLOSED |
| P1 SAEF branch, PR and merge | Git publication | CLOSED |
| P2 standalone publication | external publication | CLOSED |
| M1 metadata conformance | published tree | CLOSED |
| S1 disabled Symcon update | exact module update | CLOSED |
| S2 inactive migration postflight | read-only live | CLOSED |
| L1 credential-retention acceptance | operator statement | NOT REQUESTED |
| L2 24-hour continuous activation | one controlled start | CLOSED |
| L3 evidence and cleanup | bounded live observation | CLOSED |
| O1 ongoing private operation | separate operating activation | CLOSED |

No gate inherits authority from this implementation approval.

## 14. Next Step

Proceed with:

```text
391-continuous-receive-only-offline-validation-and-review.md
```

That step should resolve a lock-identical Composer toolchain, run PHPCS,
PHPStan and `composer check`, then perform a focused review of migration,
credential cleanup, lease expiry, recovery exhaustion and variable stability.
It must keep publication and all Symcon actions closed.
