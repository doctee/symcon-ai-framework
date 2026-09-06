# Motion-Aware Target Resolver Candidate

**Date:** 2026-08-31

## Outcome

The approved gate adds a case-study-local, pure PHP resolver for a bounded
private target set. It combines WGS84 distance progress with OwnTracks motion
state but does not mutate or read live state itself.

## Read-only evidence

The three existing sources share an archived six-state motion profile. They do
not expose a separate speed variable, and bounded position-archive samples do
not contain velocity or course. Exact state counts, timestamps, coordinates,
tracker identities and histories were intentionally discarded.

## Candidate behavior

- accepts only quality-approved, recent positions;
- computes ground and target-closing speed geodetically in WGS84;
- carries the last activity change forward with a strict age bound;
- rejects stationary and activity/speed-conflicting evidence;
- requires approach ratio, net progress, score and runner-up margin;
- returns `ambiguous` instead of guessing; and
- keeps route-aware ETA outside this resolver.

The repository candidate now excludes target candidates at a geodesic distance
of `100000` metres or more before scoring. When neither candidate remains, the
result is `outside-target-radius`; a previous target is not retained. Exact-
boundary, just-inside and farther-target synthetic tests cover the rule. The
active live package does not yet contain this repository revision; live
activation remains separately gated. The rule does not filter path or fit-all
geometry.

The implementation remains private to this case study. Navimow local
coordinates and Euclidean calculations are not accepted by its contract.

## Verification

Synthetic tests exercise both destination directions, activity carry-forward,
stale/future state, stationary state, speed conflict and ambiguous lateral
movement. The full case-study test suite and repository static checks are the
gate acceptance evidence.

## Closed live boundary

No live candidate source is yet linked to target locations. The repository
runtime now reuses exactly two `SharedLocation` references rather than copying
destination coordinates, but the installed module does not yet read
`motionactivities` for ETA. Private reference configuration and live
activation require a separate gate with a fresh package hash and exact
rollback candidate.
