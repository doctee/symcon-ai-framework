# CL-006 Dummy Retirement

**Date:** 2026-07-27
**Gate:** Approved obsolete-template removal
**Result:** PASSED

## Preflight

CL-006 was a copy-only template without enabled capabilities. Read-only
inspection found no variables, events, script references, instance references
or event references. Its only presentation dependency was one UI link; the
wrapper also owned its normal hidden target link.

A byte-exact private wrapper backup and a machine-readable preflight were
created before mutation.

## Retirement

The two owned links were deleted before the wrapper and its empty container.
The first wrapper deletion attempt failed safely because the running Symcon
version requires the explicit child-deletion argument. The resumed transaction
used that exact signature and completed successfully.

Independent postflight confirmed that the wrapper, container and both links no
longer exist. No device action was attempted.

## Inventory Effect

The current sanitized ControlLight inventory now contains 28 operational
instances. Removing the copy template also removes its pending brightness
decision and its otherwise unique configuration variant.
