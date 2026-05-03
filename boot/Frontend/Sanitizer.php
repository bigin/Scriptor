<?php

declare(strict_types=1);

namespace Scriptor\Boot\Frontend;

use Imanager\Validation\Sanitizer as ImanagerSanitizer;

/**
 * Backwards-compatible facade over `Imanager\Validation\Sanitizer`.
 *
 * Themes call methods on `$site->sanitizer` that the new iManager Sanitizer
 * doesn't ship — `templateName()`, `pageName()`, `url()` (legacy variants),
 * `text()`. We forward the modern equivalents and add the legacy-only
 * helpers without growing the iManager surface.
 */
final readonly class Sanitizer
{
    public function __construct(private ImanagerSanitizer $delegate) {}

    public function templateName(string $value): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $value);
        return $clean ?? '';
    }

    public function pageName(string $value): string
    {
        return $this->delegate->slug($value);
    }

    public function text(string $value, int $maxLength = 255): string
    {
        return $this->delegate->text($value, $maxLength);
    }

    public function multiline(string $value, int $maxLength = 65535): string
    {
        return $this->delegate->multiline($value, $maxLength);
    }

    public function slug(string $value, int $maxLength = 128): string
    {
        return $this->delegate->slug($value, $maxLength);
    }

    public function email(string $value): ?string
    {
        return $this->delegate->email($value);
    }

    public function url(string $value): string
    {
        return $this->delegate->url($value) ?? '';
    }

    public function entities(string $value): string
    {
        return $this->delegate->entities($value);
    }

    public function markdown(string $value): string
    {
        return $this->delegate->markdown($value);
    }
}
