<?php

declare(strict_types=1);

namespace Scriptor\Boot\Editor;

use Imanager\Domain\Item;
use Imanager\Query\Operator;
use Imanager\Query\Query;
use Imanager\Storage\CategoryRepository;
use Imanager\Storage\ItemRepository;

/**
 * Read access to the Users category for the editor's auth/profile flows.
 *
 * The Users category is created during the legacy Scriptor install
 * (slug = `users`); items hold `name`, plus field values for
 * `password` (bcrypt hash from PasswordFieldType), `email` and `role`.
 */
final readonly class UserRepository
{
    public int $categoryId;

    public function __construct(
        private CategoryRepository $categories,
        private ItemRepository $items,
        string $categorySlug = 'users',
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

    public function find(int $id): ?Item
    {
        $user = $this->items->find($id);
        return $user !== null && $user->categoryId === $this->categoryId ? $user : null;
    }

    public function findByName(string $name): ?Item
    {
        $query = (new Query($this->categoryId))
            ->where('name', Operator::Eq, $name)
            ->limit(1);
        foreach ($this->items->query($query) as $user) {
            return $user;
        }
        return null;
    }
}
