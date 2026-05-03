<?php

declare(strict_types=1);

namespace Scriptor\Boot\Events;

use Imanager\Domain\Event\ItemDeleted;
use Imanager\Files\FileStorage;
use Imanager\Storage\FileRepository;

/**
 * Listener for `ItemDeleted` — wipes uploaded files (and their
 * thumbnails) from the file-storage backend before the FK cascade on
 * `items` ↦ `files` removes the metadata rows.
 *
 * The dispatcher fires `ItemDeleted` from
 * {@see \Imanager\Storage\Sqlite\SqliteItemRepository::delete()}
 * **before** the SQL DELETE runs (Plan §14e contract), so the
 * FileRepository can still resolve the rows here. The metadata rows
 * disappear automatically with the cascade once delete() returns;
 * we only need to scrub the on-disk side.
 *
 * Thumbnails are stored under `<dir>/thumbnail/<W>x<H>_<file>` (the
 * convention shared by Frontend\ImageUrlBuilder and the upload
 * endpoint) — we walk the thumbnail directory so this works for any
 * thumbnail size, not just the ones we currently generate.
 */
final readonly class ItemFileCleanupListener
{
    public function __construct(
        private FileRepository $files,
        private FileStorage $storage,
    ) {}

    public function __invoke(ItemDeleted $event): void
    {
        $files = $this->files->findByItem($event->itemId);
        if ($files === []) {
            return;
        }

        foreach ($files as $file) {
            // Delete the asset itself.
            if ($this->storage->exists($file->path)) {
                $this->storage->delete($file->path);
            }
            // Plus every thumbnail size that was ever generated for it.
            $this->purgeThumbnails($file->path, $file->name);
        }
    }

    /**
     * Walks `<dir>/thumbnail/` and removes every `<W>x<H>_<file>` entry
     * for the given filename. Tolerates a missing directory (no
     * thumbnails generated yet) and unreadable / non-image siblings.
     */
    private function purgeThumbnails(string $assetPath, string $name): void
    {
        $thumbDirRel = \dirname($assetPath) . '/thumbnail';
        $thumbDirAbs = $this->storage->absolutePath($thumbDirRel);
        if (! is_dir($thumbDirAbs)) {
            return;
        }
        $entries = scandir($thumbDirAbs);
        if ($entries === false) {
            return;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            // Match `<W>x<H>_<name>` exactly so we never delete a sibling
            // file uploaded with a different stem.
            if (! preg_match('/^\d+x\d+_/', $entry) || ! str_ends_with($entry, '_' . $name)) {
                continue;
            }
            $thumbRel = $thumbDirRel . '/' . $entry;
            if ($this->storage->exists($thumbRel)) {
                $this->storage->delete($thumbRel);
            }
        }
    }
}
