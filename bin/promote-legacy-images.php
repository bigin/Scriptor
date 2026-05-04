<?php

declare(strict_types=1);

/*
 * One-shot migration — promote `Item.data.images` entries (the migrated
 * 1.x payload) into proper FileRepository rows.
 *
 * Why: the editor and the frontend used to carry two parallel render
 * paths — one for new uploads in the `files` table, one for legacy
 * entries embedded in `data.images`. Legacy rows lacked the delete
 * button, dimensions / size, and the title-PATCH endpoint. Promoting
 * the entries lets us collapse to a single rendering path.
 *
 * Per legacy entry we:
 *   1. Resolve the source on disk (relative to FileStorage root).
 *   2. Read mime, size, and image dimensions from the bytes.
 *   3. Insert a `File` row pointing at the same on-disk path (no file
 *      movement — the file already lives where FileStorage expects it
 *      under data/uploads-2.0/).
 *   4. Generate the `300x300_<name>` thumbnail (matching the editor
 *      preview convention from UploadEndpoint::ensureThumbnail).
 *   5. Strip the entry from `Item.data.images` and save the item.
 *
 * Idempotency: skips items where a file row with the same
 * (itemId, fieldId, name) already exists.
 *
 * Usage:
 *   php bin/promote-legacy-images.php
 *   php bin/promote-legacy-images.php --dry-run
 *   php bin/promote-legacy-images.php --db=/path/to/other.db
 */

require __DIR__ . '/../vendor/autoload.php';

use Imanager\Domain\File;
use Imanager\Domain\Item;
use Imanager\Files\FileStorage;
use Imanager\Files\ImageProcessor;
use Imanager\Storage\CategoryRepository;
use Imanager\Storage\FieldRepository;
use Imanager\Storage\FileRepository;
use Imanager\Storage\ItemRepository;
use Scriptor\Boot\ImanagerBootstrap;

$dryRun = false;
$dbPath = null;
foreach ($argv as $arg) {
    if ($arg === '--dry-run') {
        $dryRun = true;
    } elseif (str_starts_with($arg, '--db=')) {
        $dbPath = substr($arg, 5);
    }
}

$paths = $dbPath !== null ? ['databasePath' => $dbPath] : [];
$container = ImanagerBootstrap::create(__DIR__ . '/..', $paths);

$categories = $container->get(CategoryRepository::class);
$fields     = $container->get(FieldRepository::class);
$items      = $container->get(ItemRepository::class);
$files      = $container->get(FileRepository::class);
$storage    = $container->get(FileStorage::class);
$processor  = $container->get(ImageProcessor::class);

$pages = $categories->findBySlug('pages');
if ($pages === null || $pages->id === null) {
    fwrite(STDERR, "No 'pages' category\n");
    exit(1);
}
$field = $fields->findByName($pages->id, 'images');
if ($field === null || $field->id === null) {
    fwrite(STDERR, "No 'images' field on Pages category\n");
    exit(1);
}
$fieldId = (int) $field->id;

$total     = $items->countByCategory($pages->id);
$promoted  = 0;
$missing   = 0;
$dupes     = 0;
$itemsTouched = 0;

echo "Scanning {$total} pages" . ($dryRun ? ' (dry-run)' : '') . "\n";
echo str_repeat('-', 70) . "\n";

$batch = 100;
for ($offset = 0; $offset < $total; $offset += $batch) {
    foreach ($items->findByCategory($pages->id, $offset, $batch) as $item) {
        $rawImages = $item->data->get('images');
        if (! is_array($rawImages) || $rawImages === []) {
            continue;
        }

        $existingNames = [];
        foreach ($files->findByItemAndField($item->id, $fieldId) as $f) {
            $existingNames[$f->name] = true;
        }

        $promotedHere = 0;
        $remaining    = [];

        foreach ($rawImages as $img) {
            if (! is_array($img)) {
                $remaining[] = $img;
                continue;
            }
            $name = (string) ($img['name'] ?? '');
            $dir  = ltrim((string) ($img['path'] ?? ''), '/');
            $dir  = preg_replace('#^data/uploads(-2\.0)?/#', '', $dir) ?? $dir;
            $dir  = trim($dir, '/');
            if ($name === '') {
                $remaining[] = $img;
                continue;
            }
            $relPath = ($dir !== '' ? $dir . '/' : '') . $name;

            if (isset($existingNames[$name])) {
                $dupes++;
                printf("  item #%-3d skip dupe: %s\n", $item->id, $name);
                continue;
            }
            if (! $storage->exists($relPath)) {
                $missing++;
                printf("  item #%-3d MISSING on disk: %s\n", $item->id, $relPath);
                $remaining[] = $img;
                continue;
            }

            $absPath = $storage->absolutePath($relPath);
            $size    = (int) (filesize($absPath) ?: 0);
            $mime    = (string) (mime_content_type($absPath) ?: 'application/octet-stream');
            $width   = 0;
            $height  = 0;
            if (str_starts_with($mime, 'image/')) {
                $dims   = $processor->dimensions($absPath);
                $width  = $dims['width'];
                $height = $dims['height'];
            }

            $title = (string) ($img['title'] ?? '');
            $position = (int) ($img['position'] ?? 0);

            $file = new File(
                id:       null,
                itemId:   (int) $item->id,
                fieldId:  $fieldId,
                name:     $name,
                path:     $relPath,
                mime:     $mime,
                size:     $size,
                width:    $width,
                height:   $height,
                position: $position,
                created:  time(),
                title:    $title,
            );

            printf("  item #%-3d promote: %-50s  %dx%d  %d bytes\n",
                $item->id,
                mb_strimwidth($name, 0, 50, '…'),
                $width, $height, $size,
            );

            if (! $dryRun) {
                $files->save($file);

                $thumbRel = ($dir !== '' ? $dir . '/' : '') . 'thumbnail/300x300_' . $name;
                if (! $storage->exists($thumbRel)) {
                    try {
                        $bytes = $processor->thumbnail($absPath, 300, 300);
                        $storage->write($thumbRel, $bytes);
                    } catch (\Throwable $e) {
                        fprintf(STDERR, "    thumbnail failed for %s: %s\n", $relPath, $e->getMessage());
                    }
                }
            }
            $promoted++;
            $promotedHere++;
        }

        if ($promotedHere === 0) {
            continue;
        }

        $itemsTouched++;

        if (! $dryRun) {
            $newData = $item->data->with('images', $remaining);
            $items->save(new Item(
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
    }
}

echo str_repeat('-', 70) . "\n";
echo $dryRun
    ? "Dry-run: {$promoted} would be promoted across {$itemsTouched} items, {$dupes} skipped (dupe), {$missing} missing.\n"
    : "Done: {$promoted} promoted across {$itemsTouched} items, {$dupes} skipped (dupe), {$missing} missing.\n";
