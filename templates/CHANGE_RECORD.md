# Private Symcon Change Record

Copy into an ignored private location, not a public case study. This checklist
is not an authorization or a machine-executable deployment manifest.
Use one record per independently reviewable change; link existing receipts.

## Scope

- Purpose and measurable acceptance:
- Route (direct / isolated development / planned high-impact) and reason:
- Authoritative source, effective owner, target and affected consumers:
- Explicit authorization, exclusions, validity and iteration/resource bounds:
- Immediate code/UI effects, physical effects and quiescence:

## Before and Candidate

- Complete private before-copy location and hash; identity/configuration/state:
- Secrets backup availability (redacted values are not a full backup):
- Candidate source/revision/hash and focused diff:
- Drift check immediately before mutation:
- Recovery scope, backup retention and separate deletion/cleanup authority:

## Verification

- Offline syntax/schema and behavior tests:
- Isolation proof and synthetic test results, if applicable:
- MCP tool/version, response contract and complete response reference:
- Advertised versus behavior-tested capabilities; structured syntax verdict:
- Required PHP paths, checked/skipped lint coverage and explicit path checks:
- Diagnostic time/file coverage and any partial log counts:
- Mutation attempted / confirmed / uncertain; separate lint/validation:
- Reload need, explicit authority and all affected instance results:
- Independent content/identity/configuration readback:
- Domain observation window, metrics, baseline comparison and abort criteria:
- Recurring checks, if applicable: short visible prompt, authoritative plan
  reference, no-finding output (one sentence / agreed supported silence),
  failure/escalation and final-report policy; follow
  [Testing Standards](../standards/TESTING_STANDARDS.md#13-quiet-recurring-checks):

## Closure

- Outcome: verified / failed / uncertain / recovered / recovery required:
- Recovery attempted, restored-state verification and unresolved effects:
- Production/device/publication actions performed (or explicitly none):
- Remaining gates and retained artifacts; no implicit cleanup:
- Artifact roles/locations, producer revision, originals versus copies, holds
  and next review; use [the closure checklist](artifacts/CLOSURE_CHECKLIST.md).

See [the workflow](../project/SYMCON_CHANGE_WORKFLOW.md). This record does not
replace the stronger evidence contract of an already managed deployment.
