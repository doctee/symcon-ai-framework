# Solar forecast display migration

Status: Offline candidate. No publication, module update, archive correction or
consumer cutover is implied.

## Scope and decisions

The migration replaces the normal current-day forecast and its two-plant sum.
Upper and lower estimates are intentionally not reproduced. Legacy forecast
graphics do not require a replacement in this stage. Before provider shutdown,
their links still need an explicit hide or retirement decision; an obsolete
graphic must not remain labelled as a current forecast.

A future hourly outlook should use the existing system power API, retain real
interval timestamps and label potential PV harvest rather than house-grid
delivery. It may support load-shifting decisions but does not authorize device
control, a calibration factor, or an energy-management algorithm. This stage
does not implement that outlook.

## Separate forecasts from chart counters

A daily energy forecast can decrease and increase during the same day. Native
counter aggregation sums positive deltas, so a midnight zero followed by several
forecast revisions does not represent the latest daily forecast. The source
variable and its original history must remain unchanged.

The candidate therefore separates three contracts:

- The original module API and variables retain forecasts and their provenance.
- The display plan provides one valid two-plant sum, or an explicit failure.
- New chart-only archive series may project the latest daily forecast as one
  zero-to-forecast pair. These are presentation data, not forecast history.

The last item is a **materialized projection**, implemented by an explicitly
invoked, not yet live-qualified archive adapter.
Each revision would replace that day's pair, never append another pair. Its
data cannot answer when a forecast was issued or how it changed within the day.
Use source revisions or immutable calibration snapshots for those questions.

The existing native chart and original archive settings are not modified.
Do not copy the legacy script's deletion of source forecast records.

## Candidate contract

`candidate/SolarDisplayPlan.php` composes the existing `IntervalAligner`.
It adds no public helper, module API, object creation or generated fileset.
`evaluate()` accepts exactly two source envelopes, an explicit observation
timestamp, timezone and maximum age. Inputs contain:

- Unique positive instance IDs and stable logical keys.
- Instance status, DataState and last-success timestamp.
- Pinned expected configuration hash and before/after readback identities.
- Current TodayEnergyForecast plus the existing system power and daily API
  responses, including their success flags.

A source must be healthy and fresh, successfully published on the current local
day, and unchanged across the read. Power intervals must cover that entire day
without overlaps or gaps. Integrated power must match the daily API and current
published variable to within one micro-kWh. This rejects a transient publication
zero but accepts a genuine zero forecast. No missing value becomes zero.
No partial-plant sum or chart plan is returned on failure.

Local-day bounds use calendar days, including daylight-saving transitions.
Source arrays are bounded to 400 points. Output includes observation and expiry
times, source identities, the sum and a deterministic chart projection.
`applyAllowed` is always false; source IDs are not archive mutation targets.

`SolarDisplayReader` acquires both current module update semaphores in ascending
instance order, with a 100 ms wait per lock. It reads status/hash/LastSuccess,
power/daily responses and TodayEnergyForecast, then re-reads identities and
status. It makes one bounded attempt, rejects a read exceeding five seconds,
and validates against the completion time to catch midnight and expiry.
It never triggers a refresh or writes variables. Locks cannot prevent manual
configuration changes outside the module's lock; metadata readback additionally
detects observable drift. The lock names are version-bound to the inspected
module and require requalification if its update protocol changes.

The reader returns a structured result: only success includes sumKwh and
expiresAt. Consumers must check both success and expiry, including at midnight.
Invalid results contain no zero substitute. Existing last-good output values
must not be overwritten with zero on failure or presented as fresh. No numeric
sum variable is created or linked by this candidate; that binding belongs to
the separately approved consumer setup. There is no new diagnostics storage:
the owning caller can use the existing Registry/Statistics/ErrorRingBuffer
helpers after its diagnostics configuration has been qualified.

## Archive writer and recovery

`SolarDisplayArchive` provides preview(), apply() and rollbackPending(). Preview
is read-only. Apply either starts one fresh two-source transaction or reconciles
the one pending transaction; it never starts another in the same recovery call.
Rollback is an explicit separate operation, not an automatic reaction to drift.
No method creates objects, changes archive configuration, writes variable values,
sets events, changes links, or calls the forecast collector.

The executor enforces these constraints:

1. Bind new dedicated chart-only targets by explicit IDs, ownership and stable Idents.
   Reject source, legacy, unrelated or zero IDs; verify type, parent, profile,
   logging, aggregation and counter-zero settings immediately before mutation.
2. Do not write normal variable updates into chart-only archives. Otherwise the
   supposedly two-point projection would acquire additional counter deltas.
3. Back up the exact TimeStamp/Value target-day points before replacement. Use a
   flushed recovery journal and a common ownership lock before any deletion.
   Deletion is bounded to the approved current local day, never an open-ended
   range, earlier history or source archives.
