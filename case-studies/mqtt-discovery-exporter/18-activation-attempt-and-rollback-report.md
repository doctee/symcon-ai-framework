# 18 Activation Attempt and Rollback Report

**Case study:** IP-Symcon MQTT Discovery Exporter
**G6 sub-gate:** Bootstrap activation, restart and clean-runtime verification
**Result:** NOT PASSED — old bootstrap restored, service recovery pending
**Date:** 2026-07-15
**Device/MQTT status:** No device action or MQTT publication performed

## 1. Outcome

The exporter activation did not pass. The target-specific bootstrap was changed
only after a recoverable backup, exact 15-file staging verification, an atomic
replacement probe and a live Windows service-control probe had passed.

The first service restart took longer than the 180-second watchdog window. The
watchdog therefore restored the old bootstrap before the new-runtime gate could
be evaluated. A subsequent diagnostic confirmed:

- the old bootstrap bytes were restored;
- the old minimal artifact was the included SAEF artifact;
- the staged Runtime source was not included;
- neither exporter class was loaded;
- no watchdog success marker had been written.

The missing exporter class is consequently not evidence of an invalid fileset.
The activation observation was overtaken by the timeout-driven rollback.

## 2. Corrected Evidence Method

The Symcon script connector reports only that script execution was accepted. It
does not return script output and does not expose script exceptions. Earlier
write and assertion calls were therefore re-evaluated through a temporary,
hidden status variable that could be read independently through RPC.

This readback exposed that the first backup/staging claim had not materialized
because the bootstrap was not part of the per-script `get_included_files()`
view under the expected repository filename.

The real installation structure was then discovered without publishing its
paths or ObjectIDs. The corrected procedure:

1. resolved the active minimal artifact through reflection;
2. found exactly one included local bootstrap referencing its private runtime
   filename;
3. created and read back all three backups plus private restore metadata;
4. transferred all fifteen files again;
5. read back successful full-tree and provenance verification;
6. confirmed that staging remained inactive before the switch.

The backup and staging evidence is now based on independent state readback, not
the connector's script-acceptance boolean.

## 3. Activation Sequence

The controlled transaction performed:

1. a fresh active-bundle, bootstrap, caller and staged-tree drift check;
2. a temporary-file probe proving atomic replacement on the Windows target;
3. a live query confirming the running `IPSServer` service;
4. installation of a private delayed rollback watchdog;
5. replacement of the single old artifact reference with the staged bootstrap
   reference;
6. a controlled service restart.

The first restart was confirmed by shutdown, initialization and an advanced
kernel start time. Initialization exceeded the watchdog deadline. The watchdog
then restored the backed-up bootstrap as designed.

## 4. Rollback Restart State

After restoration, a second restart was explicitly requested so that the old
bootstrap would be loaded into a clean runtime. Shutdown was confirmed, but the
RPC endpoint did not return within the bounded observation period.

The subsequently captured Symcon log confirms the timing failure:

- kernel creation began at 14:37:56;
- Symcon reported ready at 14:40:52, after 176 seconds;
- the 180-second watchdog therefore had effectively no margin once the delayed
  stop/start sequence was included;
- the second shutdown was requested at 14:45:34 and completed cleanly at
  14:45:56, after 22 seconds;
- the restart worker waited only eight seconds before requesting start, so its
  start request occurred about fourteen seconds before shutdown completion;
- the log ends with `IPS SHUTDOWN COMPLETE` and contains no subsequent kernel
  creation entry.

There is no SAEF, PHP, include, autoload or fatal error in the captured startup
sequence. Existing library signature warnings are unrelated to this
transaction. Event errors after the shutdown request report that new scripts
could no longer start; they are a consequence of shutdown, not its cause.

Confirmed before the second shutdown:

| Check | Result |
| --- | --- |
| Backed-up bootstrap restored | PASS |
| Old minimal artifact included | PASS |
| Staged Runtime included | No |
| Exporter classes loaded | 0 |
| MQTT publication | None |
| Device action | None |

Still pending:

- manual or external start of the Windows `IPSServer` service;
- clean-runtime verification of the old seven-function state;
- migrated-caller hash verification after recovery;
- cleanup of the temporary hidden activation marker;
- cleanup or archival of private activation worker files.

## 5. Required Recovery

The service completed shutdown and is not stuck in a partial kernel state.
Starting the Windows `IPSServer` service through the Windows Services console
or an elevated PowerShell session is therefore sufficient:

```powershell
Start-Service -Name IPSServer
```

Do not edit the bootstrap or delete the private backup/staging directories.
Once RPC is available again, the next SAEF action is read-only rollback
verification. Activation must not be retried at that point.

A complete Windows restart is also acceptable after the log has been secured,
provided the service start type is automatic. It is more invasive and is not
required by the Symcon log evidence; use it if a manual service start fails or
if the operator wants to clear every remaining external worker process.

## 6. Retry Design Change

A future activation attempt requires a restart coordinator that polls actual
Windows service state instead of using fixed delays. It must:

1. wait until `IPSServer` is fully stopped before requesting start;
2. wait until the service is running and Symcon reports the ready runlevel;
3. use a watchdog deadline longer than the observed installation startup time;
4. distinguish slow initialization from a failed bootstrap;
5. preserve an out-of-process recovery channel after RPC becomes unavailable.

The staged fileset remains suitable for verification, but activation is gated
until service recovery and this restart-procedure correction are complete.

The correction is now implemented and offline-verified as the external,
state-based coordinator documented in
`20-state-based-windows-restart-coordinator-report.md`. Its Windows parser,
private preflight and first live execution remain separate gates.
