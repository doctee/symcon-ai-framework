# Physical UI Live Activation

**Status:** Exact-package activation and independent structural postflight
complete; physical acceptance pending

**Date:** 2026-09-01

## Authorized Scope

The gate authorized a fast-forward of the isolated worktree, renewed repository
verification, private transfer and exact package-only activation of the accepted
physical-UI follow-up. It did not authorize configuration changes, provider or
tile-set changes, publication, commit or retained-artifact cleanup.

The activation had to preserve all three OwnTracks source contracts, logging
states and archives, the provider and Connect protection boundary, both
visualization links and the existing private immutable tile authority.

## Repository And Transfer Evidence

The worktree was fast-forwarded to the current `origin/main` without overlap;
the upstream changes were confined to Navimow. The OwnTracks suite, performance
checks, module-fileset check, OpenLayers bundle check, PHPCS, PHPStan and diff
check passed afterward. The deterministic 29-file identity remained
`8561019a0b3946e638f88cb5369a119da7702e839df5e84dea7ac5bafa207230`.

The private transfer archive contained exactly 29 files and 172114 bytes. Its
SHA-256 digest was verified locally, after the bounded chunk transfer and again
before extraction. The server extracted only relative files below the expected
package prefix into a new inactive staging directory. Absolute paths, parent
traversal, backslashes, directory entries, duplicates, links and files outside
the signed inventory were rejected. Every staged file was independently checked
against the signed byte length and digest.

## Atomic Activation And Rollback Boundary

Immediately before mutation, the transaction rechecked the active and staged
package identities, byte-exact configuration, provider policy, source roles and
logging, WebHook configuration, visualization links, transfer digest and free
identity-qualified transaction paths.

It then wrote an exclusive byte-exact configuration backup, retained the
complete preceding package under its immutable identity, atomically activated
the verified staging package and requested one targeted module reload. No
property was written and no `ApplyChanges` operation was issued. The transaction
contained an automatic package restoration and reload path; it was not needed.

## Independent Postflight

A separate read-only postflight confirmed:

- the new exact 29-file package is active and healthy;
- the preceding exact 29-file package, configuration backup and verified upload
  archive remain retained;
- configuration and provider hashes are byte-identical to preflight;
- all three source roles, datatypes and logging states are unchanged;
- WebHook configuration and both visualization links are unchanged;
- no persistent candidate hook was introduced;
- the browser bootstrap contains exactly the three configured sources and no
  external-path source;
- selected-source ETA, dated observations, overlay-safe fit diagnostics and the
  current-overview mode are present;
- the content-security policy remains default-deny with same-origin network
  access and no external tile host; and
- neither a staging nor a failed-candidate residue exists.

No ObjectID, tracker identifier, coordinate, target label or movement history
was emitted into repository evidence.

## Remaining Gates

Physical Safari/iPad/iPhone acceptance remains required. Dynamic provider
access, a new tile revision, commit, publication and cleanup of the retained
upload, rollback package or configuration backup remain separately closed.
