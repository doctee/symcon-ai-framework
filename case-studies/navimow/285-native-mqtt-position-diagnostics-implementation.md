# 285 Native MQTT Position Diagnostics Implementation

**Case study:** Navimow native IP-Symcon module

**Status:** Position Diagnostic v1 implemented and validated offline; no
publication, Symcon update or MQTT activation performed

**Date:** 2026-08-05

**Scope:** Extend the receive-only structured MQTT parser with a bounded local
pose projection and provide an opt-in, private Account diagnostic without
changing REST authority, public variables, archives or command behavior

## 1. Result

Position Diagnostic v1 is implemented in the isolated workstream
`codex/navimow-position-diagnostics`.

The implementation adds:

- structured extraction of a complete local pose from location payloads;
- a separate bounded position reducer;
- monotonic sample sequencing independent of device source time;
- five-second retention downsampling with immediate state-change samples;
- a 512-sample hard track bound;
- counters for downsampling, eviction and source-time regressions;
- cumulative movement, bounds and source-gap summaries beyond ring eviction;
- an opt-in Account property disabled by default;
- a read-only Account diagnostic method and configuration-form action;
- automatic position-state cleanup with existing MQTT ephemeral cleanup;
- parser, reducer, privacy, size, ingestion and compatibility tests.

No regular IP-Symcon variable, action or Archive Control contract was added.

## 2. Runtime Boundary

The authoritative runtime contract remains:

```text
device state and public variables: REST authoritative
MQTT transport direction:          receive-only
MQTT local pose:                   diagnostic-only
MQTT device command path:          absent
position archive logging:          absent
```

The new `EnableMqttPositionDiagnostics` property defaults to `false` and is
independent from the existing public variable contract. Position collection
requires both this property and the existing MQTT shadow transport to be
enabled.

## 3. Parser Contract

`MqttPayloadParser` now maps a complete location pose to:

```text
localX
localY
orientation
sourceTimestamp
vehicleStateCode
```

The parser still accepts partial location payloads. It emits no pose when one
of the required geometry, timestamp or vehicle-state fields is absent.

Bounds are enforced before the Account receives the semantic patch:

- local coordinates must be finite and within `+/-10,000,000`;
- orientation must be finite and within `+/-pi`;
- source timestamp must remain an integer;
- error messages contain neither geometry values nor raw payloads.

The names `localX` and `localY` intentionally avoid a latitude/longitude claim.

## 4. Bounded Reducer

`MqttPositionDiagnostic` owns a separate format-v1 state:

```text
maximum retained samples:       512
minimum regular retention gap:  5 seconds
immediate retention exception:  vehicle-state code change
maximum serialized state:       131,072 bytes
sample ordering:                monotonic sampleSequence
source-time regressions:        accepted and counted
```

Every valid pose advances `sampleSequence` and updates `latest`. Samples inside
the five-second window are counted as downsampled rather than retained. Once
the track reaches 512 entries, the oldest entry is evicted and counted. The
detail ring covers approximately 43 minutes at the nominal five-second
retention cadence.

The following bounded cumulative values survive detail-ring eviction until
MQTT cleanup:

```text
first and last receipt time
coordinate change count
local path length
maximum local step distance
local coordinate bounds
maximum positive source-time gap
```

An offline 515-sample boundary test produced a 512-entry serialized state of
76,601 bytes, leaving bounded headroom below the hard limit.

## 5. Account Projection

`GetMqttPositionDiagnostics()` returns a fixed diagnostic envelope:

```text
formatVersion
featureEnabled
transportEnabled
authority = diagnostic-only
coordinateSystem = local-map
status
trackedDeviceCount
observation
```

The observation contains only the reduced pose, bounded track and aggregate
counters. It contains no topic, raw payload or device identity.

The projection fails closed:

- `disabled` when position diagnostics are off;
- `inactive` when diagnostics are enabled but MQTT transport is off;
- `unavailable` before a complete pose arrives;
- `available` for exactly one tracked device;
- `ambiguous` after evidence from another device;
- `invalid` for malformed or oversized persisted diagnostic state.

Multiple devices do not produce a mixed track. Device identity is retained
only as an internal SHA-256 ownership key and is never projected.

## 6. Cleanup and Persistence

Position state is part of the existing ephemeral MQTT cleanup boundary.
`ApplyChanges()` and successful owned-transport disconnect cleanup reset the
track, latest sample and counters. Disabling and applying the configuration
therefore removes retained coordinates without creating or deleting public
variables.

The implementation introduces no new long-term archive or fixture containing
installation coordinates.

## 7. Offline Verification

The following focused checks pass:

```text
MQTT fixture and parser checks
MQTT position reducer checks
MQTT Account ingestion checks
MQTT shadow diagnostic checks
MQTT pilot checkpoint checks
MQTT REST reconciliation checks
MQTT transport lifecycle checks
distribution structure validation
PHPCS for changed productive and test files
PHPStan for changed productive files
git diff --check
```

The complete repository check also passes with the canonical dependency
installation from the clean main workspace:

```text
COMPOSER_VENDOR_DIR=<canonical-main>/vendor \
composer check
```

That dependency path is toolchain input only. The implementation and evidence
remain in the isolated Navimow worktree; no Open-Meteo or ControlLight source
was copied or changed.

## 8. Privacy Review

Public source, tests and documentation use synthetic coordinates only. The
private structural analyzer and its aggregate result remain outside Git.

The public candidate contains no:

- private MQTT topic;
- device identifier;
- OAuth or MQTT credential;
- private capture payload;
- garden coordinate;
- Symcon ObjectID;
- local host or installation metadata.

## 9. Architecture Decisions

### AD-NAV-1203: Use a separate position reducer

The existing semantic shadow rejects regressing source timestamps. Position
evidence must preserve receive order and count such regressions, so combining
the two reducers would corrupt one of the contracts.

### AD-NAV-1204: Keep position opt-in and variable-free

The coordinate system is useful for pilot analysis but not characterized
enough for stable automation or archive contracts.

### AD-NAV-1205: Fail closed on multiple devices

A single Account diagnostic must never merge tracks from different mowers.

### AD-NAV-1206: Reuse MQTT ephemeral cleanup

Position coordinates follow the established transport cleanup boundary rather
than introducing independent retention controls.

### AD-NAV-1207: Preserve source time as metadata

`sampleSequence` records ingestion order. Device source time remains visible
for analysis but does not decide whether a valid pose is accepted.

## 10. Gate Status

| Gate | Status |
|---|---|
| private structural evidence | PASS |
| Position Diagnostic v1 implementation | PASS OFFLINE |
| focused offline verification | PASS |
| repository-wide verification | PASS |
| privacy review | PASS |
| local candidate commit | CLOSED |
| SAEF branch publication | CLOSED |
| standalone module publication | CLOSED |
| metadata conformance | CLOSED |
| Symcon update | CLOSED |
| MQTT activation | CLOSED |
| mower command | CLOSED |

## 11. Next Step

Review and freeze the exact offline candidate in a publication plan. A later
sequence must keep SAEF publication, standalone module publication, metadata
conformance, disabled Symcon update and receive-only pilot activation as
separate explicit gates.

The eventual live pilot should combine transport and local-position evidence
for 48 to 72 hours and at least two natural mowing cycles, followed by
mandatory credential-free cleanup.
