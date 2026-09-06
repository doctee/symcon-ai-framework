# Fit Viewport Live Activation

**Status:** Controlled package activation and independent structural postflight
complete; physical Safari/iPad acceptance pending

**Date:** 2026-09-01

## Approved Scope

This gate activated only the regenerated 36-file package from step 57. It did
not change module or provider configuration, WebHook configuration, source
mappings, archives, logging, visualization links, static tiles or cache
contents. No repository commit or publication was performed.

## Transfer And Activation

The preflight re-established the single healthy owner, exact active package,
complete configuration and provider hashes, WebHook and visualization-link
fingerprints, all three source contracts and nine logging states, canonical
private authority, bounded provider fallback and a residue-free target.

The 194,947-byte archive was transferred in six bounded chunks. Before
staging, the receiver verified its SHA-256 digest, the exact set of 36 safe
regular-file paths, every byte length and file digest, and the package fileset
identity. A byte-exact private configuration backup was then created.

The active package was moved atomically to the rollback boundary, the verified
stage became active, and one targeted module reload completed successfully. No
automatic rollback was required.

## Independent Postflight

A separate read-only Symcon MCP probe verified:

- healthy status `102` and the intended package active;
- the immediately preceding package at the exact rollback boundary;
- byte-identical complete configuration and provider configuration;
- unchanged WebHook configuration and two visualization links;
- three source mappings with variable type triples `[3, 1, 1]` and all nine
  variables still logged;
- unchanged ephemeral-header capability protection with verified Connect
  forwarding and header canonicalization;
- the canonical private XYZ authority and unchanged bounded OSM-on-miss
  policy;
- no staging or failed residue; and
- intact upload, configuration backup and rollback artifacts.

The existing provider cache remained present and safe. It was neither reset
nor populated by the activation. Its aggregate postflight state contained 23
regular files and 744,322 bytes with no symbolic links.

## Privacy And Retention

No ObjectID, source or tracker identifier, coordinate, tile index, private
movement history, private path, Connect origin or host metadata is recorded in
this evidence. The 195-KiB local transfer workspace was removed after the
postflight. The live upload, configuration backup and immediately preceding
package remain retained for rollback; removing them requires another gate.

## Physical Acceptance

The remaining physical test is deliberately narrow: in Safari or on iPad,
select the previously affected historical path and use `Fit all`. Eligible
missing tiles must now load for the fitted viewport without requiring an
additional zoom. A following resize, pan or zoom must retain protected-tile
state and may request only the newly authorized bounded viewport.

Commit, publication, cache purge, static-tile replacement and OwnTracks or
visualization-object mutation remain closed.
