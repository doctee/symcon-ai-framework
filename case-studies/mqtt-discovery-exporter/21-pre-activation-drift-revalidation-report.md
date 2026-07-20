# 21 Pre-activation Drift Revalidation Report

**Case study:** IP-Symcon MQTT Discovery Exporter
**G6 sub-gate:** Immediate read-only activation drift revalidation
**Result:** PASS
**Date:** 2026-07-15
**Live-system impact:** None

## 1. Scope

This gate revalidated the active minimal SAEF deployment, the inactive staged
exporter fileset, the migrated caller and the running kernel after the corrected
Windows restart coordinator passed its non-activating preflight.

All checks were read-only. No bootstrap, bundle, staged file, script object,
variable, MQTT topic or device state was changed. No service command or restart
was issued.

## 2. Filesystem Evidence

| Check | Result |
| --- | --- |
| Active minimal bundle SHA-256 | Matches canonical artifact |
| Matching staged candidates | Exactly 1 |
| Staged files | 15 of 15 |
| Missing staged files | 0 |
| Additional staged files | 0 |
| Staged source hash mismatches | 0 |
| Staged bootstrap hash | Matches canonical artifact |
| Staged aggregate marker | Matches canonical fileset provenance |

The staged fileset remains inactive. Its aggregate identity is
`bbc44c98500895319cf862f0dacc6492cadac2aedb0c6e3e302ec2c9027cfb2c`,
and its bootstrap identity is
`3567e73a1ac93743f6daa5a21dcd208c3a7845e4f391ebca31c9bf86839725c9`.

## 3. Runtime and Caller Evidence

| Check | Result |
| --- | --- |
| Kernel runlevel | `10103` |
| Kernel start identity | Unchanged from coordinator preflight |
| Migrated caller SHA-256 | Matches private approved snapshot |
| Expected minimal SAEF functions | 7 of 7 |
| Total loaded SAEF functions | 7 |
| Unexpected SAEF functions | 0 |
| Exporter core class loaded | No |
| Exporter runtime class loaded | No |

Caller content was read through `IPS_GetScriptContent`, hashed in the external
PowerShell process and then cleared from the temporary byte buffer. Runtime
namespace checks executed in an isolated read-only script context and returned
only counts and Boolean class-presence facts.

## 4. Gate Decision

The complete immediate pre-activation drift revalidation is **PASS**. The
active runtime still has the exact expected minimal namespace, while the single
staged exporter candidate remains complete, canonical and inactive.

The next action would replace the active bootstrap selection and invoke the
external state-based restart coordinator with its reviewed rollback source.
That is a state-changing activation transaction and requires a new explicit
authorization. This report does not grant it.
