# 191 Native MQTT Core Resume Health Observation Token Horizon Retry Plan

**Case study:** Navimow native IP-Symcon module
**Status:** Retry gates and conservative token horizons frozen; no live
operation authorized
**Date:** 2026-07-29
**Scope:** Plan one clean retry after the controlled Gate-E stop in step 190

## 1. Purpose

Step 190 proved healthy receive-only MQTT activation but stopped before the
restart because the second active baseline no longer met the frozen
1200-second token horizon.

This step:

1. explains why the former threshold was operationally insufficient;
2. defines passive scheduled-refresh evidence;
3. freezes stronger activation and restart-arm thresholds;
4. separates staging, acceptance, activation, restart and cleanup again;
5. permits no live mutation by this plan alone.

## 2. Current Safe Baseline

Step 190 ended with:

```text
installed module:           main@45c7bd50
MQTT feature:               disabled
lifecycle:                  Disabled
WebSocket:                  inactive
Authorization headers:      empty
MQTT username and password: empty
restart performed:          no
cleanup:                    PASS
```

The one activation authorization and the persistence acceptance from steps
189/190 are consumed. They cannot authorize a retry.

## 3. Finding

The former precondition required:

```text
token remaining before activation >= 1200 seconds
```

The real run started with 1358 seconds. Healthy activation, the complete Core
health interval and two baselines consumed enough wall time that the second
baseline retained only 1132 seconds.

The defect is in the test-horizon contract, not in:

- OAuth refresh behavior;
- MQTT activation;
- native Core connectivity;
- receive-only ingress;
- cleanup.

The threshold mixed two different obligations:

1. enough time before activation to complete all pre-restart evidence;
2. enough time at restart arm to survive startup, observation and cleanup.

Those obligations must have separate thresholds.

## 4. Conservative Timeline Budget

The retry uses this upper-bound budget:

| Segment | Budget |
|---|---:|
| delayed lifecycle connection and Core health | 90 s |
| two active baselines | 120 s |
| operator handoff before restart | 180 s |
| service startup and console unavailability | 300 s |
| post-ready Core observation | 90 s |
| first reachable decisive projection | 300 s |
| immediate and delayed cleanup | 180 s |
| scheduling and transport reserve | 300 s |
| **bounded total** | **1560 s** |

Frozen thresholds:

```text
before activation:  >= 2400 seconds
at restart arm:     >= 1800 seconds
```

The 2400-second activation threshold leaves 840 seconds beyond the bounded
timeline. The separate 1800-second restart-arm threshold prevents pre-restart
work or operator delay from silently consuming that reserve.

Neither threshold is a claim about token lifetime. They are readiness gates.

## 5. Gate A: Passive Token Refresh Observation

Required separately:

```text
Passive Token-Refresh-Beobachtung für den Core-Resume-Health-Observation-Retry freigegeben.
```

This gate is read-only.

### Observation procedure

1. Verify MQTT disabled and credential-free.
2. Capture the public `TokenExpiresAt` value and its variable change timestamp.
3. Capture `ConnectionState`, `ReauthRequired`, `LastRestSuccess` and
   `RestErrorCount`.
4. Poll at most once per 60 seconds.
5. Bound the observation to 25 minutes.
6. Stop as soon as `TokenExpiresAt` moves forward.
7. Require the new remaining horizon to be at least 3000 seconds.
8. Recheck authentication and REST continuity.

The observation must not call:

- `RefreshAuthentication()`;
- OAuth authorization;
- Account reset;
- `ApplyChanges()`;
- manual status refresh;
- any MQTT action;
- any mower command.

### Pass contract

```text
TokenExpiresAt moved forward
new token remaining >= 3000 seconds
ConnectionState = Connected
ReauthRequired = false
LastRestSuccess advances normally
RestErrorCount does not increase
MQTT remains disabled and credential-free
```

After observation, the user confirms that no manual OAuth or token-refresh
action was performed during the window.

### Stop conditions

Stop without mutation when:

- no expiry movement occurs within 25 minutes;
- authentication disconnects;
- reauthentication becomes required;
- REST errors increase;
- MQTT becomes active unexpectedly;
- the refreshed horizon is below 3000 seconds.

## 6. Gate B: Fresh Inactive Staging

Required separately after Gate A passes:

```text
Erneutes inaktives Staging für den Core-Resume-Health-Observation-Retry freigegeben.
```

Run the established inactive projection at least twice over more than one
60-second lifecycle interval.

Require:

- installed `main@45c7bd50`, clean and valid;
- exact retained Receiver/MQTT/WebSocket topology;
- exact symmetric Account/Receiver pairing;
- four canonical QoS-0 subscriptions;
- stable ownership, topology and subscription hashes;
- MQTT disabled;
- WebSocket inactive;
- Authorization headers empty;
- MQTT username and password empty;
- stopped lifecycle and stable connection counters;
- complete variable, archive and command compatibility.

This gate creates, deletes, reparents and configures no Core object.

## 7. Gate C: Renewed Persistence and Recovery Acceptance

Required after Gates A and B:

```text
Ich akzeptiere für einen weiteren einmaligen beaufsichtigten Neustarttest,
dass Authorization- und MQTT-Zugangsdaten während des aktivierten nativen
Transports in den eigenen IP-Symcon-Core-Instanzen gespeichert sind und der
Core sie beim Neustart vor der Account-Reconciliation wiederverwenden kann.
Nach dem Test wird MQTT unabhängig vom Ergebnis deaktiviert und bereinigt.

Falls der Core bis +90 Sekunden nach IPS_KERNELSTARTED nicht gesund ist,
darf der vorhandene receive-only Lifecycle vor Wiedererreichbarkeit der
Konsole den bereits implementierten begrenzten Recovery-Pfad beginnen.
Es werden keine MQTT-Nachrichten veröffentlicht und keine Mäherbefehle
gesendet.
```

This acceptance permits neither activation nor restart by itself.

## 8. Gate D: Retry Activation

Required separately:

```text
Temporäre Retry-Aktivierung des receive-only MQTT-Transports mit 2400-Sekunden-Tokenhorizont freigegeben.
```

### Immediate preconditions

Require:

```text
token remaining >= 2400 seconds
MQTT disabled and credential-free
current kernel epoch reconciled
Account connected
ReauthRequired false
complete compatibility PASS
```

Execute exactly:

```text
EnableMqttShadow -> true: 1
Account ApplyChanges:      1
explicit MQTT Connect:     0
```

Capture two equal active projections at least 65 seconds apart.

Both must show:

```text
ShadowActive / healthy
MQTT / WebSocket status 102 / 102
WebSocket Active true
connection attempts/successes delta +1/+1
connection failures delta 0
kernelCoreObservationCount 0
no pending reconnect
stable ownership, topology and Core configuration hashes
```

Record token remaining as an integer in each private projection. Do not retain
the former ambiguous Boolean-only contract.

Gate D passes only when the later projection still has:

```text
token remaining >= 2100 seconds
```

This reserves 300 seconds for operator handoff before the restart-arm gate.

Any stop condition triggers immediate normal Account cleanup. No activation
retry is allowed under the same authorization.

## 9. Gate E: Restart Arm

Immediately before asking for restart authorization, capture:

```text
token remaining >= 1800 seconds
restartArmedAtUtc
old kernel epoch
lastReceivedAt
all active baseline counters and hashes
ShadowActive / healthy
Core status 102 / 102
```

If the token horizon is below 1800 seconds:

- do not request or perform the restart;
- disable MQTT immediately;
- close cleanup evidence;
- return to passive refresh observation.

## 10. Gate F: One External Restart

Required separately:

```text
Ein einmaliger beaufsichtigter Symcon-Neustart für den Core-Resume-Health-Observation-Retry ist freigegeben.
```

The user performs exactly one external service restart.

Prohibited:

- restart through Symcon PHP;
- restart retry;
- explicit MQTT Connect;
- MQTT publish;
- mower command;
- fallback mutation while MCP is unreachable.

Do not classify MCP unreachability as failure. The service and console may
need up to five minutes before external inspection is available.

