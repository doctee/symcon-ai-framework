# 46 Private Pilot Observation Status Review

**Case study:** Navimow native IP-Symcon module
**Status:** Four observation goals accepted; passive live token refresh pending
**Date:** 2026-07-10
**Scope:** Consolidated review of `OBS-01` through `OBS-05`

## 1. Purpose

This step reviews the private-pilot observation matrix defined in
`37-private-pilot-observation-plan.md` after:

- deterministic harness implementation;
- recovery hardening;
- module publication;
- direct Symcon smoke testing;
- normal hardened Dock transition;
- successful active-verification service restart.

It determines which observation gates are complete, which evidence remains
limited and whether a new pilot tag or broader release is justified.

No productive PHP code is changed in this step.

## 2. Reviewed Build

Published module repository commit:

```text
db36ea37cb40298278307e88d65ae8c450603f18
fix: harden Dock and token recovery
```

Current historical tag:

```text
pilot-0.1.0.1 -> 692ea0350bb73e6581e4643a931837ae48b49ede
```

The current hardening commit is published on `main` but is not yet represented
by a new immutable pilot tag.

## 3. Evidence Levels

This review distinguishes:

| Level | Meaning |
| --- | --- |
| deterministic | fake time and scripted no-network transport |
| direct read-only | real Symcon and cloud read without mower command |
| supervised live | real mower transition under physical supervision |
| passive live | naturally scheduled behavior observed without intervention |

A deterministic pass is sufficient for scenarios that must not be induced on
the physical mower. A live requirement is not inferred from a deterministic
result unless the original observation plan explicitly allowed that boundary.

## 4. Matrix Summary

| Scenario | Required evidence | Current evidence | Status |
| --- | --- | --- | --- |
| `OBS-01` verification timeout | deterministic only | exact boundary, final read, missing deadline and no replay pass | PASS |
| `OBS-02` active restart | deterministic plus supervised live | reconstructed state and direct service restart pass | PASS |
| `OBS-03` temporary read failures | deterministic; no induced live outage | transient recovery, continuous failure, cadence and deadline pass | PASS |
| `OBS-04` token expiry and refresh | deterministic plus passive live | deterministic cases pass; prior manual refresh and current auth health exist; scheduled expiry movement not yet observed | PENDING |
| `OBS-05` repeated Dock operation | bounded supervised operation | three module Dock transitions with no duplicate finding; one included restart | PASS WITH LIMITATION |

## 5. OBS-01 Verification Timeout

### Evidence

The green harness covers:

- timeout after previously observed Docking;
- final Docked read at the exact deadline;
- missing deadline fails closed;
- elapsed restart terminates without post-deadline read;
- command-call count remains one;
- timer stops without a one-millisecond loop.

### Live boundary

Step 37 explicitly classified physical timeout as harness-only. No mower was
obstructed or kept away from the station to induce timeout.

### Decision

**`OBS-01`: PASS.**

The software boundary is deterministically proven with stronger and safer
evidence than a deliberately delayed physical return.

## 6. OBS-02 Restart During Active Verification

### Deterministic evidence

The harness proves:

- command state survives object reconstruction;
- original start time and deadline persist;
- no command replay occurs;
- verification resumes;
- elapsed restart terminates correctly;
- later Docked becomes Verified.

### Direct live evidence

Step 45 observed:

```text
Running
-> one Dock command
-> Docking / Pending Verification
-> Symcon service restart
-> Docking / Pending Verification with unchanged command timestamp
-> automatic restored timer verification
-> Docked / Verified
```

No manual refresh or verification call was used after restart before terminal
success.

### Decision

**`OBS-02`: PASS.**

Deterministic and direct live evidence agree.

## 7. OBS-03 Temporary Cloud Read Failures

### Evidence

The harness proves:

- two transient read failures remain pending;
- later Docking and Docked recover to Verified;
- continuous failure reaches Verification Timeout;
- the first unresolved read switches to 60-second cadence;
- the final interval aligns with the deadline;
- no repeated operation becomes a command call.

### Live boundary

No productive network outage, Base URL change or credential damage was induced.
This follows the explicit safety boundary from step 37.

Naturally occurring future cloud failures may add operational evidence, but
they are not required to pass this private-pilot scenario.

### Decision

**`OBS-03`: PASS.**

The required deterministic recovery and terminal behavior is complete.

## 8. OBS-04 Token Expiry and Refresh

### Deterministic evidence

The harness proves:

- successful refresh advances expiry;
- normal polling remains enabled;
- expired access token blocks status transport;
- authentication rejection requires reauthorization;
- transport failure starts a 60-second bounded retry;
- retry stops after five failed attempts;
- retry state survives restart;
- later success clears retry state;
- authorization-code exchange is not retried;
- diagnostics remain secret-safe.

### Existing direct evidence

Step 18 performed one explicit successful refresh-token exchange on an earlier
module build.

After hardening:

- step 43 observed a future token expiry, Connected state and successful
  authenticated status read;
- step 45 observed that account state remained Connected after a Symcon service
  restart;
- no reauthorization or REST error increase occurred.

