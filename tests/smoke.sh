#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

bash scripts/package.sh --check >/dev/null
php -l tests/fake-api/router.php >/dev/null

if command -v docker >/dev/null 2>&1; then
  docker compose config >/dev/null
fi

test -f dist/vedismm.zip
zipinfo -1 dist/vedismm.zip | grep -q '^vedismm/vendor/autoload.php$'
printf 'WordPress smoke checks passed.\n'