## 11. Restart Pass Contract

The first reachable projection and bounded observation timeline must prove:

```text
new kernel epoch
kernelStartObservedAt >= new kernel epoch
kernelCoreObservationDeadlineAt =
    kernelStartObservedAt + 90
at least one bounded observation
final classification healthy
state ShadowActive
reason core-resumed
Core-resume observations +1
Account connection attempts delta 0
Account connection successes/failures delta 0/0
last connection trigger and timestamps unchanged
ownership and topology hashes unchanged
```

Healthy adoption may occur at `+15`, `+30`, `+60` or `+90 s`.

If bounded recovery begins before the console returns, record all automatic
counter deltas and preserved observations without initiating any retry.

## 12. Gate G: Mandatory Cleanup

Cleanup is mandatory after every Gate-F outcome:

```text
EnableMqttShadow -> false: 1
Account ApplyChanges:      1
explicit Disconnect:       0
```

Verify immediately and after at least 120 seconds:

- MQTT disabled;
- lifecycle `Disabled`;
- no pending attempt;
- WebSocket inactive;
- Authorization headers empty;
- MQTT username and password empty;
- no later connection attempt;
- REST authority retained;
- all variable and Archive Control contracts unchanged.

Use direct Core cleanup only if normal Account cleanup fails, and record that
emergency mutation separately.

## 13. Evidence Contract

Private machine-readable evidence must record:

- every authorization;
- all token horizons as remaining seconds and capture timestamps;
- passive expiry movement;
- active baseline timestamps;
- restart-arm timestamp;
- old and new kernel epochs;
- Axis-A service-unavailability duration;
- complete bounded Core observation timeline;
- every Account and Receiver counter delta;
- whether automatic recovery started;
- every cleanup mutation;
- separate MCP transport, PHP execution and truncation status.

Public reports contain no token value, credential, private topic, endpoint,
payload, device identity, ObjectID, hostname, IP address or garden detail.

## 14. Architecture Decisions

### AD-NAV-675: Separate activation and restart token gates

**Decision:** Require 2400 seconds before activation and 1800 seconds at
restart arm.

**Reason:** Pre-restart evidence and operator delay must not consume the
startup and cleanup reserve unnoticed.

### AD-NAV-676: Observe passive refresh before retry

**Decision:** Wait for normal scheduled token refresh instead of invoking a
manual refresh.

**Reason:** The retry should exercise the normal authenticated operating state
and must not add an unplanned authentication mutation.

### AD-NAV-677: Use remaining seconds instead of only a Boolean

**Decision:** Record bounded integer horizons privately at every gate.

**Reason:** A Boolean threshold hides how quickly available margin is being
consumed.

### AD-NAV-678: Consume every credential-bearing activation

**Decision:** Every activation consumes its authorization and associated
persistence acceptance, even when restart is not reached.

**Reason:** The accepted security-sensitive state exists as soon as
credentials are written to the owned Core instances.

### AD-NAV-679: Preserve external startup patience

**Decision:** Allow up to five minutes of MCP unavailability without failure
classification or fallback action.

**Reason:** The observed service startup duration is independent from the
post-`IPS_KERNELSTARTED` Core-health axis.

## 15. Gate Matrix

| Gate | Mutation | Current decision |
|---|---|---|
| plan | none | PASS |
| A passive token refresh | none | CLOSED |
| B inactive staging | none | CLOSED |
| C renewed acceptance | none | NOT GIVEN |
| D retry activation | Account property plus `ApplyChanges()` | CLOSED |
| E restart arm | none | CLOSED |
| F external restart | one external service operation | CLOSED |
| G mandatory cleanup | Account property plus `ApplyChanges()` | ARMED ONLY AFTER ACTIVATION |

REST remains authoritative. MQTT remains receive-only. MQTT publish and mower
commands remain prohibited.

## 16. Recommended Next Step

After separate authorization, execute only Gate A:

```text
Passive Token-Refresh-Beobachtung für den Core-Resume-Health-Observation-Retry freigegeben.
```

Gate A performs no authentication action and leaves MQTT disabled.
