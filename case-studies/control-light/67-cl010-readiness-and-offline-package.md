# CL-010 readiness and offline package

## Outcome

The read-only delta preflight classifies CL-010 as a suitable next
single-device migration candidate. Its private, hash-bound candidate and
rollback package passes local verification. No live source was changed, no
wrapper was executed and no device command was sent.

The same inventory correction records CL-008 as a Zigbee2MQTT group rather
than a simple linked device. CL-008 is therefore excluded from this
single-device step and remains behind a separate group-semantics gate.

## Read-only CL-010 findings

The selected wrapper has:

- one operational and currently available Zigbee2MQTT device target;
- enabled and actionable STATE and brightness capabilities;
- matching local and authoritative target values at capture time;
- one active, explicitly bound feedback event for each capability;
- two user presentation links that must retain their targets and positions;
- no Auto-Off consumer;
- one independent STATE observer used only for light-on warning diagnostics.

The independent observer is not owned by ControlLight and issues no device
command. A migration must preserve it unchanged.

The complete 29-wrapper source baseline had no mismatches during the probe.
All reads were bounded and produced zero script, object, variable or device
side effects.

## Candidate contract

The private wrapper candidate:

- selects the already staged immutable ControlLight runtime used by the
  successfully tested Hue Wall cohort;
- enables authoritative feedback;
- records `reported` brightness semantics;
- retains the existing alarm polarity;
- disables unsupported color and color-temperature capabilities explicitly;
- delegates directly to `ControlLightRuntime`;
- contains no wrapper-side `RequestAction`, `SetValue`, script write or legacy
  script delegation.

The expected first and second reconciliation runs are command-free and
value-stable because local and authoritative values already agree.

## Rollback and activation boundary

The private overlay contains the candidate, byte-exact encoded legacy source,
read-only evidence, package manifest and executable verifier. The verifier
checks source hashes, closed gates, candidate configuration and the zero-side-
effect preflight contract.

A later activation requires a new explicit approval and a fresh read-only
delta preflight. It must fail closed unless:

1. the legacy wrapper source still has the captured hash;
2. the selected staged runtime exists with its expected hash;
3. target, local variables, feedback events, presentation links and the
   independent diagnostic observer remain unchanged;
4. the target is operational and the complete wrapper baseline is drift-free.

After an exact candidate write, the transaction stops at the first failed
condition, restores only from the hash-locked rollback source and verifies the
full 29-wrapper baseline. Device capability tests remain a later, separately
approved gate.

## CL-008 group gate

A group-level STATE or brightness report does not prove that every member
accepted a command. CL-008 therefore requires an explicit decision for:

- whether authoritative success means only a confirmed group aggregate or
  confirmation from every observable member;
- how unavailable or partially responding members are diagnosed;
- whether retained group brightness is meaningful when members diverge;
- how rollback and regression tests cover partial member failure.

Until that contract exists, CL-008 remains legacy and is not substituted for a
single-device migration candidate.
