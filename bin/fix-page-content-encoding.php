<?php

declare(strict_types=1);

/*
 * One-shot migration — strip accumulated HTML-entity encoding from
 * `data.content` of every Pages item.
 *
 * Background: PagesModule::saveAction() used to wrap the posted markdown
 * in `htmlentities()` before storing, while the textarea round-trip
 * re-escaped it on every render. Each save added one extra encoding
 * layer (`>` → `&gt;` → `&amp;gt;` → `&amp;amp;gt;` …). The save-time
 * encoding is gone in 2.0; this script normalises rows that were edited
 * one or more times under the buggy code.
 *
 * Algorithm: run `html_entity_decode()` repeatedly until the value
 * stops changing (capped at 10 iterations). That undoes any number of
 * stacked `htmlentities()` passes without touching content that was
 * already raw.
 *
 * Usage:
 *   php bin/fix-page-content-encoding.php
 *   php bin/fix-page-content-encoding.php --db=/path/to/other.db
 *   php bin/fix-page-content-encoding.php --dry-run
 */

require __DIR__ . '/../vendor/autoload.php';

use Imanager\Domain\Item;
use Imanager\Storage\Sqlite\ConnectionFactory;
use Imanager\Storage\Sqlite\SqliteStorage;

$dbPath = __DIR__ . '/../data/imanager.db';
$dryRun = false;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--db=')) {
        $dbPath = substr($arg, 5);
    } elseif ($arg === '--dry-run') {
        $dryRun = true;
    }
}
if (! is_file($dbPath)) {
    fwrite(STDERR, "Database not found: {$dbPath}\n");
    exit(1);
}

$pdo = (new ConnectionFactory($dbPath))->create();
$storage = new SqliteStorage($pdo);

$pages = $storage->categories()->findBySlug('pages');
if ($pages === null || $pages->id === null) {
    fwrite(STDERR, "No 'pages' category in {$dbPath}\n");
    exit(1);
}

$total    = $storage->items()->countByCategory($pages->id);
$batch    = 100;
$offset   = 0;
$touched  = 0;
$skipped  = 0;
$maxRound = 10;

echo "Scanning {$total} pages in {$dbPath}" . ($dryRun ? ' (dry-run)' : '') . "\n";
echo str_repeat('-', 70) . "\n";

while ($offset < $total) {
    $items = $storage->items()->findByCategory($pages->id, $offset, $batch);
    foreach ($items as $item) {
        $original = $item->data->get('content');
        if (! is_string($original) || $original === '') {
            $skipped++;
            continue;
        }

        $decoded = $original;
        $rounds  = 0;
        while ($rounds < $maxRound) {
            $next = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($next === $decoded) {
                break;
            }
            $decoded = $next;
            $rounds++;
        }

        if ($decoded === $original) {
            $skipped++;
            continue;
        }

        printf("  #%-5d %-40s  -%d layer(s)\n", $item->id ?? 0, mb_strimwidth((string) $item->name, 0, 40, '…'), $rounds);

        if (! $dryRun) {
            $newData = $item->data->with('content', $decoded);
            $storage->items()->save(new Item(
                id:         $item->id,
                categoryId: $item->categoryId,
                name:       $item->name,
                label:      $item->label,
                position:   $item->position,
                active:     $item->active,
                data:       $newData,
                created:    $item->created,
                updated:    time(),
            ));
        }
        $touched++;
    }
    $offset += $batch;
}

echo str_repeat('-', 70) . "\n";
echo $dryRun
    ? "Dry-run: {$touched} would be rewritten, {$skipped} unchanged.\n"
    : "Done: {$touched} rewritten, {$skipped} unchanged.\n";
