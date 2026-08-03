# Zigbee2MQTT v6 Readiness and Migration Plan

**Gate:** Read-only installation-wide dependency and migration assessment
**Result:** PASS for planning; module update remains closed
**Date:** 2026-07-25
**Live impact:** None

## Scope

This assessment follows the instance-specific CL-021 color capability disable.
It evaluates the separate Zigbee2MQTT v6 testing line as an installation-wide
module migration. It does not treat v6 as the repair for the previously
identified lossy RGB/xy color contract.

No module version, instance configuration, event state, script, variable or
device value was changed. No `ApplyChanges()`, production script, MQTT action or
device command was executed.

## Candidate Contract

The assessed `dev_V6` snapshot declares Zigbee2MQTT module version 6.00 build
2369 and requires IP-Symcon 9.0. It retains the library and module identities
used by the installed 5.43 line. The migration documentation states that
existing variables are not automatically deleted and their ObjectIDs, events
and links remain present.

The compatibility boundary is nevertheless material:

- dynamic module-managed `Z2M.*` profiles are replaced by native Symcon
  presentations for new or re-registered standard variables;
- existing custom profiles and presentations are preserved;
- device and group reconciliation can re-register existing variables;
- additional variables may be created from newer expose or payload contracts;
  and
- rollback to 5.43 is supported only by restoring a complete pre-update
  backup.

The update therefore has one installation-wide blast radius. The shared module
identity does not allow one existing live device or group instance to be
upgraded as an isolated pilot.

## Live Dependency Baseline

The connected IP-Symcon 9.0 installation currently has 67 active
Zigbee2MQTT instances across all five module types and 1,499 child variables.
The read-only baseline also found:

- 48 archived variables;
- 82 links referencing a Zigbee2MQTT instance or variable;
- 138 variable-triggered events, of which 135 are active;
- 24 scripts with literal references to Zigbee2MQTT objects; and
- 25 ControlLight target links into the module boundary.

Both configured bridges are online and currently report the installed
extension version 5.40 as current for module 5.43. The v6 contract requires
extension version 6.05.

The v6 bridge implementation calls its options request from
`ApplyChanges()`. When the reported extension is older than the candidate
requirement, it automatically sends an extension-save request. Consequently,
the module update is not a source-only Symcon reload: both live Zigbee2MQTT
bridges are expected to receive an external maintenance request. A restart is
not unconditionally issued by that method, but the reported extension and
restart state must be verified before normal automation resumes.

## Reconnect Consumer Risk

The 138 triggered events divide into:

- 72 ControlLight feedback events under 25 wrappers; none of their event
  owners contains an outbound `RequestAction`, Zigbee2MQTT or MQTT publish
  path;
- nine active Auto-Off events under four scripts with an outbound action path;
  and
- 57 events under 17 other scripts, including 12 events under four scripts
  with an outbound action path.

Two of the latter 12 events are already inactive. The resulting maintenance
selection is 19 currently active events under eight command-capable scripts.
This is a conservative static classification: it establishes that a reconnect
or republished state can enter command-capable consumers, not that every event
execution necessarily sends a command.

ControlLight feedback synchronization itself can remain active. Before the
module update, the 19 active command-capable trigger events must be captured
byte-exactly, deactivated as one bounded maintenance set and independently
verified. Their original active states must be restored only after bridge,
variable and automation postflight succeeds.

## Migration Gates

1. **Verified backup and rollback package**
   - create a complete Symcon backup as required by the module author;
   - verify that the backup artifact exists and is readable;
   - capture the installed module source/version and all 67 instance
     configurations;
   - preserve exact variable, link, archive, event and script dependency
     baselines; and
   - prepare a hash-locked event-state restoration manifest.

2. **Maintenance quiescence**
   - refresh the dependency hash immediately before mutation;
   - stop if any selected event, owner script or trigger has drifted;
   - deactivate only the 19 currently active command-capable trigger events;
   - verify that ControlLight feedback events remain active; and
   - record that no device or MQTT action was issued.

3. **Installation-wide module update**
   - update only the Zigbee2MQTT library to the reviewed v6 snapshot;
   - observe module reload and every instance status;
   - allow the two expected extension-save requests;
   - do not manually apply individual device or group instances yet; and
   - stop on persistent errors, duplicate extensions or unexpected device
     actions.

4. **Structural and bridge postflight**
   - require both bridges online with extension 6.05 current;
   - handle a reported restart requirement explicitly and re-run the bridge
     gate afterwards;
   - compare all 67 instance identities and configurations;
   - compare the 1,499-variable baseline, accepting additions only after
     classification and rejecting deletion or replacement of an existing
     ObjectID;
   - verify all archived variables, links and event triggers; and
   - check custom profiles and user-owned names, positions and presentations.

5. **Automation regression and resume**
   - reconcile all 29 ControlLight wrappers without a device command;
   - verify all enabled actions, feedback events, ownership rules and
     diagnostics;
   - test the nine active v2 ControlLight contracts in bounded operational
     cohorts, with CL-021 color still disabled;
   - restore the 19 maintenance-disabled events to their exact original
     states; and
   - observe regular executions before closing the migration.

6. **Separate color repair**
   - re-evaluate CL-021 color only against a corrected and independently tested
     target-module color contract;
   - keep the capability disabled throughout the v6 migration; and
   - treat any upstream fix or pull request as a separate review and activation
     gate.

## Decision

The read-only assessment is complete. The candidate is structurally suitable
for an in-place, backup-backed migration, but it is not suitable for an
ungated live update. The next permissible step is preparation and verification
of the full backup and hash-locked maintenance package. It does not include the
module update.
