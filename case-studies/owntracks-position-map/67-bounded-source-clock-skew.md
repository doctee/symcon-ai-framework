# Bounded OwnTracks Source Clock Skew

**Status:** Repository implementation and deterministic verification complete

**Date:** 2026-09-02

## Evidence And Decision

A read-only archive diagnosis of one historical day found that nearly all
otherwise valid OwnTracks observations carried payload times only one to five
seconds ahead of the Archive Control record time. The earlier strict negative
delay rule therefore reduced a 200-observation day to nine rendered points.
This was bounded clock skew, not archive loss.

The case-study-local OwnTracks query now accepts an explicit
`maximumSourceClockLeadSeconds`. The runtime fixes this value at five seconds:

- a lead from one through five seconds remains line and ETA evidence and is
  marked `source-clock-skew-tolerated`;
- a lead of six seconds or more remains `source-time-ahead` and is excluded;
- the core default remains zero, so existing callers do not silently gain a
  tolerance; and
- unknown historical accuracy remains independently marked `unverified`.

The policy applies only to OwnTracks WGS84 observations. It neither changes nor
mixes with the local Navimow coordinate and timing model.

## Verification

Boundary tests prove acceptance at exactly five seconds and exclusion at six
seconds. The complete OwnTracks suite, deterministic 36-file package check,
PHPCS, PHPStan and diff check pass. The resulting package identity is
`a03d09d1cf7cb66544bc125e679d4fec71412dfc5ff96ef591d26301efc08bc6`.

No public helper or general SAEF map abstraction was introduced. The tolerance
remains implementation-local until a second independent consumer demonstrates
the same recurring contract.
