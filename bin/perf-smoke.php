<?php

declare(strict_types=1);

/*
 * Phase 14f performance smoke — runs the four Plan §8.2 timing
 * checkpoints against the live SQLite database and prints per-call
 * averages (ms) plus a verdict against the budget.
 *
 * Budget (Plan §8.2):
 *   - Storage::items()->find($id)          < 1   ms
 *   - findByCategory($cid, 0, 20)          < 50  ms
 *   - FullTextSearch::search(query, 10)    < 100 ms
 *
 * Usage:
 *   php bin/perf-smoke.php
 *   php bin/perf-smoke.php --db=/path/to/other.db
 */

require __DIR__ . '/../vendor/autoload.php';

use Imanager\Search\FullTextSearch;
use Imanager\Storage\Sqlite\ConnectionFactory;
use Imanager\Storage\Sqlite\SqliteStorage;

$dbPath = __DIR__ . '/../data/imanager.db';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--db=')) {
        $dbPath = substr($arg, 5);
    }
}
if (! is_file($dbPath)) {
    fwrite(STDERR, "Database not found: {$dbPath}\n");
    exit(1);
}

$pdo = (new ConnectionFactory($dbPath))->create();
$storage = new SqliteStorage($pdo);

/**
 * Run `$fn` `$iter` times after a 3-call warm-up; return mean ms/call.
 */
function bench(int $iter, callable $fn): float
{
    for ($i = 0; $i < 3; $i++) {
        $fn();
    }
    $start = hrtime(true);
    for ($i = 0; $i < $iter; $i++) {
        $fn();
    }
    return (hrtime(true) - $start) / $iter / 1e6;
}

function report(string $label, float $ms, float $budget): void
{
    $verdict = $ms <= $budget ? 'OK' : 'SLOW';
    $factor  = $budget > 0 ? sprintf('(%.1fx under budget)', $budget / max($ms, 0.0001)) : '';
    printf("  %-40s %8.3f ms  [budget %5.1f ms] %-4s %s\n", $label, $ms, $budget, $verdict, $factor);
}

$category = $storage->categories()->findBySlug('pages');
$itemId = $category?->id !== null ? ($storage->items()->findByCategory($category->id, 0, 1)[0]->id ?? 1) : 1;

echo "iManager 2.0 perf smoke against {$dbPath}\n";
echo str_repeat('-', 70) . "\n";

report(
    "items()->find({$itemId})",
    bench(500, fn() => $storage->items()->find($itemId)),
    1.0,
);
report(
    'findByCategory(pages, 0, 20)',
    bench(300, fn() => $storage->items()->findByCategory($category?->id ?? 1, 0, 20)),
    50.0,
);

$fts = new FullTextSearch($pdo);
report(
    'FullTextSearch::search("scriptor", 10)',
    bench(200, fn() => $fts->search('scriptor', limit: 10)),
    100.0,
);
report(
    'FullTextSearch::count("scriptor")',
    bench(200, fn() => $fts->count('scriptor')),
    100.0,
);
