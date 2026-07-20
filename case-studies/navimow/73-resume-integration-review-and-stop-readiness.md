# 73 Resume Integration Review and Stop Readiness

**Case study:** Navimow native IP-Symcon module
**Status:** Resume evidence closed; pilot tag preparation approved; Stop remains research-only
**Date:** 2026-07-12
**Scope:** Consolidate Pause/Resume and decide the next Stop and release gates

## 1. Purpose

This step reviews the complete Resume integration chain after the direct
Symcon PASS in `72-resume-command-symcon-test-report.md` and decides:

- whether Pause and Resume are sufficiently proven for private-pilot use;
- whether the command-expanded build warrants a new immutable pilot tag;
- whether Stop may advance to evidence discovery, private capture or code;
- which Stop semantics and safety questions must be resolved first;
- which broader release and Store boundaries remain unchanged.

This is an analysis and architecture-decision step. It changes no productive
PHP, module metadata, Git tag or live Symcon configuration and sends no mower
command.

## 2. Reviewed Inputs

The review consolidates:

- `55-command-integration-sequence-and-safety-plan.md`;
- steps 56 through 64 for Pause evidence, implementation and live validation;
- steps 65 through 72 for Resume evidence, implementation and live validation;
- canonical Pause and Resume fixtures;
- all 29 deterministic command and recovery harness cases;
- the private pre-update compatibility baseline;
- the current published module commit and direct Symcon evidence.

No private capture, credential, ObjectID, archive value or installation detail
is copied into this document.

## 3. Reviewed Module State

Repository:

```text
https://github.com/doctee/symcon-navimow.git
```

Reviewed `main` commit:

```text
64188f75527abcb49b0b27ce2b56ad2d34a403fd
feat: add bounded Resume command
```

Enabled commands:

```text
Pause
Resume
Dock
```

Disabled commands:

```text
Stop
Start
```

The command-expanded commit is intentionally untagged. Existing immutable
pilot tags remain:

```text
pilot-0.1.0.1
pilot-0.1.0.2
```

## 4. Pause and Resume Evidence Matrix

| Evidence area | Pause | Resume |
| --- | --- | --- |
| static source mapping | passed | passed |
| current upstream support signal reviewed in step 55 | present | present |
| exact sanitized request fixture | passed | passed |
| successful response fixture | passed | passed |
| terminal state fixture | Paused | Running |
| isolated private one-shot capture | passed | passed |
| physical transition observed | stopped | normal mowing resumed |
| fresh current-state eligibility | Running | Paused |
| one-write maximum | passed | passed |
| command retry prevention | passed | passed |
| deterministic success | passed | passed |
| deterministic wrong-state rejection | passed | passed |
| deterministic failed-read rejection | passed | passed |
| deterministic timeout | passed | passed |
| unexpected-state fail closed | passed | passed |
| restart reconstruction | read-only | read-only |
| unsupported already-in-state | not used for success | rejected fail closed |
| official schema validation | passed | passed |
| exact remote publication | passed | passed |
| direct Symcon transition | passed | passed |
| public variable identity | retained | retained |
| five archive logging streams | retained | retained |
| official-app cleanup | passed | passed |

## 5. Resume Closure Decision

**Resume integration evidence: CLOSED for the current private pilot.**

The published implementation has agreement across:

- exact request and response evidence;
- current-state eligibility;
- later REST `Running` state;
- terminal module result `Verified`;
- visible normal mowing;
- one-write and no-retry proof;
- update, variable and archive compatibility.

Resume may remain enabled under the documented supervised private-pilot
boundary.

This closure does not claim:

- official vendor API support;
- unattended actuator safety;
- broad model or firmware compatibility;
- productive timeout or rejection induction;
- public OAuth readiness;
- Store or broad public-release readiness.

## 6. Combined Command-Lifecycle Review

Pause and Resume now form a complete suspended-task lifecycle:

```text
Running
  -> one Pause
  -> Paused
  -> one Resume
  -> Running
```

Each direction has its own:

- current-state precondition;
- exact boolean payload;
- sanitized cloud acceptance fixture;
- bounded read-only verification;
- unexpected-state handling;
- deterministic restart and timeout evidence;
- isolated physical transition evidence;
- direct Symcon verification.

