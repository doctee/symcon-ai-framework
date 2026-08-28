# 361 Private Map Capture Result And Local Sanitizer Recovery

**Case study:** Navimow native IP-Symcon module

**Status:** Single command-free cloud capture consumed; private geometry valid;
local sanitizer false positive recovered without network

**Date:** 2026-08-27

## 1. Objective And Boundary

This step records the single live attempt authorized after steps 359 and 360,
diagnoses its post-capture `internal_error`, and completes only the missing
sanitized postprocessing from existing private files.

The live attempt is consumed. No retry is authorized or required. The recovery
performs no DNS lookup, authentication, device registration, map read, Symcon
access, message transport or mower command.

No credential, token, account identifier, mower identifier, map identifier,
coordinate, polygon, area value, zone name, private host or installation path
is copied into this public report.

## 2. Live Attempt Evidence

The dedicated second-account credentials were entered locally in the Mac
terminal. The process executed exactly one bounded request for each required
operation:

| Operation class | Attempts |
|---|---:|
| Passport login | 1 |
| Device registration | 1 |
| Vehicle discovery | 1 |
| Current location | 1 |
| Map-list fallback | 0 |
| Plain map detail | 1 |
| Token refresh | 0 |
| Message transport | 0 |
| Write endpoint | 0 |
| Mower command | 0 |

The map identifiers were available from the location response, so the optional
map-list fallback was not used. Plain map detail remained within the 4 MiB
limit.

The private session contains mode-`600` raw evidence, a reduced private
projection and a positive geometry-validation report. The exclusive lock was
removed. The stable private app identity was retained as designed. Tokens were
not intentionally persisted.

## 3. Observed Terminal Result

The operator observed:

```text
Private map capture stopped: internal_error
```

The immutable attempt ledger ended as:

```text
state=FailedCleanly
outcome=internal_error
```

This outcome occurred after `map-detail`, projection and geometry validation
had all completed. It was therefore not an authentication, cloud transport,
map availability, reducer or cleanup failure.

## 4. Root Cause

The final privacy scan compared every private value as a raw substring against
the complete serialized sanitized JSON document. The actual `map_id` happened
to be a one-character string. That character also occurred in the public
numeric schema version, producing a false positive even though the identifier
was not retained in a sanitized field.

The report itself was allowlisted and contained no private identifier. The
fault was the comparison domain: serialized keys and non-string JSON scalars
were searched together with actual string values.

## 5. Sanitizer Correction

The private sanitizer now:

- scans only string-valued report fields for private string retention;
- uses exact equality for private strings shorter than eight characters;
- retains substring detection for longer credentials and identifiers;
- continues to reject every forbidden key independently;
- includes a regression test proving that private string `"1"` does not match
  numeric `schemaVersion: 1`;
- still rejects a real private string placed in a sanitized value.

This correction does not broaden the public report schema and does not expose
short identifiers.

## 6. Network-Free Postprocessing Recovery

A dedicated recovery mode processed only the one existing private session. It
required:

- absent capture lock;
- exactly one session;
- original `FailedCleanly/internal_error` ledger;
- the exact attempt vector from section 2;
- one consistent mower and map identity across the retained private files;
- a mode-`600` valid stable identity;
- accepted geometry and a format-version-1 private projection;
- absent sanitized target files.

It wrote only:

```text
sanitized/map-structure-report.json
sanitized/postprocess-recovery-report.json
```

Both files have mode `600`. The original ledger, raw files and private
projection were not rewritten. A second recovery invocation is rejected with
`recovery_output_exists`.

The recovery report preserves both truths:

```text
originalCaptureOutcome=internal_error
localPostprocessingOutcome=completed
networkRequestsDuringRecovery=0
```

## 7. Privacy-Safe Geometry Result

The recovered structure-only evidence establishes:

- plain map detail was present and within the byte limit;
- at least one zone and valid zone identifiers were present;
- all accepted boundaries passed the existing reducer checks;
- reported area information was present;
- a charging-station point was present;
- obstacle and vision-off-area fields were structurally represented;
- a private geometry projection was created;
- no private values were retained in the sanitized report;
- command and write-endpoint attempts remained zero.

