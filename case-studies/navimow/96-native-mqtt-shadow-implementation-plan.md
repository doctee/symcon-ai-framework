# 96 Native MQTT Shadow Implementation Plan

**Case study:** Navimow native IP-Symcon module
**Status:** Implementation work packages approved; source implementation not started
**Date:** 2026-07-28
**Scope:** Convert the approved optional shadow architecture into staged, testable implementation packages

## 1. Purpose

This step turns
`95-native-mqtt-shadow-integration-design.md` into an implementation sequence.

The plan defines:

- the source authority and drift gate;
- exact files to add or modify;
- dependency order;
- fixture and parser promotion;
- receiver and Account boundaries;
- transport and credential lifecycle;
- REST reconciliation;
- diagnostics and privacy controls;
- local test runners;
- publication branches;
- staged Symcon gates;
- rollback and completion criteria.

This step does not create productive PHP code, change module metadata, publish
a branch or mutate Symcon.

## 2. Implementation Objective

The first implementation milestone is:

```text
optional native MQTT receiver
    -> bounded private shadow state
    -> coalesced targeted REST reconciliation
    -> unchanged existing public variables and archive configuration
```

The first milestone is not:

```text
MQTT directly updates public state
```

REST remains the only productive mower-state and command authority.

## 3. Preconditions

Implementation may begin only with these conditions:

- step 94 native transport evidence remains closed;
- step 95 architecture remains approved;
- standalone module `main` is restored;
- no temporary probe module or instance exists;
- productive Account, Configurator and Device instances are healthy;
- the user's current variable and archive baseline is retained privately;
- no new vendor response changes OAuth or MQTT assumptions;
- the distribution-to-main drift is handled separately.

## 4. Source Authority

The canonical design and implementation source is:

```text
case-studies/navimow/distribution/
```

The standalone publication target is:

```text
https://github.com/doctee/symcon-navimow
```

The two trees must be byte-identical for productive module paths before an MQTT
publication branch is created.

No implementation package may copy files from an unverified mixed state.

## 5. Mandatory Drift Gate

Three productive files currently differ:

```text
NavimowAccount/module.php
NavimowDevice/module.php
libs/Navimow/PayloadMapper.php
```

Classification:

- Account contains later adaptive-polling hardening;
- Device differs only in formatting;
- PayloadMapper differs only in formatting.

This drift predates the MQTT work.

It must not be hidden inside an MQTT commit.

## 6. Drift Closure Sequence

Before MQTT implementation:

1. regenerate standalone `main` and distribution manifests;
2. confirm the same three-file delta;
3. review the Account behavior separately;
4. run REST, command, adaptive-polling and distribution tests;
5. publish one dedicated consolidation commit;
6. update Symcon from `main`;
7. verify productive instance identity and status;
8. verify every existing variable and archive setting;
9. verify scheduled and active polling;
10. record a separate sanitized report;
11. prove byte equality between distribution and standalone `main`.

The next implementation branch starts only from that reconciled commit.

## 7. Drift Failure Rule

If the three-file delta changes before consolidation:

- stop;
- regenerate the classification;
- do not assume formatting-only status;
- do not stage MQTT files;
- create a new drift decision before publication.

## 8. Branch Strategy

Recommended branches:

```text
framework:
codex/navimow-mqtt-shadow

standalone publication:
feature/native-mqtt-shadow-pilot
```

The standalone feature branch:

- starts from reconciled `main`;
- contains no probe module;
- contains only reviewed productive shadow files;
- receives no pilot or release tag before live closure;
- is never merged through an unrelated publication commit.

## 9. Work-Package Overview

| Package | Purpose | Live effect |
| --- | --- | --- |
| `WP-0` | close distribution/main drift | separate tested update |
| `WP-1` | add synthetic native-envelope fixtures | none |
| `WP-2` | implement envelope parser | none |
| `WP-3` | promote payload parser and reducer | none |
| `WP-4` | add receiver module | disabled until paired |
| `WP-5` | add Account pairing and ingestion | default disabled |
| `WP-6` | add targeted REST reconciliation | default inactive |
| `WP-7` | add credential endpoint and mapper | no call while disabled |
| `WP-8` | add owned transport lifecycle | explicit opt-in |
| `WP-9` | add diagnostics and recovery | internal only |
| `WP-10` | close compatibility and static gates | none |
| `WP-11` | publish feature branch | no tag |
| `WP-12` | run staged Symcon pilot | authorized live mutation |

