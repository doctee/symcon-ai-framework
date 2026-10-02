# Interrupted calibration write recovery

Status: offline candidate; not a live deployment claim.

The collector publishes an immutable JSON file and a SHA-256 sidecar. Two
renames are not a single transaction: interruption after the JSON rename used
to leave a permanently incomplete pair.

## Contract

Before publishing either component, the collector atomically publishes a
`<target>.pending` intent containing the exact payload, basename, version and
digest. Under the existing collector semaphore, each target directory is checked
before capture or analysis. Recovery therefore also covers an older forecast
issue after the provider has advanced.

| Persisted state | Restart action |
| --- | --- |
| Intent only | Publish JSON and checksum |
| Intent and JSON | Verify JSON bytes; publish checksum |
| Intent and checksum | Verify checksum bytes; publish JSON |
| Intent and complete pair | Verify both; retire intent |
| Conflicting bytes or invalid intent | Stop without overwriting evidence |
| Incomplete legacy pair without intent | Retain existing fail-closed behavior |

The intent is removed only after final readback succeeds. Completed immutable
files are never recomputed or replaced. Snapshot schema, analysis identity,
batch limits, classification and calibration factors are unchanged.

## Bounds and limitations

Recovery accepts only collector-owned filenames and rejects symlinks or
non-file targets. It allows at most 16 pending intents per target per run,
4 MiB payloads and 8 MiB encoded intents. These generous corruption guards
bound replay work; they do not alter the normal analysis batch count.

Random temporary files left before a rename are ignored, not automatically
deleted. Legacy incomplete pairs require a separate evidence/recovery decision.
This is recovery from process/service interruption with persisted intent, not
a guarantee against filesystem corruption or power loss: no filesystem-wide
transaction or durable-media flush is claimed. The existing semaphore
serializes participating collectors, not arbitrary external filesystem writers.

The fault tests construct each persisted interruption state, retry publication,
and verify byte preservation, rejection of conflicting or malformed data and
retention of failed intents. They are not physical power-loss tests.

## Deployment gate

Prepare installation-specific source from its exact current readback rather
than replacing private adapters with the generic builder. Preserve effective
configuration, policy hashes, analysis version and limits. Source backup,
hash-guarded exchange, immediate readback and a natural scheduled-cycle check
remain separate authorized steps. Rollback must account for outstanding
intents: an older writer cannot complete them automatically. Do not remove
intents or evidence as part of source rollback.