This proves that the private app-cloud map source is technically usable for the
next private calibration analysis. It does not yet prove coordinate scale,
orientation, long-term frame stability, zone-name equivalence or suitability
for public storage.

## 8. Cleanup And Retention State

| Item | State |
|---|---|
| Capture lock | absent |
| Process-local credentials | cleared |
| Persisted token bundle | absent by implementation and evidence |
| Stable private app identity | retained intentionally |
| Vendor-side session closure | unproven |
| Raw private map response | retained pending bounded review |
| Private reduced projection | retained pending calibration |
| Sanitized structure reports | retained |

Real garden geometry is identifying private data. Raw and reduced geometry
remain only in ignored private storage and must not be attached to an issue,
pull request or chat. Deletion or longer retention requires a separate decision
after calibration evidence is extracted.

## 9. Updated Private Tool Binding

| Relative private file | SHA-256 |
|---|---|
| `capture-private-map-readonly.sh` | `2ae20c15680785a56d7c003d248638a235cee8070aeddcc6359e97b21d6dd377` |
| `capture_private_map_readonly.py` | `de7d570d9fe44c2fb59098a9dd4e32ef7e4cf7c3c49ec92a58f803a8bcbd0016` |
| `reduce-private-map.php` | `49b9030b5d8bde93d294c40104a357b2a9b5598ca0963fda2b00e7452be44f2a` |
| `private-map-requirements.txt` | `f340a26260a1e6559ad71047e1156fcd31c9f5ab96c5d851a0541fd5a8dee65a` |
| `private-map-third-party-notice.md` | `e7b79212636977493beba93208dad2dbec5eb1a5cb7dfb5db3a3f8b91e8bb1bc` |

The post-recovery live preflight reports a valid retained identity and absent
lock. Static policy, reducer, sanitizer, synthetic crypto and dependency checks
all pass.

## 10. Architecture Decisions

### AD-NAV-361-01: Preserve the original failure ledger

**Decision:** Do not rewrite `internal_error` to `completed` after correcting
the local sanitizer.

**Reason:** The original runtime outcome is immutable evidence. A separate
recovery report can prove successful local completion without rewriting
history.

### AD-NAV-361-02: Recover locally instead of repeating the cloud attempt

**Decision:** Reuse the already validated private payload and projection.

**Reason:** All required cloud reads succeeded. Another login or map request
would violate the one-attempt contract and add vendor-side state without new
evidence value.

### AD-NAV-361-03: Compare secrets only against report values

**Decision:** Keep forbidden-key checks separate and scan private strings only
against string-valued report leaves.

**Reason:** JSON syntax, keys and numeric contract constants are not retained
private values. Mixing those domains creates false positives for short opaque
identifiers.

### AD-NAV-361-04: Keep geometry private pending calibration

**Decision:** Retain the raw response and reduced projection temporarily under
ignored mode-`600` storage.

**Reason:** The next step must assess frame, station anchor and zone structure
without another vendor call. Public artifacts need only structure-level
findings.

## 11. Gate Decision And Next Step

| Gate | Result |
|---|---|
| Single cloud attempt | **Consumed once** |
| Authentication and fixed map reads | **PASS** |
| Mower-command-free contract | **PASS** |
| Geometry reducer | **PASS** |
| Original live sanitizer outcome | **FAIL false positive** |
| Corrected sanitizer regression | **PASS** |
| Network-free local recovery | **PASS** |
| Credential cleanup and lock removal | **PASS** |
| Vendor-side session closure | **Unproven** |
| Retry | **NO-GO and unnecessary** |
| Productive or Symcon map integration | **NO-GO** |

The recommended next step is a private geometry calibration review using only
the retained reduced projection. It should compare zone topology, station
anchor, coordinate ranges and known manual zone semantics without publishing
vertices or names. From that review, SAEF can decide whether the vendor map is
an authoritative calibration source, a one-time bootstrap aid, or only
supporting evidence for manually maintained polygons.
