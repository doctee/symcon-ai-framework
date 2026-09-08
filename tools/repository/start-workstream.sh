#!/bin/sh

set -eu

script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd -P)
. "$script_dir/common.sh"

fetch_origin=true

if [ "$#" -eq 2 ] && [ "$1" = '--no-fetch' ]; then
    fetch_origin=false
    shift
fi

if [ "$#" -ne 1 ]; then
    printf '%s\n' \
        'Usage: tools/repository/start-workstream.sh [--no-fetch] <name>' >&2
    exit 64
fi

workstream_name=$1

case "$workstream_name" in
    [a-z0-9]|[a-z0-9]*[a-z0-9])
        ;;
    *)
        saef_repository_error \
            'workstream name must start and end with a lowercase letter or digit.'
        exit 64
        ;;
esac

case "$workstream_name" in
    *[!a-z0-9-]*)
        saef_repository_error \
            'workstream name may contain only lowercase letters, digits and hyphens.'
        exit 64
        ;;
esac

repository_root=$(saef_repository_current_root) || exit 1
primary_checkout=$(saef_repository_primary_checkout "$repository_root") || exit 1

if [ "$repository_root" != "$primary_checkout" ]; then
    saef_repository_error 'start-workstream must be run from the primary checkout.'
    exit 1
fi

if [ "$fetch_origin" = true ]; then
    git -C "$primary_checkout" fetch origin
fi

saef_assert_primary_checkout_aligned "$primary_checkout" || exit 1

if ! git -C "$primary_checkout" check-ignore --quiet \
    private/worktrees/.saef-worktree-probe; then
    saef_repository_error 'private/worktrees is not excluded from version control.'
    exit 1
fi

branch_name=codex/$workstream_name
worktree_path=$primary_checkout/private/worktrees/$workstream_name

if git -C "$primary_checkout" show-ref --verify --quiet \
    "refs/heads/$branch_name"; then
    saef_repository_error "branch already exists: $branch_name"
    exit 1
fi

if [ -e "$worktree_path" ] || [ -L "$worktree_path" ]; then
    saef_repository_error "worktree path already exists: $worktree_path"
    exit 1
fi

git -C "$primary_checkout" worktree add \
    -b "$branch_name" \
    "$worktree_path" \
    refs/remotes/origin/main

created_sha=$(git -C "$worktree_path" rev-parse HEAD)
origin_sha=$(git -C "$primary_checkout" rev-parse refs/remotes/origin/main)
created_status=$(git -C "$worktree_path" status --porcelain=v1)

if [ "$created_sha" != "$origin_sha" ] || [ -n "$created_status" ]; then
    saef_repository_error 'created worktree failed its clean-baseline postflight.'
    exit 1
fi

printf '%s\n' 'SAEF workstream created'
printf 'branch=%s\n' "$branch_name"
printf 'worktree=%s\n' "$worktree_path"
printf 'base=%s\n' "$created_sha"
