<?php

declare(strict_types=1);

namespace Scriptor\Boot\Frontend;

use Imanager\Domain\Item;
use Imanager\Query\Direction;
use Imanager\Query\Operator;
use Imanager\Query\Query;
use Imanager\Storage\CategoryRepository;
use Imanager\Storage\ItemRepository;

/**
 * Read access to the Pages category as a thin wrapper over the iManager 2.0
 * `ItemRepository` + `Query` AST. Returns `Page` DTOs so themes can keep
 * using `$page->slug`, `$page->template`, etc.
 *
 * The query helpers below replace the legacy `getItems('parent=N')` selector
 * strings with typed parameters; they're enough to drive the bundled basic
 * theme (article list, archive, navigation, footer container).
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
     * Finds the home page — by convention the page whose slug is the
     * empty string. The empty slug is the URL representation of the
     * site root, so the page that owns that slug also owns the `/`
     * URL. Returns `null` when no page has an empty slug, in which
     * case `/` 404s instead of rendering something arbitrary — the
     * right call for an API-only or docs-only install that has no
     * conceptual "home".
     *
     * Uniqueness of the empty slug is enforced at the editor-save
     * layer (see {@see \Scriptor\Boot\Editor\Pages\PagesModule}); a
     * site with two empty-slug rows is malformed.
     */
    public function findHome(): ?Page
    {
        return $this->findBySlug('');
    }

    /**
     * @return list<Page>
     */
    public function findAll(): array
    {
        return self::wrap($this->items->findByCategory($this->categoryId));
    }

    /**
     * Children of a parent page in the configured order.
     *
     * @return list<Page>
     */
    public function findByParent(
        int $parentId,
        string $orderBy = 'position',
        Direction $direction = Direction::Asc,
        bool $activeOnly = false,
        int $offset = 0,
        int $limit = 0,
    ): array {
        $query = (new Query($this->categoryId))
            ->where('parent', Operator::Eq, $parentId)
            ->orderBy($orderBy, $direction);
        if ($activeOnly) {
            $query = $query->where('active', Operator::Eq, true);
        }
        if ($offset > 0) {
            $query = $query->offset($offset);
        }
        if ($limit > 0) {
            $query = $query->limit($limit);
        }
        return self::wrap($this->items->query($query));
    }

    /**
     * Convenience for templates that just want the active children in
     * position order without thinking about defaults.
     *
     * @return list<Page>
     */
    public function findActiveByParent(int $parentId): array
    {
        return $this->findByParent($parentId, activeOnly: true);
    }

    /**
     * Active pages whose `created` timestamp falls within `[$start, $end)`,
     * scoped to a parent container (typically the blog `articles_page_id`).
     *
     * @return list<Page>
     */
    public function findInTimeRange(
        int $start,
        int $end,
        int $parentId,
        string $orderBy = 'created',
        Direction $direction = Direction::Desc,
    ): array {
        $query = (new Query($this->categoryId))
            ->where('parent', Operator::Eq, $parentId)
            ->where('active', Operator::Eq, true)
            ->where('created', Operator::Gte, $start)
            ->where('created', Operator::Lt, $end)
            ->orderBy($orderBy, $direction);
        return self::wrap($this->items->query($query));
    }

    public function countByParent(int $parentId, bool $activeOnly = false): int
    {
        $query = (new Query($this->categoryId))
            ->where('parent', Operator::Eq, $parentId);
        if ($activeOnly) {
            $query = $query->where('active', Operator::Eq, true);
        }
        return \count($this->items->query($query));
    }

    /* ---------------------------------------------------------------- *
     * Write operations
     * ---------------------------------------------------------------- */

    public function save(Item $item): Page
    {
        if ($item->categoryId !== $this->categoryId) {
            throw new \InvalidArgumentException(\sprintf(
                'Item belongs to category %d, expected %d (Pages)',
                $item->categoryId,
                $this->categoryId,
            ));
        }
        return new Page($this->items->save($item));
    }

    public function delete(int $id): void
    {
        $page = $this->find($id);
        if ($page === null) {
            return; // already gone — idempotent
        }
        $this->items->delete($id);
    }

    /**
     * Reposition a single page (the one the user just dragged) to sit
     * directly before its new next-neighbour, without touching any
     * other page's `position`. The new value is `nextPos - 1`; if the
     * moved item lands at the end of the list it takes `prevPos + 1`.
     *
     * Used by `PagesModule::renumberAction()` when JS sends the explicit
     * `moved` id alongside the new visual order — single-item drag is
     * the common case and this preserves any manually-set positions on
     * the other rows.
     *
     * If the gap between neighbours is too tight (`nextPos - prevPos < 2`),
     * this cascade-shifts every following item by `+10` so the moved item
     * still ends up just before the (shifted) next-neighbour.
     *
     * @param list<int> $idsInOrder Full top-level page id list in the
     *                              new visual order (drag-end snapshot).
     */
    public function reorderOne(int $movedId, array $idsInOrder): void
    {
        $movedIndex = array_search($movedId, $idsInOrder, true);
        if ($movedIndex === false) {
            return;
        }
        $moved = $this->items->find($movedId);
        if ($moved === null || $moved->categoryId !== $this->categoryId) {
            return;
        }

        $prevPos = 0;
        if ($movedIndex > 0) {
            $prevItem = $this->items->find($idsInOrder[$movedIndex - 1]);
            if ($prevItem !== null && $prevItem->categoryId === $this->categoryId) {
                $prevPos = $prevItem->position;
            }
        }

        $nextPos = null;
        $nextIndex = $movedIndex + 1;
        if ($nextIndex < count($idsInOrder)) {
            $nextItem = $this->items->find($idsInOrder[$nextIndex]);
            if ($nextItem !== null && $nextItem->categoryId === $this->categoryId) {
                $nextPos = $nextItem->position;
            }
        }

        if ($nextPos === null) {
            // End of list: sit just after prev.
            $newPos = $prevPos + 1;
        } elseif ($nextPos - $prevPos >= 2) {
            // Room exists: sit directly before next.
            $newPos = $nextPos - 1;
        } else {
            // Neighbours touch (e.g. 5 and 6): moved item takes next's
            // slot; next then bumps up by 1, and the ripple walks
            // forward only as far as collisions actually exist. The
            // first item with a position already higher than its new
            // predecessor stops the cascade. Result: 1/2/3/4 → drag
            // c between a and b → 1/2/3/4 (only b shifts by 1, d
            // untouched).
            $newPos = $nextPos;
            $prevAssigned = $newPos;
            for ($i = $nextIndex; $i < count($idsInOrder); $i++) {
                $cascadeItem = $this->items->find($idsInOrder[$i]);
                if ($cascadeItem === null || $cascadeItem->categoryId !== $this->categoryId) {
                    continue;
                }
                if ($cascadeItem->position > $prevAssigned) {
                    break; // gap reached
                }
                $newCascadePos = $prevAssigned + 1;
                $this->items->save(new Item(
                    id:         $cascadeItem->id,
                    categoryId: $cascadeItem->categoryId,
                    name:       $cascadeItem->name,
                    label:      $cascadeItem->label,
                    position:   $newCascadePos,
                    active:     $cascadeItem->active,
                    data:       $cascadeItem->data,
                    created:    $cascadeItem->created,
                    updated:    $cascadeItem->updated,
                ));
                $prevAssigned = $newCascadePos;
            }
        }

        if ($moved->position === $newPos) {
            return;
        }

        $this->items->save(new Item(
            id:         $moved->id,
            categoryId: $moved->categoryId,
            name:       $moved->name,
            label:      $moved->label,
            position:   $newPos,
            active:     $moved->active,
            data:       $moved->data,
            created:    $moved->created,
            updated:    $moved->updated,
        ));
    }

    /**
     * Bulk-renumber pages in the given order. Each id's `position`
     * becomes its (1-indexed) slot in the array. Pages absent from the
     * id list keep their current position.
     *
     * Kept for backwards compatibility (e.g. seed imports or admin
     * tooling that wants explicit contiguous numbering). The interactive
     * drag-reorder no longer calls this; it uses {@see reorderOne()}.
     *
     * @param list<int> $idsInOrder
     */
    public function renumber(array $idsInOrder): void
    {
        $position = 1;
        foreach ($idsInOrder as $id) {
            $item = $this->items->find($id);
            if ($item === null || $item->categoryId !== $this->categoryId) {
                continue;
            }
            if ($item->position === $position) {
                $position++;
                continue;
            }
            $this->items->save(new Item(
                id:         $item->id,
                categoryId: $item->categoryId,
                name:       $item->name,
                label:      $item->label,
                position:   $position,
                active:     $item->active,
                data:       $item->data,
                created:    $item->created,
                updated:    $item->updated,
            ));
            $position++;
        }
    }

    /**
     * Returns true when another page already uses `$slug` under the same
     * `$parentId`, ignoring `$exceptId` (so a page can keep its own slug
     * on update). Lets PagesModule preflight a save before committing.
     */
    public function slugTaken(string $slug, int $parentId, ?int $exceptId = null): bool
    {
        $query = (new Query($this->categoryId))
            ->where('slug', Operator::Eq, $slug)
            ->where('parent', Operator::Eq, $parentId);
        foreach ($this->items->query($query) as $item) {
            if ($exceptId !== null && $item->id === $exceptId) {
                continue;
            }
            return true;
        }
        return false;
    }

    /**
     * Would setting `$pageId`'s parent to `$candidateParentId` create a cycle?
     *
     * Walks the candidate parent's existing chain upwards; if it eventually
     * passes through `$pageId`, the proposed save would close a loop. We
     * deliberately don't pre-simulate the save into the storage layer — we
     * just chase the candidate's *current* chain, because the only new edge
     * created by the proposed save is `pageId → candidateParentId`, and the
     * cycle test "does the candidate's chain reach pageId" already covers
     * every loop that edge can produce.
     *
     * Edge cases:
     *   - `$candidateParentId === 0` — no parent change to a root can ever
     *     create a cycle.
     *   - `$pageId === 0` — a brand-new page that isn't persisted yet has no
     *     dependants, so no cycle is possible.
     *   - `$candidateParentId === $pageId` — direct self-parent. Always a
     *     cycle. (Caught defensively here; PagesModule also collapses this
     *     case to 0 before reaching us, but a stale callsite shouldn't be
     *     able to bypass the check.)
     *   - The candidate's chain already contains a cycle that doesn't pass
     *     through `$pageId` — pre-existing data corruption. We bail
     *     truthfully (return false) rather than blame the save for an issue
     *     that was already there.
     *   - Broken parent pointer (parent id doesn't resolve to a page) — we
     *     stop walking and return false; you don't reject a save because of
     *     someone else's dangling reference.
     */
    public function wouldCreateCycle(int $pageId, int $candidateParentId): bool
    {
        if ($candidateParentId === 0 || $pageId === 0) {
            return false;
        }
        if ($candidateParentId === $pageId) {
            return true;
        }

        /** @var array<int, true> $visited */
        $visited = [];
        $current = $candidateParentId;
        while ($current !== 0) {
            if ($current === $pageId) {
                return true;
            }
            if (isset($visited[$current])) {
                return false;
            }
            $visited[$current] = true;

            $parent = $this->find($current);
            if ($parent === null) {
                return false;
            }
            $current = $parent->parent;
        }
        return false;
    }

    public function nextPosition(): int
    {
        $items = $this->items->findByCategory($this->categoryId);
        $max = 0;
        foreach ($items as $item) {
            if ($item->position > $max) {
                $max = $item->position;
            }
        }
        return $max + 1;
    }

    /**
     * Recursive walk that materialises a tree of pages keyed by parent id.
     * Replaces 1.x `Pages::getPageLevels()` for nav-style traversals.
     *
     * @param list<int> $excludeIds
     * @return array<int, list<Page>>
     */
    public function levels(
        int $rootParent = 0,
        int $maxDepth = 0,
        bool $activeOnly = true,
        array $excludeIds = [],
    ): array {
        $tree = [];
        $this->walkLevels($rootParent, 1, $maxDepth, $activeOnly, $excludeIds, $tree);
        return $tree;
    }

    /**
     * Flat list of every descendant page beneath `$parent`, parents-first.
     *
     * @return list<Page>
     */
    public function descendants(Page $parent): array
    {
        $out = [];
        $this->collectDescendants($parent, $out, [$parent->id() ?? 0 => true]);
        return $out;
    }

    /**
     * @param list<Page>       $out
     * @param array<int, true> $visited
     */
    private function collectDescendants(Page $parent, array &$out, array $visited): void
    {
        $parentId = $parent->id() ?? 0;
        foreach ($this->findActiveByParent($parentId) as $child) {
            $childId = $child->id() ?? 0;
            if ($childId === $parentId || isset($visited[$childId])) {
                continue;
            }
            $visited[$childId] = true;
            $out[] = $child;
            $this->collectDescendants($child, $out, $visited);
        }
    }

    /**
     * @param list<int>            $excludeIds
     * @param array<int, list<Page>> $tree
     * @param array<int, true>     $visited cycle-detection accumulator
     */
    private function walkLevels(
        int $parent,
        int $depth,
        int $maxDepth,
        bool $activeOnly,
        array $excludeIds,
        array &$tree,
        array $visited = [],
    ): void {
        if (isset($visited[$parent])) {
            return; // self- or cross-cycle in the parent chain — bail out
        }
        $visited[$parent] = true;

        $children = $this->findByParent($parent, activeOnly: $activeOnly);
        // Filter the parent itself out (page-with-parent=self bug guard) plus
        // any caller-supplied exclude ids.
        $children = array_values(array_filter(
            $children,
            static fn(Page $p): bool => ($p->id() ?? 0) !== $parent
                && ! \in_array($p->id() ?? 0, $excludeIds, true)
                && ! \in_array($p->parent, $excludeIds, true),
        ));
        if ($children === []) {
            return;
        }
        $tree[$parent] = $children;
        if ($maxDepth > 0 && $depth >= $maxDepth) {
            return;
        }
        foreach ($children as $child) {
            $this->walkLevels(
                $child->id() ?? 0,
                $depth + 1,
                $maxDepth,
                $activeOnly,
                $excludeIds,
                $tree,
                $visited,
            );
        }
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
