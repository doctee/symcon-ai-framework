# 93 Native MQTT WSS Symcon Spike Harness Implementation

**Case study:** Navimow native IP-Symcon module
**Status:** Receive-only probe branch published; live Symcon Gate B pending
**Date:** 2026-07-27
**Scope:** Implement the disposable probe, offline gates, private preparation and publication evidence

## 1. Purpose

This step implements Gate A from
`92-native-mqtt-wss-symcon-live-spike-plan.md`.

It provides:

- a test-only MQTT receive child for IP-Symcon;
- a bounded envelope and payload-shape reducer;
- synthetic offline regression tests;
- static no-publish and no-command checks;
- a dedicated PHPStan and PHPCS gate;
- official Symcon JSON-schema validation;
- a private local credential-input helper;
- a private evidence-package helper;
- deterministic standalone-main and probe manifests;
- a temporary published probe branch.

This step does not:

- change the installed Symcon library;
- create a Symcon object;
- connect to the Navimow broker;
- retrieve a live credential;
- receive a live MQTT message;
- publish an MQTT message;
- send a mower command;
- change the productive Navimow distribution.

## 2. Authorization

The user explicitly granted Gate A:

```text
Freigabe erteilt.
```

This authorized implementation and publication of the receive-only probe
branch. It did not authorize Gate B, the live Symcon mutation and broker
connection.

No Gate B operation was performed.

## 3. Implemented Probe Package

The reviewed source package is:

```text
case-studies/navimow/tools/symcon-mqtt-spike-library/
  standalone-main-files.sha256
  probe-files.sha256
  NavimowMqttReceiveProbe/
    MqttReceiveProbeReducer.php
    module.php
    module.json
    form.json
    locale.json
```

The module GUID is:

```text
{35003FD6-161B-4211-8B43-718876ABA4F6}
```

The data-flow contract is:

```text
parentRequirements:
  {043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}

implemented:
  {7F7632D9-FA40-4F38-8DEA-C83CD4325A32}
```

The probe can therefore become a child of the native MQTT Client and receive
its downlink data interface.

It cannot become a parent of a productive Navimow module.

## 4. Receive-Only Module Boundary

`NavimowMqttReceiveProbe` contains:

- one masked expected-device property;
- one private aggregate-evidence attribute;
- one bounded closure timer;
- `ArmEvidence()`;
- `CloseEvidence()`;
- `GetEvidenceReport()`;
- `ReceiveData()`.

It creates no:

- variable;
- action;
- child object;
- file;
- Registry entry;
- productive diagnostic entry.

Static source checks reject:

```text
SendDataToParent
SendDataToChildren
MQTT_Publish
sendCommands
RegisterVariable
RequestAction
```

There is no REST client, uplink topic or MQTT command path.

## 5. Evidence Bounds

The reducer enforces:

| Bound | Implemented value |
| --- | ---: |
| evidence duration | 180 seconds |
| envelope bytes | 65,536 |
| decoded payload bytes | 32,768 |
| receive calls | 128 |
| accepted messages | 32 |
| unique envelope shapes | 8 |
| unique payload shapes | 8 |
| JSON depth | 32 |
| fields per object | 64 |
| publish attempts | 0 |
| command attempts | 0 |

After the first reached limit, evidence closes and later messages do not mutate
the retained aggregate.

## 6. Envelope Discovery

The reducer requires only the already proven Symcon receive `DataID`.

For the first live envelope, it:

1. decodes the bounded top-level JSON object;
2. verifies `DataID`;
3. finds exactly one string equal to one of the four generated exact topics;
4. finds exactly one other JSON object or array payload candidate;
5. verifies state-payload device identity when the channel is `state`;
6. records only field names and value types;
7. discards all topic and payload values.

It does not assume that the native envelope keys are named `Topic` and
`Payload`.

The synthetic tests use those likely names only as one candidate shape. The
live test remains the authority for the actual Symcon envelope.

## 7. Sanitized Report Contract

The retained report contains:

- format and probe version;
- local start and close times;
- accepting and limit state;
- receive, accepted, rejected and oversized counts;
- unknown-topic count;
- per-channel counts;
- bounded envelope key/type shapes;
- bounded payload key/type shapes;
- minimum and maximum payload byte sizes;
- fixed zero publish and command counters;
- a vocabulary-bound last-result code.

It retains no:

- expected device ID;
- complete or hashed topic;
- payload value;
- state string;
- battery value;
- vendor timestamp;
- coordinate;
- token, credential, host or WSS URL.

Offline leakage tests search the serialized report for representative device,
topic, state, timestamp and coordinate values.

## 8. Offline Regression Coverage

The executable test is:

```text
case-studies/navimow/tests/mqtt-symcon-probe.php
```

It verifies:

