# 105 Native MQTT Lifecycle Publication and Symcon Test Plan

**Case study:** Navimow native IP-Symcon module
**Status:** Publication and supervised live test planned; no publication,
Symcon update or live mutation performed
**Date:** 2026-07-28
**Scope:** Gate publication and the first productive native MQTT lifecycle test
from steps 98 through 104

## 1. Purpose

This step defines how the offline-validated native MQTT shadow increment may be
published to the standalone module and tested on the authorized private
IP-Symcon installation.

Ordered phases:

1. freeze and validate the canonical candidate;
2. capture a private pre-update compatibility baseline;
3. publish the exact distribution to `symcon-navimow/main`;
4. update the module manually;
5. prove instance, variable and archive compatibility;
6. inspect or prepare one dedicated inactive native transport chain;
7. validate and explicitly adopt that chain;
8. start exactly one MQTT connection attempt;
9. observe receive-only MQTT and REST-authority evidence;
10. explicitly disconnect and prove credential cleanup;
11. separately test restart preservation;
12. close private and sanitized evidence.

This planning step performs none of those mutations.

## 2. Fixed Safety Boundary

The complete test remains subject to these invariants:

- REST is the only source of public Device state;
- MQTT is receive-only;
- no mower command is sent through REST or MQTT;
- no MQTT publish path exists;
- the mower may remain docked or follow its normal schedule;
- existing Account, Configurator and Device instances are retained;
- existing variables and their ObjectIDs are retained;
- Archive Control logging and aggregation remain unchanged;
- no automatic core instance creation or deletion occurs;
- no automatic connection retry or recovery occurs;
- no credential appears in an MCP result, public report, variable, fixture,
  module debug output or Git;
- historical pilot tags remain unchanged;
- no new tag is created;
- `MC_ReloadModule()` is not used.

An ambiguous connection or receive result never permits a second connection
attempt in the same live session.

## 3. Separate Authorization Gates

Planning approval does not authorize publication or live mutation.

### Gate A: Publication

Required authorization after the candidate report:

```text
Veröffentlichung des nativen MQTT-Lifecycle-Inkrements freigegeben.
```

This permits one reviewed commit and fast-forward push to
`symcon-navimow/main`. It does not authorize a Symcon update.

### Gate B: Module update and read-only compatibility test

Required authorization after remote verification:

```text
Symcon-Update und read-only Kompatibilitätsprüfung freigegeben.
```

The user performs the Module Control update manually. The subsequent probe
must not change the transport chain or send a device action.

### Gate C: Dedicated inactive topology preparation

Required only if the exact dedicated chain does not already exist:

```text
Vorbereitung der dedizierten inaktiven MQTT-Kette freigegeben.
```

This permits creation or manual configuration of only:

```text
native WebSocket Client
  -> native MQTT Client
    -> Navimow MQTT Receiver
```

It does not authorize credentials, activation, adoption or connection.

### Gate D: Adoption

Required after candidate validation passes:

```text
Explizite Übernahme der geprüften inaktiven MQTT-Kette freigegeben.
```

Adoption changes only private Account attributes. It performs no core
configuration mutation and no network connection.

### Gate E: One connection attempt

Required after adoption readback passes:

```text
Genau ein beaufsichtigter MQTT-Verbindungsversuch ist freigegeben.
```

There is no retry after failure, timeout or ambiguity.

### Gate F: Symcon restart

Required separately after the first connect/receive gate succeeds:

```text
Beaufsichtigter Symcon-Neustart zur MQTT-Persistenzprüfung freigegeben.
```

The restart gate does not authorize another explicit Connect action.

## 4. Current Publication Baseline

Standalone repository:

```text
https://github.com/doctee/symcon-navimow.git
```

Locally verified `main` baseline:

```text
2c32b868dda3ca5683b86715c44ea4f3291472ab
feat: harden adaptive polling state bounds
```

At planning time the established publish clone is clean and aligned with its
locally known `origin/main`. A fresh fetch and fast-forward check are mandatory
before publication; this document does not claim the remote cannot change
after planning.

## 5. Expected Publication Delta

Canonical source:

```text
case-studies/navimow/distribution/
```

The expected delta against the baseline contains exactly 17 productive files:

