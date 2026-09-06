# Gate 81 — channel-v8 pilot inventory

**Status:** Read-only repository and worktree inventory complete, 2026-09-04.

## Scope and baseline

This gate inventories the retained OwnTracks workstreams and reconciles their
repository evidence with the first completed standalone-module deployment
channel, version 8. It changes no historical worktree and performs no Windows,
Symcon, provider, publication or retention operation.

The current shared baseline is `origin/main` at the merge of PR #100. It
contains ADR-0009, the deterministic standalone-module package builder, the
hash-pinned server-local adapter dispatch and the live-verified version-8
gateway. Its standalone target allowlist is intentionally empty. The baseline
contains no OwnTracks position-map case study or module distribution.

## Retained OwnTracks worktrees

Six historical worktrees were found and left byte-for-byte untouched:

| Worktree role | Branch state relative to current `origin/main` | Relevant content |
| --- | --- | --- |
| initial position-map workstream | historical and dirty | requirements and early case-study files |
| offline-core workstream | historical and dirty | broad pre-security candidate, tests and distribution |
| final-security-review workstream | historical and dirty | isolated review report |
| security-correction workstream | historical and dirty | corrected candidate before activation closure |
| Windows-ACL-hardening workstream | historical and dirty | isolated ACL report |
| security-activation workstream | historical, clean, four local commits | most complete 80-step case study and exact activated module distribution |

The security-activation worktree is the only complete source input for this
pilot. It adds the final security correction and reports 79 and 80 over the
same module package represented in the correction workstream. The other
worktrees are retained as recovery and evidence boundaries, not normalized or
deleted.

## Handover reconciliation

The framework handover after PR #100 explicitly assigns OwnTracks Position Map
as the first version-8 target adapter. It keeps the five existing channel
verbs, requires a target-bound private policy and leaves Windows installation,
target allowlisting, staging, preflight, activation, postflight and retention
as distinct approvals. No handover authorizes a live action in this workstream.

## UI and Safari requirements

The early Safari backlog is historical, not an unresolved current defect:

- source ordering/default, source-local `Fit all`, external-point isolation,
  denser line continuity and label decluttering were implemented in steps
  37–40 and physically accepted on Safari/Mac and iPad;
- Positions/Path presentation, compact controls, direction arrows, selected
  ETA and overlay-safe fit were refined in steps 41–48;
- the 46-pixel host touch boundary and iPhone control hit area were corrected
  in steps 63–64;
- viewport authorization, retries, grid alignment and provider-tile drain were
  closed by steps 69–74, after which the complete historical-fit tile result
  was accepted;
- high-zoom static detail and provider continuation were activated and browser
  accepted in steps 75–76; and
- the final security package in step 80 did not change renderer, browser bundle,
  labels, configuration or tile revision.

No later retained report records an open UI implementation defect. A future
package activation must nevertheless repeat the acceptance matrix because
repository evidence cannot prove host-layer behavior after a reload:

1. Safari/Mac initial control interaction and native pointer behavior;
2. iPhone and iPad View/Source/Day hit targets at the 46-pixel host boundary;
3. Positions fit, point visibility, dated stale labels and selected ETA;
4. source-local historical Path fit, line/arrows and readable timestamps;
5. pan/zoom without document scrolling or rotation; and
6. complete static/provider tile coverage through initial fit and retry drain.

## Decision

Gate 81 is closed. The complete security-activation source may be consolidated
into a new worktree based on current `origin/main`; historical worktrees remain
immutable. Adapter implementation is Gate 82. Windows execution and every
live/channel mutation remain closed.

No ObjectID, tracker key, coordinate, movement history, private URL, host name,
credential or installation path is recorded here.