Each package must remain independently reviewable.

## 10. WP-0: Distribution/Main Consolidation

### Inputs

- current distribution;
- standalone `main`;
- three-file classification from step 93.

### Outputs

- byte-identical productive trees;
- dedicated commit and report;
- updated private compatibility baseline;
- no MQTT source.

### Required tests

```text
rest-client-auth.php
pilot-observation-harness.php
payload-mapper-fixtures.php
validate-distribution.php
PHP syntax
PHPCS
PHPStan
```

### Symcon acceptance

- same Account, Configurator and Device IDs;
- same variable IDs, types, profiles and actions;
- same logging and aggregation;
- healthy REST polling;
- no command;
- no `MC_ReloadModule()`.

## 11. WP-1: Synthetic Native-Envelope Fixtures

Add:

```text
fixtures/mqtt/symcon-envelope-location.json
fixtures/mqtt/symcon-envelope-state.json
fixtures/mqtt/symcon-envelope-retained.json
fixtures/mqtt/symcon-envelope-invalid-data-id.json
```

The fixtures are synthetic derivatives of:

- the proven outer key/type shape;
- existing sanitized state and location payload fixtures;
- public test identity `DEVICE_001`;
- exact public test topics.

They contain no captured topic, device ID, timestamp, coordinate or credential.

## 12. Envelope Fixture Contract

Every positive fixture contains:

```text
DataID
PacketType
Payload
QualityOfService
Retain
Topic
```

The `Payload` field is a JSON string, not an embedded object.

Fixture metadata must state:

- `synthetic: true`;
- source evidence document;
- intended channel;
- retained/non-retained classification;
- no claim about unobserved packet-type values.

## 13. WP-2: Envelope Parser

Add:

```text
distribution/libs/Navimow/MqttEnvelopeException.php
distribution/libs/Navimow/MqttEnvelopeParser.php
tests/mqtt-envelope.php
```

The parser performs:

1. 65,536-byte outer limit;
2. JSON object decode with depth 32;
3. exact receive `DataID` check;
4. exact required-key check;
5. field-type validation;
6. bounded topic and payload strings;
7. QoS 0 check;
8. retained classification;
9. bounded packet-type validation;
10. immutable normalized result.

It performs no topic allowlist or payload decode.

## 14. Envelope Parser Result

Candidate result:

```text
topic: string
payload: string
qualityOfService: integer
retained: boolean
packetType: integer
```

It does not return:

- raw outer JSON;
- unknown envelope fields;
- debug content;
- credentials.

Unknown top-level keys fail closed in the first implementation. Relaxation
requires a new fixture-backed compatibility decision.

## 15. WP-3: Payload Parser Promotion

Promote:

```text
candidate/MqttPayloadException.php
candidate/MqttPayloadParser.php
candidate/MqttPartialStateAccumulator.php
```

to:

```text
distribution/libs/Navimow/
```

The promoted code must not be copied mechanically. It requires the changes
defined in step 95.

## 16. Payload Promotion Changes

Required changes:

- reduce payload limit to 32,768 bytes;
- retain exact-topic validation;
- retain device identity validation;
- preserve partial location semantics;
- keep `event` and `attributes` unsupported;
- distinguish source timestamp from local receipt time;
- support bounded serialization of accumulator state;
- clear semantic state after restart;
- remove geometry from every returned persistent shape;
- add known direct-state normalization;
- retain unknown states only as reason codes, not values;
- retain timestamp-less classification without mutation.

## 17. Geometry Reduction Boundary

The parser may validate numeric geometry fields to recognize a known payload
shape.

Before returning a persistent shadow patch, it removes:

```text
postureX
postureY
postureTheta
```

Regression tests must search:

- parser output;
- serialized accumulator;
- diagnostics;
- debug output;
- error messages.

No coordinate test value may survive those boundaries.

## 18. State Normalization

Add one narrowly scoped state mapper or extend an existing internal mapper only
when duplication is avoided.

Minimum fixture-backed mappings:

```text
isRunning
isDocking
isDocked
```

Additional mappings may be included only when already supported by the REST
mapper and backed by current evidence.

Unknown strings:

- do not throw away the entire envelope;
- produce `unknown-state`;
- queue bounded REST reconciliation;
- never enter a public variable or persisted raw value.

## 19. Reducer Shape

The runtime reducer should operate on arrays rather than relying on one
long-lived PHP object.

Candidate input:

