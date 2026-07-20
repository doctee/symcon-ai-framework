# Corrected Helper Functional Retest Report

**Gate:** CL-023 corrected-helper functional regression
**Result:** PARTIAL PASS — wait race fixed; target state coupling requires diagnosis
**Date:** 2026-07-19
**Final live state:** Safe baseline restored

## Preflight

The fresh read-only preflight passed immediately before the first command:

- the process-effective `SAEF_WaitForVariable()` had corrected SHA-256
  `4b79fb7a7339573f61a84d64e8634d6dc7faa3d161f645277a5e62228b8a7222`;
- the CL-023 wrapper and ControlLight runtime matched their pinned identities;
- STATE and DIMMER local actions remained bound to the owning wrapper;
- target STATE and DIMMER remained actionable;
- STATE used OnUpdate and DIMMER used OnChange, both with explicit Run
  Automation action binding;
- the alarm contract permitted user control;
- STATE was false locally and at the target;
- reported DIMMER was 100 locally and at the target; and
- errors, confirmation timeouts and ErrorRingBuffer content were captured as
  the immutable diagnostic baseline.

The approved sequence was STATE true, DIMMER 40, DIMMER 100 and STATE false,
with an immediate stop and safe compensation on the first discrepancy.

## Corrected Wait Confirmation

The STATE-on action issued exactly one regular command and completed within the
configured three-second authoritative feedback window. Local and target STATE
both became true. `LAST_FEEDBACK` advanced, while errors, confirmation timeouts
and ErrorRingBuffer content remained unchanged.

This is the live result that the preceding same-second race prevented. Together
with the reflected helper identity, it confirms the corrected wait algorithm
under the previously failing device condition.

## Brightness Finding and Stop Decision

The next approved action requested DIMMER 40. The target and local reported
values converged to 39, which is within the candidate's explicit one-point
brightness tolerance and produced no timeout or error. STATE, however, became
false locally and at the target. The command statistic also advanced by two
between the preceding readback and this readback rather than by the single
brightness command initiated by the test.

The sequence therefore stopped before treating DIMMER 100 as a normal test
step. The retained-brightness compensation requested DIMMER 100; target and
local brightness reached 100, while STATE became true on both sides. The final
compensation then requested STATE false. The safe baseline was fully restored:
STATE false/false and reported DIMMER 100/100.

Across the test and compensation there were five new ControlLight commands.
There were no new errors, confirmation timeouts or ErrorRingBuffer entries.
Every issued action completed with authoritative local/target equality.

## Dependency and Ownership Readback

The target is supplied by a Zigbee2MQTT device module. A bounded live search
found no installed script source containing the CL-023 local or target variable
identities. One separate active event observes target STATE for a warning
summary; its script only recalculates that summary and does not issue a device
action. The scan therefore found no known script consumer that explains the
extra command or the STATE transition.

The evidence cannot yet distinguish target-module/device coupling from an
external Zigbee2MQTT/MQTT controller acting during the brightness step. It does
exclude the wait-helper race as the cause. Capturing the command and state MQTT
messages around one isolated brightness action is the next diagnostic gate;
another device action requires separate approval.

## Postflight Regression

The postflight object map is identical to the preflight map. Existing object
IDs, ownership, user-editable names and positions, profiles, action bindings,
event trigger contracts and visibility remained unchanged. The wrapper and
corrected helper identities remained selected, and the final safe values were
authoritatively equal.

## Gate Decision

The corrected wait-helper fix is **PASS** in live use. CL-023 is not yet cleared
for broader migration evidence because brightness actions unexpectedly couple
to STATE and one additional command was observed during the DIMMER-40 interval.
Broader ControlLight rollout remains paused until that boundary is explained
and covered by an explicit regression contract.

## Follow-up

Report 19 records the isolated module trace. It disproved fixed target
brightness/STATE coupling: the brightness-only action retained STATE ON and the
full sequence completed with exactly one command per phase. The extra command
in this report is classified as concurrent activity during the earlier
readback interval. CL-023 is consequently cleared as Wave 2 evidence while the
general multi-controller boundary remains explicit.
