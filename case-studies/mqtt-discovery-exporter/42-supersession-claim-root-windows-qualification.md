# Supersession Claim-Root Windows Qualification

**Status:** Windows PowerShell 5.1 qualification passed; production gate pending
**Preparation date:** 2026-09-14
**Repository base:** `6065a565b3054482c4c1ecbc7cf2bd73915a8af7`
**Production mutation:** None

## Purpose

The latest-command-wins owner transaction records its one-use approval claim
below a private filesystem root. Repository tests prove exclusive file creation
and ordered claim phases, but cannot prove installation-specific NTFS behavior.
This gate adds the missing Windows PowerShell 5.1 and protected-DACL contract
without contacting Symcon or creating the production root during qualification.

## Reuse Decision

The gate composes the existing SAEF secure child-process contract and the
non-inheriting `SYSTEM`/Administrators ACL pattern already used by the Windows
deployment channel. It introduces no public helper and no channel operation.
The initializer remains MQTT-specific because its fixed path, empty-leaf
rollback and later claim consumer belong to this case study.

The generic Channel-v8 initializer is not extended. The claim root is not a
fileset, deployment, approval-profile or adapter-state root and must not enter
their retention or authority boundaries.

## Fixed Production Boundary

The only production target is:

`%ProgramData%\SAEF\MqttSupersessionOwnerMigrationClaims`

The target parent must already exist, contain no reparse point in its ancestor
chain, use a protected DACL and grant no untrusted principal mutation rights.
The claim root itself must have:

- protected inheritance;
- Administrators as owner;
- exactly `SYSTEM` and Administrators with inheritable full control; and
- no additional access-control entry.

The Symcon service runs as `LocalSystem`, so no deployment-user write grant is
required. Administrators retain inspection and manual-recovery authority.

## Initializer Contract

`Initialize-SaefMqttSupersessionClaimRoot.ps1` supports only `preflight` and
`install`:

- `preflight` validates the fixed path, parent and an existing root without
  creating or changing anything;
- a missing root is reported as `repairRequired=true`;
- `install` requires elevation and the exact confirmation phrase;
- an existing root is verified and never silently re-ACL'd;
- a missing root has one creation call site and receives the exact protected
  DACL;
- a post-creation failure removes only the still-empty leaf, non-recursively;
  and
- failed removal returns `manual_recovery_required` and preserves evidence.

Qualification-only alternate paths and fault injection are accepted only below
a random scratch directory in `%TEMP%`. They cannot select another production
path.

## Windows Qualification

`Invoke-SaefMqttSupersessionClaimRootWindowsQualification.ps1` binds the exact
initializer and `SaefChildProcess.ps1` hashes. It requires Windows PowerShell
5.1 Desktop, parses both files with the native parser and invokes every scratch
initializer scenario through the existing secure child-process function.

The positive matrix proves:

1. exact bounded source identities;
2. Windows PowerShell 5.1 parsing;
3. read-only missing-root preflight;
4. protected root creation; and
5. idempotent read-only postflight.

The negative matrix proves:

1. wrong confirmation stops before creation;
2. an inherited or broad existing DACL is rejected without repair;
3. a path collision is rejected without mutation; and
4. an injected post-ACL failure removes the empty created leaf exactly.

The qualification deletes only its own random scratch tree. Its status fixes
all production, Symcon RPC, owner, event, MQTT, device, service, publication and
retention mutation flags to false.

## Qualification Result

The exact flat gate package passed on Windows PowerShell `5.1.26100.9444` on
2026-09-14. The native parser accepted the bound sources. All five positive and
four negative cases passed, including the expected exit codes for wrong
confirmation (`20`), broad ACL and path collision (`10`), and injected
post-ACL rollback (`30`).

The successful status bound initializer SHA-256
`8bdede8ccacc55aec616472853c68942d74af824f867441f6de65f4c9e499cfb`
and secure child-process SHA-256
`c5d2200b15e7bda563c7ce2e393d3a45c79f04ba672e140990c5556febc493c3`.
Scratch mutation and cleanup both completed as designed. Production mutation,
live Symcon contact, owner and event mutation, MQTT publication, device action,
service restart, repository publication and retention cleanup all remained
false.

## Ordered Gates

1. Retain the successful Windows status file and its exact source hashes.
2. Integrate the repository change through the protected-main pull-request
   workflow.
3. Run production `preflight`; it must either verify an existing exact root or
   report only `repairRequired=true`.
4. If missing, create a private backup/recovery record for the absent baseline
   and obtain the exact production installation approval.
5. Run `install`, then an independent `preflight` postflight.
6. Only after the claim root is proven protected may runtime-fileset staging
   begin.

Runtime staging, activation, restart, owner migration, MQTT traffic, device
action and retention remain outside this gate.