```text
previous bounded device shadow
parsed patch
local receipt timestamp
```

Candidate output:

```text
accepted
reason
bounded next state
changed semantic fields
reconciliation hint
diagnostic deltas
```

This keeps the state serializable and fake-time testable.

## 20. WP-4: Receiver Module Scaffold

Add:

```text
distribution/NavimowMqttReceiver/
  module.json
  module.php
  form.json
  locale.json
```

The module GUID is generated with an official Symcon tool during this package.

The distribution validator expected-module set is updated in the same package.

## 21. Receiver Properties

Minimum property:

```text
AccountInstanceId: integer, default 0
```

No Receiver variable, action, timer or persistent payload attribute is
registered.

The form contains:

- Account instance selection;
- pairing status text;
- no secret input;
- no publish or command control.

## 22. Receiver Runtime

`ReceiveData()`:

1. enforces outer byte limit;
2. invokes `MqttEnvelopeParser`;
3. rejects malformed or retained semantic input safely;
4. resolves the configured Account;
5. verifies Account module GUID;
6. calls the one bounded Account ingestion method;
7. stores no topic or payload;
8. emits only sanitized debug metadata.

The Receiver contains no:

```text
SendDataToParent()
SendDataToChildren()
MQTT_Publish()
RequestAction()
REST client
command constant
uplink topic
```

## 23. Receiver Metadata Tests

Tests verify:

- unique module GUID;
- exact parent and implemented interfaces;
- no child interface;
- no variable registration;
- no action method;
- no send path;
- form and locale schemas;
- expected module set;
- Account selector restricted to the Account module where schema permits.

## 24. WP-5: Account Pairing

Modify:

```text
distribution/NavimowAccount/module.php
distribution/NavimowAccount/form.json
distribution/NavimowAccount/locale.json
```

Do not change:

```text
distribution/NavimowAccount/module.json parentRequirements
```

## 25. Account Properties

Add:

```text
EnableMqttShadow: boolean, default false
MqttReceiverInstanceId: integer, default 0
```

The default update path must:

- create no core instance;
- connect no broker;
- retrieve no MQTT credential;
- change no existing timer cadence;
- change no variable;
- show no missing-parent error.

## 26. Pairing Validation

Add one side-effect-free validation method:

```text
ValidateMqttShadowConfiguration()
```

It returns a sanitized result and validates:

- feature enabled or disabled;
- Receiver exists;
- Receiver module GUID;
- Receiver points back to the Account;
- Receiver direct connection is native MQTT Client;
- MQTT direct connection is native WebSocket Client;
- exact connection order;
- chain declaration and ownership metadata;
- no wildcard subscription.

Validation does not repair drift.

## 27. Account Ingestion Method

Add one bounded public module method:

```text
IngestMqttEnvelope(int receiverInstanceId, string envelopeJson): string
```

It is called only by the paired Receiver.

It must:

- validate feature and symmetric pairing before parsing;
- enforce limits again at the Account trust boundary;
- return a bounded result code;
- acquire an MQTT shadow semaphore;
- update internal shadow state only;
- queue, but not execute, REST reconciliation;
- increment fixed receive diagnostics;
- never call command transport.

## 28. Double Validation

The Receiver and Account both enforce the outer byte limit and module pairing.

This is deliberate:

- Receiver protects its immediate callback boundary;
- Account protects its semantic trust boundary;
- a local direct call cannot bypass Account validation;
- tests can exercise Account ingestion without a live MQTT parent.

Payload decoding remains in the Account-owned semantic layer.

## 29. Account Internal Attributes

Add versioned bounded attributes:

```text
MqttOwnershipRegistry
MqttLifecycleRegistry
MqttStatistics
MqttErrorHistory
MqttShadowState
MqttPendingReconciliation
```

Defaults:

```text
{}
{}
{}
[]
{}
{}
```

No raw string from MQTT may be written to these attributes.

## 30. Shadow State Limit

`MqttShadowState`:

- tracks at most 64 devices;
- keys devices by a deterministic local hash where possible;
- stores only semantic candidates and timestamps;
- stores no device ID when it is not required for later REST targeting;
- stores no coordinate;
- removes oldest entries deterministically;
- is cleared in `ApplyChanges()` after restart or source update;
- is never exposed through a public variable.

Pending reconciliation may retain current discovered device IDs internally
because REST targeting requires them. It remains bounded, private and cleared
on restart.

## 31. WP-6: Targeted REST Reconciliation

Modify:

