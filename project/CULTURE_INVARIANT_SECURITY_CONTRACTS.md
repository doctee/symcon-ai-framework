# Culture-Invariant Security Contracts

Status: Normative rule and channel-v8 approval correction implemented;
follow-up inventory remains gated

## Purpose

This document records where ambient locale can affect SAEF bytes or decisions.
It complements Rule RS-001.38. It is not authority to change a live channel,
apply retention or install a deployment profile.

The repository audit used channel-v8 main commit
`75a41a391afd4655fe5935c267dda6e108ddab61` as its clean baseline.

## Confirmed incidents

| Area | Failure | Resolution |
| --- | --- | --- |
| OwnTracks package identity | PowerShell `Sort-Object` changed relative-path order under the Windows culture | Replaced with `StringComparer.Ordinal`; recorded in `case-studies/owntracks-position-map/84-target-allowlist-preflight.md` |
| OwnTracks Windows qualification harness | An older copied identity implementation retained culture-dependent sorting after the production correction | Harness corrected independently; recorded in `case-studies/owntracks-position-map/90-target-allowlist-installation-windows-qualification.md` |
| Channel-v8 scope-bound approval | PHP canonicalization was bytewise, while the Windows plan/HMAC implementation used current-culture sorting; the producer and runner could share and mask the defect | Windows now uses ordinal sorting and must reproduce a fixed PHP-derived JSON and SHA-256 vector under `en-US`, `de-DE` and `tr-TR` |

The repeated harness failure establishes a process rule: a corrected production
implementation is not enough. Every copied fixture, qualification helper and
recovery tool must be searched and independently tested.

## Current disposition

| Surface | Security relevance | Disposition |
| --- | --- | --- |
| scope-bound approval canonical keys, plan SHA-256 and HMAC | authorization and replay protection | corrected in the runner and Windows qualification |
| approval-profile exact property checks | policy schema validation | corrected to ordinal comparison |
| PHP approval canonicalization | reference bytes | already uses `SORT_STRING`; fixed digest vector added |
| browser `localeCompare` in the OwnTracks marker layout | presentation only | permitted while it remains outside state, identity, deployment, retention and rollback inputs |
| numeric cleanup ordering such as directory-length descending | cleanup mechanics, not string identity | permitted only while the comparison is numeric and cannot affect authorization or retained identity |

## Separately gated follow-ups

These findings are not changed by the approval-contract correction:

1. The generic deployment-retention inventory and the OwnTracks adapter-owned
   retention inventory still contain default PowerShell string sorting. Before
   cross-root retention or any changed deletion authority, names, paths and
   hashed review-plan entries need explicit ordinal ordering plus fixed
   mixed-case vectors.
2. Gateway and OwnTracks retention timestamps use general `DateTime.Parse()`
   on protocol timestamps. Before changing those contracts, use an invariant,
   exact round-trip format and verify timezone normalization with fixed vectors.
3. Any future deployment-channel runtime upgrader must ordinal-sort retained
   backup paths and prove interrupted-transaction recovery under multiple
   cultures. Its qualification remains distinct from one-click profile
   qualification.
4. If an ASCII-only identifier or field-name contract is widened to Unicode,
   define normalization and add non-ASCII cross-runtime vectors first. Ordinal
   sorting alone does not define Unicode normalization.

Cross-root retention remains blocked by its own contract and approval gate.
This inventory grants no deletion, installation, restart, provider,
publication or live-mutation authority.

## Review checklist

- Search every production, fixture, copied harness, recovery and rollback path
  for default string comparison.
- Trace whether the resulting order reaches JSON, a hash, HMAC, manifest,
  identity, plan, backup or deletion decision.
- State the allowed alphabet and case semantics.
- Use one independently derived expected byte sequence and digest.
- Run the exact Windows source under at least `en-US`, `de-DE` and `tr-TR`.
- Keep display-localized ordering structurally separate from security inputs.
- Re-run Windows PowerShell 5.1 qualification after any exact source change.

## Related

- `standards/SYMCON_STANDARDS.md`, Rule RS-001.38
- `adr/ADR-0010-use-scope-bound-one-click-deployment-approval.md`
- `project/SCOPE_BOUND_DEPLOYMENT_APPROVAL.md`
- `project/CHANNEL_V8_ONE_CLICK_WINDOWS_QUALIFICATION.md`
- `project/STANDALONE_MODULE_CROSS_ROOT_RETENTION.md`
