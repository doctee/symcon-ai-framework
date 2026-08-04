# 148 Native MQTT Passive Pilot Publication and Symcon Test Plan

**Case study:** Navimow native IP-Symcon module
**Status:** Publication and passive pilot planned; all execution gates closed
**Date:** 2026-07-28
**Scope:** Freeze and gate publication, disabled Symcon verification and the
receive-only MQTT passive pilot after recovery hardening

## 1. Purpose

Step 145 proved corrected native MQTT ingress. Step 147 then implemented and
offline-verified:

- credential-free delayed reconstruction after `ApplyChanges()`;
- controlled MQTT rotation after OAuth refresh;
- immediate cleanup after an unexpected Core disconnect;
- reconnect delays of 60, 300 and 900 seconds;
- termination after exactly three failed reconnect attempts;
- no transport retry for authentication or configuration failures;
- reconnect-episode reset after 15 healthy minutes;
- complete disable and cleanup behavior.

This step freezes the candidate and defines separately authorized publication,
Symcon and passive live-pilot gates.

It performs no publication, Symcon mutation, MQTT connection, REST request or
mower command.

## 2. Fixed Architecture Boundary

Throughout all later gates:

- REST remains the only authority for public mower variables;
- MQTT is receive-only and may only queue bounded targeted REST
  reconciliation;
- no MQTT publish path is permitted;
- MQTT recovery never invokes Start, Pause, Resume, Dock or Stop;
- existing Account, Configurator, Device and Receiver instances are retained;
- all 14 Device variables retain identity and metadata;
- all five Archive Control logging contracts remain unchanged;
- no Core instance is created, deleted or reparented automatically;
- explicit ownership validation remains mandatory;
- MQTT remains default-disabled;
- `MC_ReloadModule()` is not used;
- no tag is created;
- credentials and private installation data remain outside public artifacts.

MQTT failure must never stop normal REST polling.

## 3. Connectivity Classification

The pilot must distinguish:

```text
A: mower -> Navimow cloud
B: Symcon -> Navimow MQTT/WSS broker
```

### Mower-side degradation

If the mower temporarily has poor network reception while Core MQTT and
WebSocket remain healthy:

- MQTT events may be absent or delayed;
- no broker reconnect is started;
- REST polling continues;
- public state remains REST-derived;
- the observation is classified as missing or stale mower evidence, not as a
  transport failure.

### Broker-side degradation

If the owned MQTT or WebSocket Core transport becomes unhealthy:

- credentials are cleaned;
- reconnect follows only `60/300/900` seconds;
- no fourth attempt occurs;
- after exhaustion MQTT remains `Disconnected`;
- REST continues as the operational fallback.

### Authentication and configuration

Authentication errors wait for successful OAuth recovery or explicit
reauthorization. Configuration and ownership errors wait for corrected
configuration and `ApplyChanges()`. Repeating the same broker attempt cannot
repair either class and is therefore prohibited.

## 4. Frozen Publication Candidate

Locally inspected standalone repository:

```text
private/navimow-publish-20260708
```

Locally known clean baseline:

```text
branch: main
commit: 511c7bbe617ee92801a9d336b96254b9b6a6adda
subject: fix(mqtt): use native QoS subscription field
```

At planning time local `HEAD` equals the locally known `origin/main`. Gate A
must fetch and revalidate the actual remote state; this plan does not claim
that the remote cannot change later.

Exact productive publication file:

```text
NavimowAccount/module.php
```

Candidate hash:

```text
4127b75e2dd451141a771f5244f185e43a7b4d3a158e6ddc2f59b630e562e48b
```

Locally known baseline hash:

```text
df820993599dded7962ae2998345db9694d44146ad33793a893e75454322fc3a
```

Content comparison against the local baseline identifies:

```text
modified: 1
added:    0
deleted:  0
```

Filesystem timestamp differences and ignored `.DS_Store` files are not
publication content.

Supporting SAEF-only evidence:

```text
tests/mqtt-transport-lifecycle.php:
0092b1626a8b94c728458b36100e131e07c6ce38e9afba13def6b75746d4adef

fixtures/mqtt/bounded-diagnostics-shadow-active.json:
be59a4c7cd31c5a4addbe0ba23bb0c2585bfe3836cec34fef31fe293635d558d
```

Tests, fixtures and SAEF reports are not copied to the standalone module
repository.

## 5. Gate A: Productive Publication

Required explicit authorization:

```text
Veröffentlichung der MQTT-Pilot-Recovery-Härtung auf main freigegeben.
```

Gate A permits only:

1. fetch and prune the standalone repository;
2. require clean local `main` equal to current `origin/main`;
3. re-run the complete Navimow offline gate;
4. require the frozen candidate hash;
5. copy exactly `NavimowAccount/module.php`;
6. prove one modified and no added or deleted productive file;
7. run standalone syntax, metadata, privacy and module validation;
8. inspect the complete staged diff;
9. commit and push one fast-forward `main` commit;
10. fetch and prove remote commit and blob equality;
11. close private machine-readable and sanitized public evidence.

Suggested commit:

```text
feat(mqtt): harden passive pilot recovery
```

Gate A permits no Symcon update, tag or broker connection.

### Gate-A stop conditions

Stop before commit when:

- remote `main` diverged or the publication clone is dirty;
- any file other than the frozen Account module differs;
- the candidate hash changed;
- a regression, PHPCS, PHPStan, distribution or module validation fails;
- a publish or mower-command path appears in MQTT lifecycle code;
- private data is detected.

## 6. Gate B: Disabled Symcon Update

Required explicit authorization after verified publication:

```text
Symcon-Update auf die MQTT-Pilot-Recovery-Härtung mit read-only Prüfung freigegeben.
```

Before the update, capture the established bounded projection twice and
require stable evidence for:

- installed branch and commit;
- Account, Configurator, Device and Receiver identity and status;
- MQTT ownership and topology classification;
- MQTT feature disabled;
- WebSocket inactive;
- credential-presence booleans all false;
- all 14 Device variable contracts;
- all five Archive Control logging contracts;
- OAuth, REST-read and command compatibility.

Gate B permits:

1. one supported Module Control update;
2. no `MC_ReloadModule()`;
3. repeated read-only projection;
4. module and wrapper availability checks;
5. bounded diagnostics reads;
6. proof that MQTT stayed disabled and credential-free.

Any variable, archive, instance or REST regression stops the sequence before
pilot staging.

## 7. Gate C: Inactive Pilot Staging

Required explicit authorization:

```text
Inaktives Staging des receive-only MQTT-Piloten freigegeben.
```

Gate C may only:

1. validate the already retained WebSocket/MQTT/Receiver chain;
2. verify Account/Receiver symmetric pairing;
3. verify canonical four-topic `Topic`/`QoS` subscriptions;
4. verify ownership hashes;
5. verify WebSocket inactive and credentials empty;
6. record a private pre-pilot baseline.

It permits no feature enable, credential request, broker connection or mower
operation. Core instances must not be recreated or reparented.

## 8. Gate D: Passive Pilot Activation

Required explicit authorization:

```text
Aktivierung des receive-only MQTT-Piloten mit Disable-Fallback freigegeben.
```

Gate D permits:

- one explicit Account opt-in;
- the implemented five-second delayed connection;
- receive-only broker traffic;
- bounded lifecycle, Receiver and reconciliation observations;
- immediate disable and cleanup on any stop condition.

There is no manual second Connect after an ambiguous activation. The mower may
remain docked or follow its existing schedule. No artificial mower action is
authorized by this gate.

Initial activation passes only when:

- exactly one credential request and connection attempt occur;
- Core MQTT and WebSocket become healthy;
- at least one Receiver ingress is observed when a natural mower event is
  available;
- targeted REST reconciliation stays coalesced;
- public variables remain REST-authoritative;
- no private value enters diagnostics or evidence.

Healthy Core without a mower message is `transport-ready/data-pending`, not a
connection failure.

## 9. Gate E: Restart Observation

Required separately after stable activation:

```text
Ein beaufsichtigter Symcon-Restart während des receive-only MQTT-Piloten ist freigegeben.
```

The observation must prove:

1. startup removes transient credentials;
2. no network call occurs inline in `ApplyChanges()`;
3. one connection is scheduled after five seconds;
4. exactly one reconstruction occurs;
5. no duplicate Core topology appears;
6. REST polling and public variables continue;
7. archive contracts remain unchanged.

Failure or ambiguity permits only disable, cleanup and evidence closure.

## 10. Gate F: Natural Token Rotation

Required separately:

```text
Passive Beobachtung der natürlichen OAuth- und MQTT-Credential-Rotation freigegeben.
```

No token expiry, credential failure or reauthentication is induced.

A naturally successful OAuth refresh must show:

