# Gate 79 — targeted Windows ACL hardening

Status: authorized live filesystem hardening complete, 2026-09-03. This does
not activate the earlier repository security-correction candidate.

## Scope and reuse

The approved change covers five exclusively owned OwnTracks runtime roots
(day cache, provider cache, miss state and two budgets), the configured static
tile root and the currently active immutable OwnTracks module package.

The existing Windows deployment scripts' protected-DACL and ACL-snapshot
approach was reused. No new public helper/API or runtime dependency was added.
Installation-specific tooling and exact evidence remain private. Shared Temp,
module and static-parent directories, historical rollback packages and other
consumers were not modified.

## Evidence before mutation

- Symcon MCP binding confirmed; no alternate live transport used.
- Exactly one positive, existing OwnTracks map owner; configuration pinned.
- Exact active fileset and source-map hashes, all 34 payload hashes verified.
- Complete bounded filesystem inventory: 566 directories and 33,987 files.
- All owners in the trusted System/Administrators set, no reparse points or
  null DACLs, no protected descendants or custom per-entry ACLs.
- Original DACLs uniform per file/directory class and root, retained in full
  as a normalized private backup. Existing files were not writable by normal
  users; the finding concerned inherited directory creation/write rights.
- Runtime roots contained only the expected owning instance, not a second
  consumer. Static and package inventories matched the fresh exact counts.

## Applied change and result

Seven root DACLs were protected from shared-parent inheritance. Their allowed
ACEs were retained as explicit ACEs, except the single normal-Users ACE granting
directory creation/write rights. Existing System/Administrators permissions,
read permissions and Creator Owner inheritance were preserved. Descendants
received the resulting inherited ACLs through Windows propagation.

The dry run checked the actual resulting DACL, not only a successful setter
return. It caught an ineffective attempt to remove still-inherited ACEs before
any live write. The final implementation reconstructs the protected DACL while
preserving generic inheritance masks and validates the intended removal.

The apply completed in about 15 seconds. A separate complete postflight checked
every entry's DACL and trusted owner, validating each distinct DACL's semantics
once to keep the scan bounded. It completed in about 9 seconds and confirmed:

- exactly seven protected roots and inherited descendant ACLs;
- no untrusted write/create/delete/permission-change rights on any entry;
- object-level full-control allow ACEs for System and Administrators throughout;
- no reparse points, null DACLs or untrusted owners;
- unchanged shared-parent DACLs at apply readback;
- all 34 active module payload files still byte-exact;
- unchanged instance configuration and healthy instance status.

MCP transport errors, PHP execution errors and truncation were inspected
separately: none occurred in the successful apply/final postflight. Earlier
bounded inventory attempts stopped before completing; they did not mutate the
system and are not used as evidence of a complete scan.

## Limits, rollback and next gates

This is exhaustive ACL inspection, not impersonation/AccessCheck under another
interactive user's token, a penetration test or a full-host security claim.
No actual unauthorized-user file creation was attempted.

Private backup, exact scripts, initial/intermediate/final results and recovery
procedure are retained. A full ACL rollback would restore the previous weaker
permissions and needs an explicit recovery decision plus fresh drift checks.
Do not restore stale runtime state or budget content during ACL recovery.

No file contents, logging, archives, OwnTracks source objects, map settings or
visualization were changed. No restart, provider/browser contact, package
activation, commit, push or cleanup occurred. Existing broader permissions on
shared parents remain outside this gate. A future deployment or rollback must
verify/harden its own newly selected immutable package before activation; this
operation is not an installer-wide ACL policy.

The prepared runtime security fixes remain inactive. Their transport acceptance,
pre-HTTP versus pre-TCP boundary, exact activation and current-budget-preserving
rollback are still separate gates. The independent PHP_CodeSniffer update is
verified in its own worktree; replacing the previous shared vendor owner awaits
lockfile integration.

## Source

Windows inheritance behavior is documented in
[Microsoft SetSecurityInfo](https://learn.microsoft.com/en-us/windows/win32/api/aclapi/nf-aclapi-setsecurityinfo).
Shared-parent and descendant permissions are inspected independently because
propagation can fail for inaccessible child objects.