```text
NavimowAccount/form.json
NavimowAccount/locale.json
NavimowAccount/module.php
NavimowDevice/module.php
NavimowMqttReceiver/form.json
NavimowMqttReceiver/locale.json
NavimowMqttReceiver/module.json
NavimowMqttReceiver/module.php
libs/Navimow/ApiClient.php
libs/Navimow/MqttCredentialMapper.php
libs/Navimow/MqttEnvelopeException.php
libs/Navimow/MqttEnvelopeParser.php
libs/Navimow/MqttPartialStateAccumulator.php
libs/Navimow/MqttPayloadException.php
libs/Navimow/MqttPayloadParser.php
libs/Navimow/MqttTransportConfiguration.php
libs/Navimow/PayloadMapper.php
```

`.DS_Store`, SAEF reports, tests, fixtures, tools, private evidence and capture
artifacts are excluded.

Any additional productive difference stops publication for reclassification.

## 6. Pre-Publication Gate

Run against the exact candidate:

```text
case-studies/navimow/tools/check-mqtt-shadow.sh
make check
git diff --check -- case-studies/navimow
```

Additionally require:

- PHP syntax for every productive PHP file;
- JSON parsing for library, module, form and locale metadata;
- strict distribution validator;
- official Symcon Module Validator for the library and all four modules;
- PHPCS and PHPStan;
- all REST, command and pilot regressions;
- all MQTT fixtures, envelope, parser, Receiver, Account, reconciliation and
  lifecycle tests;
- source scan proving no MQTT publish, automatic instance creation/deletion,
  command retry or `MC_ReloadModule()`;
- privacy scan for credentials and private installation data;
- exact 17-file publication manifest.

The official web validator result and any browser defect are recorded
separately. A browser failure is not silently treated as a schema failure.

## 7. Private Pre-Update Baseline

Before publication or update, capture:

```text
private/navimow-capture/output/
  native-mqtt-lifecycle/pre-update-baseline.json
```

The bounded record may contain private ObjectIDs only below `private/`.

Capture:

- installed library branch and current commit where available;
- exactly one Account, Configurator and Device instance;
- instance ObjectIDs, module GUIDs, parent relationships and status;
- complete configured-property hashes without property values;
- all current public variable ObjectIDs and metadata;
- archive logging and aggregation;
- current Account authentication state;
- MQTT feature property and Receiver selection;
- existence and status of any candidate native chain;
- credential-presence booleans only;
- exact-subscription equality boolean and topic count only.

Never capture:

- access or refresh tokens;
- client secret;
- WSS URL or host;
- Authorization header value;
- MQTT username or password;
- device ID or complete topic;
- raw MQTT or REST payload.

## 8. Variable and Archive Invariants

At minimum retain the established Device variables:

```text
VehicleState
Online
BatteryLevel
LastStatusUpdate
LastCommand
LastCommandAt
LastCommandResult
LastCommandError
```

The current full baseline contains 14 variables and remains the comparison
authority.

For every variable compare:

- ObjectID;
- Ident;
- variable type;
- parent;
- standard and custom profile;
- effective profile;
- action;
- visibility and position where available;
- archive logging;
- aggregation type;
- bounded history queryability for logged variables.

The five previously verified logged variables must remain logged:

```text
BatteryLevel
LastCommand
LastCommandResult
Online
VehicleState
```

The test must not delete, recreate, reparent or reprofile a variable.

## 9. Publication Procedure

After Gate A:

1. fetch the standalone repository;
2. require a clean `main` and fast-forward alignment;
3. record the actual baseline commit and immutable tag references;
4. compare the canonical distribution with standalone `main`;
5. require exactly the classified 17-file delta;
6. copy only the canonical productive distribution;
7. prove complete byte equality, excluding `.git` and ignored metadata;
8. rerun syntax, JSON, Module Validator, privacy and whitespace gates;
9. inspect the complete standalone diff;
10. create one Conventional Commit;
11. push `main` by fast-forward only;
12. independently verify the remote commit and all changed blobs;
13. verify historical tags are unchanged;
14. stop before the Symcon update.

Suggested commit:

```text
feat: add native MQTT shadow lifecycle
```

No tag is created.

## 10. Module Update Gate

After Gate B, the user updates the module from `main` through Module Control.

Before the update:

- `EnableMqttShadow` is confirmed `false` where the property already exists;
- no live transition or command verification is active;
- the mower is supervised or remains under normal official-app control;
- the private compatibility baseline exists and parses;
- the published commit is remotely verified.

