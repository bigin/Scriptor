<?php

declare(strict_types=1);

namespace Scriptor\Boot\Files;

use Imanager\Files\FileStorage;

/**
 * Removes a single uploaded file's bytes (asset + every thumbnail
 * variant that follows the `<dir>/thumbnail/<W>x<H>_<name>`
 * convention), then walks up the directory chain rmdir'ing each
 * parent that's now empty. Stops before reaching the storage root —
 * the root itself is never touched.
 *
 * Both the page-delete listener and the per-file DELETE endpoint
 * funnel through here so empty `<itemId>/<fieldId>/...` directories
 * don't accumulate after a page or its files are removed.
 */
final class DirectoryCleanup
{
    public static function purge(FileStorage $storage, string $assetPath): void
    {
        if ($storage->exists($assetPath)) {
            $storage->delete($assetPath);
        }

        $assetDir = \dirname($assetPath);
        // Match thumbnails on the path's basename, not the `File.name`
        // field — collision-free uploads (e.g. `foo-2.png` from a
        // second upload of `foo.png`) keep `name=foo.png` but their
        // thumbnails follow the path stem (`300x300_foo-2.png`).
        self::purgeThumbnails($storage, $assetDir, \basename($assetPath));

        // Walk up: rmdir each parent that became empty. Bails on the
        // first non-empty dir, missing dir, or when we run out of
        // path segments (dirname() returns "." once the relative
        // path can't climb further).
        $dir = $assetDir;
        while ($dir !== '' && $dir !== '.' && $dir !== '/') {
            $abs = $storage->absolutePath($dir);
            if (! is_dir($abs)) {
                break;
            }
            if (! @rmdir($abs)) {
                break;
            }
            $dir = \dirname($dir);
        }
    }

    /**
     * Walks `<dir>/thumbnail/` and removes every `<W>x<H>_<basename>`
     * entry. Tolerates a missing directory (no thumbnails generated
     * yet) and unrelated siblings.
     */
    private static function purgeThumbnails(FileStorage $storage, string $assetDir, string $basename): void
    {
        $thumbDirRel = $assetDir . '/thumbnail';
        $thumbDirAbs = $storage->absolutePath($thumbDirRel);
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
            // Match `<W>x<H>_<basename>` exactly so we never delete a
            // sibling file uploaded with a different stem.
            if (! preg_match('/^\d+x\d+_/', $entry) || ! str_ends_with($entry, '_' . $basename)) {
                continue;
            }
            $thumbRel = $thumbDirRel . '/' . $entry;
            if ($storage->exists($thumbRel)) {
                $storage->delete($thumbRel);
            }
        }

        @rmdir($thumbDirAbs);
    }
}