The implementation shares timing and transport mechanics without merging the
command-specific policies. Dock remains an independent long-running return
lifecycle with its own 15-minute deadline and Docking progress state.

## 7. Regression and Compatibility Review

The command-expanded build preserves:

- one account command transport path;
- one active command per device;
- no write retry after any ambiguous outcome;
- restart reconstruction with status reads only;
- all existing Dock behavior and harness cases;
- stable command/profile association values;
- all eight public variable identities;
- the operator's five archive logging configurations and histories;
- OAuth, discovery and read-only polling behavior;
- explicit rejection of Stop and Start.

No migration or public-variable addition was required for Pause or Resume.

## 8. Pilot Tag Decision

**Decision: GO for preparation of one new immutable private-pilot tag.**

The deferral condition from step 64 is now satisfied: Resume has a terminal
implementation, publication, compatibility and supervised live-test decision.
Pause and Resume identify a coherent user-facing feature boundary rather than
an intermediate code state.

The next sequential tag under the established policy is:

```text
pilot-0.1.0.3
```

The tag must be annotated and must identify a documentation-complete commit in
the dedicated module repository. It must not be created directly on the
current code commit until the README accurately states:

- enabled Pause, Resume and Dock scope;
- movement and supervision warnings;
- one-write and bounded-verification behavior;
- Stop and Start exclusion;
- private-pilot and unofficial-API limitations;
- public OAuth and Store blockers.

`library.json` remains at private-pilot metadata. No `v*` release tag is
authorized.

## 9. Tag Acceptance Gate

Before creating `pilot-0.1.0.3`, require:

1. exact canonical/published README parity;
2. all productive PHP and JSON validation;
3. all 29 deterministic harness cases;
4. distribution structure validation;
5. ten official schema passes;
6. privacy and whitespace checks;
7. remote `main` verification;
8. exact tag target verification after push;
9. proof that `pilot-0.1.0.1` and `pilot-0.1.0.2` did not move;
10. no productive code delta in the tag-publication step.

The tag denotes controlled private-pilot evidence only.

## 10. Stop Evidence Baseline

Current case-study evidence for Stop is materially weaker:

| Evidence | Status |
| --- | --- |
| legacy ioBroker symbolic mapping | available |
| provisional payload | `StartStop` with boolean `false` |
| current official integration support signal reviewed in step 55 | absent |
| vendor documentation | absent |
| sanitized request fixture | absent |
| sanitized success response | absent |
| rejection response | absent |
| terminal state fixture attributable to Stop | absent |
| already-in-state semantics | unknown |
| distinction from Pause | unknown |
| task-resumption behavior | unknown |
| model/firmware scope | unknown |
| physical transition evidence | absent |
| productive implementation | absent and rejected |

The known legacy request shape is insufficient to authorize a write.

## 11. Stop Semantic Risks

`Stop` must not be treated as a stronger Pause without evidence. Plausible but
unproven outcomes include:

- entering Paused while retaining the current task;
- entering Idle and terminating the task;
- triggering return-to-station behavior;
- being rejected as unsupported;
- behaving differently by mower model or firmware;
- accepting the request while exposing an unexpected state;
- making Resume invalid and requiring a new Start.

These outcomes imply different terminal states, timeout policies, recovery
procedures and user-facing meanings. A generic `Stopped` variable state must
not be invented because the observed device model does not currently expose
one in the validated state contract.

## 12. Stop Safety Classification

Stop is motion-reducing in name, but its lifecycle effect is unknown. It is
therefore classified as:

```text
Actuation risk: medium
Semantic uncertainty: high
Implementation readiness: blocked
```

The physical risk may be lower than Start, but semantic uncertainty can still
cause task loss, unexpected docking, inability to resume or misleading UI.
The physical stop control remains an emergency mechanism and is not equivalent
to the cloud Stop command.

## 13. Stop Readiness Decision

| Stage | Decision |
| --- | --- |
| static non-actuating research | GO |
| vendor/upstream clarification | GO |
| fixture contract definition | BLOCKED pending evidence |
| private Stop capture | NO-GO |
| productive PHP implementation | NO-GO |
| publication or Symcon action | NO-GO |

No Stop command may be sent merely to discover what it does.

## 14. Required Non-Actuating Research

The Stop track must first determine:

