# 22 Fileset Activation and Runtime Verification Report

**Case study:** IP-Symcon MQTT Discovery Exporter
**G6 sub-gate:** Clean-process fileset activation and runtime namespace verification
**Result:** PASS
**Date:** 2026-07-15
**Live-system impact:** One supervised IP-Symcon service restart and bootstrap selection change

## 1. Scope

The previously staged exporter fileset was selected through the private
bootstrap and activated with the reviewed external Windows restart
coordinator. The transaction was explicitly authorized only for the bootstrap
transition and supervised service restart.

No exporter owner, diagnostics object, MQTT publication, Home Assistant entity
or device command was created or executed by this sub-gate.

## 2. Activation Evidence

The final non-activating preflight passed immediately before the transition.
The active bootstrap was then replaced atomically while retaining both the
reviewed rollback source and an additional pre-transition backup.

The external coordinator returned exit code `0` and recorded:

| Check | Result |
| --- | --- |
| Phase/outcome | `activation_restart` / `activated` |
| Kernel runlevel | `10103` |
| Kernel start identity | Advanced from the preflight value |
| Active bootstrap | Matches the private approved candidate |
| Rollback attempted | No |

The PowerShell process remained external to the IP-Symcon service throughout
the transaction, so it could observe the complete stop, start and readiness
sequence.

## 3. Runtime Namespace Evidence

The clean process was inspected through an isolated, read-only script context.
The result was:

| Check | Result |
| --- | --- |
| Expected SAEF functions | 36 |
| Loaded SAEF functions | 36 |
| Missing SAEF functions | 0 |
| Unexpected SAEF functions | 0 |
| Expected SAEF classes | 2 |
| Loaded SAEF classes | 2 |
| Missing SAEF classes | 0 |
| Unexpected SAEF classes | 0 |
| Exporter core class loaded | Yes |
| Exporter runtime class loaded | Yes |
| Migrated caller source | Matches the private approved snapshot |

The first inspection attempt used `IPS_RunScriptText`, which reports only
whether execution was accepted. The probe was corrected to
`IPS_RunScriptTextWait`, which returns the script result. That inspection-only
correction did not change the active runtime or any installation object.

## 4. Gate Decision

The clean-process fileset load and exact runtime namespace verification are
**PASS**. The previous minimal seven-function namespace has been replaced by
the exact 36-function and two-class exporter fileset, while the existing
migrated caller remains unchanged.

The next Phase A action is diagnostics initialization followed by an identical
second initialization to prove object and Registry reuse. That action may
create installation objects and therefore remains a separate state-changing
checkpoint. It does not inherit authorization from this activation.