```text
distribution/NavimowAccount/module.php
distribution/NavimowDevice/module.php
```

Add Account timer:

```text
MqttReconcile
```

Candidate timer callback:

```text
ProcessMqttReconciliation()
```

## 32. Reconciliation Queue

The queue records:

```text
device ID
first queued at
last hint at
reason code
not-before timestamp
```

Limits:

- maximum 64 devices;
- no more than one entry per device;
- deterministic oldest-first processing;
- fixed maximum devices per timer run;
- minimum 30 seconds between MQTT-triggered REST wakes per device;
- no queue growth from repeated two-second location messages.

## 33. Targeted Poll Message

Account sends:

```text
DataID: existing Navimow child interface
SchemaVersion: existing version
Function: PollStatus
DeviceId: target device
Reason: mqtt-shadow-reconciliation
```

Device accepts:

- existing global `PollStatus` without `DeviceId`;
- targeted `PollStatus` only when its configured Device ID matches.

Other Device instances ignore the message without error.

## 34. REST Authority Test

Tests must prove:

1. MQTT message queues reconciliation;
2. MQTT ingestion alone leaves public values unchanged;
3. reconciliation invokes the existing REST `GetStatus`;
4. only the REST result reaches `applyStatusResult()`;
5. existing variable IDs and archive metadata remain unchanged.

## 35. REST/MQTT Comparison Hook

After a successful REST mapping, Account compares:

- normalized state candidate;
- battery candidate;
- local receipt timing.

The comparison updates only internal counters and bounded reason codes.

It does not change:

- REST result;
- Device result;
- polling schedule except existing adaptive logic;
- command verification.

## 36. WP-7: MQTT Credential Endpoint

Modify:

```text
distribution/libs/Navimow/ApiClient.php
distribution/libs/Navimow/PayloadMapper.php
```

Or add:

```text
distribution/libs/Navimow/MqttCredentialMapper.php
```

Prefer a separate mapper if adding MQTT response semantics to PayloadMapper
would make that class less cohesive.

## 37. API Client Method

Add:

```text
getMqttUserInfo(accessToken)
```

Contract:

```text
GET /openapi/mqtt/userInfo/get/v2
Authorization: Bearer <token>
Accept: application/json
requestId: generated UUID
```

The request has no body and no retry inside `ApiClient`.

## 38. Credential Mapper Result

Internal result:

```text
wssUrl
mqttUsername
mqttPassword
```

Validation:

- API success;
- all fields non-empty;
- WSS scheme only;
- port 443;
- bounded host, path and query;
- no fragment;
- no control characters;
- no credential in exception messages.

The client ID is generated locally and is not taken from the response.

## 39. Credential Tests

Add synthetic success and failure responses to:

```text
tests/rest-client-auth.php
```

Test:

- exact endpoint and method;
- Bearer header;
- request ID;
- successful WSS URL composition;
- plain WebSocket rejection;
- unsupported port rejection;
- missing fields;
- business error;
- HTTP error;
- malformed JSON;
- secret redaction from every exception.

## 40. WP-8: Owned Transport Lifecycle

Modify Account to manage a validated dedicated chain.

Add timer:

```text
MqttLifecycle
```

Candidate callbacks:

```text
ConnectMqttShadow()
DisconnectMqttShadow()
ProcessMqttLifecycle()
```

Only the first is an explicit form action. Timer processing is internal.

## 41. Ownership Registry

The registry stores:

```text
formatVersion
receiverInstanceId
mqttInstanceId
webSocketInstanceId
module GUIDs
connection order
account binding hash
subscription hash
redacted transport shape hash
adoptedAt
```

Use the existing `ConfigurationHash` normalization behavior on redacted
structures.

No raw core configuration is retained.

## 42. Ownership Adoption

The first implementation requires an explicit form action:

```text
Adopt Dedicated MQTT Shadow Chain
```

The action:

- requires `EnableMqttShadow=true`;
- validates symmetric pairing;
- requires both core instances inactive;
- displays the exact instance names to the user;
- requires form confirmation;
- records redacted ownership metadata;
- does not activate the WebSocket;
- does not retrieve credentials.

There is no silent adoption in `ApplyChanges()`.

## 43. Connection Action

After adoption, an explicit action:

```text
Connect MQTT Shadow
```

performs:

