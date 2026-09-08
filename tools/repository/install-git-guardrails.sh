#!/bin/sh

set -eu

script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd -P)
. "$script_dir/common.sh"

if [ "$#" -ne 0 ]; then
    printf '%s\n' 'Usage: tools/repository/install-git-guardrails.sh' >&2
    exit 64
fi

repository_root=$(saef_repository_current_root) || exit 1
primary_checkout=$(saef_repository_primary_checkout "$repository_root") || exit 1

if [ "$repository_root" != "$primary_checkout" ]; then
    saef_repository_error 'install-git-guardrails must run from the primary checkout.'
    exit 1
fi

saef_assert_primary_checkout_aligned "$primary_checkout" || exit 1
hooks_path=$primary_checkout/.githooks

for hook_name in pre-commit pre-push; do
    hook_path=$hooks_path/$hook_name

    if [ ! -f "$hook_path" ] || [ -L "$hook_path" ] || [ ! -x "$hook_path" ]; then
        saef_repository_error "hook must be an executable regular file: $hook_path"
        exit 1
    fi
done

git -C "$primary_checkout" config --local core.hooksPath "$hooks_path"
configured_path=$(git -C "$primary_checkout" config --local --get core.hooksPath)

if [ "$configured_path" != "$hooks_path" ]; then
    saef_repository_error 'the configured hooks path differs from the requested path.'
    exit 1
fi

printf 'SAEF Git guardrails installed: %s\n' "$hooks_path"
