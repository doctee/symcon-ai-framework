#!/bin/sh

set -eu

repository_root=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd -P)
check_script=$repository_root/tools/repository/check-primary-checkout.sh
start_script=$repository_root/tools/repository/start-workstream.sh
sync_script=$repository_root/tools/repository/sync-primary-checkout.sh
install_script=$repository_root/tools/repository/install-git-guardrails.sh
pre_commit_hook=$repository_root/.githooks/pre-commit
pre_push_hook=$repository_root/.githooks/pre-push
temporary_root=$(mktemp -d "${TMPDIR:-/tmp}/saef-checkout-guardrails.XXXXXX")
temporary_root=$(CDPATH= cd -- "$temporary_root" && pwd -P)

cleanup()
{
    rm -rf "$temporary_root"
}

trap cleanup EXIT HUP INT TERM

origin=$temporary_root/origin.git
primary=$temporary_root/primary
updater=$temporary_root/updater

git init --bare "$origin" >/dev/null 2>&1
git clone "$origin" "$primary" >/dev/null 2>&1
git -C "$primary" config user.name 'SAEF Test'
git -C "$primary" config user.email 'saef-test@example.invalid'
printf '%s\n' 'private/' >"$primary/.gitignore"
printf '%s\n' 'fixture' >"$primary/README.md"
mkdir -p "$primary/.githooks"
cp "$pre_commit_hook" "$primary/.githooks/pre-commit"
cp "$pre_push_hook" "$primary/.githooks/pre-push"
chmod +x "$primary/.githooks/pre-commit" "$primary/.githooks/pre-push"
git -C "$primary" add .gitignore .githooks README.md
git -C "$primary" commit -m 'test: initialize repository' >/dev/null
git -C "$primary" branch -M main
git -C "$primary" push -u origin main >/dev/null 2>&1
git --git-dir="$origin" symbolic-ref HEAD refs/heads/main

(CDPATH= cd -- "$primary" && "$check_script") >/dev/null

(CDPATH= cd -- "$primary" && "$install_script") >/dev/null

configured_hooks=$(git -C "$primary" config --local --get core.hooksPath)

if [ "$configured_hooks" != "$primary/.githooks" ]; then
    printf '%s\n' 'Guardrail installer configured an unexpected hooks path.' >&2
    exit 1
fi

if git -C "$primary" commit --allow-empty -m 'test: prohibited main commit' \
    >/dev/null 2>&1; then
    printf '%s\n' 'Pre-commit hook accepted a commit on main.' >&2
    exit 1
fi

zero_sha=0000000000000000000000000000000000000000
test_sha=1111111111111111111111111111111111111111

printf 'refs/heads/test %s refs/heads/test %s\n' "$test_sha" "$zero_sha" |
    "$pre_push_hook"

if printf 'refs/heads/test %s refs/heads/main %s\n' \
    "$test_sha" "$zero_sha" | "$pre_push_hook" >/dev/null 2>&1; then
    printf '%s\n' 'Pre-push hook accepted a direct main update.' >&2
    exit 1
fi

(CDPATH= cd -- "$primary" && "$start_script" --no-fetch sample) >/dev/null

if [ "$(git -C "$primary/private/worktrees/sample" rev-parse HEAD)" != \
    "$(git -C "$primary" rev-parse refs/remotes/origin/main)" ]; then
    printf '%s\n' 'Created worktree does not use the origin/main baseline.' >&2
    exit 1
fi

if [ -n "$(git -C "$primary/private/worktrees/sample" status --porcelain=v1)" ]; then
    printf '%s\n' 'Created worktree is not clean.' >&2
    exit 1
fi

if (CDPATH= cd -- "$primary" && "$start_script" --no-fetch 'Invalid_Name') \
    >/dev/null 2>&1; then
    printf '%s\n' 'Invalid workstream name was accepted.' >&2
    exit 1
fi

git clone "$origin" "$updater" >/dev/null 2>&1
git -C "$updater" config user.name 'SAEF Test'
git -C "$updater" config user.email 'saef-test@example.invalid'
printf '%s\n' 'remote update' >"$updater/REMOTE.md"
git -C "$updater" add REMOTE.md
git -C "$updater" commit -m 'test: advance origin' >/dev/null
git -C "$updater" push origin main >/dev/null 2>&1
git -C "$primary" fetch origin >/dev/null 2>&1

if (CDPATH= cd -- "$primary" && "$check_script") >/dev/null 2>&1; then
    printf '%s\n' 'Behind primary checkout passed alignment check.' >&2
    exit 1
fi

if (CDPATH= cd -- "$primary" && "$start_script" --no-fetch blocked) \
    >/dev/null 2>&1; then
    printf '%s\n' 'Workstream creation accepted a behind primary checkout.' >&2
    exit 1
fi

(CDPATH= cd -- "$primary" && "$sync_script" --no-fetch) >/dev/null
(CDPATH= cd -- "$primary" && "$check_script") >/dev/null

printf '%s\n' 'dirty' >>"$primary/README.md"

if (CDPATH= cd -- "$primary" && "$check_script") >/dev/null 2>&1; then
    printf '%s\n' 'Dirty primary checkout passed alignment check.' >&2
    exit 1
fi

git -C "$primary" restore README.md
git -C "$primary" commit --allow-empty --no-verify -m 'test: local main drift' \
    >/dev/null

if (CDPATH= cd -- "$primary" && "$check_script") >/dev/null 2>&1; then
    printf '%s\n' 'Ahead primary checkout passed alignment check.' >&2
    exit 1
fi

if (CDPATH= cd -- "$primary" && "$sync_script" --no-fetch) >/dev/null 2>&1; then
    printf '%s\n' 'Sync accepted a non-fast-forward primary checkout.' >&2
    exit 1
fi

printf '%s\n' 'Repository checkout guardrail tests passed.'