- one transport cleanup;
- one `token-rotation` lifecycle reason;
- one delayed credential request and connection;
- healthy Core confirmation;
- no duplicate reconnect;
- no token, header or MQTT credential in evidence.

OAuth failure must leave MQTT inactive while the existing OAuth state machine
and REST fallback remain responsible.

## 11. Gate G: Degraded-Connectivity Observation

Required separately only when a natural event is available:

```text
Passive Beobachtung einer natürlichen MQTT- oder Mäher-Verbindungslücke freigegeben.
```

Do not disable Wi-Fi, block traffic, alter DNS, invalidate tokens or otherwise
manufacture a failure.

Classify natural evidence as:

| Class | Core transport | MQTT events | Expected behavior |
|---|---|---|---|
| mower/cloud gap | healthy | absent or delayed | no reconnect; REST continues |
| broker gap | unhealthy | absent | cleanup and bounded reconnect |
| authentication | irrelevant | absent | no transport retry; OAuth owns recovery |
| configuration | invalid | irrelevant | no retry; operator correction required |

If three broker retries are naturally exhausted, the pilot records the
REST-only fallback and stops before any manual reconnect in the same evidence
run.

## 12. Circuit-Breaker Follow-up

The current implementation deliberately has no autonomous fourth attempt.

Before unattended production activation, the pilot review must decide whether
to add:

```text
Disconnected
  -> long quiet period
  -> one half-open probe
  -> healthy or another long quiet period
```

Candidate parameters such as one probe per hour are not selected in this
step. The decision requires observed failure duration, OAuth behavior and
operational need.

Until that decision is closed:

- private pilot operation may use the finite retry model;
- REST-only fallback is accepted;
- broad release or default MQTT activation remains blocked.

## 13. Pilot Evidence

Private machine-readable evidence may contain installation identifiers only
below `private/`. The public report records sanitized aggregates:

```text
module commit
observation class
rounded timestamps
Core status sequence
lifecycle reason codes
connection and reconnect counter deltas
Receiver ingress and forwarding deltas
REST reconciliation deltas
REST state sequence
authentication classification
credential-empty booleans
variable and archive contract hashes
```

Never record:

- tokens, secrets, headers or MQTT credentials;
- endpoint, host, Client ID, Device ID or topic;
- raw REST/MQTT payload;
- ObjectIDs;
- location, map or garden data.

## 14. Stop Conditions

Disable and clean MQTT immediately when:

- credentials remain after a failed connection;
- topology or ownership validation changes;
- overlapping or excess connection attempts occur;
- reconnect exceeds `60/300/900` or performs a fourth attempt;
- authentication or configuration errors enter transport retry;
- REST polling stops;
- MQTT writes public variables directly;
- targeted REST requests violate the 30-second coalescing contract;
- any publish or mower-command operation occurs;
- instance, variable or Archive Control contracts change;
- diagnostics expose private data.

Physical mower behavior is observed only for correlation and is never
controlled by this pilot.

## 15. Pilot Completion

The passive pilot is event-based with a seven-day maximum. Target evidence:

- initial activation;
- at least three natural mower transitions;
- one docked idle period;
- one supervised Symcon restart;
- one natural OAuth refresh if it occurs within the window;
- one natural connectivity gap if it occurs;
- final manual disable and verified cleanup.

An event that does not occur naturally is reported as `not observed`, not
manufactured. Seven days without all optional natural events is incomplete
evidence, not an automatic failure.

Final closure requires separate authorization:

```text
Abschluss des MQTT-Piloten mit Disable, Credential-Cleanup und Statusbericht freigegeben.
```

## 16. Current Gate Decision

| Gate | Decision |
|---|---|
| corrected ingress | PASS |
| offline recovery hardening | PASS |
| publication candidate frozen | PASS |
| productive publication | CLOSED |
| Symcon update | CLOSED |
| inactive staging | CLOSED |
| passive activation | CLOSED |
| restart observation | CLOSED |
| natural token observation | CLOSED |
| degraded-connectivity observation | CLOSED |
| MQTT state authority | PROHIBITED |
| MQTT publish | PROHIBITED |
| REST state authority | RETAINED |
| default or broad MQTT release | BLOCKED |

## 17. Decision

The exact one-file recovery publication is ready for separately authorized
execution.

The next SAEF step after Gate-A authorization should be:

```text
149-native-mqtt-passive-pilot-recovery-publication.md
```

It must publish and remotely verify only the frozen Account module and stop
before any Symcon mutation.
