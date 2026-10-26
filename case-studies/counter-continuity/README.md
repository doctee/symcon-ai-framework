# Counter continuity under asynchronous telemetry

Status: publication candidate; educational case study, not a deployable runtime.

## Engineering question

A cumulative energy feed and instantaneous power can arrive through separate
callbacks. A consumer that treats every incomplete observation as an integrity
failure can latch a permanent HOLD during ordinary message delivery. Conversely,
ignoring every counter decrease can silently lose energy or count a recovered
old value again. The useful correction is to classify the failure before
changing the accepted checkpoint.

## Decisions worth reusing

| Observation | Decision | Checkpoint effect |
| --- | --- | --- |
| Incomplete, temporarily unreadable or changing vector | Defer until later natural evidence | None |
| Any required field older than the configured limit | Defer; expose freshness separately from operating mode | None |
| Identity drift or corrupt persistent state | HOLD | None |
| Unresolved previous write intent | Reconcile that intent before new work | No new intent |
| Identical repeated measurement | Idempotent acceptance | No additional energy |
| Different values with the same second timestamp | Apply an explicit ordering policy; never infer order from seconds alone | Policy-dependent |
| Cumulative counter decreases | Require a qualified reset policy | Preserve total until resolved |

A read adapter must distinguish temporary availability from malformed data or
programming errors. Catching every exception as a transient failure hides real
defects. A durable HOLD should not emit the same uncaught exception on every
subsequent callback; return a bounded diagnostic status while retaining the
original integrity fault. This does not justify clearing that fault.

Read all required timestamps independently. A recent power update cannot make
an old energy or health field fresh. Double reads can detect some mixed
snapshots but do not establish device-wide atomicity: unchanged-between-reads
and change-away-and-back races remain possible. A semaphore coordinates only
writers that use it. Resolve persistent generation and pending intent inside
the existing owner lock, before constructing new work.

## Same-second observations

Timestamp resolution is not event identity. Keep independent checkpoints for
energy and power. A production policy may accept a separately ordered later
power observation while the cumulative energy observation is a duplicate.
Without that ordering evidence, deferring until a later observation is safer
than inventing archive timestamps or silently choosing a value. The included
model demonstrates the conservative defer policy, not all policies exercised
by an installation-specific runtime. It can lose intermediate power detail;
that limitation must remain visible.

## Counter reset and historical boundaries

Continuity can use an anchored segment:

`total = retained_total + (raw_counter - new_raw_anchor) / units_per_total`

The existing raw counter at a new anchor is not automatically new energy.
A sequence of low readings can support a reset hypothesis, but several false
low readings can look identical to a restart. An automatic policy must specify
confirmation count, elapsed time, freshness, plausibility, old-packet rejection,
cancellation on recovery, and energy not credited during confirmation. No such
heuristic proves physical reset. This candidate deliberately implements no
automatic reset detector or BOOTED collector.

Counting only positive deltas is insufficient as a general correction: the
synthetic sequence `100, 90, 101` yields eleven units of positive deltas despite
a one-unit endpoint change. A true reset and a temporary dip need different
interpretations. Existing aggregate sums must not be silently replaced merely
to make a report look plausible.

Exact historical exception pins, real raw values, recovery timestamps and
archive repair commands remain private. A reporting exception may reconcile
an existing sum without proving physical energy. A ratio of reported charge
and discharge at equal reported state of charge is not independently measured
battery efficiency. Do not publish installation-specific exceptions as generic
device behavior.

## Synthetic executable examples

Run from the repository root:

```sh
php case-studies/counter-continuity/tests/run.php
```

All values and relative times in the tests are invented. The freshness limit is
an illustrative parameter, not an operational recommendation. The pure model
accepts a previously validated state shape and models nonnegative power only;
export sign conventions require a separate domain contract. Its statuses are
returned decisions, not durable state transitions. No Symcon API, credentials,
network, devices, archive writes or filesystem persistence are used.

Tests cover positive deltas, duplicates, each field's age, the inclusive age
boundary, later resumption, transient reads, mixed reads, invalid future times,
nonfinite values, same-second conflicts, reset review, identity/integrity errors,
and pending-intent precedence. They do not qualify the private runtime, real
packet ordering, crash recovery, durable storage or physical freshness.

## Persistence qualification still required for a runtime

Before reusing the decisions in a writer, test the actual adapter with failures
before intent save, after intent save, between individual target writes, after
target write and before checkpoint commit, and after final commit. Resume in a
new process, compare targets independently, reject conflicting predecessor or
configuration identities, and prove no duplicate energy increment. A pure
function returning RECONCILE is not a write-ahead journal implementation.

Reuse existing Registry, Statistics, ErrorRingBuffer and ConfigurationHash
building blocks for owned metadata and diagnostics. The case study adds no
helper API, module, installer, timer or deployment contract. A complete runtime
would require separate architecture and native qualification.

## Publication and retention boundary

Only this explanation and synthetic model/tests are publication candidates.
Private adapters, installed sources, identifiers, source hashes, device traces,
report exception lists and recovery material are excluded. Offline checks of
this candidate do not authorize publication, live adoption or deletion. Retain
private rollback and archive evidence independently of any later public commit.

Related: [retry mechanisms](../../knowledge/EK-002-retry-mechanisms.md),
[archive processing](../../knowledge/EK-003-archive-processing.md),
[internal state](../../knowledge/EK-004-internal-state-management.md),
[diagnostics reference](../../references/RI-002-runtime-diagnostics-internal-state.md),
[private overlay decision](../../adr/ADR-0003-private-overlay.md).
