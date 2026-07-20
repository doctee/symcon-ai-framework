# 30 ParentID Fix Activation and Live Repeatability Report

**Case study:** IP-Symcon MQTT Discovery Exporter
**Gate:** Corrected fileset activation and repeated live preparation
**Result:** PASS
**Date:** 2026-07-16
**Live-system impact:** One controlled IP-Symcon service restart; no MQTT publication or device command

## 1. Scope

The corrected fileset replaced the device-tree runtime that used the invalid
`ObjectParentID` key. The external coordinator performed the bootstrap switch
and clean IP-Symcon restart. After exact runtime verification, the existing
single-device pilot ran `prepareReconcile` again.

## 2. Activation Result

The activation transaction returned exit code `0` with:

- phase `activation_restart`;
- outcome `activated`;
- runlevel `10103`;
- an advanced kernel start identity;
- no rollback attempt.

Independent runtime inspection confirmed:

| Check | Result |
| --- | --- |
| Loaded SAEF functions | 36 |
| Loaded exporter classes | 2 |
| Core SHA-256 | `910aefdaf873965b461cb1dfc36775c92e93ce0463a5e47319feb529dafa71fc` |
| Runtime SHA-256 | `a7bb5b448c04832f600abadd3a10e6250e67bab566bdb698ad29fd6900cb7167` |
| Runtime source | Corrected versioned fileset |
| Dedicated MQTT Client | Active |
| Dedicated Client Socket | Active |

The temporary runtime result object was deleted after read-back.

## 3. Repeated Preparation Result

The corrected preparation reused the complete existing resource tree:

```text
Exporter owner
|-- Diagnostics
|-- Devices
|   `-- Pilot device
|       |-- Commands
|       `-- Publishers
`-- hidden command and state events
```

Read-back proved:

- the Devices, device, Commands and Publishers category IDs were unchanged;
- all three command adapter and Value variable IDs were unchanged;
- all command and state event IDs were unchanged;
- no additional owner children or device resources were created;
- Registry ownership metadata remained structurally consistent;
- the previous failure counter and failure timestamp did not advance;
- the historical ParentID failure entry remained unchanged as evidence.

This closes the live existing-object branch that the offline fake previously
modelled incorrectly.

## 4. Publication Boundary

The Publishers category is still empty. Registry fields continue to report:

- `discoveryPublished = false`;
- `runtimePublished = false`;
- no committed discovery or runtime hash;
- no registered publisher adapters;
- no cleanup tombstones.

No Home Assistant discovery configuration, retained runtime state or physical
device command was sent during this gate.

## 5. Gate Decision

The ParentID correction, clean activation and repeated live preparation are
**PASS**.

The next independent gate may execute the prepared reconcile plan without
cleanup. That action will create publisher adapters and publish retained
discovery and runtime messages, so it requires separate explicit approval.
Physical command testing remains a later gate.
