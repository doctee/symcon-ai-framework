# 28 Device-Tree Fileset Activation and Runtime Verification Report

**Case study:** IP-Symcon MQTT Discovery Exporter
**Gate:** Device-oriented object-tree fileset replacement and clean-process verification
**Result:** PASS
**Date:** 2026-07-16
**Live-system impact:** One controlled IP-Symcon service restart and one bootstrap reference replacement

## 1. Scope

The previously verified device-tree fileset replaced the active MQTT-client
fileset. The transition used the external state-based restart coordinator, a
byte-exact bootstrap backup and a separately staged versioned fileset.

This gate only selected and loaded the new implementation. It did not call
`prepareReconcile`, create the Devices category, publish retained MQTT
messages, create a Home Assistant discovery entity or send a physical device
command.

## 2. Activation Result

The immediate preflight passed before the transition. The restart coordinator
then returned exit code `0` and recorded:

- phase `activation_restart`;
- outcome `activated`;
- ready runlevel `10103`;
- an advanced kernel start identity;
- no rollback attempt.

The result proves that the service completed a clean restart after selecting
the candidate. It does not by itself prove which PHP files the new process
loaded, so runtime identity was verified separately.

## 3. Independent Runtime Verification

An isolated IP-Symcon script context inspected the loaded namespace and source
files without invoking exporter behavior.

| Check | Result |
| --- | --- |
| Kernel runlevel | `10103` |
| Kernel start identity | Matches the activation result |
| Loaded SAEF functions | 36 |
| Loaded exporter classes | 2 |
| Exporter core class | Loaded from the new versioned fileset |
| Exporter runtime class | Loaded from the new versioned fileset |
| Core SHA-256 | `910aefdaf873965b461cb1dfc36775c92e93ce0463a5e47319feb529dafa71fc` |
| Runtime SHA-256 | `7bcee6a8b6e45d3e07b2c62e46cecd1211be81371294bc8d2a4920aa2ee933d7` |
| Dedicated MQTT Client | Active |
| Dedicated Client Socket | Active |
| Existing owner hierarchy | Unchanged |

The exact runtime source hash is the device-tree candidate identity. The core
hash remains unchanged because this evolution affects object ownership and
lifecycle behavior in the runtime adapter, not the pure MQTT protocol core.

## 4. Verification Cleanup

The MCP execution endpoint reports only whether ad-hoc script execution was
accepted. A temporary script and result variables were therefore used for
read-back. They were deleted leaf-first after the evidence was captured, and
independent object lookups confirmed that none of the temporary objects
remained.

The check did not initialize or modify the exporter Registry, diagnostics,
events, adapters or retained MQTT state.

## 5. Gate Decision

The device-tree fileset activation and clean-process runtime verification are
**PASS**.

The next gate may run `prepareReconcile` for the already approved single-light
pilot configuration. That operation is intentionally separate because it may
create the Devices, device, Commands and Publishers categories as well as
owned MQTT adapters and hidden events. Publication and physical command tests
remain later checkpoints.
