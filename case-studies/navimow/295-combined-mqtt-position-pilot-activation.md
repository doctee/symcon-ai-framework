# 295 Combined MQTT Position Pilot Activation

**Case study:** Navimow native IP-Symcon module

**Status:** Combined receive-only MQTT and position pilot activated on the
exact standalone commit; two stable active format-3 baselines accepted and
48-to-72-hour observation running

**Date:** 2026-08-06

## 1. Result

The new mutation-time readiness check passed with a 2575-second token horizon.
Exactly one Account `ApplyChanges()` enabled both receive-only MQTT transport
and position diagnostics.

The lifecycle traversed bounded recovery states before reaching:

```text
lifecycle:                ShadowActive
MQTT Core:                102
WebSocket Core:           102
REST authority:           retained
position status:          unavailable
position received:        0 samples
```

Two active format-3 baselines 113 seconds apart had the same connection
signature and passed every contract. The private pilot state is `active` with
no stop reason.

## 2. Authorization and Safety

The user confirmed that no manual authentication action occurred and issued a
new explicit activation authorization. The earlier credential-persistence and
mandatory-cleanup acceptance remains bound to this pilot.

No OAuth action, service restart or mower command was performed.

## 3. Activation Boundary

Immediately before mutation:

- the exact commit was clean and valid;
- REST, Account and all public variable and Archive contracts passed;
- both MQTT features were disabled;
- MQTT and WebSocket were inactive and credential-free;
- a private byte-exact configuration backup was captured;
- the token horizon exceeded 2400 seconds.

The activation candidate set both feature properties and invoked one Account
`ApplyChanges()`. All immediate postconditions passed. No failure cleanup was
needed.

## 4. Connection Establishment

The first Core connection ended in the normal bounded recovery path. The
module scheduled and executed its next attempt without an additional property
mutation or command from Codex.

The second attempt reached Core status `102/102` and then `ShadowActive`. The
two baseline reads remained stable. This startup recovery is pilot evidence,
not an extra activation attempt.

## 5. Pilot Window

```text
started:              2026-08-06 07:06:34 Europe/Berlin
earliest completion:  2026-08-08 07:06:34 Europe/Berlin
mandatory deadline:   2026-08-09 07:06:34 Europe/Berlin
first native check:   approximately 2026-08-06 12:01 Europe/Berlin
```

The pilot requires at least two natural REST-observed mowing cycles and at
least two position-evidence windows correlated with those cycles. Position
being initially `unavailable` is valid and does not trigger a mower action.

## 6. Side-Effect Accounting

| Operation | Count |
|---|---:|
| bounded MCP calls | 9 |
| activation script calls | 1 |
| Account ApplyChanges calls | 1 |
| MQTT activations | 1 |
| credential retrievals | 1 |
| OAuth or manual token actions | 0 |
| service restarts | 0 |
| mower commands | 0 |

All MCP calls reported empty transport and execution errors with complete
output.

## 7. Architecture Decisions

### AD-NAV-1242: Start the pilot only after two active baselines

Successful feature activation alone does not establish observation stability.

### AD-NAV-1243: Treat bounded startup recovery as pilot evidence

Runtime-owned reconnect handling does not count as another activation or
justify an external retry.

### AD-NAV-1244: Permit initially unavailable position evidence

The position stream is traffic-dependent. Missing samples at startup are not a
failure before natural mower activity has occurred.

### AD-NAV-1245: Anchor the pilot clock to the second baseline

The observation window starts only after connection stability is established.

## 8. Gate Status

| Gate | Status |
|---|---|
| mutation-time readiness | PASS |
| combined activation | PASS |
| active baseline 1 | PASS |
| active baseline 2 | PASS |
| 48-to-72-hour pilot | RUNNING |
| natural cycle evidence | PENDING |
| position evidence | PENDING |
| cleanup | MANDATORY AT CLOSURE OR STOP |

## 9. Next Step

Allow normal mowing schedules to run without intervention. Use the native
five-hour checkpoints and bounded read-only snapshots to reconstruct transport
episodes, natural REST cycles and position-counter growth. Any hard stop or the
72-hour deadline triggers the separately bounded mandatory cleanup.
