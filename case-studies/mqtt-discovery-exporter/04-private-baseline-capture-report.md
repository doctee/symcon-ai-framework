# 04 Private Baseline Capture Report

**Case study:** IP-Symcon MQTT Discovery Exporter
**Status:** G2 complete
**Date:** 2026-07-15
**Public content boundary:** Metadata and verification result only

## 1. Purpose

Record that the supplied V4.1-RC2 handover has been preserved as private source
evidence before SAEF refactoring begins.

This report does not publish the baseline source, installation history or
configuration. It records only the evidence required to make subsequent changes
traceable.

## 2. Captured Artifacts

The ignored private overlay contains:

- a byte-for-byte copy of the supplied Markdown handover;
- an unmodified extraction of the `HAExportV41_Config.php` code block;
- an unmodified extraction of the `HAExportV41_Exporter.php` code block;
- a private migration-boundary document that quarantines legacy cleanup.

The private baseline is evidence only. It is not an installable SAEF release.

## 3. Integrity Record

SHA-256 values captured at G2:

```text
d36a81ddca14502d5d5a4f8baa4750e9f09ba2928eef27ae9a895f8ced71a28e  handover
affed677b0cb89af152a8ca417449284f394312059153079232a70843e657853  extracted configuration
725358aca00cc7990228699ffc467bbf4e0ff4741daf9dcfd10b865de68abf00  extracted exporter
```

The private handover copy matched the originally supplied file byte for byte at
capture time.

## 4. Extraction and Syntax Verification

The two PHP files were extracted mechanically from their named fenced code
blocks. No source content was intentionally changed.

Both extracted files passed `php -l` with PHP 8.5.8.

This result proves syntax only. It does not prove IP-Symcon runtime behavior,
Home Assistant compatibility, cleanup safety or equivalence to the source that
was previously exercised in the live installation.

## 5. Migration Quarantine

The private RC2 baseline contains installation-history-specific legacy object
and retained-topic cleanup. No executable migration manifest was promoted or
newly created.

Any later private migration tool requires an exact allowlist, dry-run default,
one-shot activation, type and parent checks, repeatability and captured
non-production inventory evidence. Broad prefix- or name-based deletion remains
unapproved.

## 6. Provenance Status

The source was supplied by the repository owner from an earlier AI-assisted
engineering conversation. No third-party code attribution was present in the
handover.

This does not yet establish distributable authorship. Public implementation
must be reconstructed through reviewed SAEF contracts and helpers. A provenance
and license check remains part of the future reference release gate.

## 7. Gate Decision

**G2 result: Complete.**

The exact input evidence is preserved privately, syntax-checkable and separated
from public SAEF artifacts. G3 may now implement and test the canonical helper
for variable-triggered script events.