4. Verify plan freshness, unchanged input identities and local day before
   committing the intent. Complete or explicitly restore that bounded committed
   intent after a restart, even after midnight, before accepting another plan.
   Such recovery does not publish a fresh sum or imply freshness of old inputs.
5. Add exactly the planned points, request reaggregation only for the two owned
   variables, and verify daily counter totals and raw points. The documented
   AC_ReAggregateVariable API reaggregates a variable, not a selected date range.
   Poll completion on subsequent runs, without repeatedly restarting aggregation.
   After 600 seconds report aggregation_timeout and retain the pending intent.
6. Native-chart semantics remain subject to this integration proof. The pure
   offline delta test does not prove Archive Control behavior or UI rendering.

### Ownership and persistence contract

The configured owner must be a script with ObjectInfo
`SAEF SolarDisplay owner v1 <generation>`. Both float variables must be its
direct children, have Idents `SolarDisplay_Chart_<source-key>`, and ObjectInfo
`SAEF SolarDisplay chart-only v1 <generation>`. The 32-hex generation is pinned
in private configuration and must be changed on object replacement, never
copied to a replacement automatically. Symcon exposes no documented variable
creation timestamp; the generation is an ownership contract, not an assumption
about such a field. Normal names, positions and icons remain user-owned.

Targets must use ~Electricity, have no actions, enabled counter logging with
zeros included, and no compaction. Configuration is a separate helper-based
setup gate; this runtime will not repair mismatches. It admits only empty days
or its own two-point pairs, bounds each raw read to three records to detect
overflow, and rejects unknown current rows during recovery. Known partial
inserts are reconciled with delete/readback/add/readback; original source
variables are outside the owner subtree and cannot be selected as targets.

`SolarDisplayJournal` writes an immutable hash-addressed JSON intent containing
before/desired points, target identities, source evidence and a pinned source
configuration contract. It flushes and fsyncs the temporary file before rename
and verifies readback before creating the active pointer. Changed contracts,
corrupt bytes, symbolic-link targets and conflicting records fail closed.
Receipts, aggregation request markers and before-images are retained; completion
only retires the pending pointer. No evidence retention/cleanup is implemented.
The private canonical directory must already exist, be access-restricted, and
remain bound to one owner; runtime does not create or relocate it. Temporary
files left by interruption are inert and require a later cleanup decision.
Filesystem-level guarantees under sudden power loss remain dependent on the
host/filesystem; service/script interruption is covered by offline crash tests.

Aggregation-request receipts are written after the accepted platform call.
A crash in that narrow gap can cause one repeated idempotent reaggregation
request on recovery, but never another projection delta. A transaction may
temporarily show one updated chart before the other; only terminal readback
means the pair is complete. External writers not honoring the owner lock remain
unsupported and must be ruled out in the live preflight.

### Composition entrypoint

`SolarDisplayRuntime::connect()` accepts private source pins, timezone, maximum
age, owner/archive IDs, target mapping, generation and canonical journal path.
It returns a `reader` closure and a `writer` object. Including or connecting the
runtime does not execute it. Calling `reader` or `writer->preview()` reads;
`writer->apply()` and `writer->rollbackPending()` are mutating live gates.
Native composition requires the explicit timezone to equal the Symcon PHP/
archive timezone. It dispatches only the documented platform calls selected by
the adapters, not operations supplied by a network payload.

Changing old source aggregation, repairing historical bars, transferring old
archives and retention are not necessary consequences of this design and need
separate decisions.

## Verification and remaining gates

`tests/solar-display-plan.php` covers falling and rising forecasts, legitimate
zeros, publication-zero rejection, freshness limits, configuration drift,
duplicate sources, API errors, incomplete/overlapping intervals, unit mismatch,
day rollover, 23/25-hour days and year boundaries. It runs in the focused offline
gate. Private saved inventory can be replayed offline; reconstructed readback
tokens do not prove a fresh atomic live capture.

The runtime tests cover source races/busy locks, crash recovery after each
archive phase, partial insertion, explicit rollback, object/contract drift,
unknown archive conflicts, journal tampering, asynchronous aggregation, zero
days, DST and adjacent-day preservation. The archive fake is an offline model,
not an independent proof of native monthly/chart semantics.

Next gates are commit/PR review, private backup, helper-based isolated target
setup and native archive qualification, parallel observation, consumer
switching, provider-event deactivation, read-only guard, object retirement and
retention. Commit, PR/merge and publication remain independent authorizations.
The source model, horizon, calibration collector and factors stay unchanged.

Official archive semantics:
[Archive Control](https://www.symcon.de/de/service/dokumentation/modulreferenz/kern-instanzen/archive-control/).
