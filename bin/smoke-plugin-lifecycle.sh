#!/bin/bash
# End-to-end smoke for the plugin lifecycle CLI commands.
#
# Constructs a throwaway scriptor-plugin in a tmp dir, registers it
# as a path-repo against a tmp Scriptor sandbox, exercises the four
# CLI commands (list / install / uninstall / cleanup-orphan), and
# verifies both the state file and the field table after each step.
#
# No HTTP server, no editor. Runs against bin/scriptor in isolation
# and cleans up its tmp directory on exit.

set -e
ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
PHP=${PHP:-/Applications/ServBay/bin/php}
COMPOSER=${COMPOSER:-/Applications/ServBay/bin/composer}
SANDBOX=$(mktemp -d -t scriptor-plugin-lifecycle-XXXX)
PLUGIN_DIR="$SANDBOX/test-plugin"
SITE_DIR="$SANDBOX/site"
FAIL=0
trap 'rm -rf "$SANDBOX"' EXIT

pass() { printf "  ok   %s\n" "$1"; }
fail() { printf "  FAIL %s\n" "$1"; FAIL=$((FAIL+1)); }
section() { printf "\n=== %s ===\n" "$1"; }

section "Bootstrap sandbox at $SANDBOX"

# Create the test plugin package.
mkdir -p "$PLUGIN_DIR/src"
cat > "$PLUGIN_DIR/composer.json" <<'EOF'
{
    "name": "scriptor-test/lifecycle-smoke",
    "type": "scriptor-plugin",
    "license": "MIT",
    "autoload": {"psr-4": {"ScriptorTest\\LifecycleSmoke\\": "src/"}},
    "extra": {"scriptor": {"plugin": "ScriptorTest\\LifecycleSmoke\\Plugin"}}
}
EOF
cat > "$PLUGIN_DIR/src/Plugin.php" <<'EOF'
<?php
namespace ScriptorTest\LifecycleSmoke;
use Imanager\Domain\Field;
use Imanager\Storage\CategoryRepository;
use Imanager\Storage\FieldRepository;
use Scriptor\Boot\Plugin\LifecyclePlugin;
use Scriptor\Boot\Plugin\PluginContext;

final class Plugin implements LifecyclePlugin
{
    public function name(): string    { return 'scriptor-test/lifecycle-smoke'; }
    public function version(): string { return '0.0.1'; }
    public function register(PluginContext $context): void {}
    public function install(PluginContext $context): void
    {
        $cats   = $context->container()->get(CategoryRepository::class);
        $fields = $context->container()->get(FieldRepository::class);
        $pages  = $cats->findBySlug('pages');
        if ($pages === null || $pages->id === null) return;
        $fields->ensure(Field::text($pages->id, 'smoke_field', 'Smoke Field')->position(99));
    }
    public function uninstall(PluginContext $context): void
    {
        $cats   = $context->container()->get(CategoryRepository::class);
        $fields = $context->container()->get(FieldRepository::class);
        $pages  = $cats->findBySlug('pages');
        if ($pages === null || $pages->id === null) return;
        $field = $fields->findByName($pages->id, 'smoke_field');
        if ($field !== null && $field->id !== null) {
            $fields->delete($field->id);
        }
    }
}
EOF
pass "test plugin created at $PLUGIN_DIR"

# Set up a copy of the Scriptor site with the test plugin as a path repo.
cp -a "$ROOT" "$SITE_DIR"
rm -rf "$SITE_DIR/data/imanager.db"* "$SITE_DIR/data/plugin-states.json" "$SITE_DIR/data/cache" 2>/dev/null || true