- direct state-envelope acceptance;
- location-array acceptance;
- envelope key/type reduction;
- state object field/type reduction;
- location array item and field/type reduction;
- removal of topic, identity and payload values;
- malformed envelope rejection;
- unknown-topic rejection;
- state device-identity mismatch rejection;
- unexpected `DataID` rejection;
- oversized-envelope rejection;
- 32-message closure;
- post-closure immutability;
- manual closure;
- module GUID and interface metadata;
- absence of send, command, variable and action source paths.

The focused runner is:

```text
case-studies/navimow/tools/check-mqtt-symcon-probe.sh
```

It executes:

- the probe regression test;
- PHP syntax checks;
- PHPCS;
- isolated level-5 PHPStan analysis.

The isolated PHPStan configuration prevents unrelated executable fake-runtime
families from entering the same analysis process.

Result:

```text
Navimow Symcon MQTT receive probe checks passed.
PHP syntax: PASS
PHPCS: PASS
PHPStan: PASS
Navimow MQTT Symcon probe gate passed.
```

## 9. Official Symcon Module Validator

The official
[Symcon Module Validator](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/tools/module-validator/)
was included.

In the in-app browser, its cookie dialog remained visible and intercepted both
validator and cookie-choice interactions. The validator's external AJV and
schema resources were also unavailable to that browser session.

The exact resources referenced by the official validator were therefore
retrieved directly:

```text
moduleSchema.json
formSchema.json
localeSchema.json
AJV 6.10.2
```

The three new JSON files were validated locally with those unchanged official
schemas and the same AJV version:

```text
module.json: PASS
form.json: PASS
locale.json: PASS
```

This confirms that the earlier cookie-popup suspicion was operationally
correct. The popup did not indicate a module schema defect.

No private data was transmitted to the validator. The probe files contain
public source and metadata only.

## 10. `MC_ReloadModule()` Decision

`MC_ReloadModule()` is not used.

The function reloads PHP module code and reinitializes module instances. In the
current installation this would be broader than required and could invoke
`ApplyChanges()` on productive Navimow instances.

The live plan instead uses the documented Module Control branch workflow:

1. switch the standalone Navimow repository to the temporary probe branch;
2. update through Module Control;
3. create only the isolated probe topology;
4. delete the topology;
5. switch Module Control back to `main`;
6. update and verify the baseline.

This keeps module-source activation visible, bounded and reversible.

## 11. Private Input Helper

The ignored private helper is:

```text
private/navimow-capture/prepare_symcon_mqtt_spike_input.py
```

It reads fresh private:

- OAuth token response;
- discovery response;
- MQTT credential response.

It validates:

- exactly one selected known mower;
- safe device-identifier syntax;
- `wss://`;
- port 443;
- non-empty MQTT credentials;
- four exact topics;
- absence of `#` and `+`.

It writes one new mode-`600` file using exclusive creation:

```text
private/navimow-capture/output/symcon-mqtt-spike/input.local.json
```

The package contains:

- WSS URL;
- binary-mode flag;
- certificate-verification flag;
- Bearer header;
- MQTT username and password;
- random client ID;
- keepalive;
- four exact subscriptions;
- expected device ID.

It records a 15-minute deletion deadline.

The helper prints only:

- output path;
- permission mode;
- subscription count;
- deletion reminder.

It prints no credential or topic.

Static helper validation passed without a network request or real credential.

## 12. Private Evidence Helper

The ignored helper is:

```text
private/navimow-capture/symcon_mqtt_spike_evidence.py
```

It initializes:

```text
authorization.json
baseline.json
probe-publication.json
mutation.json
observation.json
cleanup.json
closure.json
```

Every file and its run directory receive private permissions.

The initial authorization status is explicitly:

```text
pending
```

The helper cannot claim Gate B authorization automatically.

For a PASS closure it requires:

```text
publishAttemptCount = 0
commandAttemptCount = 0
temporaryObjectCountAfterCleanup = 0
productiveSourceManifestEqual = true
productiveTopologyEqual = true
productiveVariablesEqual = true
archiveConfigurationEqual = true
moduleBranchRestored = true
credentialInputDeleted = true
```

Initialization and validation were tested in a temporary private directory.

## 13. Publication Manifest Gate

The publication tool is:

```text
case-studies/navimow/tools/prepare-mqtt-spike-publication.php
```

It provides:

```text
--write-manifests
--capture-main TARGET
--check
--stage TARGET
```

The gate:

1. records hashes for the reviewed five-file probe;
2. records the actual standalone `symcon-navimow/main` file manifest;
3. requires a target worktree to equal that main manifest;
4. refuses an existing probe directory;
5. copies only the reviewed probe files;
6. rechecks all main files after staging;
7. rechecks every staged probe hash.

Hidden macOS metadata and `.git/` are excluded.

## 14. Pre-Existing Distribution Drift

The manifest gate identified three files where the current SAEF distribution
differs from the published standalone `main`:

```text
NavimowAccount/module.php
NavimowDevice/module.php
libs/Navimow/PayloadMapper.php
```

