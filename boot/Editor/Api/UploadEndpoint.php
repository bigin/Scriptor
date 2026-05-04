<?php

declare(strict_types=1);

namespace Scriptor\Boot\Editor\Api;

use Imanager\Files\FileStorage;
use Imanager\Files\ImageProcessingException;
use Imanager\Files\ImageProcessor;
use Imanager\Files\UploadConstraints;
use Imanager\Files\UploadedFile;
use Imanager\Files\UploadException;
use Imanager\Files\UploadHandler;
use Imanager\Storage\FileRepository;
use Imanager\Validation\Sanitizer as ImanagerSanitizer;
use Scriptor\Boot\Editor\Editor;
use Scriptor\Boot\Files\DirectoryCleanup;

/**
 * Phase 14d-1 upload endpoint — JSON API mounted at `/editor/api/upload`,
 * called by the FilePond widget on the editor page-edit form.
 *
 * Methods:
 *   POST   multipart/form-data  → store and persist file metadata.
 *          Body fields:
 *            file       : the upload itself (FilePond default key)
 *            itemId     : owning item id (>= 1) — page must exist first
 *            fieldId    : owning field id (>= 1)
 *            tokenName  : CSRF token name
 *            tokenValue : CSRF token value
 *          Response 200: {"fileId":N, "name", "url", "mime", "size",
 *                         "width", "height", "thumbnailUrl"}
 *          Response 400: {"error": "..."}
 *
 *   DELETE                       → remove the file (FilePond `revert` /
 *                                  remove handler).
 *          Body / query fields:
 *            fileId     : id to delete
 *            tokenName, tokenValue
 *          Response 200: {"status": "ok"}
 *
 * Captions / titles do not have an endpoint here: they live on the
 * page form and travel with the page-save POST as `image_titles[<id>]`.
 *
 * Auth gate sits one level up in EditorRouter (anonymous requests get
 * 302'd to /editor/auth/ before reaching here). CSRF is enforced
 * locally for both verbs because FilePond posts JSON-friendly form
 * fields directly.
 */
final class UploadEndpoint
{
    public function __construct(
        private readonly Editor $editor,
        private readonly FileRepository $files,
        private readonly FileStorage $storage,
        private readonly ImanagerSanitizer $sanitizer,
        private readonly ImageProcessor $images,
    ) {}

    public function handle(string $method): never
    {
        match (strtoupper($method)) {
            'POST'   => $this->handlePost(),
            'DELETE' => $this->handleDelete(),
            default  => $this->error(405, 'Method not allowed'),
        };
    }

    private function handlePost(): never
    {
        $this->assertCsrf($this->editor->input->postString('tokenName'), $this->editor->input->postString('tokenValue'));

        $itemId  = $this->editor->input->postInt('itemId', 0);
        $fieldId = $this->editor->input->postInt('fieldId', 0);
        if ($itemId < 1 || $fieldId < 1) {
            $this->error(400, 'itemId and fieldId are required (>= 1)');
        }

        // Accept either the explicit `file` field we tell FilePond to use
        // or its default `filepond` field name, so cURL smokes and other
        // multipart clients keep working without configuration.
        $entry = $this->editor->input->file('file') ?? $this->editor->input->file('filepond');
        if ($entry === null) {
            $this->error(400, 'Upload missing `file` (or `filepond`) field');
        }

        try {
            $upload = UploadedFile::fromPhpUpload($entry);
        } catch (UploadException $e) {
            $this->error(400, $e->getMessage());
        }

        $constraints = self::constraintsFor($upload->declaredMime);
        $handler = new UploadHandler($this->storage, $this->files, $this->sanitizer, $this->images);

        try {
            $file = $handler->handle($upload, $itemId, $fieldId, $constraints);
        } catch (UploadException $e) {
            $this->error(400, $e->getMessage());
        }

        $payload = [
            'fileId' => $file->id,
            'name'   => $file->name,
            'url'    => $this->storage->url($file->path),
            'mime'   => $file->mime,
            'size'   => $file->size,
            'width'  => $file->width,
            'height' => $file->height,
        ];
        if ($file->isImage()) {
            $thumb = $this->ensureThumbnail($file->path, 300, 300);
            if ($thumb !== null) {
                $payload['thumbnailUrl'] = $this->storage->url($thumb);
            }
        }

        $this->json(200, $payload);
    }

