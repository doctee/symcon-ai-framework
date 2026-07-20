# 26 Client Fileset Activation and Runtime Verification Report

**Case study:** IP-Symcon MQTT Discovery Exporter
**Gate:** MQTT-client fileset replacement and clean-process verification
**Result:** PASS
**Date:** 2026-07-16
**Live-system impact:** One controlled IP-Symcon service restart and one bootstrap reference replacement

## 1. Scope

The previously verified client-transport fileset replaced the active
server-only exporter fileset. The transition used an external PowerShell
process, a byte-exact bootstrap backup, a versioned target directory and the
state-based IP-Symcon restart coordinator.

No exporter reconcile, retained MQTT publication, Home Assistant discovery
entity or physical device command was part of this activation gate.

## 2. Activation Result

The immediate preflight confirmed:

- the expected active bootstrap identity;
- the exact old exporter core and runtime identities;
- the complete candidate file map and every source hash;
- the ready IP-Symcon service state and runlevel.

The candidate was copied into a new versioned directory and reverified before
selection. The bootstrap reference was replaced byte-for-byte without
re-encoding the surrounding private file. A recoverable bootstrap backup was
retained outside the active scripts directory.

The restart coordinator returned exit code `0` with:

- phase `activation_restart`;
- outcome `activated`;
- ready runlevel `10103`;
- an advanced kernel start identity;
- no rollback attempt.

## 3. Independent Runtime Verification

A fresh IP-Symcon script context independently confirmed:

| Check | Result |
| --- | --- |
| Exporter core source | New versioned fileset |
| Exporter runtime source | New versioned fileset |
| Core SHA-256 | `910aefdaf873965b461cb1dfc36775c92e93ce0463a5e47319feb529dafa71fc` |
| Runtime SHA-256 | `2b3bd35c5195f38e39e8b52201ebd04906000753378f6a8884047978d27c9446` |
| Loaded SAEF functions | 36 |
| `transport = client` accepted | Yes |
| Generic `gatewayID` preserved | Yes |
| Test configuration entities | 0 |
| Existing owner hierarchy | Unchanged |
| Dedicated MQTT Client | Active |
| Dedicated Client Socket | Active |

The validation normalized an empty client-transport configuration through the
new core. It did not call reconcile preparation or execution.

## 4. Cleanup and Recovery State

The temporary runtime-verification script was deleted after read-back. The old
fileset and the exact pre-transition bootstrap backup remain available until
the supervised pilot passes its rollback-sensitive checkpoints.

## 5. Gate Decision

The MQTT-client fileset activation and runtime verification are **PASS**.

The next gate may validate the private single-light configuration through
reconcile preparation. That operation may create owned MQTT adapters and
events but still does not publish retained discovery messages. Publication and
each physical command remain separate checkpoints.
