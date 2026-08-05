# 263 Navimow Account Status Recovery and Update Gate Design

**Case study:** Navimow native IP-Symcon module

**Status:** Recovery architecture decided; implementation and all mutation
gates remain closed

**Date:** 2026-08-04

**Scope:** Compare recovery options for the stale Account status `101` and
design a deterministic path to the episode-summary update

## 1. Decision

Do not execute a standalone no-configuration-change `IPS_ApplyChanges()` on
the installed module.

Instead:

1. add an explicit successful status finalization to the Account module;
2. validate every normal `ApplyChanges()` exit path offline;
3. publish a new corrective standalone commit that also contains the already
   published episode-summary increment;
4. validate the exact corrective commit's metadata;
5. execute one separately authorized `MC_UpdateModule()` directly from the
   documented stale `101` baseline;
6. require immediate and delayed status `102` with MQTT still disabled and
   credential-free.

This makes the later update an explicit, reviewed recovery operation rather
than an update that happens to be used as an undocumented repair.

## 2. Fixed Evidence Baseline

Steps 261 and 262 established:

```text
installed commit:             79686e52
published episode target:     a8481c97
Account status:               101
status timestamp:             2026-08-03 18:07:53 UTC
cleanup ApplyChanges:         2026-08-03 18:07:53 UTC
kernel:                       ready and reconciled
REST:                         connected and operational
poll and refresh timers:      operational
MQTT feature:                 disabled
MQTT credentials:             absent
MQTT timers:                  disabled
diagnostic errors:            none
public variables:             14
Archive Control contracts:    5
```

The current status is not accepted as healthy. It is accepted only as the
exact, immutable recovery starting condition for a later corrective update.

## 3. Option A: Repeat Installed ApplyChanges

### Procedure

```text
IPS_ApplyChanges(Account): 1
configuration changes:     0
```

### Potential benefit

- smallest immediate live mutation;
- might cause the Core to finalize status `102`;
- would not require publication first.

### Defects

The installed Account source has no explicit terminal `SetStatus()` call. With
MQTT disabled and retained ownership metadata, `ApplyChanges()` also enters
the transport-cleanup path and applies changes to the WebSocket and MQTT Core
instances.

Therefore the operation:

- repeats the same outer lifecycle that produced the stale status;
- repeats nested Core `IPS_ApplyChanges()` calls;
- has no source-level guarantee of a final `102`;
- does not prevent recurrence after a future pilot cleanup;
- would consume a live mutation without improving the module contract.

### Decision

```text
REJECT AS PRIMARY RECOVERY
```

It may be retained only as a future diagnostic experiment if the corrective
source path becomes impossible. It requires separate authorization and must
not be executed before the implementation decision is revisited.

## 4. Option B: Explicit Status Finalization

### Required behavior

Every normal completion of Account `ApplyChanges()` must establish:

```php
$this->SetStatus(IS_ACTIVE);
```

This includes the existing normal branches for:

- incomplete cloud configuration;
- configured account without an access token;
- configured and authenticated account;
- authenticated account during kernel-reconciliation deferral.

The status means the PHP module instance completed configuration application.
It does not claim that cloud authentication or MQTT is active. Those remain
represented by the existing owned contracts:

- `ConnectionState`;
- `ReauthRequired`;
- MQTT configuration validation;
- MQTT lifecycle diagnostics;
- REST timestamps and error counters.

### Exception behavior

An uncaught exception must not be followed by `SetStatus(IS_ACTIVE)`. The
implementation should place status finalization only on successful terminal
paths, not in `finally` and not before cleanup or scheduling completes.

No exception may be swallowed merely to obtain `102`.

### Metadata impact

No new custom status code is required. The existing form has no status section,
and the operational detail already belongs to typed variables and bounded
diagnostics. The preferred correction therefore changes productive Account
PHP and tests only, not `form.json`, `locale.json` or module metadata.

### Decision

```text
SELECTED
```

## 5. Why Active Is Correct

Symcon status `102` describes an active module instance. It does not prove an
active upstream cloud session.

The Account remains an active configuration and authentication endpoint when:

- credentials are not configured yet;
- OAuth authorization is pending;
- reauthentication is required;
- optional MQTT is disabled.

These states must remain visible through the existing domain variables instead
of overloading the Core instance status.

This also avoids adding custom error codes and metadata solely to repair a
Core lifecycle residue.

## 6. Implementation Contract

The next implementation step may change only:

```text
case-studies/navimow/distribution/NavimowAccount/module.php
case-studies/navimow/tests/harness/SymconRuntime.php
focused Account lifecycle tests
the corresponding SAEF report and index
```

The test harness must gain bounded status support:

```text
initial status:          101
SetStatus capture:       exact integer
successful terminal:    102
```

Focused tests must cover:

| Scenario | Expected status |
|---|---:|
| incomplete configuration | `102` |
| authorization pending | `102` |
| connected, MQTT disabled | `102` |
| kernel reconciliation deferral | `102` |
| repeated disabled `ApplyChanges()` | `102` |
| cleanup exception before normal completion | not falsely forced to `102` |

