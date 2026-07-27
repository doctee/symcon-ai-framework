# 92 Native MQTT WSS Symcon Live Spike Plan

**Case study:** Navimow native IP-Symcon module
**Status:** Live spike planned; probe implementation and explicit mutation authorization pending
**Date:** 2026-07-27
**Scope:** Define a disposable receive-only Symcon test, evidence closure and verified rollback

## 1. Purpose

This step turns the conditional topology decision from
`91-mqtt-symcon-transport-topology-spike.md` into an executable live-test plan.

The future spike must prove:

- a Navimow WSS upgrade through the native IP-Symcon WebSocket Client;
- MQTT CONNECT and exact subscriptions through the native MQTT Client;
- receipt of at least one allowed MQTT message by a custom child module;
- the actual native MQTT `ReceiveData()` envelope;
- zero MQTT publish attempts;
- zero REST command attempts;
- complete removal of every temporary object;
- unchanged productive Navimow instances, variables and archive configuration.

This planning step:

- performs no Symcon mutation;
- retrieves no credential;
- connects to no broker;
- changes no productive module;
- creates no probe implementation yet;
- sends no mower command.

## 2. Why a Dedicated Probe Is Required

Installed module metadata proves that the native WebSocket Client implements
the parent interface required by the native MQTT Client.

It does not prove:

- successful Navimow authentication;
- broker compatibility;
- subscription acknowledgement;
- the JSON envelope delivered by the MQTT Client to a custom child.

A native MQTT Client Device could prove that a payload reached a temporary
variable. It would not prove the custom-child envelope required by the future
`NavimowAccount::ReceiveData()` implementation.

The live spike therefore uses a dedicated receive-only probe module. The probe
exists only to inspect and reduce the envelope to a private machine-readable
shape report.

## 3. Safety Classification

The spike is a live installation mutation because it will:

- temporarily update the installed Navimow library to a dedicated probe branch;
- create temporary WebSocket, MQTT and probe instances;
- connect to the vendor broker;
- receive private telemetry;
- delete those objects and restore the library branch afterward.

It is not a real-device action test:

- no REST command endpoint is called;
- no MQTT message is published;
- no action variable is created;
- the mower may remain in its normal current state;
- the official app does not need to start, stop or redirect the mower.

The connection may be tested while the mower is docked or operating normally.
No physical-state change is required for success.

## 4. Separate Authorization Gates

One approval must not silently authorize every phase.

### Gate A: Probe implementation and publication

Required authorization:

```text
Implementiere und veröffentliche den receive-only Probe-Zweig.
```

This gate permits:

- adding the test-only probe source inside this case study;
- offline tests and validation;
- creating a dedicated branch in the standalone module repository;
- publishing that branch;
- no Symcon update or instance mutation.

### Gate B: Symcon mutation and broker connection

Required authorization after review of Gate A:

```text
Der native MQTT/WSS-Symcon-Live-Spike ist freigegeben.
```

This gate permits only the bounded topology and cleanup defined here.

It does not authorize:

- changing productive Navimow source;
- sending a mower command;
- MQTT publish;
- changing productive variables or archive settings;
- leaving the temporary topology installed.

### Gate C: Productive shadow implementation

Gate C is not part of this plan. It may be opened only by a successful,
closed live report.

## 5. Probe Delivery Strategy

The probe is added on a dedicated branch of the standalone
`symcon-navimow` repository.

Proposed branch:

```text
spike/native-mqtt-wss-receive-probe
```

The branch may add only one test module:

```text
NavimowMqttReceiveProbe/
  module.json
  module.php
  form.json
  locale.json
```

The productive files must be byte-identical to `main`, including:

```text
NavimowAccount/
NavimowConfigurator/
NavimowDevice/
libs/
library.json
README.md
```

The publication gate must generate and compare SHA-256 manifests for every
productive file before the branch is pushed.

The probe:

- receives data from the native MQTT Client;
- cannot become a parent of productive Navimow instances;
- creates no public state variable;
- exposes no action;
- contains no REST client;
- contains no MQTT send method;
- calls neither `SendDataToParent()` nor `SendDataToChildren()`;
- stores no complete topic, payload or credential;
- records only bounded structural evidence;
- ignores messages after its configured evidence limit.

The branch is temporary and must never receive a pilot or release tag.

## 6. Probe Module Contract

### 6.1 Metadata

