# SAEF Step 385: Pilot Automation Isolation and Map Visual Refinement

## Status

Implemented and verified locally. The obsolete local pilot automations were
removed, the private cleanup harness now fails closed without an immutable run
binding, and the Local Map renderer contains the approved visual refinements.
No module publication, Symcon update, MQTT activation, authentication action,
restart or mower command was performed.

## Early-Closure Finding

The interrupted full-feature pilot did not close because of MQTT recovery,
authentication, configuration or the module-owned hard deadline. Sanitized
post-closure diagnostics establish:

- the module recorded `operator-disabled`;
- the current pilot session recorded zero transport incidents;
- MQTT ingress was still present 151 seconds before cleanup;
- 7,215 position samples and 7,172 coordinate changes were retained;
- a legacy cleanup watcher from an earlier pilot invoked exactly one Account
  `ApplyChanges()` while the newer pilot was still active; and
- the configured hard deadline still had 126,792 seconds remaining.

The approximately 36-hour-47-minute observation remains valid MQTT, position,
map and statistics evidence. It does not prove a complete 72-hour lifetime or
module-owned deadline closure.

## Cleanup Isolation

The obsolete one-shot start and closure automations were removed from the local
scheduler. No Navimow pilot automation remained after the inventory.

The private cleanup source now defaults to an unbound state. In that state it
returns `cleanup-run-identity-unbound` with `applyChangesCount=0` before any
Symcon function is called. A generator creates a run-specific immutable source
only from all of these values:

- exact 40-character standalone commit;
- pilot `sessionSequence`;
- pilot `startedAt`;
- pilot `hardStopAt`; and
- `notBefore`, which must be equal to or later than `hardStopAt`.

Before mutation, the bound source independently requires clean and valid
standalone `main`, the matching live commit and exact equality of all pilot
identity fields. A mismatch or early invocation stops before `ApplyChanges()`.
Early operator cleanup is intentionally outside this hard-stop harness and
continues to require a separate current-run gate.

## Map Refinement

The candidate and distribution renderers remain equivalent except for their
namespace. The visual delta is deliberately presentation-only:

- viewport padding is reduced from five to three percent, with a bounded
  minimum;
- the embedded HTML/SVG surface removes body padding and matches the Dark Skin
  background to avoid a contrasting frame;
- obstacles use a still more restrained translucent fill;
- unknown states use saturated magenta instead of the former normal-looking
  teal;
- return, attention and diagnostic states use stronger amber, red and magenta
  signals;
- the mower is a directional body with an internal arrow rather than a circle;
- heading is derived from the final two distinct projected path points and
  never changes stored coordinates or path semantics; and
- the station is structurally empty while the mower is away and contains a
  mower silhouette while docked, in addition to its state color.

The legend uses the same station and mower glyphs as the map. Existing labels,
hidden-zone behavior, zone colors, path lines, station rotation and statistical
progress labels remain unchanged.

## Architecture Decisions

### AD-NAV-385-01: Bind every mutation-capable closure watcher to one run

An active feature flag is not cleanup authority. A closure watcher must prove
the exact commit, session, start, deadline and execution horizon for the run it
owns. Any mismatch is a non-mutating stop.

### AD-NAV-385-02: Inventory schedulers before each pilot activation

Preflight for a new pilot includes a local automation inventory. A prior
mutation-capable start, retry or closure task blocks activation until it has
been explicitly reconciled. Read-only observers may coexist only when their
scope and destination cannot initiate cleanup.

### AD-NAV-385-03: Keep direction presentation-derived

The mower heading is inferred solely from the displayed path. It is not stored
as telemetry and is not treated as manufacturer-authoritative orientation.
This keeps the new symbol additive and avoids a data-contract migration.

### AD-NAV-385-04: Distinguish station occupancy by shape and color

Dock occupancy must remain understandable without color perception. The mower
silhouette therefore appears inside the station only for the REST-authoritative
docked state; color remains a redundant state cue.

## Verification

The focused offline checks pass for:

- candidate and distribution PHP syntax;
- local-map scene projection and reduced padding;
- SVG envelope, active-content denial and output bound;
- path-derived mower orientation;
- occupied and empty station glyphs;
- Dark Skin and explicit light-theme rendering;
- Device lifecycle, restart, disable and statistics behavior;
- runtime reduction and distribution fileset completeness; and
- unbound plus too-early private cleanup rejection with zero apply operations.

The visual preview was rendered locally in active/undocked and docked states.
It contains no installation geometry or live position data.

## Next Gates

1. Review and commit the SAEF candidate from the isolated worktree.
2. Publish the exact map fileset to the standalone module through the generic
   manifest publisher and run metadata conformance.
3. Perform one disabled, credential-free Symcon update with variable and
   Archive fingerprints preserved.
4. Refresh the Local Map read-only and verify both docked and naturally active
   presentation before considering another bounded MQTT pilot.
