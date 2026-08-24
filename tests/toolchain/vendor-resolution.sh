#!/bin/sh

set -eu

repository_root=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd -P)
resolver=$repository_root/tools/resolve-composer-vendor-dir.sh
temporary_root=$(mktemp -d "${TMPDIR:-/tmp}/saef-toolchain-resolution.XXXXXX")
temporary_root=$(CDPATH= cd -- "$temporary_root" && pwd -P)

cleanup()
{
    rm -rf "$temporary_root"
}

trap cleanup EXIT HUP INT TERM

fixture_root=$temporary_root/repository
external_root=$temporary_root/external

create_toolchain()
{
    toolchain_root=$1
    mkdir -p "$toolchain_root/vendor/bin"
    cp "$repository_root/composer.lock" "$toolchain_root/composer.lock"

    for tool in phpstan phpcs; do
        printf '%s\n' '#!/bin/sh' 'exit 0' >"$toolchain_root/vendor/bin/$tool"
        chmod +x "$toolchain_root/vendor/bin/$tool"
    done
}

create_toolchain "$fixture_root"
create_toolchain "$external_root"

local_result=$(
    unset COMPOSER_VENDOR_DIR
    "$resolver" "$fixture_root"
)

if [ "$local_result" != "$fixture_root/vendor" ]; then
    printf 'Local vendor resolution mismatch: %s\n' "$local_result" >&2
    exit 1
fi

external_result=$(
    COMPOSER_VENDOR_DIR=$external_root/vendor \
        "$resolver" "$fixture_root"
)

if [ "$external_result" != "$external_root/vendor" ]; then
    printf 'External vendor resolution mismatch: %s\n' "$external_result" >&2
    exit 1
fi

relative_result=$(
    COMPOSER_VENDOR_DIR=../external/vendor \
        "$resolver" "$fixture_root"
)

if [ "$relative_result" != "$external_root/vendor" ]; then
    printf 'Relative vendor resolution mismatch: %s\n' "$relative_result" >&2
    exit 1
fi

invalid_output=$temporary_root/invalid-output.txt

if COMPOSER_VENDOR_DIR=missing/vendor \
    "$resolver" "$fixture_root" >"$invalid_output" 2>&1; then
    printf '%s\n' 'Invalid vendor directory was accepted.' >&2
    exit 1
fi

expected_error="SAEF Composer toolchain error: vendor directory does not exist: $fixture_root/missing/vendor"

if [ "$(cat "$invalid_output")" != "$expected_error" ]; then
    printf '%s\n' 'Invalid vendor directory error was not deterministic.' >&2
    cat "$invalid_output" >&2
    exit 1
fi

lock_output=$temporary_root/lock-output.txt
printf '%s\n' 'different lock' >"$external_root/composer.lock"

if COMPOSER_VENDOR_DIR=$external_root/vendor \
    "$resolver" "$fixture_root" >"$lock_output" 2>&1; then
    printf '%s\n' 'Lock-mismatched toolchain was accepted.' >&2
    exit 1
fi

expected_lock_error='SAEF Composer toolchain error: repository and toolchain lock files differ.'

if [ "$(cat "$lock_output")" != "$expected_lock_error" ]; then
    printf '%s\n' 'Lock mismatch error was not deterministic.' >&2
    cat "$lock_output" >&2
    exit 1
fi

cp "$repository_root/composer.lock" "$external_root/composer.lock"
rm "$external_root/vendor/bin/phpcs"
tool_output=$temporary_root/tool-output.txt

if COMPOSER_VENDOR_DIR=$external_root/vendor \
    "$resolver" "$fixture_root" >"$tool_output" 2>&1; then
    printf '%s\n' 'Incomplete toolchain was accepted.' >&2
    exit 1
fi

expected_tool_error="SAEF Composer toolchain error: required executable is missing: $external_root/vendor/bin/phpcs"

if [ "$(cat "$tool_output")" != "$expected_tool_error" ]; then
    printf '%s\n' 'Missing executable error was not deterministic.' >&2
    cat "$tool_output" >&2
    exit 1
fi

printf '%s\n' 'Composer vendor resolution tests passed.'