The probe requires the native MQTT Client receive interface identified in step
91:

```text
parentRequirements:
  {043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}

implemented:
  {7F7632D9-FA40-4F38-8DEA-C83CD4325A32}
```

The module GUID must be newly generated and validated. It must not be reused by
any productive or test module.

### 6.2 Private configuration

The instance may accept:

- a private expected device identifier;
- the four exact expected topics;
- maximum message count;
- maximum envelope size;
- maximum decoded payload size.

The configuration form must mask or avoid rendering private values after
initial entry where Symcon permits it.

No private configuration value may be returned by the public report method.

### 6.3 Receive behavior

For each `ReceiveData()` invocation, the probe must:

1. enforce the total-envelope byte limit before JSON decoding;
2. require a JSON object;
3. require the expected MQTT receive `DataID`;
4. classify envelope keys and value types;
5. locate topic and payload candidates without assuming their names before the
   first fixture exists;
6. reject wildcards and topics outside the four exact configured values;
7. require a recognized channel suffix;
8. enforce the decoded-payload byte limit;
9. classify JSON payload type and top-level keys;
10. increment bounded counters;
11. retain only hashes, lengths, key/type shapes and channel names;
12. stop accepting evidence after the message or time bound.

The probe must not call any productive Navimow parser during this spike.

### 6.4 Bounded report

The report method may return:

```text
formatVersion
probeVersion
startedAt
closedAt
receiveCallCount
acceptedMessageCount
rejectedMessageCount
oversizedMessageCount
unknownTopicCount
channelCounts
envelopeShapes
payloadShapes
minimumPayloadBytes
maximumPayloadBytes
publishAttemptCount
commandAttemptCount
limitReached
```

It must not return:

- full topics;
- topic hashes derived without a public salt;
- payload values;
- payload hashes that enable correlation with private raw evidence;
- device or account identifiers;
- timestamps originating from the mower;
- WSS URL or broker host;
- usernames, passwords, tokens or headers;
- coordinates or geometry.

`publishAttemptCount` and `commandAttemptCount` are invariant counters fixed at
zero because the corresponding paths do not exist.

### 6.5 Probe persistence

During the short test, bounded aggregate evidence may be retained in instance
buffers or attributes.

The probe must not:

- write a file on the Symcon host;
- create child variables;
- enable archive logging;
- write to the Registry;
- write to a production diagnostic buffer;
- use the Symcon log for raw data.

Deleting the probe instance must remove all retained aggregate evidence from
the installation.

## 7. Offline Probe Gates

Before publication, tests must prove:

- valid synthetic MQTT envelopes are reduced to safe shapes;
- malformed JSON is rejected;
- oversized envelope and payload are rejected before retention;
- exact topics are accepted;
- wildcard or unknown topics are rejected;
- mismatched topic and payload device identity fail closed;
- string, object, array, boolean, integer, float and null types are classified;
- raw strings never appear in the exported report;
- all counters remain bounded;
- evidence stops after the configured limit;
- repeated `ApplyChanges()` is idempotent;
- no variable, timer, action or child object is created;
- no send method or command transport exists;
- source scans find no publish topic, `SendDataToParent()` or REST command call.

Required gates:

```text
PHP syntax
PHPCS
PHPStan
focused probe tests
module metadata validation
Symcon Module Validator
distribution-difference manifest
make check
```

## 8. Private Credential Preparation

Fresh credentials are retrieved immediately before the live run through the
existing private Mac capture workflow.

They remain below:

```text
private/navimow-capture/
```

A future local helper may prepare:

```text
private/navimow-capture/output/symcon-mqtt-spike/input.local.json
```

Required mode:

```text
600
```

The private input may contain:

- WSS URL;
- OAuth Bearer token for the upgrade header;
- MQTT username and password;
- selected device identifier;
- four exact topics;
- a unique client ID.

The helper must:

- print no secret value;
- copy no secret to the public report;
- reject non-`wss://` URLs;
- require certificate verification;
- reject wildcard topics;
- expire or delete the input after closure.

No credential is pasted into this conversation or passed as an MCP tool
argument.

## 9. Division of Work

### 9.1 Tasks Codex can perform through MCP after Gate B

Using bounded reviewed PHP, Codex can:

- capture a private installation baseline;
- inventory the relevant module and instance topology;
- create a uniquely named temporary category;
- create unconfigured temporary core and probe instances;
- connect the probe to the MQTT Client and the MQTT Client to the WebSocket
  Client;
