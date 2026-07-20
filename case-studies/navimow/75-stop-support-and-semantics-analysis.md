# 75 Stop Support and Semantics Analysis

**Case study:** Navimow native IP-Symcon module
**Status:** Official transport mapping confirmed; Stop semantics remain blocked
**Date:** 2026-07-12
**Scope:** Credential-free, non-actuating current-source analysis of Stop

## 1. Purpose

This step executes the research-only GO from
`73-resume-integration-review-and-stop-readiness.md` after publication of
`pilot-0.1.0.3`.

It determines:

- whether a current manufacturer-owned source still contains Stop;
- the exact current request mapping;
- why Stop is absent from the official Home Assistant user surface;
- whether current documentation defines its task and terminal-state semantics;
- whether a supervised private capture may now be planned;
- whether Stop should instead be formally excluded.

No OAuth credential, private API session or mower is used. No fixture,
productive PHP, module repository, Symcon instance or Git tag is changed.

## 2. Research Method

The review was performed on 2026-07-12 using public, credential-free sources.
Priority order:

1. manufacturer-owned SDK source;
2. manufacturer-owned Home Assistant integration;
3. Home Assistant's official lawn-mower entity contract;
4. manufacturer support/developer documentation;
5. maintained community integrations;
6. the original ioBroker adapter.

Code claims were checked against exact Git revisions. Search results or
community statements are treated as corroboration, not as a replacement for
captured device behavior.

## 3. Reviewed Revisions

| Source | Revision reviewed | Role |
| --- | --- | --- |
| `segwaynavimow/navimow-sdk` | `6596aa0a65dcf05ed248da87c36975f2ea236ab8` | manufacturer-owned SDK |
| `segwaynavimow/NavimowHA` | `2331841f1fbb5b28440228426469d2ceab0cbb28` | manufacturer-owned HA integration |
| `TA2k/ioBroker.navimow` | `8f8f00d7cdac258ea70437c1bb0ed4f6e69e4a42` | maintained community/legacy comparison |

Primary source links:

