<?php

declare(strict_types=1);

namespace Scriptor\Boot\Frontend;

use Imanager\Domain\Item;

/**
 * Read-only page DTO exposed to themes as `$site->page`.
 *
 * Adapts an `Imanager\Domain\Item` (Pages category) to the property surface
 * the legacy Scriptor themes know: `name`, `slug`, `template`, `parent`,
 * `pagetype`, `content`, `menu_title`, `images`, plus structural columns.
 *
 * Field values that aren't promoted to a top-level column live in the item's
 * `data` bag — `__get()` reaches in lazily so theme code that asks for an
 * uncommon field still works without us declaring every possible property.
 */
final readonly class Page
{
    public string $name;
    public string $slug;
    public string $template;
    public string $pagetype;
    public string $menu_title;
    public string $content;
    public int $parent;
    /** @var list<array<string, mixed>> */
    public array $images;

    public function __construct(
        public Item $item,
    ) {
        $data = $item->data;
        $this->name       = $item->name ?? '';
        $this->slug       = self::str($data->get('slug'));
        $this->template   = self::str($data->get('template'));
        $this->pagetype   = self::str($data->get('pagetype'));
        $this->menu_title = self::str($data->get('menu_title'));
        $this->content    = self::str($data->get('content'));
        $this->parent     = (int) ($data->get('parent') ?? 0);

        $rawImages = $data->get('images');
        $this->images = \is_array($rawImages) ? array_values($rawImages) : [];
    }

    public function id(): ?int
    {
        return $this->item->id;
    }

    public function active(): bool
    {
        return $this->item->active;
    }

    public function created(): int
    {
        return $this->item->created;
    }

    public function updated(): int
    {
        return $this->item->updated;
    }

    /**
     * Lazy access to any field not promoted to a typed property, e.g.
     * theme-specific custom fields. Returns `null` for unknown keys instead
     * of raising — themes that probe optional fields stay quiet.
     */
    public function __get(string $name): mixed
    {
        return match ($name) {
            'id'      => $this->item->id,
            'active'  => $this->item->active,
            'created' => $this->item->created,
            'updated' => $this->item->updated,
            default   => $this->item->data->get($name),
        };
    }

    public function __isset(string $name): bool
    {
        return match ($name) {
            'id', 'active', 'created', 'updated' => true,
            default => $this->item->data->has($name),
        };
    }

    private static function str(mixed $value): string
    {
        if (\is_string($value)) {
            return $value;
        }
        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }
        return '';
    }
}