1. whether current Navimow sources or vendor material support a distinct Stop;
2. why the official integration reviewed in step 55 omits it;
3. whether `StartStop/on=false` is current or legacy behavior;
4. the expected terminal state and permitted transient states;
5. whether a stopped task can be resumed or must be started anew;
6. whether Stop is model-, region- or firmware-specific;
7. known rejection and already-in-state meanings;
8. whether Pause already represents the complete supported stop-like action;
9. a safe official-app recovery path for every plausible outcome;
10. a concrete user need not already satisfied by Pause and Dock.

Preferred evidence order:

```text
vendor documentation or response
-> current official integration/source history
-> current maintained community integrations
-> credential-free issue/discussion evidence
-> only then a proposed supervised capture gate
```

Legacy code alone cannot move the track beyond research.

## 15. Conditions for a Future Stop Capture GO

A later planning step may propose one supervised Stop capture only when all of
these are documented:

- a current support signal beyond the legacy adapter;
- exact request contract;
- expected physical and REST terminal outcome;
- permitted transient states;
- distinction from Pause;
- expected Resume/Start behavior afterward;
- bounded observation deadline;
- no-retry policy;
- official-app and physical recovery procedure;
- model and firmware assumptions;
- explicit operator approval for the one-write test.

If no current support signal is found, Stop must be formally excluded rather
than experimentally probed.

## 16. Start and Store Boundary

Start remains last in the command sequence and receives no readiness approval
from this review. It initiates a new mowing task and requires independent
contract, capture, implementation and supervised evidence.

Concrete Symcon Store setup and submission remain deferred until:

- Start is complete;
- Stop is either complete or formally excluded with current evidence;
- command-set regression is consolidated;
- public OAuth/vendor feasibility is resolved;
- current Store requirements are revalidated.

The Store track may continue to collect planning requirements only.

## 17. Architecture Decisions

### AD-NAV-240: Close Pause and Resume as one lifecycle milestone

**Decision:** Treat both inverse transitions as complete for controlled
private-pilot use.

**Rationale:** Each direction independently passed fixture, deterministic,
publication, compatibility and supervised physical gates.

**Consequence:** The suspended-task lifecycle may receive one coherent pilot
marker.

### AD-NAV-241: Approve a documentation-first pilot tag

**Decision:** Prepare `pilot-0.1.0.3` only after README validation and a
documentation-only publication.

**Rationale:** An immutable marker must include its operational safety and
scope contract.

**Consequence:** The current code commit remains untagged until the publication
step passes.

### AD-NAV-242: Classify Stop by semantic uncertainty

**Decision:** Do not infer Stop behavior from its name or legacy payload.

**Rationale:** Current terminal state, task lifecycle and official support are
unknown.

**Consequence:** Stop remains rejected in transport, forms and public actions.

### AD-NAV-243: Require a current support signal before Stop capture

**Decision:** Perform non-actuating source and vendor research before proposing
any private API write.

**Rationale:** A live mower must not be used as the first protocol discovery
tool for an ambiguous command.

**Consequence:** Missing current evidence leads to formal exclusion, not an
experimental command.

### AD-NAV-244: Preserve the existing state vocabulary

**Decision:** Do not add a synthetic Stopped state without captured device
evidence.

**Rationale:** Public state must represent observed cloud/device semantics.

**Consequence:** Any future Stop contract must map to a real validated state.

### AD-NAV-245: Keep Start independently gated

**Decision:** Resume success does not advance Start readiness.

**Rationale:** Resuming a known paused task and initiating a new task have
different hazards and prerequisites.

**Consequence:** Start remains disabled until the Stop support decision is
closed and its own evidence chain begins.

## 18. Decision

**Pause integration: CLOSED for private pilot.**

**Resume integration: CLOSED for private pilot.**

**Command-expanded pilot tag preparation: GO.**

**Stop non-actuating research: GO.**

**Stop capture, implementation and publication: NO-GO.**

**Start, Store submission and broad public release: NO-GO.**

## 19. Recommended Next Steps

Execute `74-command-expanded-pilot-tag-publication.md` first to refresh and
validate the module README, publish a documentation-only commit, create the
annotated `pilot-0.1.0.3` tag and verify historical tag immutability.

After that immutable checkpoint, create
`75-stop-support-and-semantics-analysis.md` for credential-free, non-actuating
current-source and vendor research. That analysis must choose either a
conditional capture-planning GO or formal Stop exclusion.
