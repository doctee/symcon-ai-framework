# Bounded Retry Live Activation

**Status:** Exact package-only activation complete; independent structural and
security postflight passed; physical tile-retry acceptance pending

**Date:** 2026-09-02

## Approved Scope

The gate authorized the private transfer and live activation of the reviewed
36-file package from step 61. It did not authorize a configuration change,
repository commit, publication, cache purge, retained-artifact cleanup,
OwnTracks-object change, archive/logging change, visualization-link change,
WebHook change or provider-budget change.

## Drift Gate And Transfer

The fresh preflight verified one healthy pilot instance, the exact preceding
package and complete configuration, the unchanged provider envelope and a free
transaction boundary. It also confirmed the actual split between the protected
same-origin gateway and outbound provider budgets:

- gateway: 240 requests per minute and four concurrent requests;
- provider: 30 requests per minute and two concurrent requests; and
- at most 24 provider misses per accepted viewport.

The 195,452-byte archive was transferred in six bounded private chunks. The
receiver verified the archive SHA-256 digest, exactly 36 safe regular-file
entries, 34 provenance-map payloads, every payload size and digest, and the
package identity before staging it.

## Atomic Package Activation

Immediately before the package switch, the transaction rechecked healthy
status, the preceding package identity and the byte-exact complete
configuration. It retained a private byte-exact configuration backup, moved
the old package to an identity-qualified rollback boundary, activated the
verified stage and performed one targeted Module Control reload.

No property was written and no `ApplyChanges()` configuration transaction was
performed. Automatic package rollback was armed for every failure boundary but
was not needed.

The active package identity is
`7a9cc8562606eb08357553841590cb3665557476e8420b53c11df64b575c4b71`.
The immediately preceding identity
`20f0aba9c8d7c033365ccf168f314a1b81bf1a7b6fcc60c327e81da768719231`
remains available as the complete rollback package.

## Independent Postflight

A separate read-only Symcon MCP probe verified:

- healthy status `102` and the exact active and rollback package identities;
- the complete configuration and provider envelope remained byte-identical;
- the gateway remained at 240 requests per minute and concurrency four;
- the provider remained at 30 requests per minute and concurrency two;
- the 24-miss viewport budget, token lifetime, byte limit, tile limit,
  viewport ring and negative-cache lifetime remained unchanged;
- WebHook Control remained byte-identical;
- three source contracts retained type triples `[3, 1, 1]` and all nine
  source variables remained logged;
- two visualization links remained unchanged;
- no target-specific stage or failed-candidate residue remained; and
- private upload, configuration backup and rollback package were retained.

The existing provider cache was preserved with 51 regular files, 1,710,062
bytes and no symbolic links. The gate neither opened the map nor deliberately
caused another private viewport to be sent to the provider.

## Privacy, Acceptance And Rollback Boundary

No ObjectID, source key, tracker identifier, coordinate, tile index, private
movement history, private path, Connect origin or host metadata is recorded in
this evidence.

Physical iPad/Safari acceptance remains necessary. For a previously incomplete
historical `Path`, the first eligible miss may require the one bounded retry
after about 65 seconds when the shared provider minute window was already
consumed. The unchanged viewport should then fill without a zoom or pan. The
100-by-100-metre minimum `Positions` fit and compact edge-aligned overlays also
remain part of that physical check.

Restoring the retained previous package is the rollback action. It is not
needed while the postflight remains healthy and requires a separate gate unless
invoked automatically by a future activation transaction. Commit,
publication, cache purge and retained-artifact cleanup remain closed.
