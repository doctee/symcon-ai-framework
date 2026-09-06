# SharedLocation ETA Integration

**Date:** 2026-08-31

## Outcome

The repository runtime reuses the existing provider-neutral `SharedLocation`
contract for the two ETA candidates. It contains no parallel destination
coordinate configuration and introduces no general SAEF map or location API.

## Read-only precondition evidence

A bounded Symcon MCP probe confirmed exactly two active `SharedLocation`
instances. Their public wrapper was callable and both descriptors reported a
valid stable key, WGS84 numeric shape and time zone. The probe discarded all
instance IDs, names, keys, coordinates and descriptor values.

## Runtime contract

The OwnTracks module accepts exactly two private positive instance references.
For each reference it:

1. verifies that the instance exists;
2. verifies the exact `SharedLocation` module GUID;
3. bounds the descriptor to 2048 bytes;
4. requires a successful descriptor with a valid stable key;
5. validates WGS84 coordinates and the IANA time zone locally; and
6. registers the instance as an IP-Symcon reference.

The reference list is reconciled through the existing runtime reference
ownership. It is private configuration and is absent from this case study.

## Activity and ETA data flow

Activity archives are read only when both target locations are configured and
the selected date is today. The adapter reads the bounded day window plus at
most one preceding activity change, with request-generation cancellation after
the activity phase. Historical day views therefore incur no activity reads.

The resolver combines quality-approved WGS84 positions with the bounded
activity evidence. When one destination is selected, its robust closing speed
is passed explicitly to `OwnTracksEtaProjector`. The resulting strategy is
`geodesic-target-closing-speed`, remains `routeAware=false` and is presented as
`Diagnostic ETA`. Ambiguous, stationary, stale or conflicting evidence yields
no target and no ETA.

The repository runtime candidate now requires each target to be at a geodesic
distance strictly below `100000` metres from the latest quality-approved
current position before selection. If neither candidate qualifies, it returns
`outside-target-radius` and does not retain a previous destination. The active
live package does not yet enforce this new repository revision. Live activation
remains separately gated; path rendering is unaffected.

## Rollback and closed boundary

This gate changes repository and generated package artifacts only. It neither
changes the two shared locations nor the OwnTracks instances, logging,
archives, provider policy or live visualization. Live activation requires a
fresh package hash, a byte-exact rollback candidate and a separately authorized
configuration of the two existing location references.
