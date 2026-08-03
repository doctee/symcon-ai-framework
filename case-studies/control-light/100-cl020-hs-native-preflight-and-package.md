# CL-020 HS-native Preflight and Package

**Gate:** Fresh read-only live preflight and local package preparation
**Result:** PASSED — LIVE FILESET NOT PRESENT
**Date:** 2026-07-27
**Live impact:** None

## Outcome

CL-020 remains byte-exactly on its known legacy wrapper. Its four facade
variables, four active feedback events, target variables, action ownership and
presentation identities are unchanged. Target, Alexa and scene instances are
healthy, and the alarm is currently inactive.

The local Home Assistant Entity repair is still byte-exactly present and its
target property remains registered with the expected false value. The Alexa
consumer still references STATE, DIMMER, COLOR_TEMPERATURE and COLOR. The scene
continues to reference facade DIMMER and COLOR. No consumer redesign is needed
for this migration.

The new immutable ControlLight fileset is not present on the live installation.
This is the expected closed-gate result: a local package could be completed,
but nothing was staged or selected.

## Package

The private package binds:

- the exact current legacy wrapper source;
- the previously verified byte-exact rollback artifact;
- the new HS-native wrapper candidate;
- the immutable generated fileset and bootstrap hashes;
- every facade, target and event identity;
- target, Alexa, scene and repaired module hashes; and
- a fail-closed staging, activation, reconciliation and test sequence.

The candidate explicitly selects reported brightness, the target's 0–255
brightness scale, the previously proven Kelvin tolerance and the new
0.5-degree/0.5-percentage-point native HS bounds.

## Probe Correction

One initial read-only reference probe called the instance-reference API with
variable IDs and produced four warnings. It performed no mutation or device
action, and none of its reference results was used. The corrected probe used
the Alexa and scene instance IDs, completed without warnings and confirmed the
consumer relationships described above.

## Separate Gates

The next permitted step is inactive fileset staging with independent hash
readback. It must not change the wrapper, runtime selection, topology or light
values.

Wrapper activation remains a later gate requiring a fresh delta preflight and
explicit approval. The real-device color test is separate again and must prove
both native HS confirmation and unchanged brightness before Alexa and scene
regression. CL-021 remains outside this package and color-disabled.
