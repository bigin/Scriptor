<?php

/**
 * Demo-image seed.
 *
 * Idempotent: running this against an already-seeded database is a no-op
 * (we exit as soon as we see a `pages` category). The entrypoint script
 * calls us on first container start, after `schema:migrate` has run.
 *
 * What we create:
 *   - `pages`   category + 7 fields (slug, parent, pagetype, menu_title,
 *                                    content, template, images)
 *   - `users`   category + 3 fields (role, email, password)
 *   - One admin user — name=admin, password=scriptor (bcrypt-hashed via
 *     PasswordFieldType)
 *   - One example "Hello, world" page so the editor's pages-list and
 *     the public front have something to render.
 */

declare(strict_types=1);

use Imanager\Domain\Category;
use Imanager\Domain\Field;
use Imanager\Enum\FieldType;
use Imanager\Storage\CategoryRepository;
use Imanager\Storage\FieldRepository;
use Imanager\Storage\ItemRepository;
use Imanager\Domain\Item;
use Scriptor\Boot\ImanagerBootstrap;

require __DIR__ . '/../vendor/autoload.php';

$container  = ImanagerBootstrap::create(\dirname(__DIR__));
$categories = $container->get(CategoryRepository::class);
$fields     = $container->get(FieldRepository::class);
$items      = $container->get(ItemRepository::class);

// Idempotency guard.
if ($categories->findBySlug('pages') !== null) {
    fwrite(\STDOUT, "[seed] pages category already exists — skipping.\n");
    exit(0);
}

// -- Pages category --
fwrite(\STDOUT, "[seed] creating Pages category…\n");
$pages = $categories->save(new Category(null, 'Pages', 'pages'));
\assert($pages->id !== null);

$pageFieldDefs = [
    ['slug',       FieldType::Slug,       0],
    ['parent',     FieldType::Integer,    1],
    ['pagetype',   FieldType::Text,       2],
    ['menu_title', FieldType::Text,       3],
    ['content',    FieldType::LongText,   4],
    ['template',   FieldType::Text,       5],
    ['images',     FieldType::Imageupload, 6],
];
foreach ($pageFieldDefs as [$name, $type, $position]) {
    $fields->save(new Field(
        id: null,
        categoryId: $pages->id,
        name: $name,
        label: ucfirst(str_replace('_', ' ', $name)),
        type: $type,
        position: $position,
    ));
}

// -- Users category --
fwrite(\STDOUT, "[seed] creating Users category…\n");
$users = $categories->save(new Category(null, 'Users', 'users'));
\assert($users->id !== null);

$userFieldDefs = [
    ['role',     FieldType::Text,     0],
    ['email',    FieldType::Text,     1],
    ['password', FieldType::Password, 2],
];
foreach ($userFieldDefs as [$name, $type, $position]) {
    $fields->save(new Field(
        id: null,
        categoryId: $users->id,
        name: $name,
        label: ucfirst($name),
        type: $type,
        position: $position,
    ));
}

// -- Admin user --
// PasswordFieldType validates `scriptor` (8 chars, meets default minLength)
// and bcrypts it on save. Do NOT pre-hash here — let the field type do it.
fwrite(\STDOUT, "[seed] creating admin user (admin/scriptor)…\n");
$items->save(new Item(
    id: null,
    categoryId: $users->id,
    name: 'admin',
    label: 'Administrator',
    data: [
        'role'     => 'admin',
        'email'    => 'admin@example.com',
        'password' => 'scriptor',
    ],
));

// -- Example page --
fwrite(\STDOUT, "[seed] creating Hello-world example page…\n");
$items->save(new Item(
    id: null,
    categoryId: $pages->id,
    name: 'hello-world',
    label: 'Hello, world',
    data: [
        'slug'       => 'hello-world',
        'parent'     => 0,
        'pagetype'   => 'page',
        'menu_title' => 'Hello',
        'template'   => 'default',
        'content'    => <<<MD
        # Welcome to Scriptor 2.0

        You're looking at the demo container. The page you're reading lives
        in `data/imanager.db` as a single item in the `pages` category.

        Things to try:

        - **Editor:** sign in at `/editor/` with `admin / scriptor`.
        - **Add a page:** Pages → New, set a slug, save, see it appear in
          the public front automatically.
        - **CLI:** `docker compose exec scriptor vendor/bin/imanager schema:status --db=data/imanager.db`.

        Read the [iManager docs](https://github.com/bigin/imanager/tree/main/docs)
        for the storage / field-type / query model behind all this.
        MD,
    ],
));

fwrite(\STDOUT, "[seed] done.\n");
