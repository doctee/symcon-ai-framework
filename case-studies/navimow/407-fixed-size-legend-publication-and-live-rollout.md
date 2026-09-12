# SAEF Step 407: Fixed-Size Legend Publication and Live Rollout

- **Date:** 2026-09-12
- **Status:** Publication and controlled rollout complete; physical iPad
  confirmation remains pending
- **Scope:** Navimow HTML SDK legend presentation only

## 1. Purpose

This step publishes the offline correction from step 406 through both
repository boundaries and installs the exact standalone result with one
controlled IP-Symcon module update. It also verifies the generated HTML SDK
tile through a fresh read-only execution.

## 2. Canonical Publication

The SAEF candidate passed the complete repository check and two GitHub CI
runs. Pull request 116 was merged with this identity:

```text
candidate: 914833dd3ae8876e78329e726d336107f319ead2
merge:     884ce8219fe1a83d9469593bb7c7a19c290f33e9
```

The generic manifest publisher then derived and byte-verified the complete
standalone module:

```text
pull request:       14
previous commit:    fbddce0827de2f81dbb33dd941373b3fa620e8a4
pull-request head:  8ef949eae2925c724f886621dc75d9a3180bfd86
merge commit:       4ab415dd5582bd2f49cec3149ccf4c28a94fcd29
file count:         47
fileset SHA-256:    e92ecd29d6fe0d797a5848934aef72a0656cc48ccb0e3fd6fb0255574f0a58fd
publication SHA-256:c43f311d44aac2de012b9c21b732c90d960f82e35d7d25d217a90b031998132c
```

The standalone repository reported no configured checks. The publisher
recorded `checkCount: 0` explicitly and independently verified the integrated
`main` tree against the frozen candidate.

## 3. Live Preflight

The bounded structured Symcon MCP preflight verified before mutation:

- the exact preceding commit on clean and valid `main`;
- an available update to the published target;
- ready Account, Configurator, Device and Receiver instances;
- inactive MQTT and WebSocket transports;
- disabled MQTT features and absent Core transport credentials;
- unchanged Account and Device configuration hashes;
- operational REST authentication and status polling;
- exactly 64 owned variables; and
- the unchanged installation-owned 25-target Archive logging contract.

The Local Map remained visible and rendered. No ObjectIDs, values,
credentials, topics, coordinates or private geometry entered public evidence.

## 4. Controlled Update

Exactly one supported `MC_UpdateModule()` call installed commit
`4ab415dd5582bd2f49cec3149ccf4c28a94fcd29`. The call returned success and the
repository immediately reported clean and valid `main` with no further update
available.

No `ApplyChanges()`, `MC_ReloadModule()`, restart, OAuth action, MQTT
activation, credential request or mower command was executed.

## 5. Postflight

Immediate and delayed read-only checks both passed. They confirmed the target
commit and preserved every preflight condition, including the 64-variable and
25-target Archive contracts.

A fresh `IPS_GetVisualizationTile()` read proved that the installed HTML SDK
output contains:

- Fit/current scale derivation;
- bounded legend measurement through `getBBox()`;
- inverse SVG-matrix placement;
- the counter-scaled legend transform; and
- no legacy delta-only alignment or external runtime dependency.

The first auxiliary tile probe attempted a non-exported module wrapper and
failed read-only. It performed no mutation. The corrected platform-level call
then passed without repeating the module update.

## 6. Architecture Decisions

### AD-NAV-407-01: Update without instance reconciliation

**Decision:** Install the static HTML SDK asset through one normal module
update without calling `ApplyChanges()`.

**Reason:** No configuration, variable, profile or runtime ownership contract
changed. A fresh platform tile request reads the updated module asset directly.

### AD-NAV-407-02: Verify rendered code through the platform API

**Decision:** Use `IPS_GetVisualizationTile()` in a fresh bounded execution
instead of assuming a generated module wrapper exists.

**Reason:** This verifies the same assembled HTML document consumed by the
visualization and avoids executing or changing any device state.

### AD-NAV-407-03: Retain the preceding standalone commit

**Decision:** Keep `fbddce0827de2f81dbb33dd941373b3fa620e8a4` as the explicit
rollback revision until physical client confirmation closes the rollout.

**Reason:** Repository and live checks prove the delivered code contract, while
the final Safari/iPad rendering remains a separate human-visible observation.

## 7. Gate Result

| Gate | Status |
|---|---|
| SAEF publication and merge | PASS |
| Standalone publication and merge | PASS |
| Live preflight | PASS |
| One-shot module update | PASS |
| Immediate and delayed postflight | PASS |
| Live visualization-tile contract | PASS |
| Physical iPad confirmation after reload | OPEN |
