# Zigbee2MQTT v6 Final Drift Preflight

**Gate:** V6-C read-only drift and update-route preflight
**Result:** PASS; no v6 release is offered on the installed Store channel
**Date:** 2026-07-26
**Live impact:** None

## Scope

This gate refreshed the complete Zigbee2MQTT migration baseline after the full
backup completed. It also identified the exact Symcon subsystem that owns the
installed module and repeated the maintenance-package preflight.

No event was disabled. No module, instance, profile, script or variable was
changed. No `ApplyChanges()`, MQTT publication or device action was issued.

## Candidate Revalidation

The reviewed `dev_V6` branch still points to the same v6.00 build 2369 source
snapshot assessed before the backup. The library metadata, bridge
implementation and bridge extension-update helper are unchanged from that
reviewed snapshot.

The installed module is not a classic Module Control checkout. It is a Module
Store installation on the Beta channel. Its current installed contract remains
version 5.43 build 543.

This distinction changes the execution mechanism but not the migration
analysis: the later mutation must use the Store Control transaction and bind
all four inputs—Store instance, bundle, Beta channel and concrete release ID.
A Module Control branch switch is not applicable to the live installation.

## Live Drift Result

The fresh baseline reproduced the previously approved inventory:

- 67 Zigbee2MQTT instances, all in active status;
- two bridges, two configurators, 58 devices, one discovery and four groups;
- 1,499 variables, including 911 actionable and 48 archived variables;
- 82 links and 138 variable-triggered events, of which 135 are active; and
- both bridges online with extension 5.40 loaded and no restart request.

The three-variable difference between default and effective `Z2M.*` profile
counts is the already known custom-profile override, not drift. Both bridge
configuration fingerprints and their Zigbee2MQTT runtime versions also match
the pre-backup baseline.

## Maintenance Package Revalidation

The hash-locked package passed without mutation:

- 21 selected command-capable events;
- 19 currently active events in the bounded quiescence set;
- two already inactive events;
- eight unchanged owner scripts; and
- unchanged event-selection and package hashes.

ControlLight feedback events remain outside the outbound-command set and were
left active.

## Store Release Boundary

The native read-only Store query exposes the installed Bundle, channel and
release, but not the release currently offered for update. A subsequent
user-provided Store view closed that observation gap: the selected Beta channel
shows version 5.43 build 543 and offers `Reinstall`, not `Update`.

V6 is therefore not currently published to the installation's accessible Beta
channel. The reviewed `dev_V6` Git branch is a source candidate, not an
installable release in this live Store context.

This is a fail-closed distribution boundary, not live drift. Event quiescence
must not begin until an official accessible Store channel offers v6 and its
concrete release ID and declared version/build have been checked. The update
call must then use that exact release ID; “latest” is not an acceptable
implicit target.

## Decision

The live installation and maintenance package are ready for the migration
transaction. The source candidate is unchanged and the full backup remains the
verified rollback boundary.

The mutation gate remains closed because no v6 Store release is currently
available, in addition to still requiring explicit authorization. Reinstalling
5.43 would not advance the migration and is not permitted by this gate.

Once an official accessible v6 Store release is available and bound, the next
gate is one bounded transaction:

1. repeat the final hashes;
2. disable and verify only the 19 selected events;
3. install the bound v6 Store release;
4. verify all instances and both bridge extensions before resuming automation;
5. stop safely on any structural deviation; and
6. restore the 19 original event states only after the postflight passes.
