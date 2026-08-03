# Hue Wall Integration Review

**Gate:** Read-only integration and event-ownership assessment
**Result:** Separate CL-005/CL-012/Hue cohort required
**Date:** 2026-07-26
**Live impact:** None

## Scope

This review reconciles the consolidated Hue Wall Switch design with the
currently installed shared handler and the two legacy ControlLight wrappers it
controls. It focuses on integration semantics and event ownership. No script,
event, variable, device setting or light state was changed.

## Intended Event Model

The handover defines four distinct active handler events:

- one action event for each of the two Hue Wall Switch modules; and
- one state-feedback event for each of the two controlled lights.

These events are not duplicates. Action events consume physical input, while
target events synchronize the handler's assumed rocker state after external
changes. The two ControlLight target-feedback events are additional legitimate
subscribers with a different owner and purpose.

Equal trigger variables are therefore insufficient evidence of duplication.
Duplicate classification must include owner, role, stable Ident, trigger mode
and active state.

## Live Event Topology

The four intended handler events exist, are active, have stable role-specific
Idents and explicit Symcon event-action bindings.

Two additional unnamed events observe the two Hue action variables. Both are
inactive, lack Idents and have the same trigger contract as the corresponding
active action event. They have no runtime effect and are not created by the
documented final handler. They are redundant retained artifacts, but no cleanup
is authorized by this review.

One of the light targets also has an independent alarm consumer. That is
another intentional downstream dependency and must remain part of future
migration regression.

## Integration Findings

### Hue action trigger mode

Both Hue action events currently use change triggers. The Z2M action variables
retain their last `press_release` string; they are not reset after handling.
Consequently, two consecutive identical actions are updates but are not
reliably distinct value changes.

The input adapter must use update-trigger semantics for the Hue action
variables. A functional test must press every physical rocker twice in
succession to prove that identical actions are not lost.

### ControlLight action boundary

The handler currently sends `RequestAction()` directly to the two native Z2M
state variables. This preserves the target module's action path, but bypasses
ControlLight serialization, authoritative feedback confirmation and structured
diagnostics.

After both wrappers are migrated, the preferred integration is to address
their local `STATE` facades. Target feedback then updates ControlLight first,
and the Hue handler synchronizes its internal rocker model from that
authoritative result. The event chain has no command loop because feedback
handling only updates handler-owned internal state.

### Optimistic synchronization and errors

The current handler synchronizes all assumed rocker states immediately from the
requested state. A failed or delayed command can therefore leave the model
optimistic until another target update occurs. It also has no structured
handling for `RequestAction()` failures.

The replacement should synchronize from confirmed feedback, resynchronize
after an exception and record bounded diagnostics. Physical button actions
must not be retried blindly because a delayed first command could otherwise be
duplicated.

### Device mode readback

Both Z2M instances are active and expose left/right action values, but their
`device_mode` variables currently contain no reported value. The documented
`dual_rocker` setting must therefore be verified with each battery device awake
before functional closure.

## Migration Decision

CL-005 is removed from the proposed simple STATE/DIMMER wave. It and CL-012
form one sequential integration cohort with the shared Hue input adapter.

The next safe work is offline:

1. define the Hue adapter configuration and ownership contract;
2. change only action inputs to update-trigger semantics;
3. route commands through the migrated ControlLight facades;
4. preserve the two independent feedback roles and the alarm consumer;
5. add bounded diagnostics and command-failure resynchronization; and
6. prepare a four-rocker, repeated-action and external-command regression
   matrix.

Only after that package and a fresh read-only dependency preflight should live
activation be proposed.
