# Source And Presentation Live Correction

**Status:** Live package and configuration postflight passed; physical Safari
acceptance failed at host-control interaction

**Date:** 2026-08-31

## Authorized Change

The separately authorized transaction activated fileset
`4a4f8d69cd30702b68b8244b2f7413f297f4e0d92211db110d156d8882824a45`
once and changed only two pilot properties:

- the three OwnTracks sources are ordered `LT`, `CT`, `MT`, making `LT` the
  default; and
- the maximum permitted line gap is 3,600 seconds.

The explicit `External path` choice remains a fourth presentation source. It
reads only its current timestamped position, does not enter OwnTracks fit-all,
does not read an archive and does not participate in ETA.

No OwnTracks instance, logging state, archive, existing map, persistent
WebHook Control entry, provider configuration or visualization link was
changed.

## Transaction And Rollback

The candidate was transferred as a bounded archive, verified byte-for-byte,
extracted through an explicit path-safe stream boundary and checked against
its 29-file manifest before activation. The active package was moved to a new
immutable rollback directory before the candidate was made active. The prior
configuration was also retained byte-for-byte.

The targeted Module Control reload succeeded. The two property changes were
then applied together with one `ApplyChanges()` call. The automatic failure
path was prepared to restore both the retained package and the exact prior
configuration.

## Independent Postflight

An independent read-only postflight proved:

- the new 29-file package is active and the pilot remains healthy;
- the previous 29-file package and exact previous configuration remain
  available for rollback;
- source order is `LT`, `CT`, `MT` and the default browser source is `LT`;
- the browser bootstrap contains the three OwnTracks sources followed by the
  explicit external path source;
- the maximum gap is 3,600 seconds;
- source-instance configuration and required logging states are unchanged;
- provider configuration, both additive links and persistent WebHook Control
  configuration are byte-identical;
- persistent WebHook Control still contains no candidate hook because the
  module owns the native volatile Strict hook; and
- the visualization CSP still permits only same-origin tile access and names
  no external tile authority.

## Browser Boundary

The internal browser could reach the Connect origin but remained at the
Symcon loading screen before the visualization document appeared. Waiting and
one controlled reload produced no browser warning or error and no usable map
DOM. A local fallback URL resolved to a different visualization and was not
used as evidence.

Consequently, the live activation and structural security checks are passed,
but this gate does not claim a completed desktop, iPad or iPhone browser
acceptance. A physical Safari check must still confirm source interaction,
source-local fit-all, line continuity, label decluttering, touch pan and the
compact non-overlapping controls. Browser retries must not broaden into a
different live channel or visualization mutation.

The subsequent physical Safari/Mac check confirmed line, label and pan
behavior, but the selection area remained behind the pointer-active host
chrome. `CT` fit-all and external-path isolation therefore remained untested.
The repository-only follow-up is recorded in
`39-host-controls-and-theme-correction.md`.

## Remaining Gates

Corrective package activation, publication, commit, routing activation,
changes to existing OwnTracks objects and cleanup of retained live rollback
artifacts remain separately gated.
