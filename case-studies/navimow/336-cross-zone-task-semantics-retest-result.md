# 336 Cross Zone Task Semantics Retest Result

**Case study:** Navimow native IP-Symcon module

**Status:** Passed with bounded semantic findings

**Date:** 2026-08-24

## 1. Decision

The rain-affected session-8 pilot closed automatically at its 72-hour deadline
and completed credential-first cleanup. A separately authorized short
receive-only retest during a natural run in another operator-confirmed app zone
then captured useful task-phase evidence and was closed before 13:00 CEST.

No mower command, restart, OAuth action or activation retry occurred.

## 2. Previous Pilot Closure

The read-only preflight established that session 8 had closed as
`deadline-reached`. MQTT and position diagnostics were disabled, both Core
instances were inactive, credentials were absent and REST remained operational.

The retained pilot accounting contained thousands of accepted position samples
and coordinate changes, no out-of-order timestamp and dozens of segments. This
proves that the position path can be populated across a longer natural window.

## 3. Short Retest Gate

The first readiness check stopped before mutation because the remaining token
horizon was below 1200 seconds. Passive REST operation rotated the token without
manual authentication. A later fresh preflight passed with more than 3500
seconds remaining.

Exactly one restart-free Account activation started session 9. It progressed
from the accepted asynchronous `ReconnectScheduled` state to `ShadowActive`
without retry and retained zero session incidents.

## 4. Task Sequence

The receive-only task projection observed this semantic sequence:

1. Normal action code `5` near the end of the first progress range.
2. Action code `-1` while progress and area candidates continued increasing.
3. `mowingPercentage` reached 100 while task progress and subtotal reset.
4. A new `action=8`, `subAction=6` phase began and continued accumulating area.
5. Boundary correlation remained available; partition correlation appeared
   later with one partition in this observation.

The operator confirmed the governing schedule semantics: a scheduled start
resumes the last progress of the selected app zone. Reaching 100 percent
completes one pass of that zone, after which a new pass starts while the same
scheduled run continues until a stop condition such as battery, schedule-window
end or rain is reached.

Therefore `mowingPercentage=100` must not be interpreted as completion of the
entire app job. It is a zone-pass boundary. Task progress and subtotal are
pass-local candidates, while the subsequent action/sub-action phase belongs to
another pass within the same natural run.

## 5. Zone Identity Boundary

The current run occurred in an app zone that the operator identified as
different from the Saturday run. The current boundary candidate was retained
privately, but the Saturday candidate was not retained before session-8 cleanup.
Cross-zone equality or inequality therefore remains unproven.

`currentMowBoundary` and its correlation key must not yet be presented as an app
zone number. Future mapping requires overlapping evidence containing both the
operator-known app zone and the private correlation key for at least two runs.

## 6. Position Finding

Session 9 received regular task telemetry but no new accepted local-pose sample.
This is compatible with the much richer retained position accounting from the
long pilot and shows that task and pose channels have different availability
and cadence.

A path UI must tolerate position gaps and must not derive coordinates from task
progress or the discarded opaque work-position value.

## 7. Cleanup Evidence

The final private snapshot was taken before cleanup. Exactly one Account
`IPS_ApplyChanges()` then disabled MQTT and position diagnostics.

Immediate and delayed read-only checks both passed:

```text
session:             9, inactive
closure:             Closed / operator-disabled
MQTT / WebSocket:    104 / 104
Core credentials:    absent
REST:                operational
```

Every accepted Symcon-MCP result had empty `transportError` and
`executionError` channels and `truncated=false`.

## 8. Next Step

The parser contract is supported for bounded diagnostic use. The next design
step should introduce a private, retained task-observation ledger keyed by
session and privacy-safe area correlation, rather than exposing raw identifiers
or treating transient diagnostic fields as statistics.

The ledger design must separately model:

- scheduled-run lifecycle versus resumable zone passes and internal phases;
- area correlation versus user-facing zone labels;
- cumulative, run-local and pass-local area candidates;
- sparse position segments with explicit gaps.

A user-facing percentage must state its denominator explicitly. The observed
percentage is suitable as a zone-pass progress value, but not as daily,
scheduled-run or weekly completion without a separately defined aggregation
contract.
