# CL-002 external-trigger readiness

## Outcome

CL-002 is suitable as the next command-free ControlLight migration candidate.
Its available Z2M target and local facade agree on off and zero retained
brightness. Both target capabilities are actionable, and no presentation,
AutoOff, script, event or Alexa consumer depends on the facade.

The private hash-bound candidate and exact rollback package passed offline
verification. Live activation and every real device action remain closed.

## External input contract

The two existing external inputs are the independent channels of one Homematic
entrance-light wall control. They expose short-press updates:

- channel one maps every update to STATE on;
- channel two maps every update to STATE off; and
- both inputs respect the configured inverse alarm variable.

The legacy configuration called the numeric inputs `linkID`, although the live
objects are variables rather than Symcon links. The candidate makes their
actual ownership explicit as configured source variables while preserving both
existing event identities, OnUpdate behavior and on/off mapping.

The shared ControlLight semaphore serializes overlapping device commands. The
two source mappings and active-alarm block are additionally covered by
deterministic runtime regressions before activation.

## Activation boundary

The alarm contract is currently active. A configuration run is expected to
issue no command and change no facade or target value. STATE feedback moves
in-place from OnChange to OnUpdate, while both external events retain OnUpdate
and brightness feedback retains OnChange.

The next gate is explicit approval for a fresh delta preflight and command-free
activation. Physical wall-control and facade function tests remain
presence-bound.