1. ownership revalidation;
2. usable OAuth check;
3. inactive-state enforcement;
4. fresh credential retrieval;
5. deterministic exact subscriptions;
6. MQTT configuration;
7. WSS configuration with TLS verification and binary mode;
8. one WebSocket activation;
9. lifecycle state transition to Connecting.

The first private pilot uses this explicit action. Automatic startup recovery is
enabled only after the restart gate passes.

## 44. Disable Behavior

When `EnableMqttShadow=false`:

- lifecycle and reconciliation timers stop;
- shadow and pending state clear;
- an owned WebSocket is deactivated;
- core instances remain;
- ownership registry remains for explicit later reuse;
- credentials may be cleared only after ownership revalidation;
- REST polling and command behavior remain unchanged.

Ambiguous ownership causes no core mutation.

## 45. ApplyChanges Behavior

`ApplyChanges()` must be idempotent.

While disabled:

- register attributes and timers;
- keep timers at zero;
- clear transient shadow state;
- do not inspect or mutate arbitrary instances.

While enabled:

- validate configuration;
- clear transient shadow state;
- restore bounded lifecycle metadata;
- inspect the paired chain;
- do not connect before explicit adoption exists;
- do not activate during an incomplete configuration;
- schedule recovery only after the corresponding live gate permits it.

## 46. Healthy OAuth Refresh

Existing token refresh remains unchanged for REST.

After successful refresh:

- store the new OAuth token;
- do not apply WSS headers;
- do not reconnect a healthy MQTT session;
- mark newest token generation internally;
- use it only for the next initial or recovery connection.

Tests compare activation and `ApplyChanges()` call counts before and after
routine token refresh.

## 47. Authentication Failure

On confirmed OAuth reauthentication requirement:

- stop lifecycle timer;
- deactivate only an owned WSS instance;
- clear shadow and pending state;
- leave core instances in place;
- preserve public Device variables as stale according to existing REST rules;
- set existing Account authentication state;
- issue no reconnect until OAuth succeeds.

## 48. Recovery State

Lifecycle registry:

```text
state
attempt
nextAttemptAt
lastTransitionAt
lastCoreStatus
lastReceiveAt
tokenGeneration
terminalReason
```

No secret or payload is stored.

Candidate states match step 95:

```text
Disabled
WaitingForAuthentication
WaitingForPairing
Ready
Configuring
Connecting
ShadowActive
Disconnected
Backoff
ReauthenticationRequired
ConfigurationError
```

## 49. Recovery Schedule

Fixed backoff:

```text
30
60
120
300
900 seconds
```

Rules:

- one connection attempt per timer execution;
- fresh credentials for each actual recovery attempt;
- no nested retry in API client or parser;
- no reconnect from message silence alone;
- stop after the fifth failure;
- reset attempt count only after healthy connection and accepted message;
- REST remains active throughout recoverable MQTT failure.

## 50. WP-9: Diagnostics

Implement bounded internal diagnostics without new public variables.

Statistics schema:

```text
receiveCalls
acceptedState
acceptedLocation
rejectedEnvelope
rejectedTopic
rejectedPayload
retainedIgnored
unsupportedChannel
outOfOrder
missingTimestamp
restWakeQueued
restWakeSuppressed
restComparisons
stateMatches
stateMismatches
batteryMatches
batteryMismatches
transportConnectAttempts
transportDisconnects
credentialRefreshAttempts
publishAttempts
commandAttempts
lastAcceptedAt
lastRejectedAt
```

## 51. Error Ring

Store at most 20 sanitized entries:

```text
timestamp
classification
reason
channel
core status
retry attempt
```

Do not store:

- exception trace containing configuration;
- raw exception message before sanitization;
- endpoint;
- topic;
- device ID;
- payload;
- source field value;
- secret.

## 52. Diagnostic Readback

Candidate bounded Account method:

```text
GetMqttShadowDiagnostics()
```

It returns:

- format version;
- enabled and paired booleans;
- lifecycle state;
- counters;
- channel freshness timestamps;
- bounded reason-code history;
- redacted configuration hashes.

It must not return ObjectIDs, topics, identifiers or credentials.

This method supports tests and supervised diagnostics without adding variables.

## 53. Fixed Zero Counters

The diagnostics schema includes:

```text
publishAttempts = 0
commandAttempts = 0
```

Static source structure makes both paths absent. Runtime tests verify the values
never change.

The counters do not legitimize future publish or command code. Any such proposal
requires a new architecture decision.

## 54. WP-10: Compatibility Gates

Capture a deterministic baseline containing:

