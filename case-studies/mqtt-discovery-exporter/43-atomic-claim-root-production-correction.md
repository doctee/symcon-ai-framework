# Atomic Claim-Root Production Correction

**Status:** Repository implementation complete; replacement Windows PowerShell 5.1 qualification pending
**Preparation date:** 2026-09-14
**Repository base:** `36c7ecaa569f072a5673b649c231d6969c558146`
**Production mutation:** None

## Purpose

The first production read-only claim-root preflight correctly rejected the
shared `%ProgramData%\SAEF` parent. Its DACL is inherited and gives the local
Users group directory-write access. The claim root was absent and the gate
reported no creation, ACL mutation, Symcon contact, MQTT publication, device
action, restart, publication or retention cleanup.

The original initializer required a protected parent and then created the
missing leaf before replacing its inherited ACL. Relaxing only the parent
check would introduce two integrity gaps:

- the new directory would temporarily inherit local-user write access; and
- another local principal could occupy the fixed name between baseline check
  and creation, after which a non-exclusive `Directory.CreateDirectory()` call
  could not distinguish the collision.

Hardening the shared SAEF parent is not part of this correction. That parent
contains independently owned protected and inheriting roots. Changing its ACL
would alter multiple backup, qualification and migration boundaries at once.

## Decision

The MQTT-specific initializer creates the final fixed leaf with one native
Windows `CreateDirectoryW` call. The call receives a self-relative security
descriptor that already contains:

- protected inheritance;
- Administrators as owner and primary group; and
- exactly inheritable Full Control entries for `SYSTEM` and Administrators.

Native creation is exclusive. An existing file, directory or reparse point
returns an error and is never re-ACL'd or removed. The initializer immediately
reads back the plain-directory, empty-leaf, owner and exact ACL contract. A
later verification failure removes only the leaf that this invocation proved
it created and only while it remains empty.

The parent may retain ordinary inherited create-child access. It must still be
a plain directory owned by `SYSTEM` or Administrators and must not grant an
untrusted principal Delete, Delete Child, Change Permissions or Take Ownership
on the parent itself. Those rights could bypass the protected child DACL and
therefore remain fail-closed.

The implementation stays case-study-local. Repository inventory found other
Windows create-then-ACL sequences, but their parents, principals and rollback
contracts differ. Generalizing atomic protected-leaf creation requires a
separate shared-impact review of every exporter, owner and consumer.

## Replacement Qualification

The Windows PowerShell 5.1 qualification now builds a production-like scratch
parent with inherited Users read/execute and directory-write rules plus an
inherit-only Creator Owner rule. Its matrix must prove five positive and six
negative cases:

1. exact source and secure-child-process identities;
2. native Windows PowerShell 5.1 parsing;
3. read-only missing-root preflight below the inherited parent;
4. atomic protected-root creation and exact ACL readback;
5. idempotent read-only postflight;
6. wrong confirmation rejection;
7. broad existing-root rejection;
8. file-path collision rejection;
9. untrusted parent Delete Child rejection;
10. injected between-check-and-create collision rejection without re-ACL or
    deletion of the competing directory; and
11. empty-leaf rollback after an injected post-creation verification failure.

Every scenario runs through the existing hash-pinned secure child-process
contract. The qualification may change only its random `%TEMP%` scratch tree.

## Security and Compatibility

No channel verb, target allowlist, Symcon RPC, owner script, event, MQTT topic,
device or service contract changes. Existing exact protected claim roots remain
valid and are only inspected. A missing root changes from requiring a protected
parent to requiring atomic protected creation beneath a safe parent.

The production ACL inventory is private evidence. Public artifacts retain only
the reusable facts needed to explain the corrected contract.

## Ordered Gates

1. Complete repository tests and review the exact replacement source hashes.
2. Run the flat replacement gate on Windows PowerShell 5.1 and retain its
   complete status as private evidence.
3. Integrate the qualified sources through the protected-main pull-request
   workflow.
4. Run a fresh production read-only preflight from the merged source.
5. Create an absent-baseline backup/recovery record and review its exact plan.
6. Obtain a separate exact authorization for atomic production installation.
7. Run installation once and then an independent read-only postflight.
8. Only after the root is proven exact may runtime-fileset staging resume.

Shared `%ProgramData%\SAEF` ACL hardening, runtime staging, activation, owner
migration, MQTT traffic, device action, restart, publication and retention
remain separate gates.