### Missing passive evidence

The pilot has not yet recorded this complete natural sequence on the hardened
build:

```text
initial future expiry
-> scheduled module refresh without manual invocation
-> later future expiry
-> continued read-only polling
```

Token validity after update or restart does not prove that the scheduled
refresh itself executed.

### Decision

**`OBS-04`: PENDING passive live observation.**

No mower command or movement is needed to close it.

## 9. OBS-05 Repeated Normal Operation

### Evidence set

The native module has three distinct successful Running-to-Verified command
observations in the engineering record:

| Evidence | Character |
| --- | --- |
| step 30 | normal pre-hardening Running-to-Docked transition |
| step 44 | normal hardened Running-to-Docked transition |
| step 45 | hardened transition with active Symcon restart |

Across these observations:

- each user/test action sent one Dock command;
- every accepted command reached one Verified terminal result;
- no command replay was observed;
- previous terminal state did not block a later valid Dock action;
- command timestamp advanced once per action and remained stable afterward;
- no retained command error or unsanitized diagnostic payload was observed.

### Limitation

Only two of the three observations were ordinary uninterrupted operation. The
third intentionally included a service restart.

The sample nevertheless exceeds ordinary operation by exercising state
reconstruction without revealing duplicate delivery.

The plan explicitly prohibited artificial runs solely to increase sample
count. Another physical cycle is therefore not justified at this gate.

### Decision

**`OBS-05`: PASS WITH LIMITATION.**

The release-blocking condition was any duplicate-delivery finding. None was
observed.

## 10. Safety Contract Review

Across the pilot matrix:

- Dock remains the only module mower command;
- one command is sent per supervised action;
- no automatic command retry exists;
- post-acceptance verification remains read-only;
- timeout and restart paths do not replay commands;
- physical timeout and cloud failure were not induced;
- no Stop or Pause command was introduced for testing;
- all physical transitions remained supervised;
- temporary Symcon scripts were deleted;
- no private identifier, token or raw payload entered public evidence.

No safety-contract violation was found.

## 11. Current Release Decision

### Controlled private pilot

**Decision: CONTINUE.**

The hardened Dock-only module is suitable for continued supervised private use.

### New immutable pilot tag

**Decision: DEFER.**

Do not create `pilot-0.1.0.2` until the passive scheduled token-refresh
observation closes `OBS-04`.

### Broad public release

**Decision: NOT YET.**

Remaining reasons include:

- passive hardening-build token-refresh evidence is incomplete;
- OAuth client-secret distribution remains installation-specific;
- the Navimow cloud API is undocumented and may change;
- only Dock is supported;
- MQTT/WSS remains unimplemented;
- Symcon Store readiness remains unreviewed.

### Command expansion

**Decision: BLOCKED.**

Start, Stop, Pause and Resume require separate command-specific analysis,
fixtures, safety gates and live evidence.

## 12. Architecture Decisions

### AD-NAV-112: Accept scenario-specific evidence levels

**Decision:** Use deterministic evidence for intentionally non-induced failure
paths and require live evidence only where the observation plan specified it.

**Rationale:** Physical or credential disruption is not necessary to validate
pure timing and retry policies.

**Consequence:** `OBS-01` and `OBS-03` close without unsafe live failure tests.

### AD-NAV-113: Keep scheduled refresh distinct from valid authentication

**Decision:** Do not infer passive refresh execution from Connected state or a
future token expiry alone.

**Rationale:** Those values may have been established by an earlier manual or
lifecycle operation.

**Consequence:** `OBS-04` remains open until expiry movement is observed without
manual refresh.

### AD-NAV-114: Do not add another artificial mower cycle

**Decision:** Accept `OBS-05` with its documented limitation and wait for normal
future operation rather than creating another test run.

**Rationale:** Three successful module transitions already show no duplicate
finding, including one stronger restart case.

**Consequence:** No additional physical test is required for this review.

### AD-NAV-115: Defer the second pilot tag

**Decision:** Keep hardening on `main` until passive token-refresh evidence is
complete.

**Rationale:** The next immutable pilot snapshot should include both
deterministic and natural authentication-lifecycle evidence.

**Consequence:** `pilot-0.1.0.1` remains the only pilot tag for now.

### AD-NAV-116: Preserve the controlled-pilot boundary

**Decision:** Continue supervised private use without declaring broad release
readiness or expanding command scope.

**Rationale:** Core Dock safety is strong, but authentication distribution and
external API stability remain broader product concerns.

**Consequence:** The next work is passive observation, not a new mower feature.

## 13. Recommended Next Step

Create:

```text
47-passive-token-refresh-observation.md
```

That step should:

- capture a sanitized baseline while the mower is docked;
- record rounded token-expiry time, Connected state, reauth flag,
  `LastRestSuccess` and error count;
- wait for the normal scheduled refresh without invoking it manually;
- confirm that token expiry moves forward;
- confirm read-only polling continues;
- verify that reauthorization remains false and error count does not increase;
- retain no token value, private ID or raw log content.

After `OBS-04` passes, perform a short release review before deciding on
`pilot-0.1.0.2`.
