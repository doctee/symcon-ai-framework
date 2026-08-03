# CL-011 group readiness and shutdown dependency finding

## Outcome

The read-only assessment confirms CL-011 Grillbereich as a Zigbee2MQTT group
with exactly three active, available members. The recent authoritative
group-list response maps those members one-to-one to the west, center and east
device instances.

CL-011 is not activation-ready. No MQTT request, device command, script write,
object write or variable write occurred during this assessment.

## Capability evidence

The legacy facade enables STATE, brightness, color temperature and color. The
group endpoint and all three members expose actionable variables for those four
capabilities.

Only STATE and freshness currently have operational reporting evidence. Group
and member brightness, color temperature and color values are all zero and
their variable update timestamps are unset. The runtime therefore cannot yet
distinguish a valid zero value from a capability that has never reported.

Member-confirmed authority must not be enabled from this static variable layout
alone. A presence-bound capability probe is required before selecting the
brightness, temperature and color contracts.

## Existing consumers

Two central shutdown automations address Grillbereich members individually.
One contains all three member STATE controls. The other contains the center and
east controls, repeats the east control and does not contain the west control.

A separate active random-lighting instance intentionally addresses the three
member color variables independently. This is not an accidental duplicate:
independent member colors are the feature. A future group contract must either
retain that effect as an explicit foreign owner with reconciliation rules or
migrate it to a dedicated group-aware effect contract. It cannot silently be
replaced by one uniform group color.

The Alexa integration contains no Grillbereich facade or member mapping.
No presentation link targets the inspected facade or member controls.

## CL-015 dependency correction

The same central shutdown script also contains the three individual
Kuschelsofa member STATE controls. This dependency was missed by the earlier
CL-015 consumer scan.

CL-015 itself remains active and healthy: member events passively reconcile
direct changes back to the facade. Nevertheless, an individual three-command
shutdown bypasses its selected one-group-command authority and shared
confirmation boundary.

The required correction is narrow:

1. replace exactly the three Kuschelsofa member-off actions with one facade
   STATE-off action;
2. preserve every unrelated action, timer, condition and ordering decision;
3. bind exact source and rollback hashes;
4. execute no shutdown script during migration;
5. verify that the facade is the sole Kuschelsofa action target afterward; and
6. rerun the complete ControlLight structural regression.

This is a separate live mutation and remains closed pending explicit approval.

## Recommended sequence

1. Correct the CL-015 shutdown bypass without executing the shutdown script.
2. At presence, probe CL-011 STATE, brightness, color temperature and color,
   restoring all three members exactly.
3. Decide the explicit ownership contract for random per-member colors.
4. Build the CL-011 group runtime and atomically migrate both shutdown
   consumers only after those contracts are closed.