    /**
     * Generate a `thumbnail/<W>x<H>_<file>` next to the original image
     * (matching the read-side convention from Frontend\ImageUrlBuilder)
     * and return its path relative to the FileStorage root, or null
     * when generation fails or the source isn't a readable image.
     */
    private function ensureThumbnail(string $sourceRel, int $width, int $height): ?string
    {
        $thumbName = \sprintf('%dx%d_%s', $width, $height, basename($sourceRel));
        $thumbRel  = \dirname($sourceRel) . '/thumbnail/' . $thumbName;
        if ($this->storage->exists($thumbRel)) {
            return $thumbRel;
        }
        try {
            $bytes = $this->images->thumbnail($this->storage->absolutePath($sourceRel), $width, $height);
        } catch (ImageProcessingException) {
            return null;
        }
        $this->storage->write($thumbRel, $bytes);
        return $thumbRel;
    }

    private function handleDelete(): never
    {
        // PHP doesn't populate $_POST for DELETE bodies, so parse the
        // www-form-urlencoded body ourselves once and merge it with the
        // query-string fallback.
        $body = self::parseBody();
        $token  = (string) ($body['tokenName']  ?? $this->editor->input->getString('tokenName'));
        $tokenV = (string) ($body['tokenValue'] ?? $this->editor->input->getString('tokenValue'));
        $this->assertCsrf($token, $tokenV);

        $fileId = isset($body['fileId']) ? (int) $body['fileId'] : 0;
        if ($fileId < 1) {
            $fileId = $this->editor->input->getInt('fileId', 0);
        }
        // FilePond's `revert` action POSTs the file id as the raw body.
        if ($fileId < 1 && isset($body['__raw']) && ctype_digit((string) $body['__raw'])) {
            $fileId = (int) $body['__raw'];
        }
        if ($fileId < 1) {
            $this->error(400, 'fileId is required');
        }

        $file = $this->files->find($fileId);
        if ($file === null) {
            $this->json(200, ['status' => 'gone']);
        }

        DirectoryCleanup::purge($this->storage, $file->path);
        $this->files->delete($fileId);
        $this->json(200, ['status' => 'ok']);
    }

    /**
     * @return array<string, mixed>
     */
    private static function parseBody(): array
    {
        $raw = (string) file_get_contents('php://input');
        if ($raw === '') {
            return [];
        }
        $parsed = [];
        parse_str($raw, $parsed);
        if ($parsed === [] || (\count($parsed) === 1 && \array_key_first($parsed) === $raw)) {
            // Looked like a single non-form-encoded value (e.g. FilePond's
            // raw revert id) — surface it as `__raw`.
            return ['__raw' => $raw];
        }
        return $parsed;
    }

    private function assertCsrf(string $name, string $value): void
    {
        if (! ($this->editor->config['protectCSRF'] ?? true)) {
            return;
        }
        if ($name === '' || $value === '' || ! $this->editor->csrf->validate($name, $value)) {
            $this->error(403, 'CSRF token invalid');
        }
    }

    /**
     * Picks a per-mime constraint set. Image mimes get the bundled
     * `UploadConstraints::images()` policy (8 MB, gif/jpg/png/webp);
     * everything else falls back to the permissive default with a
     * 10 MB cap.
     */
    private static function constraintsFor(string $mime): UploadConstraints
    {
        return str_starts_with($mime, 'image/') ? UploadConstraints::images() : new UploadConstraints();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(int $status, array $payload): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function error(int $status, string $message): never
    {
        $this->json($status, ['error' => $message]);
    }
}
