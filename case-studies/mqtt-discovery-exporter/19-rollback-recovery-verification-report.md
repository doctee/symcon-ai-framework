# 19 Rollback Recovery Verification Report

**Case study:** IP-Symcon MQTT Discovery Exporter
**G6 sub-gate:** Clean-runtime rollback verification
**Result:** PASS
**Date:** 2026-07-15
**Activation status:** Exporter remains inactive

## 1. Recovery

The Windows `IPSServer` service was started manually after the fixed-delay
restart worker had requested start before the previous shutdown completed.
RPC returned and the recovered runtime was evaluated through an independently
readable temporary status marker.

No bootstrap, caller, staged source, MQTT topic or device state was changed
during recovery verification.

## 2. Verified Runtime State

| Check | Result |
| --- | --- |
| Kernel start time advanced | PASS |
| Kernel ready runlevel | PASS |
| Original minimal SAEF functions | 7 of 7 |
| Original helper guard constants | 2 of 2 |
| Additional fileset functions loaded | 0 |
| Exporter classes loaded | 0 |
| Active minimal bundle SHA-256 | Matches captured canonical hash |
| Active bootstrap SHA-256 | Matches private pre-change snapshot |
| Migrated callers | 1 |
| Migrated caller SHA-256 | Matches private pre-change snapshot |
| Inactive staged bootstrap SHA-256 | Matches generated provenance |

The rollback therefore restored the exact pre-activation namespace and caller
contract in a fresh kernel process.

## 3. Cleanup

After verification:

- the temporary hidden activation category and its status variable were
  deleted and their absence was confirmed through RPC;
- transient restart/watchdog scripts and marker files were removed;
- a private rollback-cleanup audit record was retained;
- the recoverable pre-change backup, activation metadata and inactive staged
  fileset were preserved.

No disposable Symcon object from the activation attempt remains.

## 4. Gate Decision

Rollback recovery is **PASS**. The connected installation is back at its
previous minimal SAEF runtime state.

The exporter activation remains **NOT PASSED** and must not be retried with the
fixed-delay procedure. A future attempt requires a state-based external restart
coordinator that waits for confirmed service stop, confirmed service start and
the ready kernel runlevel, with a watchdog window comfortably longer than the
observed startup duration.

That coordinator is now implemented and offline-verified in
`20-state-based-windows-restart-coordinator-report.md`; this does not authorize
its pending Windows preflight or a new activation transaction.
