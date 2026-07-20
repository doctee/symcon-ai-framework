# 23 Live Diagnostics Idempotency Report

**Case study:** IP-Symcon MQTT Discovery Exporter
**G6 sub-gate:** Phase A diagnostics initialization and reuse
**Result:** PASS
**Date:** 2026-07-15
**Live-system impact:** One isolated owner script, one diagnostics category and eleven diagnostic variables

## 1. Scope

The activated exporter runtime initialized its diagnostics structure twice with
the same empty pilot configuration. The test was explicitly limited to
diagnostics ownership and idempotency.

It did not prepare or publish an entity, create an event or MQTT adapter, send
an MQTT message, call `RequestAction()` or control a device.

## 2. Initialization Evidence

| Check | Result |
| --- | --- |
| Owner children | Exactly 1 |
| Diagnostic variables | Exactly 11 |
| First/second object identities | Equal |
| First/second configuration hashes | Equal |
| Registry changed on second run | No |
| Non-variable diagnostics children | 0 |
| Statistics after both runs | All zero |
| Error history | Empty |

The Registry remained empty for managed entities, command and state indices,
publishers and cleanup tombstones. This proves that diagnostics initialization
did not implicitly enter reconcile or publication behavior.

## 3. Independent MCP Read-back

The connected Symcon MCP server independently confirmed the owner hierarchy,
the eleven variable types and values, the empty error history and the empty
Registry collections.

The current MCP text-execution operation returns only execution acceptance and
does not return script output. The consolidated two-run comparison therefore
used `IPS_RunScriptTextWait` through authenticated JSON-RPC, while MCP provided
an independent read-only state check. A future MCP result-channel operation can
remove this split verification path.

## 4. Integrity-tool Classification

A separate installation integrity scan was not caused by this test. Its error
count predated the activation, and its warning count was zero.

Five-digit numeric literals reported by heuristic script analysis are not by
themselves proof of broken ObjectID references. SAEF now requires non-ID values
in this numeric range to use named or unit-explicit representations where that
improves clarity, or a documented integrity-tool exclusion after confirming
that the value is not an ObjectID.

## 5. Gate Decision

Phase A diagnostics initialization and reuse are **PASS**. Together with the
clean-process activation report, the fileset load, migrated caller, diagnostics
ownership and no-publication boundary are verified.

Phase B remains separate. It requires a private mapping for one MQTT connection,
one disposable virtual switch and one unique pilot token before any reconcile
or retained publication is authorized.
