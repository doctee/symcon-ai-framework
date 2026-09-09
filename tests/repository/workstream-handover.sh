#!/bin/sh

set -eu

repository_root=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd -P)
checker=$repository_root/tools/repository/check-workstream-handover.sh
temporary_root=$(mktemp -d "${TMPDIR:-/tmp}/saef-workstream-handover.XXXXXX")
temporary_root=$(CDPATH= cd -- "$temporary_root" && pwd -P)

cleanup()
{
    rm -rf "$temporary_root"
}

trap cleanup EXIT HUP INT TERM

origin=$temporary_root/origin.git
primary=$temporary_root/primary
feature=$primary/private/worktrees/sample
handover=$primary/private/workstreams/sample

git init --bare "$origin" >/dev/null 2>&1
git clone "$origin" "$primary" >/dev/null 2>&1
git -C "$primary" config user.name 'SAEF Test'
git -C "$primary" config user.email 'saef-test@example.invalid'
printf '%s\n' 'private/' >"$primary/.gitignore"
printf '%s\n' 'fixture' >"$primary/README.md"
git -C "$primary" add .gitignore README.md
git -C "$primary" commit -m 'test: initialize repository' >/dev/null
git -C "$primary" branch -M main
git -C "$primary" push -u origin main >/dev/null 2>&1
git --git-dir="$origin" symbolic-ref HEAD refs/heads/main
git -C "$primary" worktree add -b codex/sample "$feature" origin/main >/dev/null
mkdir -p "$handover"

base_commit=$(git -C "$feature" rev-parse HEAD)
head_commit=$base_commit

write_record()
{
    record_head=$1
    record_clean=$2
    extra_field=$3

    cat >"$handover/workstream.local.json" <<EOF
{
  "formatVersion": 1,
  "updatedAtUtc": "2026-09-08T12:00:00Z",
  "workstream": "sample",
  "status": "ready_for_handover",
  "branch": "codex/sample",
  "worktree": "private/worktrees/sample",
  "baseCommit": "$base_commit",
  "headCommit": "$record_head",
  "worktreeClean": $record_clean,
  "publicScope": "Repository-only handover fixture.",
  "privateScope": "none",
  "authorization": {
    "commit": false,
    "push": false,
    "pullRequest": false,
    "merge": false,
    "liveSymcon": false,
    "serviceRestart": false,
    "retentionCleanup": false
  },
  "verification": ["focused fixture passed"],
  "openGates": ["repository integration"],
  "rollbackAndRetention": ["retain until merge"],
  "nextAction": "Review the repository-only fixture."$extra_field
}
EOF
}

write_markdown()
{
    markdown_head=$1
    markdown_clean=$2

    cat >"$handover/HANDOVER.local.md" <<EOF
# SAEF Workstream Handover

<!-- SAEF_WORKSTREAM_HANDOVER_V1 -->

## Identity

- Workstream: \`sample\`
- Status: \`ready_for_handover\`
- Branch: \`codex/sample\`
- Worktree: \`private/worktrees/sample\`
- Base commit: \`$base_commit\`
- Head commit: \`$markdown_head\`
- Worktree clean: \`$markdown_clean\`
- Authoritative record: \`workstream.local.json\`
- Source task: \`fixture source\`
- Destination task: \`fixture destination\`

## Scope

Validate a repository-only handover.

## Repository State

The fixture branch points to the recorded commit.

## Verification

The focused fixture is expected to pass.

## Authorization Gates

All mutation gates remain closed.

## Rollback And Retention

Retain the fixture until the test exits.

## Next Action

Validate the fixture without mutation.
EOF
}

assert_rejected()
{
    label=$1
    if (CDPATH= cd -- "$feature" && "$checker" sample) >/dev/null 2>&1; then
        printf 'Invalid handover was accepted: %s\n' "$label" >&2
        exit 1
    fi
}

write_record "$head_commit" true ''
write_markdown "$head_commit" true
(CDPATH= cd -- "$feature" && "$checker" sample) >/dev/null

write_record 0000000000000000000000000000000000000000 true ''
assert_rejected 'stale head commit'

write_record "$head_commit" true ''
write_markdown 1111111111111111111111111111111111111111 true
assert_rejected 'Markdown and record mismatch'

write_markdown "$head_commit" true
grep -v '^- Destination task:' "$handover/HANDOVER.local.md" \
    >"$handover/HANDOVER.local.md.tmp"
mv "$handover/HANDOVER.local.md.tmp" "$handover/HANDOVER.local.md"
assert_rejected 'missing destination task'

write_markdown "$head_commit" true
write_record "$head_commit" true ', "unknown": true'
assert_rejected 'unknown record field'

write_record "$head_commit" true ''
printf '%s\n' 'dirty' >"$feature/dirty.txt"
assert_rejected 'unrecorded dirty state'

write_record "$head_commit" false ''
write_markdown "$head_commit" false
(CDPATH= cd -- "$feature" && "$checker" sample) >/dev/null

rm "$feature/dirty.txt"
write_record "$head_commit" true ''
write_markdown "$head_commit" true

if (CDPATH= cd -- "$feature" && "$checker" 'Invalid_Name') >/dev/null 2>&1; then
    printf '%s\n' 'Invalid workstream name was accepted.' >&2
    exit 1
fi

rm "$handover/HANDOVER.local.md"
ln -s "$handover/workstream.local.json" "$handover/HANDOVER.local.md"
assert_rejected 'symbolic-link Markdown'

printf '%s\n' 'Workstream handover tests passed.'
