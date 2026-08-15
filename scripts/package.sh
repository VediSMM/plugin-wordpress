#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_SLUG="vedismm"
BUILD_DIR="$(mktemp -d "${TMPDIR:-/tmp}/vedismm-wp-package.XXXXXX")"
STAGE_DIR="$BUILD_DIR/$PLUGIN_SLUG"
DIST_DIR="$ROOT/dist"
ZIP_PATH="$DIST_DIR/$PLUGIN_SLUG.zip"
TMP_ZIP="$BUILD_DIR/$PLUGIN_SLUG.zip"

trap 'rm -rf "$BUILD_DIR"' EXIT

required_files=(
  "vedismm.php"
  "uninstall.php"
  "readme.txt"
  "languages/vedismm.pot"
  "languages/vedismm-ru_RU.po"
  "docs/en/guide.md"
  "docs/ru/guide.md"
  "marketplace/en/listing.md"
  "marketplace/ru/listing.md"
  "docker-compose.yml"
  "tests/fake-api/router.php"
  "tests/smoke.sh"
  ".github/workflows/ci.yml"
)

fail() {
  printf 'package check failed: %s\n' "$1" >&2
  exit 1
}

for file in "${required_files[@]}"; do
  [[ -f "$ROOT/$file" ]] || fail "missing $file"
done

grep -q 'Plugin Name: VediSMM' "$ROOT/vedismm.php" || fail "missing WordPress plugin header"
grep -q 'Text Domain: vedismm' "$ROOT/vedismm.php" || fail "missing text domain header"
grep -q 'Stable tag: 1.1.0' "$ROOT/readme.txt" || fail "missing readme stable tag"
grep -q 'VediSMM account' "$ROOT/readme.txt" || fail "missing external account disclosure"

rm -f "$ZIP_PATH"
mkdir -p "$STAGE_DIR/vendor" "$DIST_DIR"
cp "$ROOT/vedismm.php" "$STAGE_DIR/vedismm.php"
cp "$ROOT/uninstall.php" "$STAGE_DIR/uninstall.php"
cp "$ROOT/readme.txt" "$STAGE_DIR/readme.txt"
cp -R "$ROOT/src" "$STAGE_DIR/src"
cp -R "$ROOT/languages" "$STAGE_DIR/languages"

cat > "$STAGE_DIR/vendor/autoload.php" <<'PHP'
<?php
spl_autoload_register(static function (string $class): void {
    $prefix = 'VediSMM\\WordPress\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $path = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});
PHP

(
  cd "$BUILD_DIR"
  find "$PLUGIN_SLUG" -type f | sort | zip -X -q "$TMP_ZIP" -@
)

entries="$(zipinfo -1 "$TMP_ZIP")"
printf '%s\n' "$entries" | grep -q '^vedismm/vedismm.php$' || fail "archive misses plugin file"
printf '%s\n' "$entries" | grep -q '^vedismm/vendor/autoload.php$' || fail "archive misses vendor autoload"
printf '%s\n' "$entries" | grep -q '^vedismm/languages/vedismm.pot$' || fail "archive misses POT"
printf '%s\n' "$entries" | grep -q '^vedismm/languages/vedismm-ru_RU.po$' || fail "archive misses Russian PO"
printf '%s\n' "$entries" | grep -q '^vedismm/tests/' && fail "archive contains tests"
printf '%s\n' "$entries" | grep -q '^vedismm/composer.json$' && fail "archive contains composer.json"
printf '%s\n' "$entries" | grep -q '^vedismm/.git' && fail "archive contains git metadata"

mv "$TMP_ZIP" "$ZIP_PATH"
printf 'Built %s\n' "$ZIP_PATH"
