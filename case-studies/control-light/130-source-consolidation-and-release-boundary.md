# ControlLight Source Consolidation And Release Boundary

**Date:** 2026-08-03
**Scope:** Repository-only source recovery and release isolation
**Result:** CONSOLIDATED — LIVE ACTIVATION REMAINS SEPARATE

## Cause

The long-running primary checkout was switched from `main` to
`feature/open-meteo-one-way-publisher` on 2026-08-02 while unrelated,
non-conflicting work remained in its working tree. Git correctly preserved
those uncommitted files across the branch switch. Later ControlLight work then
continued in the same checkout, so its source was technically based on an
Open-Meteo branch even though the ControlLight changes did not depend on that
feature.

This was a workspace-isolation failure, not a merge conflict or a live-runtime
failure. The original dirty checkout remains untouched as recovery evidence.

## Clean Boundary

ControlLight is reconstructed in a separate worktree and branch based on the
current `origin/main`. The consolidation imports only:

- the canonical ControlLight, Hue Wall and Manual-On/Pulse-Off sources;
- their complete executable regression suites and installed-contract fixture;
- the shared per-variable Statistics serialization required by the Hue Wall
  concurrency finding;
- the deterministic ControlLight fileset manifest and regenerated artifacts;
- the MQTT-exporter fileset's generated Statistics copy, source map and hash,
  because that second consumer shares the same canonical helper;
- sanitized ControlLight reports 48 through 129; and
- the current ControlLight overview and narrowly related framework guidance.

Open-Meteo, Navimow and unrelated MQTT-exporter candidate changes from the
original checkout are outside this branch. Only the MQTT exporter's generated
shared-helper closure is synchronized; its runtime source is unchanged. The
Open-Meteo offline-check wrapper receives the repository's established
512-MiB PHPStan limit after the unmodified `origin/main` wrapper exhausted the
local 128-MiB default; no module source changes. The global changelog receives
one consolidated ControlLight entry instead of replaying the original
interleaved working-tree history.

## State Classification

| Cohort | Repository state | Operational state |
| --- | --- | --- |
| ControlLight v2 facade/runtime evolution | canonical source, tests and reports consolidated | individual live gates remain described by their dated reports |
| Member-confirmed groups | canonical source and group regressions consolidated | activated cohorts remain installation-specific |
| Hue Wall adapter and concurrency handling | canonical source and regressions consolidated | handler/cleanup passed; shared Statistics helper activation is still deferred |
| Manual-On/Pulse-Off adapter | canonical source and regressions consolidated | CL-030 live result remains documented; no new action in this consolidation |
| HS and off-state color contracts | canonical source and conversion regressions consolidated | capability decisions remain per installed wrapper |
| Mired-aware Kelvin matcher | canonical source and full offline matrix consolidated | not live; staging, activation and device regression remain separate gates |
| Generated ControlLight fileset | rebuilt only from the clean branch | not staged or activated by this work |

## Release Rules

The branch may be considered a clean repository candidate only when:

1. generated files match the manifest deterministically;
2. focused ControlLight, Hue Wall, group, Manual-On/Pulse-Off and diagnostics
   regressions pass;
3. the complete repository gate passes;
4. `git diff --check` reports no defect; and
5. the worktree is clean after intentional commits.

These repository checks do not authorize package staging, bootstrap selection,
Symcon restart, wrapper mutation or a device command. The deferred shared
Statistics-helper activation must be resolved before a fileset depending on
that effective global helper is selected live. The Mired matcher then requires
its own command-free activation and targeted Z2M functional regression.
