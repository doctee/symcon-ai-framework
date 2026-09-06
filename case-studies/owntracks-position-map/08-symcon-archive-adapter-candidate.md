# OwnTracks Symcon Archive Adapter Candidate

**Status:** Repository candidate and bounded read-only live verification complete;
all mutation gates closed

**Date:** 2026-08-30

## 1. Gate Scope

The repository implementation defines the case-study-local boundary between
configured OwnTracks source roots, Archive Control and the existing WGS84
track core. It is tested first with synthetic function fakes. Gate 5B then
executes the exact candidate once through a transient, read-only Symcon MCP
probe. No persistent live script, provider request, visualization change,
commit or publication belongs to either step.

The candidate remains `OwnTracks`-specific. It neither introduces a general
SAEF archive/map API nor accepts Navimow local coordinates.

## 2. Configuration Boundary

The adapter resolves exactly one Archive Control instance through its stable
module identifier, composing the same pattern already used by an existing
case-study runtime. Private runtime configuration supplies:

- one to eight opaque source mappings;
- one private selector association value per source;
- one positive source-root ID per mapping; and
- configured position and accuracy Idents.

No installation ID, selector label or tracker identity is committed to the
case study. The adapter validates unique selector values and opaque source
keys. It resolves children by Ident and then verifies positive variable IDs,
the expected string/numeric data types and active logging before reading.

The selector remains owned by its existing action contract. This adapter maps
an already accepted selection value; it does not write the selector. A future
live integration must continue to invoke the selector through
`RequestAction()` and supply the resulting request generation separately.

## 3. Read Sequence

One request follows this bounded sequence:

1. reject an already superseded request before configuration or archive work;
2. validate the complete core query against an empty projection;
3. resolve exactly one configured source from the selector value;
4. verify Archive Control, source root, Idents, types and logging state;
5. read positions for the half-open window using the explicit record limit;
6. recheck the active generation and stop before accuracy work when stale;
7. read at most one accuracy value preceding the window plus bounded changes
   inside the window;
8. recheck the generation, project with the existing WGS84 core and recheck
   once more before returning the result.

Every `AC_GetLoggedValues()` call has explicit start, end and count bounds. A
false return or a response exceeding the requested count fails closed. Archive
records remain newest-first at this boundary; chronological ordering and
temporal attribution stay in `OwnTracksTrackCore`.

## 4. Partial-Result Semantics

Position and combined-accuracy reads each stay within
`maxArchiveRecords`. When a preceding accuracy value exists, it consumes one
slot from the accuracy budget. Equality between returned count and requested
limit is treated conservatively as limit exhaustion because the API result
does not prove that no older value remains.

The projected history is marked partial when either stream exhausts its
budget. Adapter diagnostics keep position and accuracy exhaustion separate and
report whether the preceding accuracy value was available.

## 5. Generation Semantics

The caller supplies a positive request generation and a callback that returns
the currently active generation. A mismatch returns `superseded` with the
bounded stage name and no partial track payload. In particular, a selection
change detected after the position read prevents both accuracy reads and
projection.

The callback is deliberately an integration seam, not a new shared state
helper. Ownership and persistence of the generation belong to the future
private Symcon integration.

## 6. Synthetic Evidence

`tests/symcon-archive-adapter.php` verifies:

- three selector mappings without tracker identities;
- exactly one selected position stream;
- one preceding and one in-window accuracy read;
- explicit bounds on every archive call;
- stop-after-position behavior for a superseded generation;
- refusal of unknown selector values before archive access;
- refusal of invalid core queries before archive access;
- refusal of missing logging and failed archive reads;
- separate position and accuracy limit diagnostics; and
- static absence of selector, object, archive or visualization mutation calls.

The fixture uses invented IDs and coordinates. No private source content is
serialized into repository artifacts.

## 7. Gate 5B Read-Only Live Evidence

The exact locally checked candidate classes were assembled into one transient
Symcon MCP text probe. Before execution, the assembled source was scanned for
object, value, action, archive and visualization mutation calls. The probe
created no object and persisted no source or result.

The bounded probe covered the previous complete local calendar day and
returned only anonymous aggregates:

- exactly one Archive Control and three compatible sources were resolved;
- per-source position reads ranged from 0 to 105 records and accuracy reads
  from 1 to 71 records;
- two sources contained positions while one valid source had no positions in
  that window;
- all non-empty positions except one reception-delayed observation fell into
  the selected observation-time window;
- every position and accuracy read remained below the 2,500-record bound;
- every rendered result remained below the 500-point bound;
- no position, accuracy, combined archive or render limit was reached;
- serialized per-source results remained below 49 KiB;
- per-source adapter execution remained below 100 ms in this one snapshot;
  and
- a forced generation change after the position read returned `superseded`
  at `position-read`, so no accuracy read or projection followed.

The MCP result was accepted only after independently confirming an empty
transport error, an empty PHP execution error and `truncated=false`. No object
ID, tracker key, coordinate, raw timestamp or movement sequence is retained in
this evidence.

This snapshot proves the bounded adapter path against the current installation.
It is not a latency service-level objective and does not authorize a persistent
runtime, selector action, provider request or visualization object.

## 8. Remaining Gate

**Parallel runtime and visualization gate:** create a separately owned runtime
and tile with an exact new-object rollback boundary. The provider contract is
closed in `09-provider-decision.md` without activating an authority.
