#!/bin/sh

set -eu

script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd -P)

exec php "$script_dir/check-workstream-handover.php" "$@"
