<?php

declare(strict_types=1);

namespace Scriptor\Boot\Frontend;

use Imanager\Domain\Item;
use Imanager\Query\Operator;
use Imanager\Query\Query;
use Imanager\Storage\CategoryRepository;
use Imanager\Storage\ItemRepository;

/**
 * Read access to the Pages category as a thin wrapper over the iManager 2.0
 * `ItemRepository` + `Query` AST. Returns Frontend\Page DTOs so themes can
 * keep using `$page->slug`, `$page->template`, etc.
 *
 * Phase 14b-1 only needs lookup + parent/children traversal. Sorting,
 * pagination and richer filters land with BasicTheme in 14b-2.
 */
final readonly class PageRepository
{
    public int $categoryId;

    public function __construct(
        private CategoryRepository $categories,
        private ItemRepository $items,
        string $categorySlug = 'pages',
    ) {
        $category = $this->categories->findBySlug($categorySlug);
        if ($category === null || $category->id === null) {
            throw new \RuntimeException(\sprintf(
                'Category with slug "%s" not found in the iManager database',
                $categorySlug,
            ));
        }
        $this->categoryId = $category->id;
    }

    public function find(int $id): ?Page
    {
        $item = $this->items->find($id);
        return $item !== null && $item->categoryId === $this->categoryId
            ? new Page($item)
            : null;
    }

    public function findBySlug(string $slug): ?Page
    {
        $query = (new Query($this->categoryId))
            ->where('slug', Operator::Eq, $slug)
            ->limit(1);
        foreach ($this->items->query($query) as $item) {
            return new Page($item);
        }
        return null;
    }

    /**
     * Finds the home page — by convention the page with id = 1 in Scriptor.
     * Falls back to the lowest-position page if id 1 is missing.
     */
    public function findHome(): ?Page
    {
        $home = $this->find(1);
        if ($home !== null) {
            return $home;
        }
        $items = $this->items->findByCategory($this->categoryId, 0, 1);
        return $items === [] ? null : new Page($items[0]);
    }

    /**
     * @return list<Page>
     */
    public function findAll(): array
    {
        return self::wrap($this->items->findByCategory($this->categoryId));
    }

    /**
     * @return list<Page>
     */
    public function findByParent(int $parentId): array
    {
        $query = (new Query($this->categoryId))
            ->where('parent', Operator::Eq, $parentId)
            ->orderBy('position');
        return self::wrap($this->items->query($query));
    }

    /**
     * @return list<Page>
     */
    public function findActiveByParent(int $parentId): array
    {
        $pages = [];
        foreach ($this->findByParent($parentId) as $page) {
            if ($page->active()) {
                $pages[] = $page;
            }
        }
        return $pages;
    }

    /**
     * @param list<Item> $items
     * @return list<Page>
     */
    private static function wrap(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            $out[] = new Page($item);
        }
        return $out;
    }
}
