# 294 Combined MQTT Position Retry Gate Readiness

**Case study:** Navimow native IP-Symcon module

**Status:** New activation authorization stopped by a preceding read-only token
check; activation candidate not invoked and installation unchanged

**Date:** 2026-08-05

## 1. Result

The user confirmed no manual authentication action and issued a new explicit
activation authorization. Before invoking the mutation candidate, the bounded
read-only format-3 probe reported:

```text
projection:             PASS
token remaining:        1885 seconds
required minimum:       2400 seconds
REST:                   operational and authoritative
MQTT:                   disabled and credential-free
position diagnostics:   disabled and empty
```

The activation candidate was not invoked because its mandatory token
precondition was already known to fail.

## 2. Side-Effect Accounting

| Operation | Count |
|---|---:|
| bounded read-only projections | 1 |
| activation script calls | 0 |
| Account property mutations | 0 |
| Account ApplyChanges calls | 0 |
| MQTT activations | 0 |
| credential requests | 0 |
| OAuth or manual token actions | 0 |
| service restarts | 0 |
| mower commands | 0 |

The MCP call reported `transportError=null`, `executionError=null` and
`truncated=false`.

## 3. Architecture Decisions

### AD-NAV-1239: Check volatile readiness before loading the mutation path

A full read-only projection may reject the gate before the activation
candidate is invoked.

### AD-NAV-1240: Do not convert authorization into a forced attempt

Explicit authorization permits a safe operation only while every frozen
precondition passes. It does not require invocation with a known failure.

### AD-NAV-1241: Reconfirm after the next passive window

The next token refresh occurs after this observation window. Its passive
classification and any later activation therefore require fresh contextual
confirmation.

## 4. Gate Status

| Gate | Status |
|---|---|
| exact commit and disabled contracts | PASS |
| read-only projection | PASS |
| token horizon | BELOW THRESHOLD |
| activation candidate | NOT INVOKED |
| MQTT activation | NOT PERFORMED |
| next passive token observation | REQUIRED |

## 5. Next Step

Perform one bounded read-only token-readiness check after the expected normal
refresh window. If the new horizon is at least 2400 seconds, obtain a fresh
confirmation that no manual authentication action occurred and a new explicit
activation authorization before invoking the candidate once.