Immediately after the update, before enabling MQTT:

- Account, Configurator and Device instance identities are unchanged;
- all existing variables and archive settings match the baseline;
- all three productive instances have healthy status;
- OAuth remains usable;
- one read-only discovery and status poll succeeds;
- command variables do not change;
- the MQTT Receiver module is available;
- no Receiver, MQTT Client or WebSocket Client was created automatically;
- no credential endpoint call or broker connection occurred.

Any mismatch blocks topology work.

## 11. Dedicated Chain Preconditions

The adoption candidate must be installation-dedicated and must have this exact
order:

```text
WebSocket Client (inactive)
  -> MQTT Client
    -> Navimow MQTT Receiver
      -> paired Navimow Account
```

Required configuration:

- WebSocket `Active = false`;
- WebSocket header list empty;
- MQTT username and password empty;
- MQTT client connected only to the dedicated WebSocket;
- Receiver connected only to the dedicated MQTT Client;
- Receiver paired to the tested Account;
- four exact QoS-0 topics for each currently discovered mower;
- no wildcard;
- no unrelated consumer or ownership declaration.

No WSS URL, header, username, password or client ID is entered manually.

If the chain is absent or differs, stop at Gate C. Preparation is performed
through the normal Symcon UI or a separately authorized bounded MCP mutation.
The Account does not create or repair the chain.

## 12. Read-Only Candidate Validation

With the chain inactive:

1. select the Receiver in the Account;
2. enable experimental MQTT shadow and save;
3. confirm no core client activates;
4. execute `Validate MQTT Adoption Candidate`;
5. require `candidate-ready`;
6. read back topology, module GUIDs and credential-presence booleans;
7. require zero changes to core configuration hashes.

Candidate validation failure permits no adoption. Diagnose privately, correct
only the explicitly identified configuration and rerun the read-only gate.

## 13. Explicit Adoption Gate

After Gate D:

1. capture Account attribute and core-configuration hashes;
2. execute `Adopt MQTT Shadow Chain` once;
3. require `MQTT chain adopted`;
4. read back only bounded ownership metadata;
5. require ownership format version 2;
6. require a valid local-identity hash and adoption timestamp;
7. prove the ownership JSON contains no device ID, topic, endpoint, client ID
   or credential;
8. prove both core configuration hashes are unchanged;
9. execute the adoption action a second time;
10. require `already adopted` and identical ownership evidence.

The second invocation is an idempotency check, not a second mutation.

## 14. One Connect Attempt

After Gate E:

1. confirm valid ownership and usable OAuth;
2. record native MQTT and WebSocket status plus configuration hashes;
3. begin a bounded private observation;
4. execute `Connect MQTT Shadow` exactly once;
5. require one credential-endpoint request;
6. require one WebSocket activation;
7. inspect only status and credential-presence booleans;
8. wait up to 180 seconds for one accepted allowlisted message;
9. do not stimulate the mower solely to create MQTT evidence;
10. do not press Connect again.

The mower may be docked or following its ordinary schedule. No official-app
action is required.

Acceptance:

- public result is `connection attempt started`;
- native clients reach a healthy or connecting status without configuration
  error;
- lifecycle reaches `ShadowActive` after accepted receive evidence;
- rejected, oversized and invalid-input counters do not unexpectedly rise;
- publish-attempt and command-attempt paths remain absent.

If no message arrives within 180 seconds, classify the receive gate as
`inconclusive`, disconnect and schedule a later natural-observation session.
Do not reconnect in the same session.

## 15. REST-Authority Evidence

During the observation:

- MQTT may update only private shadow, lifecycle and bounded diagnostics;
- no MQTT handler writes a public Device variable directly;
- any public Device update must pass through the existing REST status path;
- targeted reconciliation remains bounded and coalesced;
- existing command variables remain unchanged;
- archive configuration remains unchanged.

Record only:

```text
accepted-message delta
rejected-message delta
reconciliation-attempt delta
REST-success delta
public-variable metadata equality
archive equality
```

Do not publish values, timeseries, device state, coordinates or topics.

## 16. Disconnect and Cleanup

Whether the receive gate passes, fails or is inconclusive, execute the explicit
disconnect once while ownership remains valid.

Require:

