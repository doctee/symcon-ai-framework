# Z2M Directional Mired Live Activation

**Date:** 2026-09-18

**Scope:** CL-001 single target and CL-015 member-confirmed group

**Result:** PASSED

## Deployment Boundary

The merged 21-file ControlLight fileset was packaged from its deterministic
distribution and a byte-exact current bootstrap snapshot. Because Symcon MCP
does not provide an immutable-fileset transfer endpoint, the separately
authorized restricted deployment channel was used only to stage the inactive
fileset and run its read-only preflight.

The stage and preflight passed. Independent MCP readback then confirmed the
complete file count, expected fileset and core hashes, unchanged global
bootstrap source, unchanged kernel start time and ready runlevel. The global
bootstrap was not selected and the service was not restarted.

## Command-Free Wrapper Activation

Only CL-001 and CL-015 were changed to select the new immutable runtime path.
Their remaining source, configuration, user-facing variables, target bindings
and event identities were preserved. Exact pre-change sources remain in private
rollback evidence.

Each wrapper completed a command-free reconciliation:

- execution and success counters advanced together;
- command, error and confirmation-timeout counters did not increase;
- facade, target and member values remained unchanged; and
- all target, member and physical-input events retained their identities,
  active states, trigger kinds and source variables.

No other ControlLight wrapper was migrated.

## Functional Regression

CL-001 received one 6500 K facade request. The Z2M target reported 6535 K, the
facade synchronized to that authoritative value and no error or confirmation
timeout was recorded. The initial 2202 K value was then restored while the
initial on-state and brightness remained unchanged.

CL-015 was tested as its complete three-member group. All members first
confirmed on. One 6500 K facade request produced 6535 K at the group endpoint,
facade and all three members without an error or timeout. The previous 3003 K
feedback and off-state were restored, with all three members confirmed off and
available.

A delayed readback found both wrapper source hashes stable, all final facade and
target values aligned, unchanged diagnostic failure counters, unchanged global
bootstrap and the same kernel start time.

## Remaining Boundary

The previous real spoken CL-001 temperature dispatch and this direct corrected
matcher regression together close the shared 6500-to-6535 K defect. A real
spoken temperature command for CL-015 remains optional voice-consumer coverage;
its ControlLight group and shared matcher paths are fully proven.

The staged immutable fileset, prior wrapper paths and private backups remain
retained until a normal bounded retention decision.
