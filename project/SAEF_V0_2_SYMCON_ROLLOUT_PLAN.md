# SAEF v0.2 Symcon Rollout Plan

## Purpose

This plan moves the released SAEF v0.2 deployment artifacts into an existing
IP-Symcon installation without treating a repository release as an automatic
runtime update. It covers inventory, inactive staging, activation, rollback and
postflight verification. Installation paths, credentials, object IDs and
private script identities belong in a separate private activation record.

The plan does not authorize a live mutation by itself. Every activation and
every device-affecting verification remains a separately supervised gate.

**Current status:** Gates B through E and the MQTT portions of Gate F passed
between 2026-07-20 and 2026-07-21. The immutable v0.2.0 MQTT fileset was first
selected as the process-effective global SAEF owner. A later supervised hotfix
activation selected the generated unreleased MQTT fileset
`3c72d2b7269743b6ffa57477bf34bffd818d183274f972b16ef480e3fe59e3d1`
without modifying the v0.2.0 release artifact in place.

Repeated preparation and no-op reconcile runs preserved topology. Independent
producer tests verified power and brightness commands with compensation.
Home Assistant verified color-temperature dispatch through the private
per-entity confirmation contract. Rapid superseding UI commands remain a
bounded operational caveat and are not evidence of an unbounded loop.

ControlLight uses its hash-addressed current fileset for seven independently
migrated and fully device-tested v2 wrappers. The other 22 wrappers retain
their reviewed legacy implementation and `pending` brightness semantics; they
are not authorized for bulk migration by this plan. This is the closed v0.2
ControlLight scope. The System Functions pilot completed all three direct
replacements and now has three SAEF calls with no retained legacy pilot call.
The final migration and its subsequent regular scheduled execution passed
bounded read-only verification without manual caller execution or a device
action.
Navimow is installed at its recorded adaptive-polling revision, and its passive
natural-departure observation passed without a mower command or artificial
state transition.

## Read-only baseline

An authorized read-only inventory established the following sanitized facts:

- the active global SAEF owner is an MQTT Discovery Exporter fileset;
- that owner is a complete and internally consistent development build;
- no final v0.2.0 fileset is staged in the inspected installation;
- Validation, EnsureVariable, EnsureCategory and the exporter core already
  match the released sources;
- exporter runtime, Runtime Diagnostics, event, instance and wait behavior are
  older than the released sources;
- multiple inactive historical development filesets remain present; and
- ControlLight is a consumer of the globally owned helper functions and cannot
  select a later guarded helper implementation by itself.

The mixed source state is valid historical deployment evidence, but it is not
the final v0.2.0 runtime identity. Updating individual helper files in place
would weaken provenance and rollback and is therefore rejected.

## Release artifacts

The rollout uses only generated artifacts from the immutable `v0.2.0` tag:

| Responsibility | Artifact | Release identity |
| --- | --- | --- |
| Global owner and MQTT runtime | `dist/symcon/saef-mqtt-discovery-exporter/` | fileset `553518512dfabdebf0f24fc668f4ce35234fe578f69e0a5eae22687a334d039c` |
| ControlLight consumer | `dist/symcon/saef-control-light/` | fileset `434d0ad0cfd2789214e98e5ff843c7a3218612e6b4b4d130f3aee679a1abc8be` |
| Minimal legacy-call adapter | `dist/symcon/saef-ensure-variable.php` | artifact `235b075c9e7f9d4c9da1fa2db9248d2b320aa2cdf68d50f7ee617fa587599c39` |

Generated files are copied as complete artifact sets and are never edited on
the target installation. Manifests, sidecars and every ordered source hash are
part of the activation contract.

The live confirmation correction is an unreleased successor fileset generated
from the working tree. It is not part of the immutable v0.2.0 table above and
must be included in a later release after its observation and release gates
pass.

The minimal bundle is not an alternative owner once the complete MQTT fileset
owns the same global functions. It remains migration provenance for existing
script-only callers and must not be loaded in parallel as a competing version.

## Legacy classification

### Update during the v0.2 rollout

- the complete active MQTT fileset through a new versioned directory;
- the global helper implementation through the MQTT owner;
- the MQTT runtime and its generated provenance;
- the ControlLight fileset as an inactive matching v0.2 consumer candidate;
- bootstrap selection only through the reviewed activation transaction.

### Preserve during deployment

- existing variables and their values;
- domain state, user-visible state and deliberate UI or trigger variables;
- archive configuration, links, custom actions and presentation metadata;
- existing Registry, Statistics and ErrorRingBuffer variables with compatible
  types and ownership;
- events and object identities owned by existing configuration scripts; and
- the shared legacy function library while it still has callers.

Ensure helpers may reconcile only their documented owned properties. Type
conflicts are failures, not migration instructions. Existing objects are not
deleted or recreated merely because their implementation originated before
v0.2.0.

### Migrate through separate gates

- the remaining variable-creation control call after its scheduled observation
  gate passes;
- name-based object creation only after stable Idents and ownership are defined;
- profile helpers only after association, range and presentation semantics are
  mapped to `SAEF_EnsureProfile()`;
- wait helpers only after timeout, polling, value and update/change contracts
  are proven equivalent;
- event lookups and creation only as one coordinated Ident and ownership
  migration; and
- domain helpers only when a reusable SAEF responsibility has actually been
  demonstrated.

