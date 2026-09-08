#!/bin/sh

set -eu

script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd -P)
. "$script_dir/common.sh"

fetch_origin=true

if [ "$#" -eq 1 ] && [ "$1" = '--no-fetch' ]; then
    fetch_origin=false
    shift
fi

if [ "$#" -ne 0 ]; then
    printf '%s\n' \
        'Usage: tools/repository/sync-primary-checkout.sh [--no-fetch]' >&2
    exit 64
fi

repository_root=$(saef_repository_current_root) || exit 1
primary_checkout=$(saef_repository_primary_checkout "$repository_root") || exit 1

if [ "$repository_root" != "$primary_checkout" ]; then
    saef_repository_error 'sync-primary-checkout must be run from the primary checkout.'
    exit 1
fi

saef_assert_primary_checkout_shape "$primary_checkout" || exit 1

if [ "$fetch_origin" = true ]; then
    git -C "$primary_checkout" fetch origin
fi

if ! git -C "$primary_checkout" merge-base --is-ancestor \
    refs/heads/main refs/remotes/origin/main; then
    saef_divergence=$(
        git -C "$primary_checkout" rev-list --left-right --count \
            refs/heads/main...refs/remotes/origin/main
    ) || saef_divergence='unknown'
    saef_repository_error \
        "main cannot be fast-forwarded (local/remote counts: $saef_divergence)."
    exit 1
fi

git -C "$primary_checkout" merge --ff-only refs/remotes/origin/main
saef_assert_primary_checkout_aligned "$primary_checkout" || exit 1

main_sha=$(git -C "$primary_checkout" rev-parse refs/heads/main)
printf 'SAEF primary checkout synchronized: %s\n' "$main_sha"
