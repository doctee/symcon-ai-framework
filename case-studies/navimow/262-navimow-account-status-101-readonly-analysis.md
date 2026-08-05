# 262 Navimow Account Status 101 Read-Only Analysis

**Case study:** Navimow native IP-Symcon module

**Status:** Causal boundary identified; no live mutation performed

**Date:** 2026-08-04

**Scope:** Explain the persistent Account status `101` that stopped step 261
without applying configuration, updating the module or changing MQTT

## 1. Result

The Account is not operationally stuck in creation. Its Core status is a stale
lifecycle residue whose timestamp matches the step-248 cleanup
`IPS_ApplyChanges()` call exactly.

```text
Account InstanceChanged:       2026-08-03 18:07:53 UTC
step-248 cleanup ApplyChanges: 2026-08-03 18:07:53 UTC
timestamps equal:             yes
Account status since then:    101
current kernel runlevel:      10103
MQTT lifecycle:              Disabled
MQTT timers:                 disabled
REST polling:                operational
token refresh:               operational
diagnostic errors:           none
```

The exact internal Core reason why the completed ApplyChanges call did not
promote the status back to `102` cannot be observed through the available
read-only APIs. The causal boundary is nevertheless high-confidence: the
status timestamp and cleanup call are identical, and no other Account
configuration application occurred in between.

## 2. Official Status Semantics

Official Symcon documentation defines:

```text
101: Instanz wird erstellt
102: Instanz ist aktiv
```

`IPS_GetInstance()` also exposes `InstanceChanged` as the timestamp at which
configuration was last applied. See the official
[IPS_GetInstance documentation](https://www.symcon.de/de/service/dokumentation/befehlsreferenz/instanzenverwaltung/ips-getinstance/)
and [SetStatus documentation](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/module/setstatus/).

Status `101` is therefore not reclassified as healthy. The analysis only proves
that the Account's actual runtime work continues despite the stale status.

## 3. Exact Timeline

| Event | UTC |
|---|---|
| final active-pilot connection attempt | 2026-08-03 18:01:41 |
| cleanup property application and `IPS_ApplyChanges()` | 2026-08-03 18:07:53 |
| Account `InstanceChanged` | 2026-08-03 18:07:53 |
| immediate cleanup verification | 2026-08-03 18:08:11 |
| delayed cleanup verification | 2026-08-03 18:11:48 |
| current kernel start | 2026-08-04 08:05:35 |
| current kernel reconciliation complete | 2026-08-04 08:07:21 |

The status survived a later service restart. Thus it is persistent Core
instance state, not an unfinished current kernel startup.

## 4. Step-248 Evidence Gap

Step 248 correctly proved cleanup of:

- MQTT feature state;
- WebSocket activity and Authorization;
- MQTT username and password;
- lifecycle state;
- REST operation;
- variables, archive contracts and transport topology.

Its cleanup result and immediate and delayed snapshots did not include the
Account's `InstanceStatus`. The cleanup therefore passed without observing
that the Account remained at `101` after `IPS_ApplyChanges()`.

This is a monitoring gap, not evidence that step 248 reported a known false
status.

## 5. Runtime Health

### Kernel and lifecycle

```text
kernel runlevel:             10103
kernel epoch reconciled:     yes
MQTT feature:                disabled
lifecycle:                   Disabled
scheduled MQTT work:         none
reconnect attempt:           0
diagnostic error entries:    0
```

### Account-owned timers

| Timer | Interval | Last-run evidence | State |
|---|---:|---|---|
| `PollStatus` | 300000 ms | current kernel | scheduled, not running |
| `RefreshToken` | 3300000 ms | current kernel | scheduled, not running |
| `MqttReconcile` | 0 | none | disabled |
| `MqttLifecycle` | 0 | kernel reconciliation | disabled |
| `MqttPilotCheckpoint` | 0 | none | disabled |

All five expected Account timers exist. No timer was stuck in `Running`.

### REST and authentication

At the main introspection point:

```text
connection state:          connected
reauthentication required: false
REST success age:          7 seconds
device status age:         7 seconds
token usable:              yes
```

Polling and token refresh both ran after the status became `101` and after the
subsequent kernel restart.

## 6. Source Analysis

The installed Account source:

- calls `parent::ApplyChanges()` first;
- never calls `SetStatus(101)`;
- initializes variables, polling, token refresh and MQTT cleanup in
  `ApplyChanges()`;
- performs nested Core `IPS_ApplyChanges()` calls while removing MQTT
  credentials from the MQTT and WebSocket instances;
- catches transport-cleanup exceptions into bounded MQTT diagnostics;
- currently exposes no MQTT diagnostic error.

The published `a8481c97` delta does not change `ApplyChanges()`, status handling,
transport cleanup, polling or authentication. It only adds the bounded pilot
summary and cumulative sequence projection.

The new target therefore neither caused nor fixes status `101`.

## 7. Causal Assessment

### Proven

- `101` means creation state according to Symcon.
- Its `InstanceChanged` timestamp equals the cleanup ApplyChanges call.
- The cleanup verifier omitted Account status.
- The status persisted through a later complete kernel restart.
- No creation, MQTT lifecycle, polling, refresh or REST work is currently
  blocked.
- No module source path explicitly sets `101`.

### High-confidence inference

The step-248 cleanup ApplyChanges lifecycle left the Account status at `101`
after successfully disabling and sanitizing the native transport.

### Not proven read-only

The available public APIs do not expose the internal PHP-SDK/Core status
finalization path. This analysis cannot distinguish whether the residue arose
from the outer Account ApplyChanges, its nested Core ApplyChanges calls or a
Core status-finalization edge case.

## 8. Safety Decision

Step 261 remains correctly stopped. Operational REST health does not authorize
silently weakening the established `102` module-status gate.

```text
installed commit:       79686e52
target commit:          a8481c97, not installed
MC_UpdateModule():      0
MC_ReloadModule():      0
IPS_ApplyChanges():     0 in this analysis
MQTT activation:        0
mower commands:         0
```

## 9. Architecture Decisions

### AD-NAV-1035: Bind stale status to the exact configuration application

Equal Core and evidence timestamps establish the strongest available causal
boundary without mutating the installation.

### AD-NAV-1036: Treat step-248 status omission as an evidence gap

The earlier cleanup remains valid for credentials and transport. It did not
validate Account lifecycle status and cannot be used as evidence of `102`.

### AD-NAV-1037: Keep semantic health separate from Core status

Timers and REST prove continued operation; they do not redefine official
status semantics.

### AD-NAV-1038: Do not expect the episode-summary target to repair status

The published delta does not touch ApplyChanges or status handling. Updating
as an implicit repair would remain uncontrolled.

## 10. Next Step

Proceed with:

```text
263-navimow-account-status-recovery-and-update-gate-design.md
```

That step should compare two controlled paths without executing either:

1. a single no-configuration-change Account `IPS_ApplyChanges()` followed by
   immediate and delayed disabled-state verification; or
2. a narrowly reviewed source correction that explicitly establishes a valid
   successful Account status before publication and update.

The design must account for the nested Core ApplyChanges calls in MQTT cleanup,
preserve all 14 variables and five Archive Control contracts and keep REST
authoritative. No recovery mutation is authorized by this analysis.