Unused legacy functions may be removed only after static and dynamic caller
audits. The legacy library is not replaced wholesale by a copy of SAEF.

## Rollout gates

### Gate A: immutable source selection

1. Check out the annotated `v0.2.0` tag in a clean workspace.
2. Run `composer install`, `composer validate --strict` and `make check`.
3. Verify both fileset hashes and the minimal bundle hash against their tracked
   sidecars.
4. Record only hashes and release identity in the private activation record.

### Gate B: backup and inactive staging

1. Capture byte-exact backups of the active bootstrap and selected fileset.
2. Stage the complete MQTT v0.2.0 fileset under a new immutable directory.
3. Stage the complete ControlLight v0.2.0 fileset without selecting it.
4. Verify every staged relative path and SHA-256 value against
   `fileset.sources.json`.
5. Confirm that no staged bootstrap, class or helper is loaded.

Staging must not create or update Symcon objects and must not execute an
exporter, wrapper or device action.

### Gate C: maintenance preflight

1. Re-read the active bootstrap and source identities immediately before the
   change.
2. Confirm the MQTT fileset is still the earliest global helper owner.
3. Confirm the active bootstrap contains exactly one replaceable owner
   reference.
4. Verify rollback files and hashes independently.
5. Run `Invoke-SaefSymconRestart.ps1` with `-PreflightOnly` from an elevated
   external PowerShell process.
6. Require outcome `passed` with `restartAttempted: false`.

Any drift closes the gate and requires a fresh private package. Do not repair
drift inside the activation transaction.

### Gate D: owner activation

1. Replace only the reviewed bootstrap reference atomically.
2. Run the existing state-based restart coordinator outside IP-Symcon.
3. Require a stopped service, a running service, ready runlevel and a newer
   kernel start identity.
4. Restore the backed-up bootstrap and perform the configured recovery restart
   if activation verification fails.

Fixed restart delays, in-place helper replacement and partial fileset copying
are not permitted.

### Gate E: read-only runtime verification

After the clean restart, verify without executing configuration or device
commands:

- the active bootstrap and fileset hashes;
- the complete manifest and ordered source map;
- Reflection source hashes for every loaded shared helper;
- the MQTT runtime and core identities;
- exactly one effective implementation for every global `SAEF_` function;
- exporter event bindings, diagnostics structure and bounded history shape; and
- unchanged object, archive, link and action topology.

Every MCP result must have no transport error, no execution error and no
truncation.

### Gate F: controlled behavior verification

1. Execute the owning MQTT configuration/reconcile path twice and verify
   idempotency.
2. Confirm that no duplicate object or event was created.
3. Confirm that configuration hashes, counters and timestamps change only for
   the expected execution.
4. Exercise publication and command paths separately with bounded observation
   and no unrelated device action.
5. Select the v0.2 ControlLight runtime only through its existing wrapper
   migration process.
6. Run each selected wrapper configuration twice before any separately
   authorized real-device capability test.

Navimow remains an independent native-module deployment. Its installed module
identity must be compared with the v0.2 distribution before deciding whether a
module update is necessary; it does not inherit the script-fileset activation.

## Rollback and stop conditions

Rollback is mandatory when:

- a staged or active hash differs from the reviewed package;
- the global helper owner is not the expected MQTT fileset;
- a helper resolves to an unexpected source after restart;
- the service fails to reach the ready runlevel in the bounded window;
- object identity, archive configuration, links or actions drift unexpectedly;
- a configuration pass creates duplicates or reports a type conflict; or
- runtime diagnostics cannot be read defensively after initialization.

Early setup failures remain visible through exceptions and the Symcon log.
Runtime diagnostics become authoritative only after their structure has been
successfully initialized.

## Completion criteria

The v0.2 Symcon rollout is complete only when:

- the final MQTT fileset is the proven global owner after a clean restart;
- every effective shared helper matches its v0.2 source hash;
- MQTT postflight and repeated configuration are successful;
- the current ControlLight fileset is selected by all seven chosen consumers,
  each completed its own migration gate, and the other 22 wrappers have an
  explicit retain decision;
- the Navimow installed identity has been classified as current or separately
  updated;
- the legacy library and remaining callers have an explicit retain, migrate or
  retire decision; and
- all private backups, paths and installation evidence remain outside the
  public repository.

## Related artifacts

- `adr/ADR-0005-generate-symcon-helper-bundles.md`
- `helpers/README.md`
- `project/SAEF_SYMCON_BUNDLE_BUILD_DESIGN.md`
- `project/SAEF_V0_2_SYMCON_GATE_B_REPORT.md`
- `project/SAEF_V0_2_SYMCON_GATE_C_REPORT.md`
- `project/SAEF_V0_2_SYMCON_GATE_D_E_REPORT.md`
- `project/SAEF_V0_2_SYMCON_GATE_F_MQTT_REPORT.md`
- `project/SAEF_V0_2_SYMCON_GATE_F_COMMAND_REPORT.md`
- `project/SAEF_V0_2_SYMCON_GATE_F_CAPABILITY_REPORT.md`
- `project/SYSTEM_FUNCTIONS_MIGRATION_WAVE_1.md`
- `project/SYSTEM_FUNCTIONS_PILOT_DEPLOYMENT_PLAN.md`
- `deployments/symcon/windows/README.md`
- `references/RI-002-runtime-diagnostics-internal-state.md`
