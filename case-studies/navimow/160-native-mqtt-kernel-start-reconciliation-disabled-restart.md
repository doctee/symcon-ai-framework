# 160 Native MQTT Kernel Start Reconciliation Disabled Restart

**Case study:** Navimow native IP-Symcon module
**Status:** Gate D passed; kernel-start hook observed and reconciled exactly
once with MQTT disabled and credential-free
**Date:** 2026-07-28
**Scope:** Execute only disabled kernel-hook restart Gate D from step 156

## 1. Purpose

Step 159 verified the retained native transport as an inactive,
credential-free topology.

This step:

1. captured an immediate disabled pre-restart baseline;
2. observed exactly one external IP-Symcon service restart;
3. proved the new kernel epoch and ready runlevel;
4. verified delayed Account reconciliation;
5. confirmed unchanged MQTT counters and public contracts;
6. stopped before credential acceptance or MQTT activation.

## 2. Authorization

The user explicitly authorized:

```text
Ein beaufsichtigter Symcon-Neustart mit deaktiviertem MQTT zur Kernel-Hook-Prüfung ist freigegeben.
```

This authorized exactly one external service restart and bounded read-only
projections.

It did not authorize:

- enabling MQTT;
- retrieving or persisting credentials;
- connecting to the broker;
- a second service restart;
- Core configuration mutation;
- MQTT publish or mower commands.

## 3. Restart Execution Boundary

The configured SAEF deployment channel intentionally exposes no free
service-restart operation. Restarting the Windows service from its own Symcon
PHP process also remains prohibited.

The user therefore restarted the IP-Symcon service exactly once on the
Windows host and confirmed completion.

No mower action was required.

## 4. Pre-Restart Baseline

Immediately before restart:

```text
kernel start:                 captured privately
kernel runlevel:              ready
lifecycle:                    Disabled
MQTT status:                  inactive
WebSocket status:             inactive
MQTT feature:                 disabled
Authorization headers:        empty
MQTT username and password:   empty
connection attempts:          captured privately
Core-resume observations:     captured privately
```

The complete compatibility projection passed:

- module `main@aed0b434`, clean and valid;
- 14 variable identities and metadata;
- five Archive Control logging contracts;
- archive history queryable;
- command evidence captured;
- Account authentication connected;
- reauthentication not required;
- token usable;
- four canonical QoS-0 subscriptions.

## 5. Kernel-Start Observation

The first available post-restart projection proved:

```text
kernel start changed:             yes
kernel runlevel:                  ready
diagnostic kernel start matches:  yes
kernel-start observation:         present
kernel-start reconciliation:      present
observation-to-reconciliation:    15 seconds
minimum delay satisfied:          yes
lifecycle:                        Disabled
last transition reason:           disabled
next attempt:                     none
```

The implementation therefore received the supported kernel-start message,
scheduled rather than performed reconciliation in `MessageSink()`, and
executed the disabled-state reconciliation after the configured grace period.

## 6. Idempotency

A second read-only projection of the same kernel epoch returned identical:

- kernel start time;
- observation timestamp;
- reconciliation timestamp;
- lifecycle state;
- next-attempt state;
- connection counters;
- Core-resume counter.

Classification:

```text
duplicate reconciliation observed: no
same-epoch projection stable:      yes
```

## 7. Disabled Safety Contract

Before and after restart:

```text
MQTT feature:                 disabled
MQTT Core:                    inactive
WebSocket Core:               inactive
WebSocket Active:             false
Authorization headers:        empty
MQTT username and password:   empty
connection-attempt delta:     0
Core-resume observation delta: 0
```

No credential request, broker connection or Account-owned connection attempt
occurred.

## 8. Compatibility Verification

The post-restart compatibility projection passed and all four contract hashes
matched the immediate pre-restart baseline.

| Contract | Result |
|---|---|
| productive instance identities and connections | unchanged |
| variable identities and metadata | 14/14 unchanged |
| Archive Control logging | 5/5 unchanged |
| archive history | queryable |
| command evidence | unchanged |
| Receiver pairing | retained |
| canonical subscriptions | 4/4 QoS 0 |
| Account authentication | connected |
| reauthentication required | false |
| token | usable |

The user's configured mower-variable logging remains intact.

## 9. Architecture Closure

### AD-NAV-547: Prove the kernel hook without credentials first

**Decision:** The first live kernel-start test runs with MQTT disabled and all
Core credential fields empty.

**Reason:** This isolates message registration, timing and idempotency from
native transport persistence.

### AD-NAV-548: Require exact epoch correlation

**Decision:** The diagnostic kernel-start value must equal the new
`IPS_GetKernelStartTime()` value.

**Reason:** Timestamp presence alone would not prove reconciliation of the
current restart.

### AD-NAV-549: Treat unchanged counters as a safety assertion

**Decision:** Disabled restart acceptance requires zero deltas for Account
connection attempts and Core-resume observations.

**Reason:** The kernel hook may reconcile disabled state but must not initiate
or adopt a transport.

## 10. Side-Effect Accounting

| Operation | Count |
|---|---:|
| external Symcon service restarts | 1 |
| property mutations | 0 |
| `ApplyChanges()` | 0 |
| MQTT enable operations | 0 |
| credential requests | 0 |
| MQTT connection attempts | 0 |
| MQTT publish operations | 0 |
| mower actions | 0 |
| created or deleted objects | 0 |
| Archive Control mutations | 0 |

Every MCP result was checked separately for transport error, PHP execution
error and truncation.

## 11. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-kernel-start-reconciliation-disabled-restart/
    gate-d-evidence-closure.json
```

Reusable private read-only source:

```text
private/navimow-capture/
  native-mqtt-kernel-start-restart-readonly.php
```

No private installation identifier, credential, topic, endpoint, payload,
ObjectID or garden detail appears in this public report.

## 12. Gate Decision

| Gate | Decision |
|---|---|
| Gate A publication | PASS |
| Gate B disabled Symcon update | PASS |
| Gate C inactive topology staging | PASS |
| Gate D disabled kernel-hook restart | PASS |
| exact 15-second reconciliation | PASS |
| same-epoch idempotency | PASS |
| credential-free restart | PASS |
| Gate E credential-persistence acceptance | CLOSED |
| Gate F receive-only activation | CLOSED |
| MQTT state authority | PROHIBITED |
| MQTT publish | PROHIBITED |
| REST state authority | RETAINED |

The next step requires the exact bounded credential-persistence acceptance
from Gate E in step 156. Without that acceptance, work stops here with MQTT
disabled and credential-free.
