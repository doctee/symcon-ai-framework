# MQTT Latest-Command-Wins Live Adoption Result

**Status:** Completed
**Scope:** Sanitized terminal evidence for V05-001
**Date:** 2026-09-16

## Purpose

This report closes the operational evidence gap left by the repository-only
implementation and adoption design. It records only reusable behavior and
omits installation identifiers, topics, object paths and protected evidence
locations.

## Runtime And Ownership Adoption

The deterministic current MQTT exporter fileset was staged, preflighted and
activated through the restricted Channel-v8 runtime-fileset path. The guarded
activation replaced only the reviewed bootstrap pointer, performed the
contracted service restart, passed runtime-health and mirror postflight, and
required no rollback.

The separately reviewed owner transaction then migrated exactly two exporter
owners and ten events. Its one-use claim completed, independent read-only
inspection reported the migrated state, and the temporary lifecycle script was
removed with final name and parent-child absence checks. No MQTT publication,
device action or unrelated object mutation was part of the migration.

## Functional Supersession Evidence

A passive two-snapshot observation produced no natural command traffic and was
therefore accepted only as stable, non-conclusive evidence. The functional
gate remained separate.

The later supervised scenario started with the selected reversible pilot off.
An independent temporary producer submitted `ON`, waited 250 milliseconds and
submitted `OFF`. The final command restored the starting state. Terminal
readback established all of the following:

- both commands were published through the intended client transport;
- the newest command completed with authoritative state confirmation;
- exactly one intermediate command was classified as superseded;
- the command counter advanced once for the accepted generation;
- the failure counter and bounded error history remained unchanged;
- no compensation command was required; and
- the temporary producer was absent after the scenario.

The result demonstrates latest-command-wins for rapidly superseding commands
without reclassifying genuine action rejection or confirmation timeout as
success. Those negative paths remain covered by deterministic tests.

## Issue Acceptance

The implementation and evidence satisfy the V05-001 acceptance boundary:

- deterministic tests cover rapid supersession and genuine failures;
- the newest command is confirmed and published;
- superseded intermediate work has dedicated diagnostics;
- Registry state remains bounded and contains no unbounded queue;
- the public helper API is unchanged; and
- the complete repository check passes in the release-candidate workstream.

The consumed migration, command and postflight plans are terminal. This report
does not authorize another command, migration, restart, cleanup or release.
