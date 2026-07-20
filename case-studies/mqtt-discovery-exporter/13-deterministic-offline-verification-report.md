# 13 Deterministic Offline Verification Report

**Case study:** IP-Symcon MQTT Discovery Exporter
**Gate:** G5
**Result:** PASS
**Verification date:** 2026-07-15
**Deployment status:** No network access, live object mutation or device action

## 1. Outcome

The complete helper-first candidate passed the G5 deterministic offline gate.
The evidence now includes reviewable full discovery fixtures for every
capability combination accepted by the normalized public contract.

Passing G5 authorizes preparation for G6 only. It does not authorize runtime
deployment, broker publication, Home Assistant mutation or a device command.

## 2. Pure Verification Trace

| G5 criterion | Deterministic evidence | Result |
| --- | --- | --- |
| Entity alias normalization | `core.php` complete alias and state/action-pair scenarios | PASS |
| Complete and incomplete capability contracts | `core.php` rejection and normalization scenarios | PASS |
| Unique identity, Ident and topic derivation | Core identity assertions plus exact fixture topics | PASS |
| Discovery payloads for all supported combinations | Six full JSON fixtures and byte-exact comparison | PASS |
| Runtime payload construction | Core value assertions plus fixture runtime-topic sets | PASS |
| RGB and Kelvin parsing | Strict core command parsing scenarios | PASS |
| Malformed payload rejection | Core and dispatch tests without device action | PASS |
| Configuration and payload hash stability | Diagnostics configuration hash and canonical core hash tests | PASS |
| Managed reconciliation and exact removal | Preparation, execution and cleanup suites | PASS |
| Command failure propagation | Dispatch action, confirmation and publication failure scenarios | PASS |
| Repeated setup without duplicates | Diagnostics, preparation and execution idempotency scenarios | PASS |

The six supported public combinations are:

1. power-only switch;
2. on/off light;
3. brightness light;
4. brightness plus RGB;
5. brightness plus Kelvin color temperature;
6. brightness plus RGB and Kelvin color temperature.

Color capabilities without brightness are intentionally rejected by the
normalized contract and are therefore not omitted test cases.

## 3. Reviewable Fixture Contract

`tests/mqtt-discovery-exporter/fixtures/discovery-capabilities.json` records for
each supported combination:

- exact single-component discovery topic;
- complete decoded discovery payload;
- exact ordered runtime-topic set.

The fixture uses only neutral names, reserved example URLs and synthetic test
IDs. The test rebuilds the complete matrix and performs a byte-exact formatted
JSON comparison. Fixture changes must therefore be generated, reviewed and
accepted explicitly.

## 4. External Contract Recheck

The external contracts were rechecked on 2026-07-15:

- Home Assistant MQTT Discovery:
  <https://www.home-assistant.io/integrations/mqtt/#mqtt-discovery>
- Home Assistant MQTT Light:
  <https://www.home-assistant.io/integrations/light.mqtt>
- IP-Symcon Event Management:
  <https://www.symcon.de/en/service/documentation/command-reference/management-events/>
- IP-Symcon Instance Management:
  <https://www.symcon.de/en/service/documentation/command-reference/management-instances/>
- IP-Symcon Variable Access:
  <https://www.symcon.de/en/service/documentation/command-reference/accessing-variables/>

Confirmed contracts relevant to the candidate are:

- the single-component discovery topic structure remains supported;
- a stable `unique_id` prevents duplicate discovery identities;
- retained discovery is a documented restart-recovery option;
- an empty retained discovery payload removes an entity;
- Basic MQTT Light supports separate brightness, RGB and color-temperature
  topics, explicit `color_mode_state_topic`, brightness scaling and Kelvin;
- RGB state is represented as comma-separated channel values;
- event actions remain explicitly bindable for current IP-Symcon versions.

Home Assistant now recommends Device Discovery when one device exposes several
components. The candidate intentionally retains the accepted single-component
architecture from G4. Device Discovery remains a possible later architecture
change and is not required to pass this candidate's documented scope.

## 5. Static Verification

The repository-wide `composer check` passed with:

- PHP syntax and repository lint;
- current generated bundle verification;
- helper and bundle tests;
- 9 core tests;
- 5 runtime-diagnostics tests;
- 5 preparation tests;
- 3 reconcile-execution tests;
- 8 dispatch tests;
- 6 cleanup tests;
- 6 byte-exact discovery fixture scenarios;
- PHPStan for repository and generated bundle;
- PHPCS.

A focused scan found no private network address, hostname, credential, personal
ObjectID, private MQTT topic or installation name in the candidate, tests,
fixtures or reports.

## 6. Offline Boundary

All exporter tests use the stateful IP-Symcon fake. They do not:

- open sockets or access the network;
- connect to an MQTT broker or Home Assistant;
- read a private configuration file;
- modify a live IP-Symcon object tree;
- invoke a physical action.

## 7. Gate Decision

G5 is **PASS**.

G6 remains gated by all of the following:

1. a reviewed deployment adapter for the candidate and its canonical helper
   dependency closure;
2. an isolated private pilot configuration and topic namespace;
3. a recoverable private pre-change snapshot;
4. an operator-confirmed rollback window;
5. explicit authorization for each device-affecting command.

The companion G6 plan defines these controls but performs none of them.
