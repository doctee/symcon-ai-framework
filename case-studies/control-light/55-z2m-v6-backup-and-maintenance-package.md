# Zigbee2MQTT v6 Backup and Maintenance Package

**Gate:** V6-B verified full backup and hash-locked maintenance package
**Result:** PASS
**Date:** 2026-07-26
**Live impact:** Full backup only; no Zigbee2MQTT or event mutation

## Scope

This gate implemented only the backup and rollback preparation from the
approved v6 migration plan. The Zigbee2MQTT library, its instances, all
automation events and all device values remained unchanged.

The installation already had an active official Symcon Backup instance.
Its normal incremental configuration excluded several large data areas and
was therefore insufficient for the migration rollback gate. The bounded
transaction temporarily selected full-backup mode, disabled its timer and
removed the configurable directory filters. It then invoked the documented
backup operation and restored the exact original configuration in a
`finally` boundary.

## Full Backup

The live data tree contained approximately 3.27 GB across more than 44,000
files when the transfer began. The file-by-file FTP backup consequently ran
for about 21 hours and 38 minutes. The module completed successfully and
reported approximately 3.24 GB transferred.

The initiating MCP request reached its five-minute transport limit while the
server-side backup continued. This was treated as an indeterminate transport
result, not as a failed backup. Subsequent read-only checks proved the active
server thread and serialized transaction until the module published its new
completion timestamp.

Final postflight confirmed:

- the backup server thread had ended;
- the transaction semaphore was free;
- the Backup instance remained active;
- the completion timestamp advanced;
- the original incremental configuration hash was restored byte-exactly; and
- the tracked remote full-backup directory remained present and readable.

## Remote Artifact Verification

The remote artifact contains every backup-relevant root entry. A non-empty
`settings.json` and non-empty database, scripts, modules and media directories
were verified directly.

Two current local runtime directories are absent. Inspection of the exact
installed Backup module source proved that these are the module's unconditional
Windows exclusions:

- `logs`, because Windows keeps logs inside the data directory; and
- `session`, because session files are explicitly classified as unnecessary
  for backup.

They are therefore expected module behavior rather than unexpected loss.
No other root entry was missing. The live source tree continued changing
during the long online transfer, so exact final byte equality with a later
source snapshot is neither expected nor claimed.

## Maintenance Package

The private package identifies 21 Zigbee2MQTT-triggered events under eight
owner scripts. Nineteen are currently active and form the bounded maintenance
quiescence set; two are already inactive.

The package:

- validates every event identity, trigger and parent script;
- validates each owner source hash;
- serializes maintenance execution;
- disables only the 19 originally active command-capable triggers;
- verifies the complete quiescent state;
- rolls back to the original event states if quiescence fails;
- restores the exact original active-state contract after migration; and
- contains no module update, MQTT publish or device command.

The non-mutating preflight passed again after the backup. No event was disabled
during this gate.

## Decision

Gate V6-B is closed successfully. The installation now has a verified remote
full backup and a drift-sensitive, rollback-safe maintenance package.

The next gate begins with a fresh read-only drift preflight. Quiescing the 19
events and updating the Zigbee2MQTT library remain live mutations and require
separate explicit authorization.
