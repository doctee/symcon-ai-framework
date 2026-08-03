# CL-020 Off-State Color Preflight and Package

**Date:** 2026-07-27
**Gate:** Read-only live delta preflight and local package preparation
**Result:** PASSED — INACTIVE FILESET STAGING NOT APPROVED
**Live impact:** None

## Live Delta

The current CL-020 wrapper still matches its byte-exact legacy rollback source.
Its four facade variables, four active feedback events and explicit event-action
bindings are unchanged. The lamp remains off with retained brightness and color
temperature. The target's native HS value remains at the bounded compensation
result from the preceding failed test.

The target, Alexa and scene configuration hashes still match the previous
evidence. The repaired Home Assistant Entity module source, shared
`System.Locals`, managed runtime mirror and 21-v2/eight-legacy wrapper inventory
also remain unchanged.

No script was executed. No source, object, configuration or variable was
written, and no `RequestAction()` or device command was attempted.

## New Package

The private package binds:

- the exact current legacy wrapper and verified decoded rollback source;
- the new immutable ControlLight fileset;
- a CL-020-only wrapper selecting `target-turns-on`;
- standard 0.5-degree/0.5-point on-state HS confirmation;
- transition-only 2.0-degree/0.5-point off-state confirmation;
- the existing 10-second confirmation and 200-millisecond polling bounds;
- the unchanged alarm, target, event, Alexa and scene identities; and
- two required command-free reconciliation runs before any device test.

The candidate fileset is not present on the live system. Staging it would be
inactive and would not change runtime selection, but it is still a live
filesystem mutation and therefore remains a separate approval gate.

## Later Functional Gate

After separately approved staging and activation, the decisive device test
starts from authoritative STATE=false. One facade color request must produce
exactly one target color action, confirm bounded native HS and STATE=true under
one shared deadline, and leave target and facade brightness unchanged.

The initial state must then be restored. The same off-state color transition is
repeated through the installed Alexa text-command path. The scene remains a
structural regression because it controls additional targets outside the
CL-020-only authorization.

Any drift, extra command, missing power-on feedback, brightness movement,
unclassified error or restoration failure requires immediate wrapper rollback.