Classification:

- `NavimowAccount/module.php` contains later adaptive-polling hardening;
- the other two differences are formatting-only;
- none was part of Gate A;
- none was copied to the probe branch.

The standalone publication authority remained:

```text
main commit:
397b4b0199b2caef963ebc542c84dbda9ca5ade8
```

This drift requires a separate later consolidation decision. It must not be
hidden inside a transport spike.

## 15. Published Probe Branch

Repository:

```text
https://github.com/doctee/symcon-navimow
```

Branch:

```text
spike/native-mqtt-wss-receive-probe
```

Commit:

```text
ce507287c94dc5f15637a849f93723a800e7f450
```

Commit subject:

```text
test: add receive-only mqtt websocket probe
```

The remote branch was verified through `git ls-remote`.

The complete branch difference against `main` is:

```text
NavimowMqttReceiveProbe/MqttReceiveProbeReducer.php
NavimowMqttReceiveProbe/form.json
NavimowMqttReceiveProbe/locale.json
NavimowMqttReceiveProbe/module.json
NavimowMqttReceiveProbe/module.php
```

No productive path changed.

No tag or pull request was created.

## 16. Gate Results

| Gate | Result |
| --- | --- |
| Gate A authorization | PASS |
| receive-only reducer | PASS |
| 180-second and message bounds | PASS |
| exact-topic enforcement | PASS |
| device identity check | PASS |
| sanitized shape report | PASS |
| no-publish source scan | PASS |
| no-command source scan | PASS |
| no-variable/action source scan | PASS |
| focused regression tests | PASS |
| PHP syntax | PASS |
| PHPCS | PASS |
| isolated PHPStan | PASS |
| official module schema | PASS |
| official form schema | PASS |
| official locale schema | PASS |
| private input helper | PASS |
| private evidence helper | PASS |
| standalone-main identity gate | PASS |
| temporary branch publication | PASS |
| remote commit verification | PASS |
| productive distribution mutation | NONE |
| Symcon update | NOT AUTHORIZED |
| broker connection | NOT AUTHORIZED |

## 17. Architecture Decisions

### AD-NAV-346: Keep the reducer value-free

**Decision:** Persist only bounded key/type shapes and counters.

**Rationale:** The spike needs the native envelope contract, not private mower
data.

**Consequence:** Live evidence can be reviewed without exporting payload
values.

### AD-NAV-347: Make the probe self-contained

**Decision:** Keep reducer and wrapper under the temporary probe module.

**Rationale:** Adding a probe library to productive `libs/` would violate the
byte-identical main contract.

**Consequence:** The probe branch adds one isolated directory only.

### AD-NAV-348: Do not use `MC_ReloadModule()`

**Decision:** Use Module Control branch update and restoration.

**Rationale:** Reloading all instances of a module is broader than the isolated
test requires.

**Consequence:** Productive instances are not deliberately reinitialized by
the spike harness.

### AD-NAV-349: Treat standalone main as publication authority

**Decision:** Build the temporary branch from remote standalone `main`, not
from the locally advanced SAEF distribution.

**Rationale:** Gate A must not publish unrelated pending differences.

**Consequence:** Three existing drift files remain outside the probe branch and
are documented separately.

### AD-NAV-350: Preserve explicit human secret entry

**Decision:** Generate a local private package but do not transmit it through
MCP or chat.

**Rationale:** Credential handling must remain outside persistent tool
transcripts.

**Consequence:** The user enters values directly into temporary Symcon forms.

### AD-NAV-351: Keep Gate B closed after publication

**Decision:** Publishing the probe does not authorize its installation or
execution.

**Rationale:** Live topology mutation requires its own reviewed consent.

**Consequence:** No Symcon object or broker session exists after this step.

## 18. Decision

**Gate A probe implementation: PASS.**

**Gate A probe publication: PASS.**

**Official Symcon metadata validation: PASS.**

**Productive source changes in standalone branch: NONE.**

**`MC_ReloadModule()` use: REJECTED FOR THIS SPIKE.**

**Symcon live Gate B: PENDING EXPLICIT AUTHORIZATION.**

**Broker connection: NOT ATTEMPTED.**

**MQTT publish and mower commands: NONE.**

## 19. Recommended Next Step

Create:

```text
94-native-mqtt-wss-symcon-live-spike-report.md
```

Before that report can be written, the user must explicitly authorize Gate B:

```text
Der native MQTT/WSS-Symcon-Live-Spike ist freigegeben.
```

After authorization:

1. initialize private evidence;
2. capture the private Symcon baseline;
3. switch Module Control to the published probe branch;
4. create the inactive temporary topology;
5. enter fresh private values directly in Symcon;
6. activate exactly once for at most 180 seconds;
7. collect only the sanitized probe report;
8. clean every temporary object;
9. restore Module Control to `main`;
10. prove full baseline equality;
11. close private and public evidence.