- WebSocket inactive;
- WebSocket header list empty;
- MQTT username empty;
- MQTT password empty;
- client identity retained;
- client ID and exact subscriptions retained;
- Receiver, MQTT Client and WebSocket Client retained;
- ownership format and adoption timestamp retained;
- ephemeral shadow and reconciliation state cleared;
- no public variable or archive metadata change.

After readback, disable experimental MQTT shadow and save. This must remain
idempotent and must not delete the chain.

## 17. Failure and Rollback Matrix

| Failure point | Required response |
|---|---|
| Unexpected publication delta | Stop before commit. |
| Remote branch advanced | Fetch, reclassify and repeat all gates. |
| Post-update identity/archive mismatch | Stop; do not enable MQTT; restore only the published module version if required. |
| Candidate invalid | No adoption or core mutation. |
| Adoption ambiguity | Read back ownership and core hashes; do not connect. |
| Credential request failure | Require automatic inactive cleanup; no retry. |
| Inactive-shape readback failure | Require rollback before activation; no retry. |
| Activation ambiguity | Execute ownership-checked Disconnect once; do not Connect again. |
| No message in 180 seconds | Classify inconclusive, Disconnect and close the session. |
| Ownership drift | Perform no mutation; diagnose privately. |
| Public variable/archive drift | Disconnect, disable MQTT and stop the pilot. |
| Credential cleanup cannot be proven | Keep the WebSocket inactive through the UI and stop. |

Rollback never deletes or recreates a productive variable or instance.

## 18. Restart Preservation Gate

Gate F is a separate session after the first connect/receive test passes.

While the owned transport is connected:

1. capture bounded lifecycle, ownership and core-status baselines;
2. restart the Symcon service once;
3. execute no explicit Connect action after restart;
4. require unchanged Account, Device and chain ObjectIDs;
5. require unchanged ownership and local client identity;
6. require zero increment of explicit connection-attempt statistics;
7. observe native reconnect behavior for up to 180 seconds;
8. require accepted receive evidence or classify natural receive as
   inconclusive;
9. execute explicit Disconnect and the cleanup gate.

This gate tests persistence, not automatic Account recovery. Any native
core reconnect results from the persisted native `Active` configuration.

## 19. MCP Boundary

With the configured Symcon MCP, Codex may perform after explicit authorization:

- bounded read-only instance, configuration-shape, variable and archive probes;
- direct bounded public-method execution for candidate validation;
- private machine-readable evidence capture;
- separately authorized adoption, connect and disconnect calls;
- post-update and post-restart readback.

MCP results must evaluate `transportError`, `executionError` and `truncated`
separately.

The user retains:

- Module Control update;
- normal UI topology creation or connection correction where required;
- Symcon service restart;
- physical supervision and official-app control.

MCP output must expose only aggregate booleans, counts and hashes. Credential
values and private topics are never returned.

## 20. Evidence Closure

Private machine-readable evidence:

```text
private/navimow-capture/output/
  native-mqtt-lifecycle/
    pre-update-baseline.json
    post-update-compatibility.json
    candidate-validation.json
    adoption.json
    connect-observation.json
    disconnect-cleanup.json
    restart-observation.json
    evidence-closure.json
```

Each record contains:

- format version and UTC time;
- phase and authorization kind;
- tested standalone commit;
- MCP transport, execution and truncation results;
- mutation and connection-attempt counts;
- redacted configuration and ownership hashes;
- invariant booleans and bounded counter deltas;
- rollback and cleanup result.

Public follow-up reports contain no private identifiers or hashes.

## 21. Acceptance Decision

The native MQTT lifecycle may enter a disabled-by-default private pilot only
when:

- publication and remote byte verification pass;
- post-update identity and archive compatibility pass;
- candidate validation and repeated adoption pass;
- exactly one connection attempt is observed;
- at least one allowed message reaches `ShadowActive`;
- REST remains the only public state authority;
- explicit disconnect clears credentials;
- disabling preserves every instance and variable;
- restart preservation passes in its separate gate;
- private and sanitized evidence are both closed.

Automatic instance creation, automatic credential refresh/reconnect, Store
preparation and broad release remain blocked.

## 22. Next Step

After explicit Gate A authorization, execute:

```text
106-native-mqtt-lifecycle-publication.md
```

That step publishes and remotely verifies the exact 17-file candidate and
stops before any Symcon update or live transport mutation.
