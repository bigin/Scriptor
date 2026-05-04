<?php

declare(strict_types=1);

namespace Scriptor\Boot\Events;

use Imanager\Domain\Event\ItemDeleted;
use Imanager\Files\FileStorage;
use Imanager\Storage\FileRepository;
use Scriptor\Boot\Files\DirectoryCleanup;

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
 * Asset bytes, every thumbnail variant, and the now-empty
 * `<itemId>/<fieldId>/...` directory chain are scrubbed in one pass
 * via {@see DirectoryCleanup::purge()} — same helper used by the
 * per-file DELETE endpoint so both paths leave an identical state.
 */
final readonly class ItemFileCleanupListener
{
    public function __construct(
        private FileRepository $files,
        private FileStorage $storage,
    ) {}

    public function __invoke(ItemDeleted $event): void
    {
        foreach ($this->files->findByItem($event->itemId) as $file) {
            DirectoryCleanup::purge($this->storage, $file->path);
        }
    }
}
