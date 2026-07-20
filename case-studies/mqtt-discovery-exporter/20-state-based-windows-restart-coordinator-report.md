# 20 State-based Windows Restart Coordinator Report

**Case study:** IP-Symcon MQTT Discovery Exporter
**G6 sub-gate:** Corrected restart and rollback mechanism
**Result:** WINDOWS PARSER AND NON-ACTIVATING PREFLIGHT PASS
**Date:** 2026-07-15
**Live-system impact:** None

## 1. Outcome

The fixed-delay restart worker that failed during the first activation attempt
has been replaced in the repository by an external PowerShell coordinator. The
new procedure observes real Windows service and IP-Symcon kernel states.

The coordinator is not an IP-Symcon script. It must be launched from an
elevated PowerShell process that remains available while the `IPSServer`
process is stopped. This preserves the recovery channel that was lost when the
previous restart logic depended on fixed timing around the service lifecycle.

## 2. State Contract

An activation restart succeeds only after all of these facts are observed:

1. preflight service state is `Running`;
2. preflight kernel runlevel is `10103`;
3. the service reaches `Stopped` after the stop request;
4. the service reaches `Running` after the start request;
5. RPC returns runlevel `10103`; and
6. the kernel start time is newer than the preflight value.

The same reviewed script provides a `-PreflightOnly` mode. After successful
service, RPC, policy and optional hash checks, that mode writes
`restartAttempted: false` and exits before the activation restart block.

`StopPending` and `StartPending` are observed without issuing duplicate service
commands. Connection failures during kernel initialization are treated as an
expected transient state until the readiness deadline expires.

The default 900-second readiness deadline is an upper bound, not a delay. It
leaves substantial margin over the 176-second startup observed in the failed
transaction, while returning immediately when readiness is proved.

## 3. Rollback Contract

When rollback is configured, the source bootstrap hash is checked before an
atomic replacement of the active bootstrap. The restored target hash is checked
again before the rollback restart.

The coordinator publishes one of five unambiguous process outcomes:

| Exit code | Outcome |
| ---: | --- |
| `0` | Activated and ready |
| `10` | Preflight failed |
| `20` | Activation failed without configured rollback |
| `30` | Activation failed; rollback recovered a ready kernel |
| `40` | Rollback recovery failed; manual recovery required |

An atomically replaced JSON status file records phase, outcome, timestamps and
state facts. Credentials, private paths and exception messages are excluded.

## 4. Offline Evidence

The repository test verifies:

- the service-state, RPC-readiness, hash and atomic-replacement contracts;
- the absence of fixed sleeps, shell-style service commands and private paths;
- positive policy bounds and a readiness window above the observed startup;
- eight deterministic traces covering non-activating preflight, success, slow
  success, activation stop
  and readiness failures, rollback success, rollback failure, activation
  failure without rollback and preflight rejection.

The artifacts are:

- `deployments/symcon/windows/Invoke-SaefSymconRestart.ps1`;
- `deployments/symcon/windows/restart-policy.json`;
- `deployments/symcon/windows/README.md`; and
- `tests/deployments/windows-restart-coordinator.php`.

## 5. Windows Preflight Evidence

The exact coordinator artifact with SHA-256
`6629d92e79ad5bcaaa48700633ebf79143a2a3713d638afec0016d9682fcd8bd`
passed the Windows PowerShell parser on the target host.

The first non-activating run exposed and corrected two Windows-specific
integration details before any service control was possible:

- Windows PowerShell required an explicit UTF-8 HTTP Basic header for the
  Symcon license-email and Remote Access credential;
- the installed .NET runtime required a real backup path for `File.Replace`
  rather than a null backup argument.

The final private preflight returned process exit code `0` and recorded:

| Check | Result |
| --- | --- |
| Phase/outcome | `preflight` / `passed` |
| Windows service state | `Running` |
| Symcon kernel runlevel | `10103` |
| Active bootstrap hash | Matches the private approved snapshot |
| Restart attempted | No |
| Rollback attempted | No |
| Existing status replacement | PASS on the target Windows/.NET runtime |

Repeated rejected authentication probes temporarily triggered the target's
login slowdown. The gate proceeded only after the documented protection window
had elapsed and a single read-only RPC probe succeeded. No credential, header
or private path is included in public evidence.

## 6. Remaining Gate

No live restart was performed while implementing or verifying this correction.
The first state-changing use remains a separately authorized maintenance action
with the reviewed bootstrap paths and hashes supplied only through the private
activation record.

The corrected mechanism removes the known timing defect. It does not by itself
authorize exporter activation, MQTT publication or a device command.
