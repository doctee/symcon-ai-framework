# Overview Controls Live Activation

**Status:** Exact-package activation and independent structural postflight
complete; physical acceptance pending

**Date:** 2026-09-01

## Authorized Scope

The gate authorized private transfer and activation of the repository-accepted
overview-controls package. It did not authorize configuration changes, dynamic
tile loading, provider publication, a new tile revision, a history restriction,
commit, publication or retained-artifact cleanup.

The transaction was required to preserve:

- all three existing OwnTracks source instances and their variables;
- Archive Control logging and archive contents;
- module and provider configuration;
- the WebHook inventory and Connect protection boundary;
- the existing visualization links; and
- the immutable private basemap authority.

## Preflight And Transfer

The live channel was Symcon MCP. The first validation attempt stopped before
transfer because its local type expectation for the accuracy role was too
narrow. A read-only, anonymized role/type probe established the existing
integer accuracy contract for all three sources; it returned no ObjectIDs,
tracker identifiers, coordinates or movement data. The corrected preflight then
passed with one healthy pilot, three distinct configured sources, active
logging for the required roles, one Archive Control, one WebHook Control and a
healthy Module Control.

The candidate identity was
`cba7f1c722f6d79e30962ce1259c4dc47b984f7be3fdfe9d0b9942e527004f1d`.
The transfer archive contained exactly 29 files. The receiving host verified
the archive byte length and SHA-256 digest before extracting it into a new
inactive staging directory. Extraction rejected absolute paths, parent
traversal, backslashes, duplicate entries, directories, links and any inventory
outside the signed fileset. Every staged payload was then independently checked
against its declared size and digest.

## Atomic Activation And Rollback Boundary

Immediately before activation the transaction rechecked the complete package,
configuration, provider, source/logging, WebHook and link evidence. It wrote an
exclusive byte-exact configuration backup, retained the complete active
29-file package under its immutable identity, atomically moved the verified
candidate into the active module path and requested one targeted module reload.

No module property was written and no `ApplyChanges` operation was issued. The
transaction included automatic restoration of the retained package and a
targeted reload if any activation postcondition failed. No rollback was needed.

## Independent Postflight

An independent second probe confirmed:

- the candidate is the active exact 29-file package;
- the preceding exact 29-file package and byte-exact configuration backup are
  present and independently valid;
- the pilot remains healthy;
- module and provider configuration are byte-identical to the preflight;
- the three source contracts, datatypes and logging states are unchanged;
- WebHook Control remains unchanged and contains no persistent
  OwnTracks-position-map hook;
- both existing visualization links are unchanged;
- the delivered browser bootstrap contains exactly the three configured
  sources and no external-path selection;
- overview mode, disabled overview controls and direction-arrow rendering are
  present;
- the content-security policy remains same-origin and contains no external tile
  authority; and
- neither a failed candidate nor an unconsumed staging directory exists.

The verified transfer archive remains retained beside the rollback artifacts.
Removing any retained artifact is a separate destructive gate.

## Remaining Gates

Physical Safari/iPad/iPhone acceptance is still required for the live package.
Dynamic provider access, a new or enlarged tile revision, a 30-day selection
limit, commit, publication and cleanup remain separately closed.
