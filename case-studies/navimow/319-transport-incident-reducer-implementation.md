# 319 Transport Incident Reducer Implementation

**Case study:** Navimow native IP-Symcon module

**Status:** Incident reducer, bounded closure rules and additive diagnostics
implemented and verified offline; publication and live gates remain closed

**Date:** 2026-08-17

## 1. Purpose

Step 318 showed that session-5 episode 54 began only three seconds after the
preceding episode was classified as recovered. This implementation separates
individual transport episodes from session-local instability incidents while
preserving all existing episode evidence.

The change is limited to:

- `distribution/NavimowAccount/module.php`;
- `tests/mqtt-pilot-checkpoints.php`; and
- `tests/mqtt-transport-lifecycle.php`.

It changes no REST behavior, reconnect delay, credential-rotation timing,
public variable, Device action, MQTT topic, payload parser or cleanup ordering.

## 2. Implementation

### 2.1 Incident registry

The existing bounded pilot Registry now retains:

```text
incidentSequence
sessionIncidentBaseline
openIncident
lastIncident
```

An incident contains only bounded coordinate-free metadata:

- sequence and session sequence;
- absolute start, last-episode and recovery-candidate timestamps;
- episode and reconnect-attempt counts;
- state, close time, duration and outcome.

No new Attribute, helper, public variable or storage mechanism was introduced.
Existing Registry ownership and size protection remain in force.

### 2.2 Episode-to-incident reduction

The original episode detector still deduplicates repeated observations while
an episode is open and still records every distinct outage separately.

The reducer applies these rules:

1. the first episode opens incident 1;
2. episode recovery moves the incident to `stabilizing`;
3. another episode before 900 healthy seconds is a relapse in that incident;
4. 900 healthy seconds close the incident as recovered;
5. a later episode opens a new independent incident; and
6. incident state is persisted across ApplyChanges and restart.

The 900-second threshold reuses
`MQTT_LIFECYCLE_HEALTHY_RESET_SECONDS`, which already controls reconnect-history
reset. There is no competing recovery clock.

### 2.3 Bounded closure

Automatic closure is requested when:

- a fourth episode opens inside one incident;
- one incident reaches 1800 seconds;
- a second independent incident opens;
- the existing three-attempt reconnect sequence exhausts;
- MQTT reaches terminal authentication or configuration state; or
- the existing absolute 72-hour deadline is reached.

The first accepted reason remains immutable. Closure still stops lifecycle and
checkpoint timers, clears credentials first, disables both properties and
finishes through the existing idempotent state machine.

### 2.4 Additive diagnostics

Full and summary pilot projections now add:

- cumulative incident sequence;
- session incident count;
- fixed policy limits;
- current incident with age and remaining limits; and
- most recently closed incident.

The summary remains under its fixed 16384-byte bound. Credentials, private
topics, endpoints, ObjectIDs, device identity and coordinates remain excluded.

## 3. Offline Evidence

Focused tests prove:

- duplicate observations do not create another episode or incident;
- the observed three-second relapse remains in incident 1;
- three episodes remain within the incident allowance;
- episode 4 requests `incident-episode-limit` closure;
- 900 seconds of sustained health close incident 1;
- a later incident requests `second-transport-incident` closure;
- the absolute 1800-second incident cap survives transient recovery;
- restart preserves both remaining incident time and healthy-reset time;
- terminal authentication requests `terminal-authentication` closure;
- terminal configuration requests `terminal-configuration` closure;
- the first closure reason cannot be overwritten; and
- cleanup still finalizes exactly once.

Existing tests additionally retain proof for:

- reconnect exhaustion after exactly three attempts;
- absolute 72-hour deadline and restart reconciliation;
- credential-first crash-resumable cleanup;
- MQTT position accounting and coordinate cleanup;
- REST pilot observation and command safety; and
- unchanged distribution structure.

## 4. Architecture Decisions

### AD-NAV-1312: Extend the owned Registry instead of adding infrastructure

Incident metadata is small, bounded and implementation-specific. The existing
pilot Registry is its correct owner; another Attribute, helper or public API
would duplicate lifecycle state.

### AD-NAV-1313: Keep episode truth independent from policy reduction

Every distinct outage remains visible as an episode. Incident grouping affects
only the pilot closure decision, so diagnostics do not lose the evidence that
two Core failures occurred.

### AD-NAV-1314: Make terminal states fail closed during a pilot

Authentication and configuration terminal states cannot enter transport retry.
During an active credential-bearing pilot they now request the same owned,
idempotent cleanup path as deadline and retry exhaustion.

### AD-NAV-1315: Preserve all established authority boundaries

REST remains authoritative for public mower state. MQTT remains receive-only,
position data remains diagnostic-only, and no transport condition issues a
mower command or writes a public Device state variable.

## 5. Verification

```text
PHP syntax:                         PASS
MQTT pilot checkpoint tests:       PASS
MQTT transport lifecycle tests:    PASS
MQTT position diagnostics:         PASS
MQTT shadow diagnostics:           PASS
REST pilot observation harness:    PASS
Navimow distribution validator:    PASS
repository-wide PHPStan:           PASS
repository-wide PHPCS:             PASS
complete composer check:            PASS
git diff --check:                   PASS
```

The repository-wide tools used the canonical `vendor/` installation whose
`composer.lock` is byte-identical to the isolated worktree.

## 6. Gate State

| Gate | Status |
|---|---|
| session-5 forensic evidence | PASS |
| incident reducer implementation | PASS LOCALLY |
| focused and static checks | PASS |
| complete repository check | PASS |
| commit | CLOSED |
| SAEF publication | CLOSED |
| standalone publication | CLOSED |
| metadata conformance | CLOSED |
| Symcon update | CLOSED |
| MQTT activation | CLOSED |

## 7. Next Step

Review the exact productive and test diff, then prepare a separately gated
publication-readiness step. No standalone or live operation may be inferred
from this local implementation.
