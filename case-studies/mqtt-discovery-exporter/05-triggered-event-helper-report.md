# 05 Triggered-Event Helper Report

**Case study:** IP-Symcon MQTT Discovery Exporter
**Status:** G3 complete
**Date:** 2026-07-15
**Implementation scope:** Canonical SAEF event helper and deterministic tests

## 1. Purpose

Provide the missing reusable infrastructure for idempotent variable-triggered
script events before the exporter implementation begins.

The helper is deliberately independent of MQTT, Home Assistant, entity classes
and exporter-specific naming.

## 2. Added Contract

`SAEF_EnsureTriggeredScriptEvent()` now ensures an IP-Symcon triggered event
with:

- a stable Ident;
- a compatible existing event type or safe creation;
- one existing trigger variable;
- trigger type `0` for update or `1` for change;
- deterministic name, position, visibility and active state;
- the IP-Symcon Run Automation action binding;
- explicit ownership below the target script.

Threshold and fixed-value triggers are intentionally excluded. They require a
separate trigger-value contract and are not needed by the initial exporter.

## 3. Parent and Target Script Decision

The Run Automation event action derives its target from the event context. For
the supported script-event contract, the event must therefore be a child of the
script it executes.

The helper retains the existing `parentID` and `targetScriptID` parameters for
API consistency, but rejects configurations where the IDs differ. This makes a
previously implicit and potentially invalid relationship explicit.

## 4. Cyclic Event Correction

Reviewing the official IP-Symcon contract exposed an existing defect in
`SAEF_EnsureCyclicScriptEvent()`.

The helper passed a numeric script ID to `IPS_SetEventScript()`. The official
function expects PHP source text and changes the action to Execute PHP Code; it
does not assign a target script ID.

The cyclic helper now:

- requires event parent and target script to be identical;
- uses only the explicit Run Automation action binding;
- does not call `IPS_SetEventScript()`.

RI-001 was updated so its cyclic event is owned below the target script rather
than below the created demo category.

## 5. Test Evidence

The new fake-runtime test covers:

1. creation of an update-triggered event;
2. idempotent update to a change trigger without duplication;
3. rejection of mismatched event parent and target script;
4. rejection of missing trigger variables and unsupported trigger types;
5. rejection of incompatible existing objects and event types;
6. cyclic-event regression: Run Automation binding without
   `IPS_SetEventScript()`.

All six tests pass.

## 6. Repository Verification

The complete repository `composer check` passed after the change, including:

- PHP syntax checks;
- generated Symcon bundle currency check;
- existing bundle tests;
- the new event-helper tests;
- PHPStan analysis;
- bundle PHPStan analysis;
- PHPCS.

The project lint, PHPStan and PHPCS scopes now include `tests/helpers` so the
new test remains part of the normal verification path.

## 7. Files Changed by G3

- `helpers/object/EnsureEvent.php`;
- `helpers/README.md`;
- `stubs/symcon.php`;
- `tests/helpers/ensure-event.php`;
- `references/RI-001-idempotent-configuration-script.md`;
- project test-runner configuration.

## 8. External Contract Evidence

- `IPS_SetEventTrigger()`: <https://www.symcon.de/de/service/dokumentation/befehlsreferenz/ereignisverwaltung/ips-seteventtrigger/>
- `IPS_SetEventAction()`: <https://www.symcon.de/de/service/dokumentation/befehlsreferenz/ereignisverwaltung/ips-seteventaction/>
- `IPS_SetEventScript()`: <https://www.symcon.de/de/service/dokumentation/befehlsreferenz/ereignisverwaltung/ips-seteventscript/>

## 9. Gate Decision

**G3 result: Complete.**

The exporter may now use one canonical helper for both MQTT command update
events and IP-Symcon state change events. G4 may begin the helper-first exporter
candidate without embedding local event-creation infrastructure.
