# 127 Native MQTT Fresh-Client-ID Live Test and Restoration

**Case study:** Navimow native IP-Symcon module
**Status:** One-shot live experiment completed safely; zero Receiver ingress;
runtime and Module Control restored to `main`
**Date:** 2026-07-28
**Scope:** Execute Gate C from step 124 exactly once and close the temporary
diagnostic installation

## 1. Purpose

Step 126 installed and verified the temporary experiment branch without
connecting.

This step:

1. executes the frozen Fresh-Client-ID harness exactly once;
2. observes the native MQTT and WebSocket clients for at most 165 seconds;
3. restores the stable Client ID and disables MQTT in `finally`;
4. returns Module Control to verified `main`;
5. repeats the complete read-only compatibility projection;
6. updates the client-session hypothesis from live evidence.

No mower command or MQTT publish was executed.

## 2. Authorization

The user explicitly authorized the live gate:

```text
Ein einmaliger Fresh-Client-ID-Live-Test mit automatischem Restore und Rückkehr zu main ist freigegeben.
```

Immediately before execution, the user separately confirmed:

```text
Mäher mäht sichtbar und ist beaufsichtigt.
```

## 3. Frozen Source

Executed source:

```text
private/navimow-capture/fresh-client-id-experiment/live-one-shot.php
```

Verified SHA-256:

```text
4622a5f9ae5c9c01db745c5b22e67d11be0ebf9dfa650fecca2e966d529cb06b
```

Static safety verification confirmed:

```text
fresh-ID Connect call sites: 1
Restore call sites:          1
normal Connect call sites:   0
MQTT publish call sites:     0
mower-command call sites:    0
module reload call sites:    0
instance create/delete:      0
```

## 4. Execution Channels

The bounded Symcon script-text execution reported:

```text
MCP transport success: true
transportError:        null
executionError:        null
truncated:             false
harness pass:          true
```

Exactly one experimental Connect and one Restore were invoked.

## 5. Preconditions

All frozen preconditions passed:

- Account connected;
- no reauthentication required;
- access token usable;
- Receiver pairing retained;
- MQTT feature disabled;
- WebSocket inactive;
- authorization header empty;
- MQTT username and password empty;
- stable Client ID present;
- four exact QoS-0 subscriptions;
- no subscription wildcard.

## 6. Live Observation

The run applied a fresh run-specific Client ID without exposing either the
stable or temporary value.

Observed:

```text
initial sample:              315 ms
initial MQTT status:         104
initial WebSocket status:    104
steady MQTT status:          102
steady WebSocket status:     102
final sample:                163086 ms
WebSocket active:            true
Receiver receive delta:      0
Receiver forwarded delta:    0
Receiver last result:        none
```

The native MQTT and WebSocket clients reached and retained status `102` for
the observation window. Nevertheless, no message reached the Receiver.

## 7. Runtime Cleanup

Cleanup completed at:

```text
163104 ms
```

Verified:

| Invariant | Result |
|---|---|
| Restore calls | exactly one |
| Restore result | PASS |
| emergency cleanup | not used |
| cleanup before 180-second deadline | PASS |
| MQTT shadow disabled | PASS |
| WebSocket inactive | PASS |
| authorization header empty | PASS |
| MQTT username empty | PASS |
| MQTT password empty | PASS |
| stable Client ID restored | PASS |

Safety outcome:

```text
PASS
```

## 8. Return to Main

After runtime cleanup, Module Control was changed exactly once to:

```text
branch: main
commit: 046529c5
clean:  true
valid:  true
```

`MC_ReloadModule()` was not used.

A separate PHP execution then verified:

- both temporary experiment wrappers absent;
- both productive diagnostic wrappers present;
- the repository branch and commit exact;
- no stale temporary source surface.

The same-execution wrapper state after the branch mutation was deliberately
not used as evidence because already-loaded PHP functions remain visible until
that script execution ends.

## 9. Compatibility After Restoration

The complete read-only projection passed after returning to `main`.

| Contract | Result |
|---|---|
| productive instance topology | unchanged |
| variable identities and metadata | 14/14 unchanged |
| Archive Control logging | 5/5 unchanged |
| archive history queryability | PASS |
| command evidence | unchanged |
| Receiver pairing | retained |
| subscriptions | four exact QoS 0 |
| wildcard | absent |
| Account authentication | retained |
| MQTT feature | disabled |
| WebSocket | inactive |
| credentials | empty |

All four structural hashes are byte-equal to the repeated pre-update
baseline. The user's configured mower-variable logging remains intact.

## 10. Hypothesis Result

Fresh Client ID as the sole changed variable:

```text
NOT SUFFICIENT
```

The experiment weakens the hypothesis that the zero-ingress behavior is caused
only by reusing the retained MQTT Client ID.

It does not prove that client-session identity is irrelevant in every
combination. It does prove that one fresh identity, with otherwise unchanged
native topology, credentials and subscriptions, produced the same zero-ingress
result while both Core clients remained healthy.

The next investigation should therefore move away from repeating Client-ID
variants and toward the boundary between the native MQTT Client and its child
Receiver, including broker delivery semantics, subscription activation and
possible sibling-session behavior.

## 11. Private Evidence

Machine-readable evidence is stored below:

```text
private/navimow-capture/output/
  native-mqtt-fresh-client-id-experiment/
    live-one-shot-result.json
    main-restoration.json
```

The projection contains no credential, endpoint, topic, payload, client ID,
device identity, ObjectID or garden detail.

## 12. Architecture Decisions

### AD-NAV-464: Classify safety independently from ingress

**Decision:** Close the run as operationally successful despite zero Receiver
ingress.

**Reason:** Restore, disablement and source rollback passed independently of
the experimental hypothesis.

### AD-NAV-465: Reject repeat testing of the same variable

**Decision:** Do not repeat or vary the Fresh-Client-ID experiment.

**Reason:** The approved one-variable test completed without ambiguity and a
retry would add device and broker exposure without a new hypothesis.

### AD-NAV-466: Verify wrapper removal in a new execution

**Decision:** Use a separate PHP execution after Module Control rollback.

**Reason:** PHP functions loaded before a module update can remain visible
inside the current execution and are not valid post-update source evidence.

### AD-NAV-467: Preserve REST authority

**Decision:** Keep REST authoritative for public Device variables.

**Reason:** No native MQTT message reached the Receiver, and the experiment
provides no basis for changing the established authority contract.

## 13. Gate Result

One-shot execution:

```text
PASS
```

Receiver ingress:

```text
ZERO
```

Automatic runtime restoration:

```text
PASS
```

Return to verified `main`:

```text
PASS
```

Variable and Archive Control preservation:

```text
PASS
```

## 14. Temporary Branch Deletion

After runtime and source restoration passed:

1. the clean publication clone was switched to `main`;
2. local and remote `main` were verified at the unchanged baseline commit;
3. the remote experiment branch was deleted;
4. the local experiment branch was deleted;
5. branch absence was verified through local and remote references.

Final publication-clone state:

```text
branch:                   main
HEAD:                     046529c518feefb15a51bd2f1c404401b3a7f474
origin/main:              046529c518feefb15a51bd2f1c404401b3a7f474
worktree:                 clean
local experiment branch: absent
remote experiment branch: absent
```

The temporary diagnostic installation and publication are therefore fully
closed.
