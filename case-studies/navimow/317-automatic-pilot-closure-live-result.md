# 317 Automatic Pilot Closure Live Result

**Case study:** Navimow native IP-Symcon module

**Status:** Pilot closed automatically and removed all Core credentials after
the second transport episode; delayed read-only verification passed

**Date:** 2026-08-17

## 1. Result

The corrected receive-only pilot did not run until its absolute 72-hour hard
stop. It closed after 10025 seconds because the module observed the second
transport episode of the active session.

```text
installed commit:       888325d8649160c5bae473f4f8a052cf86e703b6
session start:          2026-08-14 04:39:06 UTC
closure requested:      2026-08-14 07:26:11 UTC
closure completed:      2026-08-14 07:26:12 UTC
pilot duration:         10025 seconds (2 h 47 min 5 s)
configured hard stop:   2026-08-17 04:39:06 UTC
closure reason:         second-transport-episode
```

A fresh read-only check on `2026-08-17 07:39:06 UTC`, more than three days
after closure and after the configured hard-stop time, passed all disabled and
credential-free contracts.

## 2. Automatic Cleanup

The module completed its cleanup one second after requesting closure:

- MQTT and position diagnostics disabled;
- MQTT and WebSocket Core instances inactive;
- Authorization header absent;
- MQTT username and password absent;
- lifecycle `Disabled` with no pending attempt;
- automatic-closure state `Closed`; and
- Account, Configurator, Device and Receiver status healthy.

No manual cleanup, ApplyChanges, OAuth action, restart or mower command was
required for this verification.

## 3. Observation Result

The retained coordinate-free position accounting contains:

```text
received position samples:       33
coordinate changes:              29
out-of-order timestamps:          0
position segments:                4
counter reset count:              3
```

The transport totals increased from the activation baseline of 56053 received
messages to 56169. This provides useful MQTT and position evidence even though
the stability window was much shorter than planned.

The retained transport counters are lifetime diagnostics and are not attributed
solely to this session. They must not be interpreted as 73 disconnects or 271
credential rotations during this single pilot.

## 4. Channel Verification

Both final read-only calls independently reported:

```text
transportError: null
executionError: null
truncated:      false
```

The complete disabled-state probe returned `pass=true`. REST remained
operational, authentication connected and reauthentication unnecessary.

## 5. Architecture Decisions

### AD-NAV-1305: Automatic cleanup is proven live

The credential-first automatic-closure path is no longer only an offline
contract. The live module closed the pilot, cleared all owned Core credentials
and disabled both feature properties without external mutation.

### AD-NAV-1306: The second-episode rule is safe but too strict for endurance

Closing on the second transport episode limits credential exposure and worked
exactly as designed. It also prevented the intended 48-to-72-hour stability
observation after less than three hours. The rule is therefore accepted as a
safety mechanism but not yet as a suitable long-running pilot policy.

### AD-NAV-1307: Separate cumulative diagnostics from session evidence

Global transport counters remain useful for long-term health trends but cannot
be attributed to one session without a start baseline. Session conclusions use
the explicit session timestamps, closure reason, position accounting and the
observed received-message delta.

## 6. Gate State

| Gate | Status |
|---|---|
| corrected one-attempt activation | PASS |
| receive-only transport | PASS |
| MQTT and position evidence | PASS, bounded window |
| intended 48-to-72-hour endurance | NOT REACHED |
| second-episode automatic closure | PASS |
| credential-first cleanup | PASS |
| delayed disabled verification | PASS |
| REST authority and availability | PRESERVED |

## 7. Recommendation

Keep MQTT disabled. The next SAEF step should analyze the two session transport
episodes and design a bounded grace or recovery policy that distinguishes a
short recoverable interruption from repeated instability. A new pilot should
not be activated until that policy is implemented, tested offline and passed
through the normal publication and disabled-rollout gates.
