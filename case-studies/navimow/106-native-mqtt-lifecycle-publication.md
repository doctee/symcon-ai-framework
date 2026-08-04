# 106 Native MQTT Lifecycle Publication

**Case study:** Navimow native IP-Symcon module
**Status:** Published and remotely verified; Symcon update and live MQTT
lifecycle remain blocked
**Date:** 2026-07-28
**Scope:** Execute publication Gate A from step 105 and stop before Gate B

## 1. Purpose

This step publishes the offline-validated native MQTT shadow implementation
from steps 98 through 104 to the standalone module repository.

It closes:

- exact candidate validation;
- official metadata-schema validation;
- the classified 17-file publication boundary;
- one standalone commit and fast-forward push;
- remote commit and byte-equality verification.

It does not:

- update the installed Symcon module;
- inspect or change a live Symcon object;
- create or adopt a native MQTT chain;
- retrieve a private MQTT credential;
- connect to the broker;
- send a mower command;
- create a tag.

## 2. Authorization

The user explicitly granted publication Gate A from step 105.

This authorized:

- synchronization of the canonical distribution into the established private
  standalone publish clone;
- one commit on `main`;
- one fast-forward push;
- remote readback and byte verification.

The authorization did not include Gate B or any later live gate.

## 3. Baseline Revalidation

The established standalone clone was clean.

After a fresh fetch:

```text
local main:
2c32b868dda3ca5683b86715c44ea4f3291472ab

origin/main:
2c32b868dda3ca5683b86715c44ea4f3291472ab
```

No reconciliation or merge was required.

## 4. Offline Candidate Gate

The exact canonical candidate passed:

```text
case-studies/navimow/tools/check-mqtt-shadow.sh
make check
git diff --check -- case-studies/navimow
```

Verified areas:

- REST and OAuth regression;
- all established command and pilot-observation regressions;
- MQTT fixture, envelope and parser contracts;
- native Receiver scaffold;
- Account pairing and private ingestion;
- bounded REST reconciliation;
- credential endpoint and WSS mapping;
- explicit adoption, one-attempt connect, rollback and disconnect;
- distribution structure;
- PHP syntax;
- PHPCS;
- PHPStan;
- complete repository gate.

## 5. Official Module Validator

The official Symcon Module Validator page was opened on 2026-07-28.

The validator form was visible and no cookie overlay blocked interaction. Its
result rendering still failed because the page references `$` although the
required dependency is unavailable. This is the previously observed validator
page defect, not a candidate schema result.

The current unchanged resources referenced by the official page were
downloaded temporarily:

```text
librarySchema.json
moduleSchema.json
localeSchema.json
formSchema.json
AJV 6.10.2
```

AJV SHA-256:

```text
25ed94e422941346a247a08672ac1fce9702728df86fa788e4ae0ca8d6ff0549
```

Equivalent local results:

```text
PASS library.json
PASS 4 x module.json
PASS 4 x locale.json
PASS 4 x form.json
```

All 13 JSON artifacts passed. Temporary validator resources remained below
`/private/tmp` and were not published.

## 6. Published Boundary

Exactly the planned 17 productive files entered the commit:

```text
modified: 6
added:    11
total:    17
```

Modified:

```text
NavimowAccount/form.json
NavimowAccount/locale.json
NavimowAccount/module.php
NavimowDevice/module.php
libs/Navimow/ApiClient.php
libs/Navimow/PayloadMapper.php
```

Added:

```text
NavimowMqttReceiver/form.json
NavimowMqttReceiver/locale.json
NavimowMqttReceiver/module.json
NavimowMqttReceiver/module.php
libs/Navimow/MqttCredentialMapper.php
libs/Navimow/MqttEnvelopeException.php
libs/Navimow/MqttEnvelopeParser.php
libs/Navimow/MqttPartialStateAccumulator.php
libs/Navimow/MqttPayloadException.php
libs/Navimow/MqttPayloadParser.php
libs/Navimow/MqttTransportConfiguration.php
```

No SAEF report, fixture, test, tool, private artifact or `.DS_Store` entered
the standalone commit.

## 7. Privacy and Safety Scan

The complete staged delta was scanned for:

- access and refresh tokens;
- private keys;
- synthetic fixture credentials;
- synthetic device and user identities;
- private broker endpoints;
- automatic instance creation or deletion;
- `MC_ReloadModule()`;
- MQTT publish paths;
- parent-send paths.

No match was found.

The source still has no MQTT command transport and cannot actuate the mower
through MQTT.

## 8. Standalone Commit

Repository:

```text
doctee/symcon-navimow
```

Branch:

```text
main
```

Commit:

```text
6cc41d32df6cc2e528bdd4059dda3e006055241a
```

Subject:

```text
feat: add native MQTT shadow lifecycle
```

Commit result:

```text
17 files changed
2950 insertions
1 deletion
```

The push was a direct fast-forward:

```text
2c32b86..6cc41d3
```

## 9. Remote Verification

A fresh post-push fetch proved:

```text
local HEAD:
6cc41d32df6cc2e528bdd4059dda3e006055241a

origin/main:
6cc41d32df6cc2e528bdd4059dda3e006055241a
```

Additional results:

- local `main` is clean and aligned with `origin/main`;
- `HEAD` and `origin/main` have no diff;
- no tag points at the new commit;
- the remote archive contains 30 productive files;
- the canonical distribution contains 30 productive files after excluding
  `.DS_Store`;
- all 30 remote files are byte-identical to the canonical distribution.

## 10. Private Baseline Boundary

The private pre-update Symcon baseline from step 105 was not captured in this
publication-only gate because live Symcon inspection belongs to the separately
authorized Gate B.

This does not affect the published Git commit, but it creates a hard stop:

```text
Do not update the Symcon module before the private pre-update baseline passes.
```

The baseline must still prove existing instance, variable, archive and current
inactive-topology invariants before the user presses Update.

## 11. Decision

| Gate | Result |
|---|---|
| Fresh standalone baseline | PASS |
| Focused MQTT shadow checks | PASS |
| Complete repository checks | PASS |
| Official-schema equivalent validation | 13/13 PASS |
| Exact 17-file boundary | PASS |
| Privacy and forbidden-path scan | PASS |
| Standalone commit | PASS |
| Fast-forward push | PASS |
| Remote commit equality | PASS |
| Remote/canonical byte equality | 30/30 PASS |
| New tag | NONE |
| Symcon pre-update baseline | PENDING |
| Symcon update | NOT PERFORMED |
| Live MQTT mutation | NOT PERFORMED |

**Publication Gate A: CLOSED.**

**Symcon Gate B: BLOCKED pending explicit authorization and pre-update
baseline.**

## 12. Next Step

After explicit Gate B authorization:

```text
107-native-mqtt-preupdate-baseline-and-symcon-update.md
```

That step must first capture and validate the private baseline, then instruct
the user to update through Module Control, and finally close the read-only
post-update compatibility gate. It must stop before topology preparation,
adoption or MQTT connection.
