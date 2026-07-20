# 31 Shared Wait Helper Load-Path Analysis

**Case study:** IP-Symcon MQTT Discovery Exporter
**Gate:** Read-only global dependency and restart analysis
**Result:** PASS — supervised clean-process transition required
**Date:** 2026-07-19
**Live-system impact:** None

## 1. Trigger

A corrected `SAEF_WaitForVariable()` fileset passed deterministic offline tests
but a later ControlLight device test reproduced the old timeout behavior.
Reflection proved that ControlLight had not loaded its corrected helper copy:
the shared PHP context already contained the function from the active MQTT
Discovery Exporter fileset.

## 2. Authoritative Load Path

The live read-only inventory established this order:

1. generated Symcon includes;
2. the installation autoloader;
3. `System.Locals.ips.php`;
4. the selected MQTT Discovery Exporter bootstrap;
5. exporter runtime, helpers and core; and
6. `System.Functions.ips.php`.

`System.Locals.ips.php` contains exactly one MQTT exporter bootstrap token. The
active bootstrap defines the complete guarded SAEF namespace before ordinary
scripts execute. `System.Functions.ips.php` contains no SAEF symbol or exporter
fileset reference.

Fresh-script Reflection resolved the wait helper and the sampled object and
diagnostic helpers to the same active MQTT fileset. This is an ownership fact,
not merely an include discovered in source text.

## 3. Minimal Fileset Delta

The active fileset identity is
`f60372c890cc6ba98fdcc4f54b660dee69ca4a369cf8dd2a7be0a3454069b2af`.
The regenerated candidate identity is
`591acf8ff4418aec0fdbb711efa291254f6718935795c6b56be91fce0fdb755e`.

Exact source-map comparison found one changed file only:

| Source | Active SHA-256 | Candidate SHA-256 |
| --- | --- | --- |
| `helpers/variable/WaitForVariable.php` | `0e39bf12da3a88f1a79b99cbeb54ed87d5a71e573146cce4e9ae7ed9f4c55bbb` | `4b79fb7a7339573f61a84d64e8634d6dc7faa3d161f645277a5e62228b8a7222` |

Exporter core, runtime, bootstrap and all eleven other sources are byte
identical. The bootstrap identity remains
`3567e73a1ac93743f6daa5a21dcd208c3a7845e4f391ebca31c9bf86839725c9`.

The performance contract therefore changes only conditioned wait polling:
timestamp-only waits still perform no value read, while a conditioned wait
performs at most one value read per poll. Exporter reconciliation, publication,
object ownership and steady-state event handling are unchanged.

## 4. Consumers and Regression Boundary

The live installation has:

- one exporter caller using the globally loaded runtime;
- active exporter command and state events;
- an existing owned diagnostics and device hierarchy;
- two ControlLight v2 wrappers that currently reference the preceding
  ControlLight fileset; and
- no direct ordinary-script call to `SAEF_WaitForVariable()`.

The MQTT runtime and ControlLight runtime are the two repository consumers of
the helper. A global MQTT namespace change can therefore affect both command
confirmation paths even though only the helper source changes.

## 5. Restart Decision

A wrapper or bootstrap file replacement alone is insufficient. PHP cannot
redefine the existing global function, and the installation autoloads it before
each ordinary script body. Effective selection must be proved in a clean
process.

The required mechanism is the existing external Windows restart coordinator,
which already provides:

- service and ready-runlevel preflight;
- atomic bootstrap replacement with byte-exact rollback backup;
- state-based stop, start and readiness observation;
- advanced kernel-start proof;
- bounded recovery and rollback restart; and
- explicit machine-readable outcomes.

No in-process Symcon script may coordinate its own service restart.

## 6. Migration Gates

### Gate A — package and inactive staging

1. Build a private activation package from the deterministic candidate.
2. Prove that only the wait helper differs from the active source map.
3. Stage the complete candidate under a new hash-addressed directory.
4. Independently read back all files and confirm that the directory is not
   selected.

### Gate B — immediate activation preflight

1. Revalidate the active `System.Locals` hash and its unique old include token.
2. Capture a byte-exact rollback copy outside the active scripts directory.
3. Snapshot exporter owner topology, event contracts, Registry/error-history
   identities and diagnostic counters.
4. Confirm ready service/runlevel and the restart coordinator's non-activating
   preflight.

### Gate C — supervised clean-process transition

1. Replace only the equal-length bootstrap directory token atomically.
2. Start one supervised service restart from the external coordinator.
3. Roll back the bootstrap and restart again automatically on activation
   failure.

This gate must not run exporter reconciliation, publish MQTT messages or send a
device command.

### Gate D — post-start read-only verification

1. Prove a newer kernel start identity and ready runlevel.
2. Use Reflection before any ControlLight include to confirm the corrected wait
   helper source and hash.
3. Verify the exact expected global functions and exporter classes.
4. Confirm unchanged exporter caller, topology, event actions, Registry,
   error-history and command/publication counters.
5. Confirm that both ControlLight wrappers and their authoritative values are
   unchanged.

### Gate E — later ControlLight retest

Only after Gate D passes may CL-023 again select the corrected ControlLight
fileset and perform two non-commanding synchronizations. A real STATE/DIMMER
test remains a subsequent explicit approval.

## 7. Gate Decision

The read-only dependency analysis is **PASS**. The load owner, minimal source
delta, consumers and restart requirement are now authoritative.

No live file, bootstrap, object, event, variable, MQTT publication or device
state was changed. The next state-changing step is Gate A inactive staging and
requires separate approval.

## 8. Subsequent Gate

Gate A was separately approved and passed. Report 32 records the private
package, inactive live staging and independent proof that the old global helper
remains effective until a later bootstrap/restart gate.
