# 328 Wet-Delay Read-Only Observation

**Case study:** Navimow native IP-Symcon module

**Status:** Observation passed; wet-delay reason not exposed; forced Start NO-GO

**Date:** 2026-08-20

## 1. Purpose

This step observes the module while the official app reports delayed mowing
because of wet conditions. It asks whether the existing REST variables or
retained receive-only MQTT diagnostics expose the same reason.

No MQTT activation, OAuth action, configuration change, mower command or
productive code change occurs.

## 2. Method

A private bounded PHP projection was executed twice through
`symcon_run_script_text_ex`. The second observation followed a new regular REST
poll.

Both MCP calls were checked independently for:

```text
transportError: null
executionError: null
truncated: false
```

The projection emitted no ObjectID, device identifier, credential, topic, raw
payload, coordinate or private schedule label.

## 3. Observed Contract

Both observations established:

- Account and Device instances are operational;
- authentication is usable and does not request reauthentication;
- REST polling is current;
- the mower is online;
- canonical REST state is Docked;
- the optional raw-status variable is unavailable;
- no explicit rain, wetness, weather, delay-reason or error signal is exposed;
- native MQTT is disabled and credential-free; and
- position diagnostics are disabled.

The delayed observation contained a later successful REST timestamp while the
state and weather-signal projection remained unchanged.

## 4. Interpretation

The app-visible wetness delay is not represented by the current stable module
contract. This does not prove that the cloud never transmits the information.
It proves only that:

- the current mapped REST status does not expose it;
- no optional raw REST status is retained in this installation; and
- disabled MQTT cannot provide concurrent event evidence.

Docked is therefore not sufficient to distinguish normal waiting from
weather-delayed waiting.

## 5. Start Safety Decision

Generic Start contains no documented force or weather-override parameter. A
Start request during a known wetness delay could be rejected, could remain
pending or could initiate movement while conditions are unsuitable. The
result is not predictable from the supported contract.

The first Start capture from step 327 remains restricted to suitable dry
conditions with no app-visible wetness delay.

**Forced or deliberate wet-delay Start: NO-GO.**

## 6. Evidence Gap

The remaining command-free routes are:

1. observe a naturally delayed scheduled job while receive-only MQTT is
   already active under a separately approved bounded pilot; or
2. compare a privately captured raw REST response without retaining or
   publishing identifying content.

Either route may discover a rain/error event, but neither justifies bypassing
device safety behavior.

## 7. Architecture Decisions

### AD-NAV-1346: Do not infer weather readiness from Docked

**Decision:** Treat Docked as physical/task state only.

**Rationale:** The same state is observed with an app-visible wetness delay.

### AD-NAV-1347: Keep weather override outside generic Start

**Decision:** Do not add or test an undocumented force parameter.

**Rationale:** Official command mapping contains only Boolean Start and no
weather override contract.

### AD-NAV-1348: Require suitable conditions for causal Start evidence

**Decision:** Execute the first Start capture only after the app-visible delay
has cleared.

**Rationale:** Transition evidence must not be combined with an unknown safety
interlock or unsuitable ground conditions.

## 8. Decision And Next Step

**Read-only wet-delay observation: PASS.**

**Wet-delay visibility in current Symcon contract: NOT AVAILABLE.**

**Forced Start test: NO-GO.**

The next command-free step is the bounded multi-area MQTT observation from
step 326, ideally spanning a naturally delayed and a normally executed
scheduled job. The Start capture waits until the app no longer reports a
wetness delay.
