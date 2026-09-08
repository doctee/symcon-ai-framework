#!/bin/sh

set -eu

script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd -P)
. "$script_dir/common.sh"

if [ "$#" -ne 0 ]; then
    printf '%s\n' 'Usage: tools/repository/check-primary-checkout.sh' >&2
    exit 64
fi

repository_root=$(saef_repository_current_root) || exit 1
primary_checkout=$(saef_repository_primary_checkout "$repository_root") || exit 1
saef_assert_primary_checkout_aligned "$primary_checkout" || exit 1
main_sha=$(git -C "$primary_checkout" rev-parse refs/heads/main)

printf '%s\n' 'SAEF primary checkout: aligned'
printf 'path=%s\n' "$primary_checkout"
printf 'main=%s\n' "$main_sha"
printf 'origin/main=%s\n' "$main_sha"
