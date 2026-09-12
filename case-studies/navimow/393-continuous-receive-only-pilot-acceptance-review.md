# SAEF Step 393: Continuous Receive-Only Pilot Acceptance Review

**Case study:** Navimow native IP-Symcon module

**Status:** 24-hour private pilot accepted; credential-free cleanup and
existing Archive contracts verified

**Date:** 2026-09-12

## 1. Purpose

This step closes the evidence gate left open by steps 388 to 392 and decides
whether retained MQTT position evidence may be used as input for the next
offline map and mowing-statistics increment.

It reports only privacy-safe aggregate evidence. It performs no publication,
Symcon update, MQTT activation, OAuth action, restart or mower command.

## 2. Evidence Reviewed

The bounded continuous-mode validation ran for 24 hours plus the final
read-only checkpoint. At the final active checkpoint:

- Account, Configurator, Device, MQTT Receiver and native Core instances were
  operational;
- the operation state remained `Active` and the MQTT lifecycle remained
  `ShadowActive`;
- REST remained ready and authoritative;
- the last accepted position was fresh;
- more than 100,000 receive-only messages had been accepted;
- transient disconnects recovered without a continuous-mode circuit opening;
- no MQTT publish or mower-command path existed; and
- variable, Archive and command contract fingerprints remained unchanged.

The pilot then executed exactly one bound cleanup. Immediate read-only
verification proved:

- MQTT and position diagnostics disabled;
- WebSocket Authorization and MQTT username/password absent;
- the operation state `Stopped`;
- all module instances still operational;
- REST still ready; and
- variable, Archive and command contract fingerprints preserved.

## 3. Archive Continuity

After pilot closure, the installation-owned logging plan was applied to 25
existing Navimow variables. Immediate and delayed postflights both proved
25/25 targets with the requested standard aggregation.

This is an installation decision, not a module feature. The implementation in
steps 394 to 399 must not call Archive Control, recreate existing variables or
change aggregation. New analytics variables may receive logging only after a
separate rollout and installation-owned Archive gate.

## 4. Acceptance Decision

The pilot is accepted for these bounded uses:

- retained receive-only position paths;
- task-correlated zone attribution;
- revision-bound map presentation;
- diagnostic distance, duration and coverage estimates; and
- a future continuously monitored private operating decision.

The pilot does not prove manufacturer-defined cutting state, survey-grade
geometry, exact blade coverage, rain cause, public protocol stability or a
safe MQTT command channel.

## 5. Architecture Decisions

### AD-NAV-393-01: Accept MQTT as evidence, not state authority

REST remains authoritative for public mower state and every supported command.
MQTT may supply receive-only position and task evidence to diagnostics.

### AD-NAV-393-02: Preserve cleanup as an independent safety boundary

Successful data collection does not weaken credential-first shutdown. Every
future bounded live gate still requires explicit activation and cleanup.

### AD-NAV-393-03: Preserve installation-owned Archive configuration

Archive settings survive module evolution by retaining existing Idents and by
keeping Archive mutation outside productive module code.

## 6. Gate Result

| Gate | Result |
|---|---|
| 24-hour receive-only operation | PASS |
| REST authority | PASS |
| Position freshness | PASS |
| Finite recovery | PASS |
| Credential-free cleanup | PASS |
| Existing variable and Archive continuity | PASS |
| Productive mowing analytics | OPEN in step 394 |
| Publication and Symcon rollout | CLOSED |
