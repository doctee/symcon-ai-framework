#!/bin/sh

set -eu

if [ "$#" -ne 1 ]; then
    printf '%s\n' \
        'SAEF Composer toolchain error: expected exactly one repository root.' \
        >&2
    exit 64
fi

if ! repository_root=$(CDPATH= cd -- "$1" 2>/dev/null && pwd -P); then
    printf 'SAEF Composer toolchain error: repository root is invalid: %s\n' \
        "$1" >&2
    exit 1
fi

configured_vendor_dir=${COMPOSER_VENDOR_DIR:-vendor}

case "$configured_vendor_dir" in
    /*)
        vendor_candidate=$configured_vendor_dir
        ;;
    *)
        vendor_candidate=$repository_root/$configured_vendor_dir
        ;;
esac

if [ ! -d "$vendor_candidate" ]; then
    printf 'SAEF Composer toolchain error: vendor directory does not exist: %s\n' \
        "$vendor_candidate" >&2
    exit 1
fi

if ! vendor_dir=$(CDPATH= cd -- "$vendor_candidate" 2>/dev/null && pwd -P); then
    printf 'SAEF Composer toolchain error: vendor directory is not accessible: %s\n' \
        "$vendor_candidate" >&2
    exit 1
fi

for tool in phpstan phpcs; do
    if [ ! -x "$vendor_dir/bin/$tool" ]; then
        printf 'SAEF Composer toolchain error: required executable is missing: %s\n' \
            "$vendor_dir/bin/$tool" >&2
        exit 1
    fi
done

repository_lock=$repository_root/composer.lock
toolchain_lock=$(dirname "$vendor_dir")/composer.lock

if [ ! -f "$repository_lock" ]; then
    printf 'SAEF Composer toolchain error: repository lock file is missing: %s\n' \
        "$repository_lock" >&2
    exit 1
fi

if [ ! -f "$toolchain_lock" ]; then
    printf 'SAEF Composer toolchain error: toolchain lock file is missing: %s\n' \
        "$toolchain_lock" >&2
    exit 1
fi

if ! cmp -s "$repository_lock" "$toolchain_lock"; then
    printf '%s\n' \
        'SAEF Composer toolchain error: repository and toolchain lock files differ.' \
        >&2
    exit 1
fi

printf '%s\n' "$vendor_dir"
