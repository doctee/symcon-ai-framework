# 33 Shared Wait Helper Maintenance Preflight Report

**Case study:** IP-Symcon MQTT Discovery Exporter
**Gate:** Gate B rollback capture and maintenance preflight
**Result:** PASS
**Date:** 2026-07-19
**Live-system impact:** None

## 1. Rollback Capture

The active `System.Locals.ips.php` file was read as raw bytes and captured in
the private package as Base64. Decoding reproduces exactly 907 bytes and
SHA-256 `d10383e425d2c3967729a197c29dc8024b9b846677aec679cc4748e71109d44c`.

The encoded artifact remains private because the installation bootstrap
contains local definitions. No private source, ObjectID or configuration was
copied into public SAEF documentation.

## 2. Internal Live Preflight

One bounded read-only probe confirmed:

- ready IP-Symcon 9.0 kernel and unchanged start identity;
- exact active `System.Locals` identity;
- exactly one old MQTT bootstrap token and no candidate token;
- complete and exact active and staged filesets;
- the old MQTT wait helper remains the effective reflected function;
- unchanged exporter caller and both ControlLight wrapper identities;
- six active exporter events with explicit script action binding;
- unchanged exporter Registry and ErrorRingBuffer identities;
- unchanged exporter command, publication and failure counters;
- unchanged exporter topology including presentation and functional metadata;
- unchanged ControlLight topology, ownership and presentation metadata; and
- safe ControlLight STATE `false/false` and DIMMER `100/100`.

Private deterministic hashes for both live topology snapshots are retained in
the package so a post-restart probe can compare the complete structures without
publishing installation names or IDs.

The connector reported no transport or PHP execution error and no truncation.

## 3. Restart Coordinator Evidence

The existing restart coordinator and policy retain their reviewed identities.
Its deterministic repository test passed all eight service-state and rollback
traces. A new private wrapper performs only the maintenance preflight:

- verifies active bootstrap, candidate fileset and rollback identities;
- verifies the unique old and absent candidate include tokens;
- forces the coordinator's `-PreflightOnly` path; and
- records that neither activation nor restart was attempted.

The wrapper contains no bootstrap replacement or service-control operation.
The private package verifier and complete SHA-256 inventory pass.

## 4. External Windows Preflight

The external portion was executed from an elevated Windows PowerShell session
using the private `Invoke-SaefSharedWaitHelperPreflight.ps1` artifact. The
machine-readable coordinator status reported:

- coordinator phase/outcome `preflight` / `passed`;
- service state `Running`;
- kernel runlevel `10103`;
- exit code `0` and unchanged kernel start identity;
- `restartAttempted = false`; and
- `rollbackAttempted = false`.

This closes the recovery-channel and Windows service-state evidence that an
in-process Symcon probe cannot provide.

## 5. Gate Decision

Rollback capture, internal maintenance preflight and external Windows
service/recovery preflight are **PASS**. Gate B is complete. No live bootstrap,
object, event, variable, MQTT state or device state changed.

Gate C remains a separate state-changing decision. Gate B does not authorize
the atomic bootstrap selection or supervised service restart.
