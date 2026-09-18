# Z2M Directional Mired Request Matcher

**Date:** 2026-09-18

**Scope:** Shared ControlLight confirmation matcher and deterministic fileset

**Result:** IMPLEMENTED AND QUALIFIED OFFLINE — LIVE ACTIVATION SEPARATE

## Reproduction

A real voice request reached the existing ControlLight facade and selected the
configured Z2M color-temperature target. The device accepted the request, but
the authoritative response at the upper Kelvin boundary was normalized from
6500 K to 6535 K. The previous matcher rejected the functional result and
recorded a false confirmation timeout.

A bounded direct facade request reproduced the same command, feedback and false
timeout independently of the voice consumer. This isolated the defect to the
shared confirmation matcher.

## Contract

The existing fixed Kelvin tolerance still runs first. For explicitly
Mired-quantized Z2M targets only, the fallback comparison now mirrors the
directional transport contract:

1. convert the requested Kelvin value to integer Mired by truncation;
2. map the returned Kelvin value to the nearest represented integer Mired;
3. accept only identical Mired values.

No public helper or configuration option was added. The comparison performs no
additional device read, wait or loop. Matter, Home Assistant, Homematic and an
explicit Z2M quantization opt-out retain their previous Kelvin comparison.

## Qualification

The regression suite covers the observed 6500-to-6535 K result, rejection of
adjacent 154-Mired feedback, every integer request from 2000 through 6500 K,
runtime dispatch with one target action and no false timeout, unchanged Matter
behavior and explicit Z2M opt-out.

The candidate and generated distribution use the same source. The immutable
fileset is rebuilt deterministically from the clean worktree and remains subject
to its fileset checks and the complete repository gate.

## Next Gate

No live source changed during this implementation. Staging a new immutable
fileset, command-free activation of the affected wrappers and a targeted live
6500-to-6535 K confirmation are separate controlled steps.
