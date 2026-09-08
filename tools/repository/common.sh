#!/bin/sh

saef_repository_error()
{
    printf 'SAEF repository error: %s\n' "$1" >&2
}

saef_repository_current_root()
{
    saef_current_root=$(git rev-parse --show-toplevel 2>/dev/null) || {
        saef_repository_error 'the current directory is not inside a Git worktree.'
        return 1
    }

    CDPATH= cd -- "$saef_current_root" 2>/dev/null && pwd -P
}

saef_repository_primary_checkout()
{
    saef_repository_root=$1
    saef_worktree_list=$(git -C "$saef_repository_root" worktree list --porcelain) || {
        saef_repository_error 'cannot enumerate Git worktrees.'
        return 1
    }
    saef_primary_path=$(printf '%s\n' "$saef_worktree_list" | sed -n '1s/^worktree //p')

    if [ -z "$saef_primary_path" ]; then
        saef_repository_error 'the primary checkout cannot be resolved.'
        return 1
    fi

    CDPATH= cd -- "$saef_primary_path" 2>/dev/null && pwd -P
}

saef_assert_primary_checkout_shape()
{
    saef_primary_checkout=$1

    if ! saef_primary_branch=$(
        git -C "$saef_primary_checkout" symbolic-ref --quiet --short HEAD 2>/dev/null
    ); then
        saef_repository_error 'the primary checkout must have branch main checked out.'
        return 1
    fi

    if [ "$saef_primary_branch" != 'main' ]; then
        saef_repository_error "the primary checkout is on $saef_primary_branch, expected main."
        return 1
    fi

    saef_primary_status=$(
        git -C "$saef_primary_checkout" status --porcelain=v1 --untracked-files=normal
    ) || {
        saef_repository_error 'the primary checkout status cannot be read.'
        return 1
    }

    if [ -n "$saef_primary_status" ]; then
        saef_repository_error 'the primary checkout is not clean.'
        return 1
    fi

    if ! git -C "$saef_primary_checkout" show-ref --verify --quiet refs/heads/main; then
        saef_repository_error 'local branch main is missing.'
        return 1
    fi

    if ! git -C "$saef_primary_checkout" \
        show-ref --verify --quiet refs/remotes/origin/main; then
        saef_repository_error 'tracking reference origin/main is missing.'
        return 1
    fi
}

saef_assert_primary_checkout_aligned()
{
    saef_primary_checkout=$1
    saef_assert_primary_checkout_shape "$saef_primary_checkout" || return 1

    saef_local_main=$(
        git -C "$saef_primary_checkout" rev-parse refs/heads/main
    ) || return 1
    saef_origin_main=$(
        git -C "$saef_primary_checkout" rev-parse refs/remotes/origin/main
    ) || return 1

    if [ "$saef_local_main" != "$saef_origin_main" ]; then
        saef_divergence=$(
            git -C "$saef_primary_checkout" rev-list --left-right --count \
                refs/heads/main...refs/remotes/origin/main
        ) || saef_divergence='unknown'
        saef_repository_error \
            "main differs from origin/main (local/remote counts: $saef_divergence)."
        return 1
    fi
}