- productive instance module GUID, parent and connection;
- all Account and Device variable IDs;
- Ident, type, profile, action, visibility and position;
- archive logging and aggregation;
- form property defaults;
- module GUID inventory;
- timers and public method contract;
- standalone productive manifest.

The baseline contains no private values in the repository.

## 55. REST-Only Regression

Tests instantiate an Account with default properties:

```text
EnableMqttShadow=false
MqttReceiverInstanceId=0
```

They prove:

- no native instance lookup is required;
- no timer is active;
- no MQTT API request occurs;
- no variable is added;
- OAuth, discovery and polling pass;
- Dock, Pause and Resume pass;
- adaptive polling behavior remains unchanged.

## 56. Receiver Regression

Tests use a dedicated fake family separate from the current pilot harness.

The fake models:

- module GUID lookup;
- instance connections;
- core properties;
- ApplyChanges counts;
- status changes;
- Account pairing;
- receiver callback.

Do not load incompatible fake families into the same PHPStan process.

## 57. Lifecycle Fake-Time Harness

Add:

```text
tests/mqtt-shadow-lifecycle.php
```

It verifies:

- explicit adoption;
- first connect;
- inactive configuration order;
- one activation;
- healthy token refresh with no reconnect;
- disconnect to backoff;
- fixed retry schedule;
- terminal fifth failure;
- successful recovery reset;
- authentication reset;
- restart reconstruction;
- ownership drift fail-closed behavior.

## 58. Reconciliation Harness

Add:

```text
tests/mqtt-shadow-reconciliation.php
```

It verifies:

- state message queues one target;
- repeated location messages coalesce;
- cooldown suppression;
- target filtering in multiple Devices;
- REST success comparison;
- REST failure leaves shadow internal;
- stale pair not comparable;
- one-percent battery tolerance;
- no MQTT public write.

## 59. Focused Runner

Add:

```text
tools/check-mqtt-shadow.sh
```

It runs:

```text
mqtt fixture tests
mqtt envelope tests
mqtt payload parser tests
receiver tests
shadow reducer tests
reconciliation tests
lifecycle fake-time tests
REST/auth regression
pilot observation regression
distribution validation
PHP syntax for changed files
isolated PHPStan for matching source/fake groups
PHPCS for changed source
static no-publish scan
static no-command-from-MQTT scan
```

No root Composer or Makefile change is required.

## 60. Distribution Validator Changes

Update expected modules to:

```text
NavimowAccount
NavimowConfigurator
NavimowDevice
NavimowMqttReceiver
```

Extend validation to check:

- unique module GUIDs;
- receiver interface metadata;
- required files;
- library file syntax;
- no first-level directory without `module.json`;
- no probe module in distribution;
- no `.DS_Store`;
- no private fixture or local file.

## 61. Official Symcon Validation

Before publication:

- use the official GUID Generator for the receiver;
- use the Module Generator only as a comparison aid, not as an overwrite source;
- validate all new metadata through official schemas;
- run the official Module Validator;
- document cookie or browser limitations if they recur;
- retain local schema validation as the deterministic fallback.

## 62. Static No-Publish Scan

Scan only MQTT implementation files for:

```text
SendDataToParent
MQTT_Publish
/uplink/
publish topic
RequestAction
sendCommands
```

The existing REST command files contain legitimate `sendCommands`. The scan
must target the new Receiver, envelope, payload, shadow and lifecycle paths
rather than suppress the repository-wide finding.

## 63. Privacy Scan

Search generated and changed public files for:

```text
Bearer followed by a value
access token keys
refresh token values
MQTT passwords
private hostnames
complete private topics
device IDs other than public test identities
coordinates from private captures
local ObjectIDs
```

Templates and synthetic identities remain allowed when clearly marked.

## 64. Full Repository Gate

After the focused runner passes:

```text
make check
```

If unrelated existing worktree changes cause failure:

- record the exact unrelated failure;
- do not weaken a gate;
- rerun every Navimow-focused gate;
- do not claim complete repository PASS;
- resolve or isolate the unrelated issue before publication.

## 65. WP-11: Publication Preparation

Create a clean standalone feature worktree from reconciled `main`.

Publication tooling must:

1. capture reconciled main manifest;
2. stage exact distribution;
3. prove no probe directory exists;
4. prove all productive files match reviewed hashes;
5. prove only intended shadow files differ from main;
6. run standalone metadata and syntax validation;
7. create one feature commit;
8. push only the feature branch;
9. create no tag.

