# Tile-Grid Zoom Alignment Live Activation

**Status:** Exact package activation and independent structural postflight complete

**Date:** 2026-09-02

## Approved Scope

The gate authorized repository correction, private package activation and one
live browser call that could disclose the visible position-derived XYZ indices
to the configured OSM Standard provider. It did not authorize a configuration,
provider-policy, OwnTracks-object, archive, logging, visualization-link,
publication, commit, cache-purge or retained-artifact-cleanup change.

## Live Diagnosis

The single authorized browser call reproduced the historical Fit-all gap. The
client started 65 protected tile requests. Thirty requests failed across the
initial and one recovery viewport. Sanitized server state attributed exactly
15 requests in each generation to the selection allowlist, with no provider
budget rejection.

This established that static tiles were only exact successful XYZ hits; they
did not claim a larger coverage area. The actual OpenLayers tile zoom differed
from the rounded view zoom authorized by the client.

## Repository And Synthetic Verification

The renderer now shares one OpenLayers XYZ tile grid between viewport
authorization and the protected image source. The full OwnTracks suite and
the repository-wide SAEF checks passed. A provider-free browser fixture then
loaded 20 of 20 visible tiles with identical authorized, minimum-requested and
maximum-requested zoom levels, no failed request and no retry.

## Controlled Activation

The fresh 36-file package identity is
`a5a4892641c7cc70538f4236a6401b76a18420beaa100889807514c49bb06c42`.
Windows independently verified the 197,261-byte upload digest, all 36 regular
files, all 34 provenance payloads and every payload size and digest.

The first switch stopped at the reload boundary because a GUID-based core
lookup selected a healthy instance that did not implement `MC_ReloadModule`.
Automatic rollback quarantined the candidate and restored the preceding
package. A read-only retry preflight then selected the single healthy Module
Control instance by its advertised function list, not by a guessed identity.
The restaged candidate activated successfully through the exact module
directory reload identifier.

No property was written and no `ApplyChanges()` transaction was performed.
The complete configuration remained byte-identical.

## Independent Postflight

A separate read-only probe verified:

- healthy status `102`;
- the active 36-file identity and all 34 payload hashes;
- the preceding complete identity
  `44ded6323a8030af194af22df66f0e94cf74bfdd59b9aa1ee006b0221850d402`
  as the rollback package;
- the installed shared-tile-grid contract and absence of rounded view zoom;
- unchanged same-origin XYZ and OSM-on-miss modes;
- no stage or failed-candidate residue; and
- retained verified upload, byte-exact configuration backup and provider cache.

The one-time live-browser authorization was exhausted by the diagnostic call,
so no second provider-reaching browser call was made after activation. A
physical device retest remains the presentation acceptance for the reported
historical Fit-all case.

No ObjectID, source key, tracker identifier, coordinate, tile index, movement
history, private origin or host metadata is recorded here. Commit, publication,
cache purge and retained live-artifact cleanup remain closed.
