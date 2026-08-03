# Engineering Knowledge EK-006

# Runtime Diagnostics in IP-Symcon

**Status:** Draft 1.0

## Purpose

This Engineering Knowledge article explains how runtime diagnostics should be
modelled in SAEF-based IP-Symcon automations.

Runtime diagnostics are the script-owned metadata, counters and recent error
context that help operators and engineers understand an automation after it has
run. This article explains concepts behind RS-001 and RI-002. It does not
define additional mandatory rules.

---

## Problem

Logs alone are often not enough to understand automation behaviour.

Typical problems include:

- the current state is visible, but not why it changed,
- intermittent errors are overwritten by later runs,
- retry or execution counts exist only in logs,
- configuration changed but no fingerprint was stored,
- diagnostic JSON grows without a clear boundary.

Runtime diagnostics solve this by making the important diagnostic state explicit, owned and bounded.

---

## Initialization Boundary

Runtime diagnostics begin after the diagnostics structure has been initialized
successfully.

Setup or Ensure failures that occur before Registry, Statistics or
ErrorRingBuffer variables exist cannot be captured fully in those diagnostics.
Those early failures should remain visible through `IPS_LogMessage()`,
exceptions or the Symcon log.

After the diagnostics structure exists, runtime failures should be modeled in
the established diagnostics responsibilities:

- ErrorRingBuffer for bounded error or event history,
- Statistics for counters, timestamps and duration values,
- Registry for small structured metadata.

This boundary keeps initialization failures observable without pretending that a
not-yet-created diagnostics store can record them.

---

## Engineering Context

Runtime diagnostics are useful when an automation:

- runs repeatedly,
- creates or updates managed objects,
- needs supportable configuration fingerprints,
- should expose execution or error counters,
- needs recent error history,
- must be reviewable by humans and AI agents.

They complement operational logs. They do not replace error handling, recovery logic or archive processing.

---

## Recommended Composition

RI-002 demonstrates the current SAEF composition model:

```text
Configuration
    |
    v
Configuration Hash
    |
    v
Registry Metadata
    |
    +-- Statistics Variables
    |
    `-- Error Ring Buffer
```

Each diagnostic concern has a separate responsibility.

## Diagnostic Building Blocks

### Configuration Hash

A configuration hash is a stable fingerprint of desired configuration data.

Use it to record which configuration shape an automation last processed.
Ignore volatile keys such as timestamps, runtime values or last-run fields
before hashing.

The hash is diagnostic metadata. It should not be treated as a secret, signature or integrity guarantee.

### Registry

A registry is a small JSON metadata map stored in a script-owned string variable.

Good registry entries include:

- version,
- configuration hash,
- previous configuration hash,
- migration marker,
- current phase.

Avoid using a registry for discovery payloads, large API responses, historical
data or arbitrary dumps. If the data is large or unbounded, it is not registry
metadata.

### Statistics

Statistics are explicit variables for counters and timestamps.

Typical statistics include:

- executions,
- errors,
- retries,
- last run,
- last success.

Use separate typed variables where practical. This keeps profiles, ownership and type conflicts visible.

Counter increments are read-modify-write operations. SAEF serializes each
statistics variable independently so parallel automation paths cannot lose an
increment. The lock is scoped to one counter update; it must not serialize the
surrounding device command or unrelated statistics variables.

### Error Ring Buffer

An error ring buffer is a bounded JSON list of recent errors.

It preserves intermittent failure context without growing forever. Entries
should contain concise metadata such as timestamp, message, error type or small
context fields.

Do not store credentials, tokens, private network details, discovery payloads or full external responses in the buffer.

---

## Trade-offs

Benefits:

- easier support and review,
- better restart diagnostics,
- clearer separation of operational state and diagnostic detail,
- fewer hidden runtime assumptions.

Costs:

- additional variables,
- more documentation,
- decisions about what belongs in diagnostics and what belongs in logs or archives.

Runtime diagnostics are worthwhile when an automation is reused, scheduled,
stateful or expected to be maintained over time.

---

## Common Anti-Patterns

### One Large JSON Dump

Putting all runtime state, diagnostics, cache and discovery data into one string
variable makes ownership and evolution unclear.

### Unbounded Error History

Appending every error forever creates an unreliable diagnostic store. Use a fixed-capacity ring buffer.

### Statistics Hidden in Registry

Frequently updated counters and timestamps are usually clearer as typed variables.

### Configuration Hash with Runtime Fields

Including timestamps or last-run values in a configuration hash makes the hash
change on every run and destroys its diagnostic value.

### Private Data in Diagnostics

Diagnostics must not store credentials, tokens, private hostnames, private IP addresses or private MQTT topics.

---

## Relationship to RS-001

RS-001 defines the engineering expectations for explicit internal state,
diagnostics, bounded context and helper-first reuse.

This article explains how those expectations can be applied to runtime diagnostics using existing SAEF helpers.

---

## Related Standards

- RS-001 Symcon Engineering Standards
- PHP Standards
- Documentation Standards

---

## Related Knowledge

- EK-004 — Internal State Management
- EK-005 — Idempotent Configuration
- EK-002 — Retry Mechanisms

---

## Related Reference Implementations

- RI-002 — Runtime Diagnostics / Internal State
- RI-001 — Idempotent Configuration Script
