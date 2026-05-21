#!/usr/bin/env bash
#
# Regression smoke test for bin/scriptor install.
#
# Scriptor does not ship a PHPUnit setup today. This script gives us
# a re-runnable end-to-end check for the install command's contract:
# bad input rejected, valid input seeds the schema correctly, re-run
# is refused, and the seeded DB boots through Scriptor's request
# pipeline.
#
# Run from the repo root: bash bin/smoke-install.sh

set -uo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SCRIPTOR_BIN="$ROOT/bin/scriptor"
TMP_DB="$(mktemp -t scriptor-install-smoke.XXXXXX).db"
PHP_BIN="${PHP_BIN:-php}"
PASSED=0
FAILED=0

trap 'rm -f "$TMP_DB"' EXIT

case_pass() { printf '  \033[32mOK\033[0m   %s\n' "$1"; PASSED=$((PASSED+1)); }
case_fail() { printf '  \033[31mFAIL\033[0m %s\n' "$1"; FAILED=$((FAILED+1)); }

assert_exit() {
    local label="$1" expected="$2" actual="$3"
    if [ "$expected" = "$actual" ]; then
        case_pass "$label (exit $actual)"
    else
        case_fail "$label (expected exit $expected, got $actual)"
    fi
}

# 1. Too-short password rejected.
rm -f "$TMP_DB"
"$PHP_BIN" "$SCRIPTOR_BIN" install --password=short --db="$TMP_DB" --yes >/dev/null 2>&1
assert_exit "rejects password < 8 chars" 2 $?

# 2. Blacklisted password rejected.
"$PHP_BIN" "$SCRIPTOR_BIN" install --password=letmein12345 --db="$TMP_DB" --yes >/dev/null 2>&1
assert_exit "rejects blacklisted password (letmein12345)" 2 $?

# 3. Valid install succeeds.
"$PHP_BIN" "$SCRIPTOR_BIN" install --password=smoke-test-2026 --db="$TMP_DB" --yes >/dev/null 2>&1
assert_exit "valid install succeeds" 0 $?

# 4. DB structure matches expected shape.
CAT_COUNT=$(sqlite3 "$TMP_DB" "SELECT COUNT(*) FROM categories")
[ "$CAT_COUNT" = "2" ] && case_pass "creates 2 categories" || case_fail "expected 2 categories, got $CAT_COUNT"

FIELD_COUNT=$(sqlite3 "$TMP_DB" "SELECT COUNT(*) FROM fields")
[ "$FIELD_COUNT" = "10" ] && case_pass "creates 10 fields (7 pages + 3 users)" || case_fail "expected 10 fields, got $FIELD_COUNT"

ITEM_COUNT=$(sqlite3 "$TMP_DB" "SELECT COUNT(*) FROM items")
[ "$ITEM_COUNT" = "2" ] && case_pass "creates 2 items (admin user + Home page)" || case_fail "expected 2 items, got $ITEM_COUNT"

# 5. Re-run refused.
"$PHP_BIN" "$SCRIPTOR_BIN" install --password=smoke-test-2026 --db="$TMP_DB" --yes >/dev/null 2>&1
assert_exit "refuses re-run (Pages exists)" 1 $?

# 6. No-TTY without --yes refused.
echo "" | "$PHP_BIN" "$SCRIPTOR_BIN" install --password=smoke-test-2026 --db=/tmp/no-tty.db >/dev/null 2>&1
NO_TTY_EXIT=$?
rm -f /tmp/no-tty.db
[ "$NO_TTY_EXIT" = "4" ] && case_pass "refuses non-TTY without --yes" || case_fail "expected exit 4 (no TTY), got $NO_TTY_EXIT"

# 7. Password roundtrip: hash verifies via password_verify().
HASH=$(sqlite3 "$TMP_DB" "SELECT json_extract(data, '\$.password') FROM items WHERE name='admin'")
SCR_PASS=smoke-test-2026 SCR_HASH="$HASH" "$PHP_BIN" -r "exit(password_verify(getenv('SCR_PASS'), getenv('SCR_HASH')) ? 0 : 1);"
assert_exit "admin password verifies (good case)" 0 $?
SCR_PASS=wrong-password SCR_HASH="$HASH" "$PHP_BIN" -r "exit(password_verify(getenv('SCR_PASS'), getenv('SCR_HASH')) ? 0 : 1);"
assert_exit "wrong password rejected" 1 $?

# 8. PageRepository can boot against the seeded DB.
"$PHP_BIN" -d display_errors=stderr -r "
require '$ROOT/vendor/autoload.php';
\$c = Scriptor\Boot\ImanagerBootstrap::create('$ROOT', ['databasePath' => '$TMP_DB']);
\$repo = new Scriptor\Boot\Frontend\PageRepository(
    \$c->get(Imanager\Storage\CategoryRepository::class),
    \$c->get(Imanager\Storage\ItemRepository::class)
);
exit(\$repo->findBySlug('home') === null ? 1 : 0);
" 2>/dev/null
assert_exit "PageRepository::findBySlug(home) returns the seeded page" 0 $?

echo
printf 'Result: %d passed, %d failed\n' "$PASSED" "$FAILED"
[ "$FAILED" = "0" ] || exit 1
