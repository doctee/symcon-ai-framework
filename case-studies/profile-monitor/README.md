# Profile Monitor presentation support

Status: published fork and live migration verified; long-term observation open.

## Problem and boundary

Profile Monitor selects variables by their effective legacy profile. Variables
using native Symcon presentations can therefore disappear from the monitored
set even though they still carry battery status. This case study adds explicit
identity-based rules while retaining the original profile configuration,
central ignore list and output contracts. It does not create a second battery
lifetime, debounce, reachability or exclusion policy.

`candidate/Evaluator.php` is an internal, deterministic component with no
Symcon calls. `candidate/MonitorRuntime.php` collects one snapshot and supplies
a read-only preview. It is integrated into the original module only by the
local `tools/prepare-candidate.py` tool. No new public SAEF helper is introduced.
The existing Ensure and Diagnostics libraries were considered: this extension
creates no new runtime objects, owns no new persistent metadata and needs no
additional Ensure logic. Existing module registration remains the lifecycle
owner. Unknown-state reasons use the existing debug channel and preview;
Registry, Statistics and ErrorRingBuffer are reserved for a later justified
persistent diagnostics requirement.

## Selection and evaluation

1. Resolve valid custom profile first, otherwise valid default profile.
2. Preserve matching `Profiles2Monitor` rules, including duplicate-name
   last-row-wins and PHP's original comparison semantics.
3. If explicitly enabled, match an exact provider module GUID and variable
   Ident, or an explicit positive variable ID. Names and icons are not selectors.
4. Keep legacy matches unless an explicit variable rule sets `overrideLegacy`.
   Multiple effective rules for a variable are rejected before output writes.
5. Put every recognized variable in `Profile_Monitor_AllCheckedVariables`,
   including exclusions and unknowns. Apply `IDs2Ignore` only to warnings.
6. Treat missing first updates as `no_data` (default, separately switchable),
   validate native type/presentation/action/scale and evaluate admitted values.
7. Render through the original module output and notification code.

Native rules support Boolean equality and numeric equality/less-or-equal.
Thresholds must be typed JSON values: `false`, `2.2`, `10`, not string encodings.
Existing profile threshold text, including comma decimals, is deliberately not
normalized: review and convert it separately if needed. Unknown fields,
unsupported String rules, duplicate names and invalid selectors fail closed.

Built-in presets (all recognition opt-ins default to off):

| Preset | Identity | Warning | Unknown |
| --- | --- | --- | --- |
| Zigbee2MQTT | module `{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}`, Ident `battery`, Integer, value presentation | Normalized percent <= configured threshold, default 10 | No update, invalid scale/type/presentation, active action or out-of-range value |
| Blink Home Device | module `{7D2B8EFA-23D0-D29C-DBEE-E81F1FC2DBDC}`, Ident `battery`, Integer, value presentation | Raw state <= configured 1 or 2; default 2 | State 0, no update or unexpected value/contract |

Blink's source maps 0 to unknown, 1 to critical, 2 to low and 3 to OK. Its
German presentation labels differ (Niedrig/Mittel/Gut). Match numeric states,
not translated captions. The threshold of 2 retains the intended old
`BHS.Battery <= 2` policy while treating unknown 0 separately. Do not treat
this 0..3 status as a charge percentage. Only Blink Home Device is admitted;
accessories require their own verified contract.

Percent normalization requires `PERCENTAGE === true`, finite numeric MIN/MAX
(actual integers for Integer variables; floating-point bounds are rejected),
MAX > MIN and an in-range value. Formula: `100 * (value - MIN) / (MAX - MIN)`.
A suffix `%` is insufficient. For a verified provider with raw percent values
but no percentage scaling, use an explicit numeric `raw` rule with range
[0,100]. Real measured zero still warns. A changed presentation or actuator
binding produces an unknown state rather than silently losing the recognized ID.

Example provider rule (generic voltage monitoring, not a universal battery
voltage threshold): use the exact documented module GUID/Ident, `type: 2`,
`unit: "raw"`, `operator: "le"`, a device-appropriate numeric `threshold`, and
an optional expected `presentationId`. Explicit variable IDs belong only in
private installation configuration; no dummy zero IDs are accepted.

## Public configuration and compatibility

- `PresentationMonitoring`: false by default; gates all native rules.
- `MonitorZigbeeBattery`, `MonitorBlinkBattery`: false by default.
- `PresentationPercentThreshold`: integer 0..100, default 10.
- `BlinkBatteryThreshold`: 1 or 2, default 2.
- `PresentationRules`: JSON list, default `[]`.
- `SkipNeverUpdated`: true by default; deliberate independently reversible fix.
- `BW_PreviewPresentations(instance)`: returns checked/warnings/ignored/unknown
  ID lists and per-variable reason/status; never writes values or sends messages.
  The form button evaluates saved native rules even while the master switch is
  off. Save the preset/rule selection with PresentationMonitoring=false, preview,
  then activate only after review. Preview requires 8.1; normal legacy checks do
  not. Unsaved form edits are not included.

The installed module/library GUIDs, `BW` prefix, `BW_Check`, RemoteTrigger,
Warning, Devices_With_Empty_Battery, LastUpdate, both JSON variable Idents,
HTML options, timers and notification paths are preserved. RAW remains a JSON
array of integer warning IDs. AllCheckedVariables remains a JSON array of
recognized IDs. Ordering follows the inventory order. New results do not add
objects to those arrays. The original no-warning notification behavior is
preserved; this change does not redesign notification recovery semantics.
The HTML no-warning text reports an unknown count when appropriate. Existing
consumers that understand only the two ID lists do not gain an unknown-state
field automatically; preview is the authoritative diagnostic view for this
candidate. No history or archive configuration is changed.