## 66. Publication Commit Scope

The feature commit may contain:

- Receiver module;
- MQTT libraries;
- Account shadow and lifecycle changes;
- targeted Device polling support;
- forms, locale and README;
- no fixtures or SAEF reports in standalone module;
- no private helper;
- no test-only probe.

If drift consolidation appears in the diff, publication stops.

## 67. WP-12 Gate A: Disabled Symcon Update

Authorization permits only:

- switch Module Control to feature branch;
- update module;
- leave `EnableMqttShadow=false`;
- inspect identities and behavior;
- restore `main` on failure.

Verify:

- Account, Configurator and Device healthy;
- no Receiver instance created automatically;
- no WebSocket or MQTT Client created;
- existing variables unchanged;
- archive unchanged;
- REST status updates;
- no credential endpoint call;
- no command;
- no `MC_ReloadModule()`.

## 68. Gate A Rollback

On any compatibility failure:

1. do not enable shadow;
2. switch Module Control to `main`;
3. update;
4. verify source commit;
5. verify variable and archive baseline;
6. retain failure evidence;
7. keep feature branch unmerged.

No variable is deleted or recreated.

## 69. Gate B: Inactive Pairing

Separate authorization permits:

- create one dedicated inactive WSS/MQTT chain;
- create one Receiver;
- configure symmetric pairing;
- adopt the chain explicitly;
- keep WebSocket inactive.

Verify:

- exact module GUIDs;
- exact connection order;
- redacted ownership hashes;
- no wildcard;
- no credential retrieval;
- no public value change;
- repeated `ApplyChanges()` produces no duplicate.

## 70. Gate C: Docked Shadow

Separate authorization permits:

- explicit Connect action;
- one credential retrieval;
- one activation;
- bounded receive-only observation;
- internal diagnostics readback;
- no mower command.

Pass requires:

- native WSS and MQTT healthy;
- at least one accepted fixture-backed message;
- zero publish and command counters;
- no direct public MQTT write;
- REST remains healthy;
- no geometry or raw payload retained.

## 71. Gate D: Scheduled Active Operation

Use a normal mower schedule. The test does not start or redirect the mower.

Observe:

- first active location hint;
- one coalesced targeted REST wake;
- MQTT direct state;
- REST state and battery comparison;
- cooldown suppression;
- continued normal polling;
- no device action from MQTT.

Existing archive variables may change only because the authoritative REST read
updates their existing values.

## 72. Gate E: Restart

Run only while the mower is supervised or in a safe normal state.

Verify:

- no stale shadow promotion;
- exactly one Receiver;
- exactly one dedicated core chain;
- exact subscription set;
- no command;
- no publish;
- REST availability;
- bounded recovery;
- no duplicate timer or pending queue;
- archive continuity.

## 73. Gate F: Credential Recovery

Do not force invalid credentials merely to complete the plan.

Preferred evidence:

- natural disconnect or token lifecycle;
- supervised explicit reconnect;
- fake-time and fake-core failure tests.

A destructive or account-lockout scenario requires a separate plan and
authorization.

## 74. Mainline Decision

Merge to standalone `main` only after:

- disabled compatibility Gate A passes;
- inactive pairing Gate B passes;
- docked receive Gate C passes;
- active scheduled Gate D passes;
- restart Gate E passes or is explicitly deferred with bounded private-pilot
  classification;
- all temporary evidence is closed;
- current README documents Shadow status accurately.

No release tag is created at merge time.

## 75. Shadow Observation Period

Before any field-authority decision:

- observe at least one complete scheduled mowing cycle;
- include Running, Docking and Docked;
- include stable docked time;
- include one Symcon restart;
- include one ordinary OAuth refresh;
- record mismatch and wake-suppression counters;
- verify no command or publish;
- preserve archive continuity.

Duration alone is insufficient; the operating phases must be covered.

## 76. Completion Criteria

Implementation is complete only when:

- all work packages are closed;
- distribution and standalone feature branch are byte-identical;
- default-disabled update is backward compatible;
- Receiver and Account pairing is explicit;
- native envelope and payload tests pass;
- MQTT cannot write public state;
- REST reconciliation is bounded;
- credentials never enter diagnostics;
- recovery is bounded;
- no duplicate topology appears;
- user archive configuration is unchanged;
- private and public evidence are reconciled.

## 77. Stop Conditions

Stop implementation or publication when:

