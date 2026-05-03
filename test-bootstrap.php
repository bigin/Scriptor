<?php

declare(strict_types=1);

/**
 * Phase 14a smoke page.
 *
 * Boots the full Scriptor + iManager 2.0 container and reads back enough
 * data from the migrated SQLite database to prove the wiring works
 * end-to-end. Delete after Phase 14a is merged or keep as a developer
 * sanity check — it makes no assertions about the rest of Scriptor.
 */

require __DIR__ . '/vendor/autoload.php';

use Imanager\Cache\FilesystemCache;
use Imanager\Field\FieldTypeRegistry;
use Imanager\Files\FileStorage;
use Imanager\Files\ImageProcessor;
use Imanager\Search\FullTextSearch;
use Imanager\Storage\CategoryRepository;
use Imanager\Storage\FieldRepository;
use Imanager\Storage\FileRepository;
use Imanager\Storage\ItemRepository;
use Scriptor\Boot\App;
use Scriptor\Boot\ImanagerBootstrap;

App::set(ImanagerBootstrap::create(__DIR__));
$container = App::container();

$categories = $container->get(CategoryRepository::class)->findAll();
$fields     = $container->get(FieldRepository::class);
$items      = $container->get(ItemRepository::class);
$files      = $container->get(FileRepository::class);

$payload = [
    'imanager_version' => \Imanager\Imanager::VERSION,
    'services' => [
        'CategoryRepository' => $container->get(CategoryRepository::class)::class,
        'FieldRepository'    => $fields::class,
        'ItemRepository'     => $items::class,
        'FileRepository'     => $files::class,
        'FieldTypeRegistry'  => $container->get(FieldTypeRegistry::class)::class,
        'FullTextSearch'     => $container->get(FullTextSearch::class)::class,
        'FilesystemCache'    => $container->get(FilesystemCache::class)::class,
        'FileStorage'        => $container->get(FileStorage::class)::class,
        'ImageProcessor'     => $container->get(ImageProcessor::class)::class,
    ],
    'categories' => array_map(static fn($c) => [
        'id'       => $c->id,
        'name'     => $c->name,
        'slug'     => $c->slug,
        'position' => $c->position,
        'fields'   => array_map(
            static fn($f) => ['name' => $f->name, 'type' => $f->type],
            $fields->findByCategory($c->id ?? 0),
        ),
        'item_count' => count($items->findByCategory($c->id ?? 0)),
    ], $categories),
];

header('Content-Type: application/json; charset=utf-8');
echo json_encode($payload, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