The implementation must preserve all existing timer, authentication, cleanup
and MQTT lifecycle assertions.

## 7. Offline Gates

Implementation readiness requires:

- focused Account status tests;
- all existing REST and authentication fixtures;
- complete MQTT parsing, lifecycle and recovery tests;
- pilot diagnostics and episode-accounting tests;
- distribution validation;
- PHPCS and PHPStan;
- private pilot accounting harness;
- complete repository `make check`.

Static review must prove:

- no new MQTT writer or command route;
- no optimistic public-variable write;
- no credential exposure;
- no new property, attribute, variable or timer;
- no change to polling, token refresh or retry semantics;
- no metadata delta unless separately justified.

## 8. Corrective Publication Design

The corrective candidate supersedes `a8481c97` as the live update target. It
must be based on current standalone `main` and preserve the episode-summary
blob content except for the narrow status correction.

Publication remains a separate gate and must prove:

- fetched standalone `main` equals the reviewed baseline;
- exact expected path set;
- source and standalone hashes equal;
- remote fast-forward verification;
- no tag or release;
- no Symcon access.

Because the preferred correction changes PHP only, metadata files are expected
to remain byte-identical. Gate B must nevertheless bind conformance to the
exact new publication commit.

## 9. Corrective Symcon Update Gate

The later Gate C is intentionally different from step 252. It may accept the
documented Account status `101` only when every other precondition reproduces
steps 261 and 262 exactly.

### Required preconditions

- installed commit exactly `79686e52`;
- Account `InstanceChanged` still bound to the step-248 cleanup;
- repository clean and valid;
- Configurator, Device and Receiver at `102`;
- MQTT and WebSocket at `104`;
- MQTT disabled and credential-free;
- no pending MQTT lifecycle work;
- REST connected and operational;
- token usable and no reauthentication required;
- five Account timers present with only Poll and Refresh enabled;
- all 14 variable and five Archive Control contracts unchanged;
- exact corrective target published and metadata-conformant.

Any second deviation stops before mutation.

### Authorized mutation

```text
MC_UpdateModule():        exactly 1
MC_ReloadModule():        0
explicit IPS_ApplyChanges(): 0
service restart:          0
```

No retry is permitted after an ambiguous update result.

### Immediate success conditions

- exact corrective commit installed;
- repository clean and valid;
- Account, Configurator, Device and Receiver all `102`;
- MQTT and WebSocket remain `104`;
- credentials remain absent;
- REST remains operational;
- variables and archive contracts remain unchanged;
- `NAVAC_GetMqttPilotSummary()` passes its 16-KiB contract;
- no Registry, Statistics or command evidence changes from summary reads.

### Delayed success conditions

After at least 70 seconds:

- Account remains `102`;
- MQTT remains disabled and credential-free;
- no MQTT timer or counter advanced;
- REST remains operational;
- summary and cumulative sequences remain stable;
- all structural and archive hashes remain equal.

## 10. Failure Handling

If the corrective update completes but Account remains `101`:

1. do not issue a second update;
2. keep MQTT disabled;
3. preserve installed commit and complete channel evidence;
4. verify REST and credentials read-only;
5. open a new Core/PHP-SDK compatibility analysis.

No automatic rollback is authorized. A rollback would itself require a second
module update and a separately reviewed gate.

## 11. Architecture Decisions

### AD-NAV-1039: Reject mutation without a durable contract

A one-time ApplyChanges experiment could change the symptom without preventing
recurrence. Recovery must improve the module's lifecycle contract.

### AD-NAV-1040: Separate module activity from cloud connectivity

Core status `102` represents successful module configuration application.
Authentication and MQTT remain explicit domain states.

### AD-NAV-1041: Finalize status only after normal completion

Status must be set on every successful terminal path and never from `finally`
or before work that may throw.

### AD-NAV-1042: Permit one documented 101-to-corrective update

The later live gate may accept `101` only because its exact origin, timestamp
and semantic-health envelope are proven. This is not a general weakening of
the `102` precondition.

### AD-NAV-1043: Do not add custom status metadata without need

Existing typed variables and diagnostics already model configuration,
authentication and MQTT state. The minimal repair should remain PHP-only.

### AD-NAV-1044: Supersede the episode-only live target

Commit `a8481c97` remains a valid publication but is no longer the recommended
Symcon target. The corrective commit must contain both the episode summary and
the status finalization.

## 12. Gate Status

| Gate | Status |
|---|---|
| read-only cause analysis | PASS |
| recovery design | PASS |
| standalone ApplyChanges experiment | REJECTED / NOT PERFORMED |
| status-finalization implementation | CLOSED |
| corrective publication | CLOSED |
| corrective metadata conformance | CLOSED |
| corrective Symcon update | CLOSED |
| MQTT staging or activation | CLOSED |
| restart | CLOSED |
| mower command | NOT PLANNED |

## 13. Next Step

Proceed with:

```text
264-navimow-account-status-finalization-implementation.md
```

That step may implement and test the narrow source and harness correction
offline. It must not publish, access Symcon, activate MQTT, retrieve
credentials, restart a service or send a mower command.
