# Managed Runtime Mirror Generator

**Gate:** Repository implementation only
**Result:** Implemented; live pilot unchanged
**Date:** 2026-07-20
**Live impact:** None

## Purpose

The successful runtime-mirror pilot proved that a visible, inert Symcon script
can restore console reference discovery for the file-backed ControlLight
runtime. This step turns that manually constructed pilot into an idempotent,
testable deployment implementation without changing the live object.

The general engineering decision is recorded in ADR-0006, RS-001.37 and
EK-007. The implementation remains ControlLight-local until a second independent
use case validates the same contract.

## Implementation

`candidate/ControlLightRuntimeMirror.php` provides two operations:

- `render()` validates the pinned runtime hash, sorts and deduplicates private
  ObjectIDs, creates the inert preamble and appends the runtime byte-for-byte;
- `reconcile()` reads the authoritative runtime file, locates the owned script
  by parent and Ident, performs an idempotent content-only update, verifies
  direct readback and restores the exact previous source on failure.

Object creation is delegated to the existing `SAEF_EnsureScript()` helper. The
local provisioner first inspects an existing object and passes its current name
back to the helper while leaving position, icon, information text and visibility unmanaged. Thus
the helper is reused without introducing a second public object-creation API or
overwriting user presentation.

No event, variable action or autoload binding is created. The generated mirror
contains no `RequestAction()` or device write in its executable preamble.

## Private Deployment Input

A live deployment adapter loads `SAEF_EnsureScript()`, the candidate class and a
private configuration with this shape:

```php
$config = [
    'parentID' => 0, // private live value
    'ident' => 'SAEF_CONTROL_LIGHT_RUNTIME_MIRROR',
    'defaultName' => 'SAEF ControlLight Runtime Mirror',
    'defaultPosition' => 90,
    'expectedScriptID' => null, // persist the returned private ID after creation
    'runtimePath' => 'C:/private/deployment/path/ControlLightRuntime.php',
    'expectedRuntimeSha256' => '<64 hexadecimal characters>',
    'referenceIDs' => [/* private wrapper, variable, event and target IDs */],
];

$result = \SAEF\CaseStudy\ControlLight\ControlLightRuntimeMirror::reconcile($config);
```

The placeholder parent and path are deliberately non-operational. Real IDs,
paths and the returned script ID belong only in excluded private deployment
material. Reconciliation itself is a live mutation and still requires the SAEF
live-system gate and explicit authorization.

For the first creation, `expectedScriptID` is `null`. The deployment record must
then persist the returned private `scriptID` and supply it on every later run.
This makes a moved, deleted or replaced owned object an explicit ownership
failure instead of silently creating a duplicate.

## Failure and Rollback Contract

Before an update, the provisioner retains the complete previous script source.
If IP-Symcon rejects the write or the readback hash differs, it restores that
source and verifies the rollback hash. If the same execution created a new
mirror, it deletes only that new, owned script.

Unexpected object type under the owned Ident fails before content mutation.
Changing the parent or Ident is configuration/ownership drift rather than a
presentation change and is not reconciled implicitly.

## Offline Regression

`tests/control-light/runtime-mirror.php` covers:

- deterministic sorting and deduplication of private references;
- byte-exact embedding of the authoritative runtime;
- rejection of a mismatched pinned runtime hash;
- first creation and creation defaults;
- a second no-op reconciliation without a content write;
- preservation of a user-modified name, position, icon, information text and visibility;
- rejection of moved or replaced ownership when `expectedScriptID` is pinned;
- content update with direct readback;
- exact rollback of an existing mirror;
- cleanup after failed first creation; and
- rejection of an unexpected object type.

Run:

```console
composer test:control-light-runtime-mirror
```

## Promotion Reminder

Do not promote `ControlLightRuntimeMirror` to `helpers/` after this first use.
When a second independent file-backed runtime needs the same facility, compare
both implementations against EK-007 and explicitly decide whether a general
helper API is now justified. This is the next required architecture reminder.
