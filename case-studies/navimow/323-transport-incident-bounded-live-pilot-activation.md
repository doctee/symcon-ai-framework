# 323 Transport Incident Bounded Live Pilot Activation

**Case study:** Navimow native IP-Symcon module

**Status:** One bounded restart-free receive-only pilot activated and verified

**Date:** 2026-08-17

## 1. Result

Gate L2 passed for exact standalone commit
`405fd24b5450c909c35e038a12bd69378d33deb6`.

After a fresh disabled read-only preflight and explicit renewed authorization,
exactly one Account `ApplyChanges` activated MQTT shadow and position
diagnostics. The synchronous result correctly entered `ReconnectScheduled`.
Without retry or further mutation, delayed read-only verification then reached
`ShadowActive`.

Every accepted MCP execution separately satisfied:

```text
transportError: null
executionError: null
truncated:      false
```

## 2. Authorization Boundary

The user confirmed that no manual OAuth, login or token-refresh action had
occurred since the fresh preflight. The user also accepted temporary storage
of Authorization and MQTT credentials in the owned IP-Symcon Core instances
for exactly one bounded receive-only activation.

The gate authorized no restart, module update, reload, OAuth action, MQTT
publish, mower command or activation retry. Automatic closure and mandatory
cleanup remain required independently of the pilot outcome.

## 3. Immediate Preflight

The read-only preflight passed immediately before mutation:

```text
installed commit:       405fd24b5450c909c35e038a12bd69378d33deb6
repository branch:      main
repository clean/valid: yes / yes
token horizon:          1946 seconds
required horizon:       1200 seconds
MQTT / WebSocket:       104 / 104
Authorization present: no
MQTT credentials:       absent
REST operational:       yes
incident sequence:      0
session incidents:      0
open incident:          none
```

The identity, Archive, command-evidence, topology and subscription hashes
matched the disabled rollout. All 14 variables and five Archive logging
contracts remained intact.

## 4. Single Activation

The activation probe recomputed every precondition immediately before setting
the two opt-in properties and calling `IPS_ApplyChanges()` once.

```text
activation attempts: 1
ApplyChanges calls:  1
activation retries:  0
cleanup attempts:    0
```

All activation postconditions passed. The returned lifecycle state was
`ReconnectScheduled` with reason `restart-scheduled`. This is an accepted
asynchronous transition: it did not justify a second activation call.

## 5. Two-Phase Read-Only Evidence

The first observation found the new pilot session active while the transport
was still `Connecting`. Both Core instances were already active at status
`102`, the closure state was `Active`, and the incident count remained zero.
The full probe was not classified as passed because its stable-state contract
requires `ShadowActive`.

The delayed observation then passed completely:

```text
lifecycle:                    ShadowActive
MQTT / WebSocket:             102 / 102
pilot session:                6
closure state:                Active
hard stop:                    2026-08-20 10:57:37 CEST
incident sequence:            0
session incident count:       0
open incident:                none
last incident:                none
connection attempts delta:    +1
connection successes delta:   +1
new MQTT messages:            0
new position samples:         0
```

No immediate message ingress is required for activation success. Message,
position and incident behavior belong to the bounded observation window.

## 6. Incident Policy Under Test

```text
sustained-health reset:          900 seconds
maximum incident duration:       1800 seconds
maximum episodes per incident:   3
recoverable incidents per pilot: 1
absolute pilot duration:         259200 seconds
```

The first qualifying transport incident may recover within these bounds. A
second independent incident, exhaustion, terminal authentication or
configuration failure, or the absolute deadline must close the pilot and
clear transport credentials.

## 7. Preserved Architecture

```text
public device-state authority: REST
MQTT direction:                receive-only
MQTT publish path:             absent
MQTT mower-command path:       absent
public variables:              unchanged
Archive logging identities:    unchanged
```

MQTT and local-map position remain diagnostic evidence. They do not replace
REST state or create a device-control channel.

## 8. Private Evidence

The object-ID-free operation summary and private probes are retained under:

```text
private/navimow-capture/transport-incident-l2/
private/navimow-capture/output/transport-incident-l2/
```

They are excluded from publication. Public documentation contains no tokens,
credentials, private topics, payloads, coordinates, device identities,
ObjectIDs or host metadata.

## 9. Architecture Decisions

### AD-NAV-1328: Treat reconnect scheduling as asynchronous acceptance

`ReconnectScheduled` after the single ApplyChanges is neither success at the
stable-state gate nor an activation failure. Only later read-only evidence may
establish `ShadowActive`; no second mutation is permitted.

### AD-NAV-1329: Start incident accounting at the new session boundary

Session 6 begins with incident sequence and session count zero. Historical
episode, closure and position accounting remains retained diagnostic context
and is not misclassified as a new incident.

### AD-NAV-1330: Keep activation and message evidence separate

Core readiness and credential placement prove transport activation. Actual
MQTT and position ingress must be evaluated over time and must not be inferred
from status `102` alone.

## 10. Gate State And Next Step

| Gate | Status |
|---|---|
| SAEF merge | PASS |
| standalone publication | PASS |
| metadata conformance | PASS BY BYTE EQUIVALENCE |
| disabled Symcon rollout | PASS |
| bounded incident-policy activation | PASS |
| bounded live observation | ACTIVE |
| final cleanup and closure proof | PENDING |

The next SAEF step is bounded read-only observation of message ingress,
position evidence, healthy-reset behavior and any grouped transport incident.
The pilot must close automatically no later than the hard stop. Its final
classification requires immediate and delayed proof that MQTT and position
diagnostics are disabled and all Core credentials are absent.