- apply non-secret bounded settings;
- inspect instance status and configuration shape;
- read the probe's sanitized aggregate report;
- deactivate and delete owned temporary objects;
- verify baseline equality after cleanup.

Every MCP result must be checked separately for:

- transport error;
- PHP execution error;
- truncation.

Creation and deletion require the explicit Gate B authorization.

### 9.2 Tasks the user performs locally

The user:

- runs the private OAuth/MQTT credential preparation on the Mac;
- keeps all resulting values private;
- temporarily selects the published probe branch in Symcon Module Control;
- enters WSS URL and Bearer header in the temporary WebSocket Client;
- enters MQTT username, password, client ID and exact topics in the temporary
  MQTT Client;
- enters the private device/topic allowlist in the temporary probe;
- applies the forms when Codex gives the phase instruction;
- confirms completion without returning secret values;
- restores the Module Control source to `main` after probe deletion.

This manual boundary prevents secrets from entering MCP transcripts or public
repository history.

### 9.3 Tasks Codex must not perform

Codex must not:

- ask the user to paste a token or password into chat;
- read raw private credential files into public output;
- send a mower command;
- invoke any MQTT publish API;
- configure a wildcard subscription;
- mutate the productive Navimow Account;
- delete an object not proven to belong to the current run.

## 10. Temporary Topology

The entire spike is isolated under one temporary category:

```text
SAEF Navimow MQTT Spike <RUN_ID>
  WebSocket Client <RUN_ID>
    MQTT Client <RUN_ID>
      Navimow MQTT Receive Probe <RUN_ID>
```

Requirements:

- `<RUN_ID>` is a random non-secret run identifier;
- the baseline must prove the identifier did not exist before creation;
- every created ObjectID is recorded only in private evidence;
- no productive Navimow instance is reparented;
- no existing WebSocket or MQTT instance is reused;
- no temporary object is placed below the productive Navimow Account;
- the category contains no other object.

## 11. Pre-Mutation Baseline

Before object creation, the private baseline must record:

- kernel version and runlevel;
- installed Navimow library source URL, branch and revision where available;
- module GUID and source hash inventory;
- count and parent relation of WebSocket and MQTT Client instances;
- absence of the proposed run identifier;
- productive Navimow instance identities and parent relations;
- productive variable ObjectIDs, Idents, types, profiles and actions;
- archive logging and aggregation settings for productive Navimow variables;
- current productive Navimow instance status;
- hash of relevant productive configuration with secrets redacted;
- baseline timestamp and explicit authorization scope.

Values, IDs, names and configuration details remain only in the private
machine-readable evidence.

The public report records only equality or difference counts.

## 12. Mutation Sequence

The sequence minimizes accidental connection and duplicate reconnects.

### Phase 1: Install the passive probe code

1. verify the probe branch commit against the published hash;
2. update Module Control to the probe branch;
3. verify every productive source hash is unchanged;
4. verify the probe module is available;
5. do not create an instance yet.

Failure requires immediate restoration to `main`.

### Phase 2: Create inactive topology

1. create the temporary category;
2. create the WebSocket Client with `Active = false`;
3. create the MQTT Client and connect it to the WebSocket Client;
4. configure no wildcard subscription;
5. create the receive probe and connect it to the MQTT Client;
6. apply non-secret bounds;
7. verify all three instances are owned by `<RUN_ID>`;
8. verify no productive parent relation changed.

### Phase 3: Private manual configuration

The user enters:

- binary WebSocket transfer;
- TLS certificate verification enabled;
- private WSS URL;
- private Bearer authorization header;
- MQTT credentials;
- unique client ID;
- keepalive;
- four exact topics;
- matching probe allowlist.

The WebSocket Client remains inactive until all exact settings pass a
shape-only read-back.

### Phase 4: Bounded activation

1. record pre-activation statuses;
2. activate the WebSocket Client exactly once;
3. do not reapply settings while connection is healthy;
4. wait up to 180 seconds;
5. poll only statuses and sanitized probe counters;
6. stop after the first accepted state or location message plus one additional
   receive cycle, or at the duration limit;
7. perform no automatic retry after an ambiguous failure.

The first run is transport evidence, not a reconnect test.

### Phase 5: Close and clean

