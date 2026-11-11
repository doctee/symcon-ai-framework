# Opt-in normalized LEVEL target

Status: local candidate, not a live qualification.

Some dimmers expose one float LEVEL in 0..1 instead of separate power and
brightness endpoints. `levelTarget: true` uses that single endpoint for both
capabilities. Existing configurations omit the key and retain their normalized
configuration, hash and mapping behavior.

Required configuration: identical nonempty `identState` and `identDim`, both
target types 2, `dimmerTargetMax: 1`, `brightnessSemantics: effective`, and
`brightnessOffStateTransition: {mode: target-turns-on}`. Color, temperature,
groups, brightness-range calibration and off-only control are excluded.

Positive dim commands send one float value (30% -> 0.3), without a prior ON.
OFF sends 0.0. Explicit ON from OFF sends 1.0, preserving the legacy full-level
ON contract; ON on an already lit target is idempotent and does not brighten it.
Feedback derives STATE from LEVEL > 0 and DIMMER by rounding LEVEL * 100.
This is effective brightness, not remembered pre-OFF brightness. No new cache
or last-brightness variable is introduced.

Feedback rejects nonnumeric, nonfinite and out-of-range values. Brightness
confirmation permits half a percentage point of representation tolerance but
never confuses zero with a positive value. A state ON confirmation means a
positive observed LEVEL, not necessarily 100% brightness.

The existing runtime still owns RequestAction, shared semaphore, bounded wait,
diagnostics and feedback synchronization. No new helper or adapter is needed.
The effective-brightness contract synchronizes both facades when the shared
LEVEL event is resolved as state feedback. Offline does not manufacture OFF;
the existing bounded failure path retains actual stored feedback, and no retry
loop or seasonal polling is added.

## Deployment prerequisite

Status emulation must not be mistaken for authoritative device feedback.
Qualify the actual module feedback path before activation. Turning emulation
off is a separate live configuration change, not an effect of this option.
Availability may live on a sibling maintenance channel; do not invent a target
Ident below the action channel or copy stale availability into a fake feedback
variable. Optionally configure `availability.sourceVariableID` as a positive
installation-private variable ID, with `targetIdent: ''`, the exact `targetType`
and `availableValue` (for Boolean UNREACH: false). Explicit source and nonempty
targetIdent are mutually exclusive. Missing or wrongly typed explicit sources
fail before topology writes. Existing ident-based configurations are unchanged.
This source only classifies an unconfirmed command; a stored offline indication
does not prevent a command from succeeding with fresh target feedback.
Its installation binding and physical feedback test remain migration work.

The installed immutable fileset is not modified. A later pilot needs its own
deployment/activation gates; existing consumers stay on their pinned runtime.
Regression coverage is synthetic and does not qualify physical feedback or
seasonal reconnection.
