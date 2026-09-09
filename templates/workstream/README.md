# Workstream Handover Template

This directory defines the public, installation-neutral template for a private
SAEF workstream handover.

## Purpose

Codex task lists and generated task summaries are useful for navigation, but
they are not an authoritative engineering record. A cross-task transfer uses
two files below the primary checkout's ignored private overlay:

```text
private/workstreams/<workstream>/
|-- workstream.local.json
`-- HANDOVER.local.md
```

`workstream.local.json` is the machine-readable source of truth.
`HANDOVER.local.md` explains that state for the receiving human or AI task.
The receiving task verifies both files and the referenced Git worktree before
continuing.

## Starting A Handover

1. Copy `WORKSTREAM_RECORD.example.json` to the private
   `workstream.local.json` path.
2. Copy `HANDOVER.md` to the private `HANDOVER.local.md` path.
3. Replace every `{{PLACEHOLDER}}` and update both files from current evidence.
4. Run the checker from any worktree of the same repository:

   ```sh
   tools/repository/check-workstream-handover.sh <workstream>
   ```

5. Send the receiving task only the canonical private handover path and a
   concise reason for the transfer.

The checker is read-only. It does not fetch, modify Git state, grant an
authorization or prove a live-system condition. The receiving task must still
refresh any remote or live evidence required by its next gate.

## Privacy

The templates contain no private installation values. Actual task identifiers,
local paths, ObjectIDs, hostnames, topics, rollback locations and live evidence
belong only in the ignored private copies. Do not commit completed handovers.