1. set `Active = false`;
2. verify receive counters stop changing;
3. read the final sanitized aggregate report;
4. delete the probe instance;
5. delete the MQTT Client;
6. delete the WebSocket Client;
7. delete the empty temporary category;
8. verify all recorded temporary ObjectIDs are absent;
9. restore Module Control to `main`;
10. verify the probe module is no longer installed;
11. rerun the baseline comparison.

Cleanup is mandatory after PASS, FAIL, timeout or operator abort.

## 13. Time and Volume Bounds

| Bound | Initial value |
| --- | ---: |
| activation attempts | 1 |
| automatic reconnect attempts initiated by test logic | 0 |
| observation duration | 180 seconds |
| accepted messages retained as aggregates | 32 |
| total receive calls counted | 128 |
| unique envelope shapes | 8 |
| unique payload shapes per channel | 8 |
| envelope size | 64 KiB |
| decoded payload size | 32 KiB |
| public raw payload samples | 0 |
| MQTT publish attempts | 0 |
| REST command attempts | 0 |

Native-client internal protocol keepalive and reconnection are not classified
as test-logic retries. Their observed behavior is recorded without extending
the run.

## 14. Success Criteria

The live spike passes only when:

- the probe branch and commit are verified;
- productive source hashes remain identical;
- the WebSocket Client connects with TLS verification enabled;
- the MQTT Client reaches a connected state;
- all configured subscriptions are exact and wildcard-free;
- the probe receives at least one expected state or location message;
- the envelope `DataID` matches the inspected interface;
- topic and payload candidates can be identified safely;
- the payload is valid JSON of a fixture-backed channel shape;
- zero unknown topics are observed;
- zero publish attempts are observed;
- zero mower commands are attempted;
- no productive variable or archive setting changes;
- every temporary object is deleted;
- Module Control is restored to `main`;
- post-cleanup topology equals the baseline.

Connection alone is insufficient. Message reception and cleanup are both
mandatory.

## 15. Failure and Stop Conditions

Stop activation and begin cleanup immediately on:

- certificate verification disabled or unavailable;
- non-`wss://` endpoint;
- unexpected endpoint port;
- missing or malformed Bearer header;
- MQTT authentication rejection;
- wildcard or duplicate subscription;
- unexpected topic;
- envelope or payload size violation;
- unsupported envelope `DataID`;
- raw private data appearing in a report or log;
- any publish indication;
- any mower command indication;
- any productive source-hash difference;
- any productive object or archive mutation;
- inability to prove temporary-object ownership;
- MCP transport error, execution error or truncation during a mutation;
- operator abort.

The spike must not retry with altered credentials or relaxed TLS settings.

## 16. Rollback

Rollback is deterministic:

1. deactivate only the recorded temporary WebSocket instance;
2. delete recorded temporary objects from child to parent;
3. delete the temporary category only if empty;
4. restore the Navimow Module Control source to its exact baseline branch and
   revision;
5. update the library;
6. verify productive source hashes;
7. verify productive object, variable and archive invariants;
8. preserve private failure evidence;
9. delete or expire the local credential input.

No productive variable may be deleted and recreated as recovery.

If an object cannot be proven to belong to the run, cleanup stops and the
uncertain object is reported rather than deleted.

## 17. Private Evidence Package

Private root:

```text
private/navimow-capture/output/symcon-mqtt-spike/<RUN_ID>/
```

Required files:

```text
authorization.json
baseline.json
probe-publication.json
mutation.json
observation.json
cleanup.json
closure.json
```

All files require mode `600`.

`closure.json` must record:

- format version;
- run identifier;
- authorization kind and bounded scope;
- tested branch and commit;
- productive source-manifest equality;
- activation count;
- status transition classes;
- accepted and rejected message counts;
- envelope and payload shapes;
- zero publish and command counts;
- MCP transport, execution and truncation results per phase;
- temporary object creation and deletion counts;
- post-cleanup baseline equality;
- credential-input deletion result;
- final PASS, FAIL or ABORTED outcome.

Exact ObjectIDs and private installation details remain private.

## 18. Sanitized Public Report

After the live run, create:

```text
94-native-mqtt-wss-symcon-live-spike-report.md
```

The report may include:

- Symcon major/minor version;
- tested module branch and commit;
- native topology;
- bounded duration;
- status classes without private instance details;
- envelope key/type shape;
- channel and payload key/type shape;
- aggregate message counts;
- zero publish and command counts;
- cleanup equality result;
- architecture gate decision.

