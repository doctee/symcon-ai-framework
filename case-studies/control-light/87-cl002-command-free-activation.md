# CL-002 command-free activation

## Outcome

CL-002 is active on ControlLight v2 with authoritative STATE and brightness
feedback and `reported` brightness semantics. Two direct waiting reconciliation
runs succeeded without a device command, facade change, target change, error or
confirmation timeout.

All 29 wrappers remain classified and unchanged outside CL-002. The current
installation baseline is seventeen active v2 wrappers and 12 retained legacy
wrappers.

## External inputs and alarm

Both existing Homematic short-press event identities were preserved. Channel
one remains an OnUpdate input mapped to on, while channel two remains an
OnUpdate input mapped to off. Both continue to respect the configured inverse
alarm contract.

The alarm value remained false, which means the alarm contract was active
during activation. No physical wall input, facade action or device action was
submitted. The mappings and alarm block are covered by deterministic runtime
regressions; their real end-to-end test remains presence-bound.

## Ownership and consumers

The existing facade variables retained their IDs, names, positions, profiles
and action owner. Both target-feedback event identities were reused with
explicit event actions. STATE feedback moved in place from OnChange to
OnUpdate, while brightness feedback retained OnChange.

No AutoOff, Alexa, presentation-link, script or foreign-event consumer required
a coordinated mutation. The global Alexa configuration remained byte-stable.
