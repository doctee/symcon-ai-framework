# Wave 4 CL-004 Activation Report

**Gate:** First sequential Wave 4 member
**Result:** PASS — CL-004 ACTIVE ON V2
**Date:** 2026-07-20
**Live impact:** One wrapper source selected; two non-commanding configuration runs

## Protected activation history

Every write began with exact rollback-source comparison and direct candidate
readback. CL-017 remained untouched throughout.

The first postflight rejected the activation because it required feedback values
to remain byte-for-byte equal to the earlier preflight. During an unusually long
execution-channel interval, authoritative DIMMER and color-temperature feedback
changed naturally from zero placeholders to current device values. Local and
target variables advanced together, but the immutable-value assertion still
failed. The exact legacy source and baseline topology were restored.

Two subsequent coordinator attempts exposed a second verifier error:
`IPS_RunScript()` invoked inside the coordinator did not wait for the target
script to finish in this installation context. The coordinator therefore
inspected topology before diagnostics existed and rolled back. A bounded,
always-rollback trace proved that timing behavior.

One incomplete integer variable named `Successes` without an Ident appeared
during the earlier cleanup/execution overlap. It was absent from the captured
preflight baseline and matched the expected partially created statistic in
parent, type, name, empty Ident and absence of an action. A compare-and-swap
cleanup deleted exactly that object and independently proved restoration of the
three original wrapper children.

No device action was requested during any attempt. Every stopped attempt restored
the legacy source before the next gate.

## Corrected execution contract

The successful transaction used the direct script-execution channel and waited
for each of the two runs to complete before starting postflight. Its acceptance
contract allows authoritative values to change naturally, while still requiring:

- local/target equality after the completed runs;
- equal execution and success counters;
- zero commands, errors and confirmation timeouts;
- zero `LAST_FEEDBACK` command timestamp;
- exact presentation, action, link and event contracts;
- the complete baseline child set plus only the ten identified v2 diagnostics;
  and
- all 29 expected wrapper source identities.

## Final result

The final activation passed with:

- two executions and two successes;
- zero commands, errors and confirmation timeouts;
- STATE true locally and at the target;
- DIMMER 100 locally and at the target;
- color temperature 2702 locally and at the target;
- all ten diagnostics present with valid types and Registry contract;
- parent topology, local presentation and target link preserved;
- all three target events active with explicit Run Automation action binding;
- all 29 wrapper sources exact; and
- CL-017 still on its byte-exact legacy rollback source.

## Gate decision

CL-004 is active on the current v2 fileset. Wave 4 does not advance automatically.
The next gate is a fresh read-only delta preflight for CL-017 that must also
reconfirm its three presentation links and the health of CL-004. Activation of
CL-017 and any real-device sequence remain separately approved operations.