It must not include:

- ObjectIDs;
- object names containing private installation data;
- endpoints or complete topics;
- credentials or headers;
- device identifiers;
- raw payload values;
- coordinates;
- private timestamps that identify household activity.

If the envelope shape becomes a current parser contract, a synthetic fixture
and offline regression test must be added before the report closes.

## 19. Post-Spike Decisions

### PASS

A PASS allows:

- exact-envelope fixture promotion;
- receive-only Account interface design;
- offline shadow-transport implementation;
- later isolated restart and stale-state tests.

It does not allow productive MQTT variable updates.

### FAIL: native transport incompatibility

A reproducible native WSS or MQTT failure returns the architecture to step 91.
Custom MQTT framing is considered only after the failure is classified and
documented.

### FAIL: probe defect

A probe defect does not reject the native topology. Fix and revalidate the
probe offline before requesting another live authorization.

### FAIL: cleanup or ownership

Any cleanup or ownership failure blocks every further live MQTT test until the
installation returns to a verified baseline.

## 20. Architecture Decisions

### AD-NAV-339: Require a custom receive-only probe

**Decision:** Do not use a native MQTT Client Device as the sole proof.

**Rationale:** The future Account implementation needs the actual custom-child
`ReceiveData()` envelope.

**Consequence:** A test-only child module is implemented and validated before
the live run.

### AD-NAV-340: Publish the probe only on a temporary branch

**Decision:** Keep the probe out of the productive `main` branch and all tags.

**Rationale:** It is an engineering instrument, not user-facing module
functionality.

**Consequence:** Productive files must be byte-identical and the library must
be restored to `main` after cleanup.

### AD-NAV-341: Keep secrets outside MCP and chat

**Decision:** The user enters private transport configuration directly in
Symcon from a local mode-`600` credential package.

**Rationale:** This prevents credentials from entering tool transcripts or
public artifacts.

**Consequence:** MCP owns bounded topology operations and sanitized evidence,
not secret transfer.

### AD-NAV-342: Use one activation and no test-logic reconnect

**Decision:** Activate the native chain once for at most 180 seconds.

**Rationale:** The first gate proves transport, not recovery.

**Consequence:** Ambiguous results close as FAIL or ABORTED without automatic
credential changes or retries.

### AD-NAV-343: Make cleanup part of acceptance

**Decision:** Message reception without verified baseline restoration is not a
PASS.

**Rationale:** Temporary core instances and probe code must not become
installation residue.

**Consequence:** Cleanup runs for every outcome and is independently read back.

### AD-NAV-344: Separate transport proof from state authority

**Decision:** The probe cannot call productive parsers or variables.

**Rationale:** Transport and lifecycle evidence must precede authority changes.

**Consequence:** REST remains authoritative after a successful spike.

### AD-NAV-345: Preserve normal mower operation

**Decision:** Require no physical mower transition during the spike.

**Rationale:** Docked and active captures already prove passive downlink
traffic, and transport testing does not justify actuation.

**Consequence:** The official schedule and app remain untouched.

## 21. Readiness Decision

| Gate | Decision |
| --- | --- |
| topology selected | PASS |
| live mutation plan | PASS |
| rollback plan | PASS |
| private evidence schema | PASS |
| probe implementation | PENDING |
| probe offline tests | PENDING |
| probe branch publication | PENDING |
| explicit Symcon mutation authorization | PENDING |
| native live transport | NOT RUN |
| productive shadow implementation | NO-GO |
| productive MQTT variable updates | NO-GO |

## 22. Decision

**Live-spike planning: PASS.**

**Live Symcon mutation in this step: NONE.**

**Probe implementation: AUTHORIZATION REQUIRED.**

**Broker connection: NOT ATTEMPTED.**

**MQTT publish and mower commands: PROHIBITED.**

**Productive source and runtime: UNCHANGED.**

## 23. Recommended Next Step

Create:

```text
93-native-mqtt-wss-symcon-spike-harness-implementation.md
```

That step should implement and test:

- the receive-only probe module;
- its synthetic envelope tests;
- the productive-source identity manifest;
- the private credential-package helper;
- the private evidence and cleanup harness;
- the temporary standalone-module branch package.

It may publish the reviewed probe branch only after Gate A authorization. It
must not update Symcon or connect to Navimow until Gate B is granted separately.