Native presentation API use requires Symcon >= 8.1. When disabled, no native
presentation call is made; the older profile path remains available. Real
Symcon 6.0/8.0 installation qualification and their exact PHP versions remain
open; passing a CLI fake is not a compatibility certification. Textual battery
states and inverted-Boolean archive fallback semantics in consumers require a
separate migration and are intentionally not enabled here.

## Reproducible local preparation

Keep an authorized original module tree below this worktree's `private/`.
The preparation tool accepts only two reviewed module.php SHA-256 values
(installed 1.5 and public 1.6 source), validates module identities, rejects
symlinks and existing output directories, and creates a hash manifest. It
requires no network and never publishes or installs anything. Original source
files are not distributed in this case study. Source form/library/locale hashes
are recorded in each manifest; review them with the module hash before a live
migration. Generated candidates include MIT license, provenance and a standalone README.
Preparation does not publish or deploy a release.

```sh
python3 case-studies/profile-monitor/tools/prepare-candidate.py \
  private/profile-monitor/baseline private/profile-monitor/candidate-rN
php case-studies/profile-monitor/tests/runtime.php
php case-studies/profile-monitor/tests/runtime.php --legacy-runtime
python3 case-studies/profile-monitor/tests/preparation.py private/profile-monitor/baseline
python3 case-studies/profile-monitor/tests/integration.py \
  private/profile-monitor/baseline/ProfileMonitor/module.php \
  private/profile-monitor/candidate-rN/ProfileMonitor/module.php
php case-studies/profile-monitor/tools/compare-snapshot.php \
  private/profile-monitor/snapshot.local.json
```

The integration harness executes both full original/candidate sources in
separate PHP processes using synthetic Symcon functions. It compares legacy
outputs, HTML and notification arguments, and verifies native additions,
preview without writes, scan failures before writes, RemoteTrigger, disabled
timer and stable IDs across repeated ApplyChanges. It never contacts Symcon.
Production analysis uses `tools/AnalysisHost.php` with the canonical Symcon
stubs; executable global test doubles are kept outside that symbol table.
`composer test:profile-monitor` is part of `make check`. Tests requiring the
private original are a separately reported gate, not silently claimed by CI.

## Migration and rollback gates

1. Upstream MIT permission was confirmed on 1 October 2026. The attributed
   fork was published as `doctee/Profile-Monitor` version 1.7. Preserve the
   attribution and consent links in `fork/PROVENANCE.md`, include `fork/LICENSE`,
   and respect the narrow exception in `LICENSE-SCOPE.md`. The framework license
   is unchanged.
2. Retain the exact private original module tree, source/configuration hashes,
   instance/output IDs, archive/link/event metadata and dependent-script hashes.
   Source readback and offline comparison are evidence, not deployment approval.
3. Recheck configuration and source drift; fix ambiguous old thresholds only
   through a separate reviewed configuration change.
4. Review every added variable's location, archive impact and policy class.
   AllCheckedVariables can trigger downstream archive setup and assignment
   warnings on normal scheduled runs. Do not assume adding an ID is side-effect
   free for consumers. Retain the central ignore list and event-driven policy.
5. The exact Store-to-Git migration retained the original instance and output
   IDs; no second library with the same module GUID or BW functions was left
   installed. Do not repeat that one-time migration.
6. Native rules were enabled only for the reviewed cohort after a write-free
   preview. Continue to validate
   natural scheduled cycles, JSON contracts, warning counts, unknowns and
   notification absence/presence as agreed. A restart is a separate gate.
7. On failure, disable the new rules and restore the saved exact code/config
   through the approved deployment owner; preserve variables, links and history.
   A code rollback does not undo notifications already sent or downstream
   archive configuration; prevent/record those effects during the rollout.
8. Keep baseline, candidates and worktree until evidence and retention are
   explicitly closed. No cleanup authority follows from this implementation.

## Sources

- [Original module, pinned revision](https://github.com/elueckel/Profile-Monitor/tree/c550dd1e2ddebd74aa09b3e33a1f3a32659f3928)
- [Blink battery contract, pinned revision](https://github.com/Wilkware/BlinkHomeSystem/blob/9e578d09cbe516ec24d7218658ed58be007c2edb/Blink%20Home%20Device/module.php)
- [Official API index](https://www.symcon.de/de/llms/function-index.md)
- [Resolved presentation](https://www.symcon.de/de/service/dokumentation/befehlsreferenz/variablenverwaltung/ips-getvariablepresentation/)
- [Value presentation](https://www.symcon.de/de/service/dokumentation/komponenten/objekt-darstellung/wertanzeige/)
- [No-first-update proposal](https://community.symcon.de/t/modul-profile-monitor-batterie-ueberwachung/132012/167)
- SAEF module template, PHP/Testing/Symcon standards, EK-004/EK-005,
  ADR-0003 and ADR-0008. No shared runtime helper or deployment channel changes.

## Rust/Ninja qualification

The candidate supplies explicit empty profile arguments to inherited variable
registrations. Exported notification methods throw without output when no
destination is configured, and require a positive destination ID. Integer
percentage rules reject floating-point MIN/MAX options without coercion.
Malformed or incompatible presentation data remains recognized but unknown.

Sources: [Ninja validation update](https://community.symcon.de/t/ip-symcon-9-1-ninja/44478/564),
[Rust incorporation](https://community.symcon.de/t/symcon-9-1-rust-edition/144258/516),
[Integer option types](https://community.symcon.de/t/symcon-9-1-rust-edition/144258/523).
The resolved `IPS_GetVariablePresentation()` API already returns an array;
no blanket JSON-decoding fallback is added. These are offline compatibility
checks, not a claim that the modified module has run on the live Rust kernel.
