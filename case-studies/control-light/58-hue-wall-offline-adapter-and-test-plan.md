# Hue Wall Offline Adapter and Test Plan

**Gate:** Offline candidate and regression preparation
**Result:** Passed; live activation remains closed
**Date:** 2026-07-26
**Live impact:** None

## Scope

This package implements the offline replacement candidate for the shared Hue
Wall Switch handler reviewed in report 57. It covers the two physical Hue Wall
Modules and the ControlLight facades for CL-005 and CL-012. It neither changes
the live handler nor migrates either ControlLight wrapper.

The candidate consists of:

- `candidate/HueWallSwitchCore.php` for side-effect-free configuration and
  action mapping;
- `candidate/HueWallSwitchRuntime.php` for owned topology, serialized
  dispatch, authoritative confirmation and bounded diagnostics; and
- `candidate/HueWallSwitchWrapper.php` as a sanitized private-overlay example.

Installation-specific object IDs and the existing event Idents must be supplied
only by the private activation package.

## Functional Contract

### Physical input

Each Hue action variable has exactly one handler-owned active action event. The
event uses Symcon update-trigger mode because consecutive physical operations
can publish the same retained `press_release` string.

Only left and right press/release actions are command-capable. Hold, release and
unknown payloads are ignored and counted without producing a target action.
Left/right wiring reversal remains explicit per wall module.

### Rocker semantics

The Hue action payload identifies a side and gesture but no absolute top/bottom
rocker position. The former model persisted an assumed position and then
resynchronized it from target feedback. Once synchronized, its inversion
formula reduced algebraically to toggling the confirmed target state for both
values of `invertTopBottom`.

The candidate therefore reads the current local ControlLight `STATE` facade
under the target semaphore and requests its inverse. It does not persist an
unobservable rocker position. `invertTopBottom` remains accepted as migration
metadata so private configuration can be compared without pretending that it
changes the available input semantics.

This contract remains correct after visualization, automation or voice
commands, and after a process restart. It also avoids optimistic state writes.

### Control and feedback boundary

Commands use `RequestAction()` on the local ControlLight `STATE` facade. Native
Z2M variables are not addressed by the candidate. ControlLight remains the
owner of device dispatch, authoritative confirmation and its own diagnostics.

Each target facade retains an independent handler-owned feedback event. That
event records observation statistics only; it never emits a command and cannot
form a loop. Existing ControlLight feedback subscribers and the independent
alarm consumer remain outside the handler's ownership and must be preserved.

### Parallelism and duplicate protection

Dispatch uses one short target-specific semaphore. The current confirmed state,
debounce decision and command form one critical section. This prevents two
threads for the same target from reading the same baseline and issuing the same
derived state.

An owned per-source-and-target timestamp suppresses only burst repeats for the
same rocker path inside the bounded debounce interval. The other rocker of the
same module remains independent, so both target lights can be operated
simultaneously. The action event still uses update semantics, so two
intentional identical presses outside that interval both execute. Two presses
from different wall modules remain distinct and wait for bounded serialization
when they address the same target.

### Operational failures

Each physical action causes at most one `RequestAction()` attempt. A rejected
action, thrown target action, busy semaphore or missing confirmation is
classified and stored in the bounded error history. Expected operational
failures return normally at the event boundary. There is no blind retry and no
optimistic facade update.

The ControlLight facade normally completes its own confirmation before
`RequestAction()` returns. The adapter performs a short additional bounded
facade check to cover event scheduling without duplicating the device command.

## Ownership and Idempotency

Configuration accepts the four existing stable live event Idents. Reconciliation
therefore updates those event objects in place, including the two action
triggers from change to update, while preserving their user-facing names,
positions and visibility.

The candidate owns only:

- its explicitly configured action and feedback event Idents;
- `HWS_DEBOUNCE_<SOURCE>_<TARGET>` variables below the handler script;
- `HUE_WALL_REGISTRY` and `HUE_WALL_ERROR_HISTORY`; and
- `HWS_*` statistic variables.

Obsolete events with the candidate-owned prefixes are deactivated, not deleted.
Unnamed legacy events and foreign events are not mutated. The two already
inactive unnamed duplicate artifacts therefore remain untouched until a
separate cleanup approval.

Normal variable-event execution reads a compact resource index from the owned
Registry. Object creation and event reconciliation happen only when the owner
script is executed explicitly. This keeps the physical input path bounded and
requires an explicit reconciliation after every configuration change.

## Offline Regression Result

The executable tests prove:

- localized and native Hue action normalization;
- left/right wiring reversal;
- two identical action updates producing two intentional toggles;
- burst debounce without a target command and without suppressing the other
  target of the same wall module;
- target semaphore rejection without a target command;
- immediate, delayed and missing authoritative feedback;
- one attempt and no retry after a timeout;
- no optimistic state after rejection or timeout;
- action-event update triggers and feedback-event change triggers;
- reuse of existing event objects with presentation preservation;
- two-run idempotency; and
- non-interference with unidentified and foreign events.

Run:

```console
composer test:hue-wall-core
composer test:hue-wall-runtime
composer test:hue-wall-topology
```

## Proposed Live Sequence

Live work requires a fresh read-only preflight and a separate explicit
authorization. The safe sequence is:

1. capture exact handler source, four active event contracts, two inactive
   duplicate events, both wall-module action variables, both target facades and
   all downstream subscribers;
2. prepare a private hash-locked candidate and rollback package containing the
   existing four event Idents;
3. migrate CL-005 and CL-012 sequentially to ControlLight v2, retaining the
   old handler's direct target path during each wrapper transition;
4. verify both local `STATE` facades against native feedback before changing
   the handler;
5. quiesce physical wall input for the short handler transaction;
6. replace the handler source, execute it once to reconcile in place and then
   execute it a second time to prove idempotency;
7. verify that exactly two active action events use update triggers and exactly
   two active feedback events use change triggers, with no additional active
   command consumer;
8. run the bounded physical and external-control matrix below; and
9. close private evidence, sanitized report and current regression fixtures.

Rollback restores the former handler source and the four captured event
contracts. Newly created diagnostic and debounce variables are script-owned and
may remain inert; rollback must not delete user objects or the inactive legacy
events.

## Physical and Integration Test Matrix

Before the first command, wake each battery device and verify the configured
dual-rocker mode. Record initial target states and all ControlLight and Hue
diagnostic counters.

For each of the four physical side/module combinations:

1. press once and require exactly one adapter command attempt, one ControlLight
   command and confirmed target inversion;
2. wait beyond the debounce window;
3. press the same side again while the action payload is unchanged and require
   the inverse result with the same one-to-one command deltas.

Then test:

- change each light through its local ControlLight facade and confirm that the
  next physical press toggles that confirmed state;
- issue a voice or visualization command immediately before a physical press
  and require serialized, feedback-consistent outcomes without optimistic
  state;
- issue near-simultaneous presses from both modules for the same target and
  require two serialized attempts, no overlap and a final state consistent
  with two toggles;
- remove physical power from one light, press once and require a classified
  failure without ScriptEngine fatal, retry or facade drift;
- restore physical power and immediately issue an allowed voice command,
  proving that stale availability does not suppress the command;
- press the wall control after recovery and require a toggle from the newly
  confirmed state;
- confirm that the independent alarm subscriber and both ControlLight feedback
  events remain active and receive their expected updates; and
- restore both lights and all diagnostic baselines where restoration is part
  of the approved test contract.

No live step in this sequence has been performed by this offline gate.