- [official Navimow SDK](https://github.com/segwaynavimow/navimow-sdk)
- [official Navimow Home Assistant integration](https://github.com/segwaynavimow/NavimowHA)
- [original ioBroker adapter](https://github.com/TA2k/ioBroker.navimow)
- [Home Assistant lawn-mower entity contract](https://developers.home-assistant.io/docs/core/entity/lawn-mower)

## 4. Official SDK Command Enum

The current manufacturer-owned SDK defines five command enum values:

```text
START
PAUSE
DOCK
RESUME
STOP
```

`STOP` has existed since the SDK's initial public release commit
`af44444f65eae199eeba5b3d4657108f1997ddc8` dated 2026-03-12. It is not a
recent community addition.

This is a current implementation-level manufacturer support signal.

Source:
[SDK command model](https://github.com/segwaynavimow/navimow-sdk/blob/main/mower_sdk/models.py).

## 5. Official SDK Request Mapping

The current `MowerAPI.async_send_command()` mapping is:

| Symbolic command | Cloud command | Parameters |
| --- | --- | --- |
| Start | `action.devices.commands.StartStop` | `{ "on": true }` |
| Stop | `action.devices.commands.StartStop` | `{ "on": false }` |
| Pause | `action.devices.commands.PauseUnpause` | `{ "on": false }` |
| Resume | `action.devices.commands.PauseUnpause` | `{ "on": true }` |
| Dock | `action.devices.commands.Dock` | omitted |

The endpoint remains:

```text
POST /openapi/smarthome/sendCommands
```

The request shape matches the independently analyzed ioBroker mapping.

Source:
[official SDK API mapping](https://github.com/segwaynavimow/navimow-sdk/blob/main/mower_sdk/api.py).

**Transport-contract finding:** the Stop opcode and request payload are no
longer legacy-only evidence.

## 6. Official SDK Exposure Gap

Despite the internal enum and API mapping, the same SDK has three important
omissions:

- its README advertises Start, Pause, Resume and Dock, but not Stop;
- its documented core capabilities omit Stop;
- its high-level `MowerClient` provides wrappers for Start, Pause, Resume and
  Dock, but no Stop wrapper.

The low-level generic API can receive `MowerCommand.STOP`, but Stop is not part
of the SDK's advertised convenience interface.

This creates a distinction:

| Layer | Stop status |
| --- | --- |
| enum | present |
| low-level command map | present |
| high-level client | absent |
| public SDK feature list | absent |
| semantic documentation | absent |

The distinction prevents treating the mapping alone as a complete public
support promise.

## 7. Official Home Assistant Finding

The current manufacturer-owned Home Assistant integration exposes:

```text
Start
Pause
Resume
Dock
```

It imports the SDK's `MowerCommand` but never invokes `MowerCommand.STOP`.

Source:
[official Navimow lawn-mower entity](https://github.com/segwaynavimow/NavimowHA/blob/main/custom_components/navimow/lawn_mower.py).

This omission is not by itself proof that Navimow rejects Stop. Home
Assistant's official lawn-mower entity contract defines only:

```text
START_MOWING
PAUSE
DOCK
```

and describes Start as starting or resuming a task. It has no separate Stop
feature, service or terminal `Stopped` activity.

Source:
[Home Assistant lawn-mower developer contract](https://developers.home-assistant.io/docs/core/entity/lawn-mower).

**Inference:** the Home Assistant omission can be explained by its entity
model and therefore cannot classify Navimow Stop as unsupported. It also
provides no Stop semantics.

## 8. Official State-Model Finding

The SDK normalizes mower state to:

```text
idle
mowing
paused
docked
charging
error
returning
unknown
```

There is no `stopped` state. Raw `isPaused` maps to `paused`; raw idle variants
map to `idle`.

Consequently, a future Stop verifier would need evidence choosing among at
least:

- Paused;
- Idle;
- Returning;
- Docked;
- another raw state currently normalized away or unknown.

Adding a synthetic Stopped state remains unjustified.

## 9. Manufacturer Documentation Finding

Public manufacturer material uses the word Stop for more than one concept:

- mower manuals describe the physical STOP control as stopping mower
  operation;
- current app support material describes `End Task` as stopping an ongoing
  task;
- an accessory API guide labels `MOWER_STOP` as `Pause mowing`;
- firmware notes separately refer to an emergency-stop state.

Sources:

- [Navimow i2 AWD user manual](https://segwaynavimow.zendesk.com/hc/en-us/article_attachments/55039632761753)
- [Navimow H2 task guidance](https://segwaynavimow.zendesk.com/hc/en-us/articles/54023758855833--Navimow-H2-Why-did-my-Navimow-H2-start-mowing-on-its-own)
- [Navimow developer guide attachment](https://segwaynavimow.zendesk.com/hc/en-us/article_attachments/58146768281113)

These interfaces are not proven to share the Smart Home cloud command
semantics. They demonstrate terminology ambiguity rather than resolve it.

## 10. Community Corroboration

The maintained ioBroker adapter and a recent experimental openHAB binding both
expose the same five symbolic commands. The openHAB binding is described as an
early alpha based on the official SDK and currently advertises Stop without a
published Stop-specific transition report.

Source:
[openHAB Navimow binding discussion](https://community.openhab.org/t/navimow-binding/169223).

No reviewed community evidence establishes:

- which REST state follows Stop;
- whether the current task ends;
- whether Resume remains valid;
- whether Start creates or continues the task;
- how `alreadyInState` should be interpreted;
- model and firmware equivalence.

Community exposure corroborates transport plausibility, not terminal
semantics.

## 11. Resolved Questions

| Question | Finding |
| --- | --- |
| Does a current manufacturer source contain Stop? | yes |
| Exact cloud command | `action.devices.commands.StartStop` |
| Exact parameter | JSON boolean `on=false` |
| Endpoint | existing `sendCommands` endpoint |
| Is it legacy-adapter-only? | no |
| Does Home Assistant omission prove unsupported behavior? | no |
| Is a separate Stopped state documented? | no |
| Is Stop semantically identical to Pause? | not established |
| Is task termination documented for this command? | no |
| Is post-Stop Resume valid? | unknown |
| Is Stop exposed in the SDK high-level client/README? | no |

## 12. Remaining Semantic Questions

Before any Stop write, resolve:

1. Does `StartStop/on=false` pause or end the current mowing task?
2. Which raw and normalized state follows a successful request?
3. Is the expected terminal state Paused or Idle?
4. Can `PauseUnpause/on=true` resume afterward?
5. Must `StartStop/on=true` be used after Stop?
6. Does task progress survive Stop?
7. Does the mower remain stationary or begin Docking?
8. What is `alreadyInState` when Stop is sent from Running, Paused, Idle or
   Docked?
9. Is support uniform across H, i, X and H2 families and current firmware?
10. Why is Stop internal to the SDK but omitted from its README and high-level
    client?

These are protocol-contract questions, not merely UI wording questions.

## 13. Candidate Stop Contract Status

| Contract element | Status |
| --- | --- |
| symbolic command | known |
| endpoint | known |
| cloud command and parameter | confirmed by official SDK |
| response envelope | structurally known from shared endpoint |
| fresh precondition | provisionally Running |
| successful response fixture | missing |
| expected terminal state | unresolved |
| permitted progress states | unresolved |
| already-in-state policy | unresolved |
| task lifecycle after Stop | unresolved |
| safe verification deadline | unresolved |
| model/firmware scope | unresolved |
| recovery path | official app/physical stop available, exact task recovery unknown |

The transport half of the contract is ready. The state and safety half is not.

## 14. Capture Readiness Decision

The current-support prerequisite from step 73 is now satisfied by the official
SDK. The expected-outcome prerequisite is not satisfied.

Therefore:

| Stage | Decision |
| --- | --- |
| static support classification | PASS |
| exact request definition | PASS |
| targeted vendor/upstream clarification | GO |
| capture procedure design | DEFER |
| private one-shot Stop capture | NO-GO |
| fixture promotion | NO-GO |
| productive implementation | NO-GO |
| publication or Symcon action | NO-GO |

No mower should be used to answer the unresolved semantic questions without a
prior expected-state and recovery contract.

## 15. Required Clarification

The next inquiry should be directed first to the manufacturer-owned SDK
repository and ask narrowly:

- whether `MowerCommand.STOP` is a supported public SDK operation;
- why it is absent from README and high-level client wrappers;
- whether it means pause or end task;
- the expected raw/normalized terminal state;
- whether Resume or Start is valid afterward;
- model/firmware constraints;
- `alreadyInState` semantics.

The inquiry must contain no credentials, device identifiers, raw private
payloads or claims that Stop has already been tested.

Possible response classifications:

| Response | Consequence |
| --- | --- |
| supported, semantics documented | plan one supervised capture |
| supported, model-specific | plan only for explicitly supported pilot model |
| internal/unsupported | formally exclude Stop |
| equivalent to Pause | exclude duplicate Stop UI unless a distinct user need exists |
| deprecated | formally exclude and retain transport rejection |
| no response | remain blocked; do not probe experimentally |

## 16. Architecture Decisions

### AD-NAV-250: Upgrade Stop from legacy to official transport evidence

**Decision:** Treat the official SDK enum and low-level mapping as confirmation
of the exact request shape.

**Rationale:** Manufacturer-owned current source independently matches the
legacy adapter.

**Consequence:** Future analysis need not rediscover the opcode or boolean.

### AD-NAV-251: Separate transport support from product support

**Decision:** Do not equate an internal command mapping with a documented
public capability.

**Rationale:** The same SDK omits Stop from its README and high-level client.

**Consequence:** Capture remains blocked pending semantic clarification.

### AD-NAV-252: Treat Home Assistant omission as inconclusive

**Decision:** Do not use the official integration's missing Stop button as an
unsupported verdict.

**Rationale:** Home Assistant itself has no separate lawn-mower Stop feature.

**Consequence:** The SDK repository, not the HA entity surface, is the primary
clarification target.

### AD-NAV-253: Refuse a synthetic Stopped state

**Decision:** Preserve the validated state vocabulary until a real response
identifies the terminal state.

**Rationale:** Neither official state model nor existing fixtures define
Stopped.

**Consequence:** Productive implementation cannot precede transition evidence.

### AD-NAV-254: Require task-lifecycle semantics before physical discovery

**Decision:** Clarify pause-versus-end-task and Resume-versus-Start behavior
before designing a capture.

**Rationale:** Those outcomes determine safety, verification and recovery.

**Consequence:** A mower is not used as the first semantic discovery tool.

### AD-NAV-255: Keep Stop and physical emergency stop distinct

**Decision:** Never describe the cloud Stop action as an emergency stop.

**Rationale:** The physical safety control has separate documented behavior and
must remain immediately available.

**Consequence:** Future UI and documentation must use task-oriented language.

### AD-NAV-256: Prefer formal exclusion over indefinite ambiguity

**Decision:** If the manufacturer classifies Stop as internal, duplicate,
deprecated or unsupported, exclude it explicitly.

**Rationale:** Complete command integration means evidenced support decisions,
not enabling every observed opcode.

**Consequence:** Store readiness can later close Stop by justified exclusion.

## 17. Decision

**Current official Stop transport signal: CONFIRMED.**

**Exact request mapping: CONFIRMED.**

**Stop task and terminal-state semantics: UNRESOLVED.**

**Vendor/upstream clarification: GO.**

**Private capture and productive implementation: NO-GO.**

The evidence is stronger than at step 73 but does not yet permit actuation.
Stop remains absent from forms, public actions and the account allowlist.

## 18. Recommended Next Step

Create `76-stop-vendor-and-upstream-clarification-plan.md` to prepare a
credential-free issue for the manufacturer-owned SDK repository. The plan
must define exact questions, duplicate-search procedure, response
classification, privacy review and the conditions for either a future
supervised capture plan or formal Stop exclusion. It must not send the inquiry
or a mower command yet.
