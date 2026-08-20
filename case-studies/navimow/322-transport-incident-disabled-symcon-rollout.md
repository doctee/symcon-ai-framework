# 322 Transport Incident Disabled Symcon Rollout

**Case study:** Navimow native IP-Symcon module

**Status:** Exact published incident-reducer commit installed and verified with
MQTT and position diagnostics disabled

**Date:** 2026-08-17

## 1. Result

Two equal bounded read-only preflights passed before exactly one supported
module update. Immediate and delayed read-only postflights then passed.

```text
installed before: 888325d8649160c5bae473f4f8a052cf86e703b6
installed after:  405fd24b5450c909c35e038a12bd69378d33deb6
module updates:   1
module reloads:   0
explicit ApplyChanges calls: 0
```

Every accepted MCP result separately satisfied:

```text
transportError: null
executionError: null
truncated:      false
```

## 2. Authorization Boundary

Gate L1 authorized only the disabled supported module update and bounded
read-only verification. It did not authorize MQTT or position activation,
credential retrieval, an OAuth action, restart, `MC_ReloadModule()`, explicit
`IPS_ApplyChanges()` or a mower command.

## 3. Probe Correction

The first read-only execution stopped before mutation because the reused probe
treated the installed automatic-closure commit as if closure fields were still
absent. The live installation correctly reported those fields in inactive
`Closed` state.

The probe was corrected only at that version boundary. Its mutation scan,
commit constraints and all operational preconditions remained unchanged. Two
subsequent accepted preflights passed before the update. The retained probe
failure caused no live mutation.

## 4. Accepted Preflight

Both accepted observations proved:

- exact baseline commit on branch `main`;
- clean and valid module repository;
- ready kernel and all Navimow module instances at status `102`;
- MQTT and WebSocket Core instances inactive at status `104`;
- MQTT and position diagnostics disabled;
- Authorization header and MQTT username and password absent;
- REST operational and authentication usable;
- exact four-topic allowlist without wildcards;
- 14-variable identity contract unchanged;
- all five configured Archive logging contracts present and queryable;
- historical position accounting retained; and
- automatic closure inactive and complete.

The contract, identity, Archive, command-evidence and subscription hashes were
equal across both preflights.

## 5. Single Mutation

The update probe recomputed all safety conditions immediately before calling
`MC_UpdateModule()` exactly once. It returned success and immediately reported
the target commit `405fd24b` from a clean and valid repository.

There was no retry, reload, explicit ApplyChanges, property mutation, OAuth
action, restart, MQTT activation or mower command.

## 6. Immediate And Delayed Postflight

The immediate postflight passed ten seconds after the update. The delayed
postflight passed more than three minutes later.

Both proved:

- exact target commit on branch `main`;
- all module statuses remained `102`;
- MQTT and WebSocket remained inactive at `104`;
- MQTT and position diagnostics remained disabled;
- Authorization and MQTT credentials remained absent;
- REST remained operational;
- all variable identities and five Archive contracts remained unchanged;
- command evidence and subscription topology remained unchanged;
- historical coordinate-free position accounting remained available; and
- automatic closure remained inactive and complete.

No delayed activation, credential reappearance or contract drift was observed.

## 7. Incident Contract

The target commit exposes the new additive disabled-state projection:

```text
format version:                  2
incident sequence:               0
session incident baseline:       0
session incident count:          0
healthy reset:                   900 seconds
maximum incident duration:       1800 seconds
maximum episodes per incident:   3
maximum recoverable incidents:   1
open incident type:              valid
last incident type:              valid
```

The zero values are expected because no new pilot has been activated. The
projection was verified without triggering transport behavior.

## 8. Preserved Architecture

```text
public device-state authority: REST
MQTT direction:                receive-only
MQTT default:                  disabled
MQTT publish path:             absent
MQTT mower-command path:       absent
public variables:              unchanged
Archive logging identities:    unchanged
cleanup ordering:              credential first and idempotent
```

## 9. Private Evidence

The object-ID-free operation summary and private probes are retained under:

```text
private/navimow-capture/transport-incident-l1/
private/navimow-capture/output/transport-incident-l1/
```

They are not publication inputs and contain no credentials, private MQTT
topics, payloads, coordinates, device identity, ObjectIDs or hostname.

## 10. Architecture Decisions

### AD-NAV-1324: Correct a stale probe boundary before mutation

The stopped first read must not be reclassified as a product failure. Updating
only the known version boundary preserved every safety precondition and allowed
two clean read-only baselines before mutation.

### AD-NAV-1325: Verify additive incident diagnostics while disabled

The new projection can be proven without credentials or transport activity.
This keeps installation evidence separate from later behavior evidence.

### AD-NAV-1326: Preserve historical diagnostics across module update

Bounded episode, closure and position history remain diagnostic state. The
update adds incident fields without resetting unrelated retained counters.

### AD-NAV-1327: Keep the live mutation singular

One supported Module Control update is sufficient. Successful immediate and
delayed read-back do not justify reload or ApplyChanges operations.

## 11. Mutation Counts

```text
retained read-only probe failure: 1
accepted read-only preflights:    2
module updates:                   1
immediate postflights:            1
delayed postflights:              1
module reloads:                   0
explicit ApplyChanges:            0
OAuth actions:                    0
MQTT activations:                 0
position activations:             0
service restarts:                 0
mower commands:                   0
```

## 12. Gate State And Recommendation

| Gate | Status |
|---|---|
| SAEF merge | PASS |
| standalone publication | PASS |
| metadata conformance | PASS BY BYTE EQUIVALENCE |
| disabled Symcon rollout | PASS |
| bounded incident-policy pilot | CLOSED |

Retain exact commit `405fd24b` with MQTT and position diagnostics disabled.
The next SAEF step should prepare Gate L2 for exactly one bounded restart-free
receive-only pilot. It requires a fresh disabled preflight, the 1200-second
token horizon and renewed explicit acceptance of temporary credential storage.
