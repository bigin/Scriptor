<?php

declare(strict_types=1);

namespace Scriptor\Boot\Frontend;

use Imanager\Files\ImageProcessingException;
use Imanager\Files\ImageProcessor;

/**
 * Builds public URLs for image-field values stored on items, generating
 * resized thumbnails on demand and caching them next to the original.
 *
 * Image entries on items carry a `path` that historically used one of
 * two legacy prefixes — `data/uploads/` (1.x flat-file shape) or
 * `data/uploads-2.0/` (2.0 pre-public-webroot shape). Both are
 * transparently rewritten to the current modern prefix (`uploads/`,
 * matching public/uploads/ in the webroot) so existing items keep
 * rendering without a DB migration.
 *
 * Thumbnails land at `<W>x<H>_<file>` inside a sibling `thumbnail/`
 * directory — same convention 1.x used so the existing on-disk
 * thumbnails stay reachable without re-encoding.
 *
 * Pass `width = 0` (or `height = 0`) to keep the source aspect ratio
 * along that axis. Both 0 → return the original image URL, no resize.
 */
final readonly class ImageUrlBuilder
{
    /**
     * @param list<string> $legacyPathPrefixes  Old path-prefixes to rewrite
     *                                          on read. Order doesn't matter
     *                                          (no prefix is a substring of
     *                                          another).
     */
    public function __construct(
        private ImageProcessor $processor,
        private string $scriptorRoot,
        private array $legacyPathPrefixes = ['data/uploads/', 'data/uploads-2.0/'],
        private string $modernPathPrefix = 'uploads/',
    ) {}

    /**
     * @param array<string, mixed> $image  field-value entry (`path`, `name`, …)
     */
    public function url(array $image, int $width = 0, int $height = 0): string
    {
        $name = (string) ($image['name'] ?? '');
        $path = (string) ($image['path'] ?? '');
        if ($name === '' || $path === '') {
            return '';
        }

        $publicDir = $this->rewritePath($path);
        if ($width <= 0 && $height <= 0) {
            return $this->joinUrl($publicDir, $name);
        }

        $thumbName = self::thumbnailFilename($name, $width, $height);
        $absDir    = $this->scriptorRoot . '/' . trim($publicDir, '/');
        $absThumb  = $absDir . '/thumbnail/' . $thumbName;
        $absSource = $absDir . '/' . $name;

        if (! is_file($absThumb) && is_file($absSource)) {
            $this->generateThumbnail($absSource, $absThumb, $width, $height);
        }

        return $this->joinUrl($publicDir . 'thumbnail/', $thumbName);
    }

    /**
     * Rewrites any of the configured legacy path prefixes
     * (`data/uploads/`, `data/uploads-2.0/`) to the modern prefix
     * (`uploads/`). Foreign or already-modern paths pass through
     * untouched.
     */
    private function rewritePath(string $path): string
    {
        $clean = trim($path, '/');
        if ($clean === '') {
            return $this->modernPathPrefix;
        }
        foreach ($this->legacyPathPrefixes as $legacy) {
            $legacy = trim($legacy, '/');
            if ($legacy === '') {
                continue;
            }
            if (str_starts_with($clean, $legacy . '/')) {
                $clean = trim($this->modernPathPrefix, '/') . '/' . substr($clean, \strlen($legacy) + 1);
                break;
            }
        }
        return $clean . '/';
    }

    private function joinUrl(string $dir, string $file): string
    {
        return '/' . trim($dir, '/') . '/' . ltrim($file, '/');
    }

    private function generateThumbnail(string $source, string $target, int $width, int $height): void
    {
        $dir = \dirname($target);
        if (! is_dir($dir) && ! @mkdir($dir, 0o755, true) && ! is_dir($dir)) {
            return;
        }
        try {
            $bytes = $this->processor->thumbnail($source, $width, $height);
        } catch (ImageProcessingException) {
            return;
        }
        @file_put_contents($target, $bytes);
    }

    private static function thumbnailFilename(string $name, int $width, int $height): string
    {
        return \sprintf('%dx%d_%s', max(0, $width), max(0, $height), $name);
    }
}
