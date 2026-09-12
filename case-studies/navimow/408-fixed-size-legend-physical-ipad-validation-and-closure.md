# SAEF Step 408: Fixed-Size Legend Physical iPad Validation and Closure

- **Date:** 2026-09-12
- **Status:** Physical validation passed; rollout complete
- **Scope:** Navimow HTML SDK fixed-size legend closure only

## 1. Purpose

Step 407 installed the fixed-size legend correction and proved the exact live
HTML SDK contract, but deliberately left physical iPad rendering open. This
step records the resulting client-side observation and closes that rollout.

## 2. Physical Evidence

After the published module update, the user reloaded the Navimow visualization
on the physical iPad and exercised map zoom. The user confirmed that the result
fits and that the legend remains at a constant visual size while the map is
zoomed.

This observation closes the defect reported in step 406, where the legend had
previously enlarged together with map geometry.

## 3. Mutation Boundary

This confirmation gate performed no additional technical mutation:

- no SAEF source or module implementation change;
- no standalone module publication or update;
- no `MC_UpdateModule()`, `ApplyChanges()` or reload;
- no OAuth or token action;
- no MQTT activation or credential request;
- no restart; and
- no mower command.

The installed standalone revision therefore remains:

```text
4ab415dd5582bd2f49cec3149ccf4c28a94fcd29
```

## 4. Architecture Decisions

### AD-NAV-408-01: Require physical client confirmation

**Decision:** Treat the live tile contract and controlled browser checks as
necessary but not sufficient until zoom behavior is observed on the target
iPad client.

**Reason:** Browser engines, embedded visualization containers and touch zoom
can differ from the controlled desktop harness. The original defect was found
through physical use and is closed through the same channel.

### AD-NAV-408-02: End special rollback retention

**Decision:** The preceding standalone commit no longer requires a special
rollout hold after successful physical confirmation.

**Reason:** Offline browser checks, canonical publication, immediate and
delayed live postflights, live tile inspection and physical iPad behavior now
form a complete evidence chain. Normal immutable Git history remains available
for later diagnosis or rollback.

### AD-NAV-408-03: Keep cleanup separately authorized

**Decision:** Do not delete topic branches, worktrees or private evidence as
part of physical validation.

**Reason:** Functional closure establishes cleanup eligibility but is not
destructive cleanup authority. Exact retention candidates must be inventoried
before a separate cleanup gate.

## 5. Gate Result

| Gate | Status |
|---|---|
| Controlled browser validation | PASS |
| Canonical SAEF and standalone publication | PASS |
| Controlled Symcon rollout | PASS |
| Live HTML SDK contract | PASS |
| Physical iPad zoom validation | PASS |
| Fixed-size legend rollout | COMPLETE |
| Worktree, branch and private-evidence cleanup | NOT EXECUTED |

## 6. Next Gate

Inventory the exact fixed-size-legend topic branches, worktrees and private
evidence retained by steps 406 to 408. Propose only attributable and safely
deletable candidates; cleanup remains separately gated.
