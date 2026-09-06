# Current Overview And Direction Arrows

**Status:** Repository candidate, synthetic responsive browser acceptance and
exact-package live activation complete; physical acceptance pending

**Date:** 2026-08-31

## Accepted Interpretation

The default presentation is now a current-position overview. It displays at
most one timestamped WGS84 point for each of the three configured OwnTracks
sources. Selecting `Path` instead loads the bounded day history only for the
currently selected source and renders it as a line with sampled timestamp
labels.

The former external-path anchor remains backward-compatible runtime
configuration and is not deleted, mutated or reused. It is no longer offered
as a browser source. This separates the three OwnTracks sources from the
unrelated external point while preserving the live rollback boundary.

The requested personal display names remain private source configuration. They
are deliberately absent from public files. Their separately authorized live
change was limited to the three labels in the existing source order.

## Bounded Implementation

The overview:

- reads one current string value per configured OwnTracks source;
- validates timestamp and WGS84 coordinate through the existing case-study
  coordinate validator;
- optionally attributes a current finite accuracy value within the configured
  age limit;
- skips an invalid current value without exposing its payload;
- performs no Archive Control read and produces no ETA; and
- returns no line segments and at most three points.

`Path` retains the existing bounded Archive Control adapter, source-local
fit-all, one-hour line-gap limit, ETA resolver and timestamp decluttering.
Direction is shown by at most twelve small triangle markers per rendered
segment. The arrows use the already projected line coordinates and therefore
add no provider, routing or coordinate-system authority.

## Presentation Refinement

The Source, Day and Path panel is narrower and lower. Day has a fixed compact
desktop width and a smaller proportional mobile width. The navigation panel
uses smaller buttons while retaining distinct touch targets. Day is disabled
in the current overview because that mode has no archive-day semantics and is
enabled immediately when `Path` is selected.

## Synthetic Acceptance

The local dark-theme fixture with a pointer-active host band verified:

- exactly three browser sources and no external-path option;
- three current points and three source-aware time labels by default;
- no overview line, arrows, archive request or ETA;
- one selected-source path with a line and bounded direction arrows;
- Day disabled for the overview and enabled for `Path`;
- compact non-overlapping desktop, iPad-sized and iPhone-sized controls;
- a narrower Day field, native hit targets and no horizontal overflow; and
- unchanged dark-theme, touch-pan, rotation-disabled and same-origin tile
  boundaries.

Runtime, renderer, package, security and performance tests passed again after
deterministic package generation. The subsequent live gate activated that
exact package and changed only the three private source labels. It did not
change the external anchor, provider, hook, link, archive, logging or existing
OwnTracks objects.

## Live Gate Result

The exact package identity and all packaged payload digests were independently
verified before and after activation. The previous exact package, complete
previous configuration and transferred archive remain retained as private
rollback evidence. The independent postflight confirmed:

- active status of the pilot;
- three valid current-position contracts in the existing source order;
- a configuration delta limited to the three private labels;
- unchanged provider configuration and one-hour line-gap policy;
- unchanged persistent WebHook configuration and visualization links; and
- absence of failed-package or staging residue.

Physical Safari/Mac and iPad acceptance must now confirm the private labels,
default overview, selected-source path, arrow direction and compact controls.
Commit, publication and retained-artifact cleanup remain separate gates.
