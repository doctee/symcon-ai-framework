# 313 Automatic Pilot Closure Disabled Symcon Rollout

**Case study:** Navimow native IP-Symcon module

**Status:** Exact published commit installed and verified with MQTT and
position diagnostics disabled

**Date:** 2026-08-13

## 1. Result

Two equal bounded read-only preflights passed before exactly one supported
module update. Immediate and delayed read-only postflights then passed.

```text
installed before: 50b365200e0c5c55990214c31f4a46f28b1406c7
installed after:  888325d8649160c5bae473f4f8a052cf86e703b6
module updates:   1
module reloads:   0
explicit ApplyChanges calls: 0
```

Every MCP result separately satisfied:

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

## 3. Preflight

Both accepted observations proved:

- exact baseline commit on branch `main`;
- clean and valid module repository;
- ready kernel and healthy Navimow instances;
- MQTT and WebSocket inactive;
- Authorization header and MQTT username and password absent;
- REST operational and authentication usable;
- exact four-topic allowlist without wildcards;
- 14-variable identity contract unchanged;
- all five configured Archive logging contracts present and queryable;
- position diagnostics disabled and empty; and
- retained pilot-wide position counters remained available as historical
  diagnostic evidence.

An initial private preflight was retained as a probe failure. It incorrectly
required historical position counters and the bounded reconnect-attempt value
to reset to zero while transport was disabled. The probe was corrected to
accept non-negative retained accounting and a reconnect value within the
established zero-to-three bound. No live mutation occurred before the two
corrected preflights passed.

## 4. Single Mutation

The mutation probe recomputed all safety conditions immediately before calling
`MC_UpdateModule()` once. The call returned success and reported the exact
target commit. There was no retry, reload, explicit ApplyChanges, OAuth action,
restart, MQTT activation or mower command.

## 5. Postflight

Immediate and delayed observations, separated by more than three minutes,
proved:

- exact target commit, clean and valid;
- all module instances healthy;
- MQTT and position diagnostics still disabled;
- MQTT and WebSocket Core instances inactive;
- Authorization and MQTT credentials absent;
- REST still operational;
- instance, configuration, variable identity, Archive, command-evidence and
  subscription contracts unchanged;
- historical position accounting preserved; and
- automatic-closure fields present, inactive and without a pending cleanup
  phase.

No transient activation or delayed contract drift was observed.

## 6. Architecture Decisions

### AD-NAV-1293: Preserve bounded historical diagnostics while disabled

Disabling the transport removes credentials and activity but does not erase
bounded episode, reconnect or coordinate-free accounting evidence. Read-only
release probes must distinguish retained diagnostics from active transport
state.

### AD-NAV-1294: Verify the new closure contract without triggering it

Gate L1 proves that the target exposes automatic-closure diagnostics in an
inactive state. Deadline, second-episode and exhaustion behavior remain covered
offline until a separately authorized bounded receive-only pilot supplies
natural live evidence.

### AD-NAV-1295: Keep the live mutation singular

One supported Module Control update is sufficient. A probe correction or
read-only observation cannot justify another update, reload or ApplyChanges.

## 7. Mutation Counts

```text
initial retained probe failure: 1
accepted read-only preflights:   2
module updates:                  1
immediate postflights:           1
delayed postflights:             1
module reloads:                  0
explicit ApplyChanges:           0
OAuth actions:                   0
MQTT activations:                0
service restarts:                0
mower commands:                  0
```

## 8. Gate State

| Gate | Status |
|---|---|
| SAEF merge | PASS |
| standalone publication | PASS |
| metadata conformance | PASS BY BYTE EQUIVALENCE |
| disabled Symcon rollout | PASS |
| bounded automatic-closure pilot | CLOSED |

## 9. Recommendation

Retain the exact module commit with MQTT and position diagnostics disabled.
The next SAEF step should define Gate L2 readiness for one bounded receive-only
pilot. It must start from a fresh credential-free read-only preflight, renew
the user's temporary credential-persistence acceptance and keep activation,
observation and mandatory cleanup within one explicit live gate.