# Patch composer.json to include the test plugin via path repo.
"$PHP" -r '
$path = $argv[1];
$pluginPath = $argv[2];
$c = json_decode(file_get_contents($path), true);
$c["repositories"][] = ["type" => "path", "url" => $pluginPath, "options" => ["symlink" => false]];
$c["require"]["scriptor-test/lifecycle-smoke"] = "@dev";
$c["minimum-stability"] = "dev";
$c["prefer-stable"] = true;
file_put_contents($path, json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
' "$SITE_DIR/composer.json" "$PLUGIN_DIR"
# `composer update` (not install) because we just edited composer.json
# to require a new path-repo package; the lockfile would be stale.
( cd "$SITE_DIR" && "$PHP" "$COMPOSER" update scriptor-test/lifecycle-smoke --no-interaction --quiet ) || fail "composer install"
[ -d "$SITE_DIR/vendor/scriptor-test/lifecycle-smoke" ] && pass "test plugin in vendor/" || fail "test plugin not in vendor/"

# Seed the iManager DB (Pages category + admin user).
SCRIPTOR_ADMIN_PASSWORD='gT5nLazzyBob' "$PHP" "$SITE_DIR/bin/scriptor" install --yes >/dev/null
[ -f "$SITE_DIR/data/imanager.db" ] && pass "iManager DB seeded" || fail "DB seed"

section "plugin:list (pending install)"
OUT=$("$PHP" "$SITE_DIR/bin/scriptor" plugin:list 2>&1)
echo "$OUT" | grep -q 'scriptor-test/lifecycle-smoke' && pass "test plugin listed" || fail "missing from list"
echo "$OUT" | grep -q 'pending install' && pass "kind=lifecycle status=pending install" || fail "missing pending install status"

section "plugin:install"
"$PHP" "$SITE_DIR/bin/scriptor" plugin:install scriptor-test/lifecycle-smoke >/dev/null
[ "$?" = "0" ] && pass "install exit 0" || fail "install non-zero exit"
[ -f "$SITE_DIR/data/plugin-states.json" ] && pass "state.json created" || fail "state.json missing"
grep -q 'scriptor-test/lifecycle-smoke' "$SITE_DIR/data/plugin-states.json" && pass "state.json has entry" || fail "state.json missing entry"

# Verify the field was registered.
"$PHP" -r '
$pdo = new PDO("sqlite:'"$SITE_DIR"'/data/imanager.db");
$row = $pdo->query("SELECT id FROM fields WHERE name = \"smoke_field\"")->fetchColumn();
echo $row === false ? "MISSING" : "FOUND ($row)";
' | grep -q FOUND && pass "smoke_field registered in fields table" || fail "smoke_field missing"

section "plugin:install (already installed)"
OUT=$("$PHP" "$SITE_DIR/bin/scriptor" plugin:install scriptor-test/lifecycle-smoke 2>&1 || true)
echo "$OUT" | grep -q 'already installed' && pass "refuses double-install" || fail "should have refused"

section "plugin:list (installed)"
OUT=$("$PHP" "$SITE_DIR/bin/scriptor" plugin:list 2>&1)
echo "$OUT" | grep -q 'installed' && pass "status now installed" || fail "still pending"

section "plugin:uninstall"
"$PHP" "$SITE_DIR/bin/scriptor" plugin:uninstall scriptor-test/lifecycle-smoke >/dev/null
[ "$?" = "0" ] && pass "uninstall exit 0" || fail "uninstall non-zero"
grep -q 'scriptor-test/lifecycle-smoke' "$SITE_DIR/data/plugin-states.json" && fail "state entry still there" || pass "state entry removed"

# Verify the field was removed.
"$PHP" -r '
$pdo = new PDO("sqlite:'"$SITE_DIR"'/data/imanager.db");
$row = $pdo->query("SELECT id FROM fields WHERE name = \"smoke_field\"")->fetchColumn();
echo $row === false ? "GONE" : "STILL THERE ($row)";
' | grep -q GONE && pass "smoke_field removed from fields table" || fail "smoke_field still there"

section "plugin:uninstall (not installed)"
OUT=$("$PHP" "$SITE_DIR/bin/scriptor" plugin:uninstall scriptor-test/lifecycle-smoke 2>&1 || true)
echo "$OUT" | grep -q 'not marked installed' && pass "refuses uninstall when not installed" || fail "should have refused"

section "Orphan path"
# Re-install, then composer-remove without plugin:uninstall first.
"$PHP" "$SITE_DIR/bin/scriptor" plugin:install scriptor-test/lifecycle-smoke >/dev/null
( cd "$SITE_DIR" && "$PHP" "$COMPOSER" remove scriptor-test/lifecycle-smoke --no-interaction --quiet ) || fail "composer remove"
OUT=$("$PHP" "$SITE_DIR/bin/scriptor" plugin:list 2>&1)
echo "$OUT" | grep -q 'ORPHAN' && pass "orphan detected by plugin:list" || fail "no ORPHAN row"
OUT=$("$PHP" "$SITE_DIR/bin/scriptor" plugin:cleanup-orphan scriptor-test/lifecycle-smoke 2>&1)
echo "$OUT" | grep -q 'Cleared orphan' && pass "cleanup-orphan removed state" || fail "cleanup-orphan failed"
grep -q 'scriptor-test/lifecycle-smoke' "$SITE_DIR/data/plugin-states.json" 2>/dev/null && fail "state still there after cleanup" || pass "state cleaned"

echo ""
if [ "$FAIL" = "0" ]; then
    echo "ALL PLUGIN-LIFECYCLE SMOKE CHECKS PASS"
else
    echo "FAILURES: $FAIL"
    exit 1
fi
