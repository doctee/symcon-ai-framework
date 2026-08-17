# 314 Automatic Pilot Closure L2 Readiness Abort

**Case study:** Navimow native IP-Symcon module

**Status:** Retained fail-closed preflight; its 2400-second classification was
superseded by the existing restart-free 1200-second decision

**Date:** 2026-08-13

## 1. Result

The user accepted temporary Core credential persistence and authorized exactly
one activation attempt after a fresh passing preflight. The fresh bounded
read-only preflight stopped the gate before invoking the activation candidate:

```text
installed commit:              888325d8649160c5bae473f4f8a052cf86e703b6
required token horizon:        2400 seconds
observed token horizon:        1778 seconds
activation candidate invoked:  no
Account ApplyChanges calls:    0
credential requests:           0
```

Every MCP result separately satisfied:

```text
transportError: null
executionError: null
truncated:      false
```

## 2. Preserved Safety State

The same observation proved:

- exact clean and valid standalone commit on `main`;
- ready kernel and healthy Account, Configurator, Device and Receiver;
- MQTT and WebSocket Core instances inactive;
- receive-only MQTT and position diagnostics disabled;
- Authorization header and MQTT username and password absent;
- REST operational and no reauthentication required;
- exact four-topic allowlist without wildcards;
- 14 public variable identities unchanged;
- all five configured Archive logging contracts retained and queryable;
- automatic pilot closure fields present with no active or pending phase; and
- historical coordinate-free position and recovery diagnostics retained.

## 3. Decision

The probe correctly stopped before mutation according to its configured
threshold, but that threshold did not match this gate's already accepted
restart-free operating boundary. Step 309 binds `1200` seconds to exactly one
bounded activation and baseline path without a service restart. The
`2400`-second reserve applies to restart and Core-resume paths.

The observation is retained as a fail-closed probe result, not as evidence that
the restart-free L2 gate was genuinely unready. The activation candidate and
its one-attempt allowance remained unused.

Because the activation candidate was not invoked, the authorization's one
activation attempt was not consumed. No cleanup mutation is required because
no credentials were requested and no feature was enabled.

## 4. Architecture Decisions

### AD-NAV-1296: Token readiness belongs before credential acquisition

A valid module and healthy REST session do not justify credential persistence
when the access-token horizon is below the activation minimum. The preflight
must stop before requesting MQTT credentials or changing Account properties.

### AD-NAV-1297: Do not count a fail-closed readiness abort as activation

The one-attempt bound applies when the credential-bearing mutation candidate is
invoked. A read-only prerequisite failure preserves that attempt for a later,
freshly authorized retry.

### AD-NAV-1298: Do not perform redundant cleanup after zero mutation

The observed disabled and credential-free state is already the required
post-abort state. Calling ApplyChanges would add risk without changing the
contract.

## 5. Mutation Counts

```text
read-only preflights:       1
activation attempts:        0
Account ApplyChanges:       0
cleanup operations:         0
OAuth actions:              0
MQTT credential requests:   0
MQTT activations:           0
service restarts:           0
mower commands:             0
```

## 6. Gate State

| Gate | Status |
|---|---|
| exact installed commit | PASS |
| disabled credential-free baseline | PASS |
| REST readiness | PASS |
| configured token horizon at least 2400 seconds | FAIL, 1778 seconds |
| applicable restart-free horizon at least 1200 seconds | PASS |
| L2 activation | NOT ATTEMPTED |
| cleanup | NOT REQUIRED |

## 7. Next Step

Correct the private activation candidate to the established 1200-second
restart-free threshold and perform a fresh read-only preflight. Activation may
proceed only if that fresh observation still satisfies the threshold and every
other disabled, credential-free safety condition. A service restart remains
prohibited.
