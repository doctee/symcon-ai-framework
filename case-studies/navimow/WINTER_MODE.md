# Seasonal storage

`SeasonStateVariable` is an optional string property on Account and Device.
An empty property preserves existing installations. A configured missing,
invalid or incomplete state blocks mower commands and normal status polling.
The referenced script-owned string uses this contract:

```json
{"version":1,"mode":"winter","since":"2026-11-22","applied":true}
```

Modes are `season`, `winter`, and `commissioning`. Commissioning permits status
reads but never mower commands. Winter stops status/map/command-verification
timers and retains raw telemetry, paths, statistics and stored visualization.
The map labels the retained data as historical and hides the current-position
marker. Authentication is retained. This is not the manufacturer's sleep
scenario and does not operate the mower or change its schedule.

## Installation coordinator

`tools/season-controller.php` is an installation-bound script body, not an
automatically installed module component. Bind positive, verified IDs privately.
It owns state, a native mode selection, explanatory text and a derived supply
warning input. Existing raw sensor variables remain untouched. Its battery
policy changes only the configured mower row's `Excluded` flag, retaining the
previous value in the season state. Other rows, especially a separate camera,
must remain byte-equivalent after structured comparison.

Installation must also guard the site's delayed station-power action and
power-hint script using the same state. The power action and coordinator use
the semaphore `NavimowSeason:<state ID>`. Replace only the mower's central
supply-warning trigger source with the derived input. A disabled event alone
is insufficient where the aggregator still reads its source variable.

Optional legacy action bindings disable the existing variable action and hide
its visualization link during winter and commissioning. Preserve both original
settings in the seasonal state and restore them only on verified season entry.
This is required when a separate integration still exposes a mower start button.

Season re-entry requires an explicit commissioning step, then fresh docked,
online and power telemetry since commissioning and no older than five minutes.
It never sends Start/Resume/Dock or changes the manufacturer's schedule. The
station must be reconnected manually. No automatic seasonal date transition.

## Deployment and recovery

Back up module files, scripts, configurations and affected events privately.
Preserve existing pending configuration changes; stop on any drift. Publish
the module through its existing fileset/PR contract and update through Module
Control. Initialize winter state incomplete before binding consumers; complete
only after exclusions and bindings are independently verified. Test command
blocking offline, never by sending a live movement command.

For rollback keep winter blocking in place while restoring consumers. Restore
only owned fields and original script contents after drift checks, not whole
shared configurations. Never remove evidence or created objects automatically.
Camera settings, raw telemetry, archive data and unrelated warnings are outside
the rollback and winter-mode ownership boundary.