- distribution/main drift is unresolved;
- receiver GUID conflicts;
- existing Account becomes parent-dependent;
- a default update creates a core instance;
- any MQTT path can call a command;
- any MQTT path can publish;
- geometry survives into persistent state;
- secret scanning finds a real value;
- repeated `ApplyChanges()` duplicates subscriptions or objects;
- archive metadata changes;
- full rollback cannot be demonstrated.

## 78. Architecture Decisions

### AD-NAV-368: Close drift before MQTT implementation

**Decision:** Publish and test the existing three-file delta separately.

**Rationale:** Transport work must not conceal unrelated production changes.

**Consequence:** MQTT implementation starts from a byte-equal authority.

### AD-NAV-369: Use synthetic envelopes derived from proven shape

**Decision:** Build public regression fixtures from the proven key/type contract
and existing sanitized payloads.

**Rationale:** The live report intentionally retained no private topic or
payload.

**Consequence:** Tests remain reproducible without weakening privacy.

### AD-NAV-370: Separate envelope and semantic parsers

**Decision:** Implement two independent bounded decoding stages.

**Rationale:** Native MQTT delivers the payload as a JSON string inside a JSON
envelope.

**Consequence:** Each trust boundary has its own size and type checks.

### AD-NAV-371: Keep Receiver stateless

**Decision:** Receiver validates and hands off but persists no message.

**Rationale:** Account owns device semantics, credentials and reconciliation.

**Consequence:** Receiver removal cannot delete shadow history or public state.

### AD-NAV-372: Require explicit chain adoption and connection

**Decision:** The first pilot uses separate Adopt and Connect actions.

**Rationale:** Enabling a checkbox must not silently mutate or connect unrelated
core instances.

**Consequence:** Initial setup is deliberate and auditable.

### AD-NAV-373: Defer automatic startup recovery until restart evidence

**Decision:** The first implementation contains the state machine but gates
automatic recovery behind successful staged tests.

**Rationale:** One live transport success does not prove restart behavior.

**Consequence:** The private pilot may require an explicit reconnect before Gate
E closes.

### AD-NAV-374: Keep targeted REST updates backward compatible

**Decision:** Extend the existing `PollStatus` message with optional targeting.

**Rationale:** Existing global polling and Device update paths are already
tested.

**Consequence:** MQTT wake uses REST without adding a second public write path.

### AD-NAV-375: Keep diagnostics internal during shadow

**Decision:** Use versioned bounded attributes and a sanitized readback method.

**Rationale:** New public variables would expand the compatibility and archive
surface before the feature is authoritative.

**Consequence:** Symcon object identity remains stable during the pilot.

### AD-NAV-376: Add a case-study-local focused runner

**Decision:** Run every new MQTT test through
`tools/check-mqtt-shadow.sh`.

**Rationale:** The work remains inside this Case Study and avoids unrelated root
script churn.

**Consequence:** The focused runner precedes, but does not replace, the full
repository gate.

### AD-NAV-377: Publish to a feature branch before main

**Decision:** Use Module Control branch testing with no tag.

**Rationale:** Disabled compatibility and live shadow behavior need proof before
mainline exposure.

**Consequence:** Rollback is a visible branch restoration, not a source reload.

### AD-NAV-378: Preserve release tags until shadow observation closes

**Decision:** Create no pilot tag for implementation or initial merge.

**Rationale:** A tag should identify an observed capability, not only compiled
code.

**Consequence:** Tagging remains a later SAEF decision.

## 79. Decision

**Implementation sequence: APPROVED.**

**Productive PHP implementation: NOT STARTED.**

**Distribution/main drift closure: FIRST BLOCKING PACKAGE.**

**Optional Receiver module: PLANNED.**

**Account parent metadata change: PROHIBITED.**

**Default MQTT state: DISABLED.**

**REST authority: RETAINED.**

**MQTT publish and command paths: PROHIBITED.**

**Automatic core creation: DEFERRED.**

**Existing variable and archive identity: HARD GATE.**

## 80. Recommended Next Step

Create:

```text
97-distribution-main-drift-consolidation.md
```

That step should:

1. regenerate both manifests;
2. verify the exact three-file delta;
3. test the adaptive-polling hardening;
4. publish the delta separately to standalone `main`;
5. update Symcon;
6. verify variables and archive configuration;
7. prove byte equality;
8. leave MQTT implementation untouched.

After step 97 closes, begin `WP-1` through `WP-3` as the first offline MQTT
implementation increment.
