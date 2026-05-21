<?php

declare(strict_types=1);

namespace Scriptor\Boot\Cli;

use Imanager\Domain\Category;
use Imanager\Domain\Field;
use Imanager\Domain\Item;
use Imanager\Storage\CategoryRepository;
use Imanager\Storage\FieldRepository;
use Imanager\Storage\ItemRepository;
use League\Container\Container;
use Scriptor\Boot\App;
use Scriptor\Boot\ImanagerBootstrap;

/**
 * `bin/scriptor install` implementation.
 *
 * Seeds a fresh Scriptor database with the Pages + Users categories,
 * their fields, an admin user, and one Home page so the frontend
 * renders on first request. Refuses to run if any of those already
 * exist (idempotent via real-state check, not lock-files). Reads the
 * admin password from `--password`, then `SCRIPTOR_ADMIN_PASSWORD`,
 * then a TTY prompt. Enforces a 12-character minimum and rejects a
 * small blacklist of obvious defaults.
 *
 * See `docs/scriptor-install-cli-plan.md` for the security
 * rationale (G1..G6) and the design alternatives that were rejected.
 */
final class InstallCommand
{
    private const EXIT_OK             = 0;
    private const EXIT_ALREADY        = 1;
    private const EXIT_BAD_PASSWORD   = 2;
    private const EXIT_NOT_CLI        = 3;
    private const EXIT_UNEXPECTED     = 4;
    private const EXIT_NEED_YES_FLAG  = 4;

    /**
     * Hard-coded blacklist of obvious-default passwords. Lower-case
     * comparison; includes this project's old demo password plus the
     * top entries from the 2024 NCSC list. Not exhaustive; the
     * 12-char minimum is the primary defence.
     *
     * @var list<string>
     */
    private const PASSWORD_BLACKLIST = [
        'admin',
        'administrator',
        'password',
        'password123',
        'qwerty12345',
        'gt5nlazzybob',
        'scriptor',
        'scriptor123',
        'changeme',
        'letmein12345',
    ];

    private const MIN_PASSWORD_LENGTH = 12;

    public function __construct(
        private readonly string $scriptorRoot,
        private readonly Console $console,
        private readonly PasswordPrompt $passwordPrompt,
    ) {
    }

    /**
     * @param array{
     *     password?: string,
     *     username?: string,
     *     email?: string,
     *     db?: string,
     *     yes?: bool,
     * } $options
     */
    public function run(array $options): int
    {
        try {
            return $this->runUnsafe($options);
        } catch (\Throwable $e) {
            $this->console->errln('Install failed: ' . $e->getMessage());
            $this->console->errln('  in ' . $e->getFile() . ':' . $e->getLine());
            return self::EXIT_UNEXPECTED;
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function runUnsafe(array $options): int
    {
        $username = isset($options['username']) && \is_string($options['username']) && $options['username'] !== ''
            ? $options['username']
            : 'admin';
        $email = isset($options['email']) && \is_string($options['email']) && $options['email'] !== ''
            ? $options['email']
            : 'admin@localhost';
        $skipConfirm = ! empty($options['yes']);

        $databasePath = isset($options['db']) && \is_string($options['db']) && $options['db'] !== ''
            ? $options['db']
            : $this->scriptorRoot . '/data/imanager.db';

        // G5: explicit confirmation. The --yes flag is the only escape
        // hatch, intended for CI / Docker entrypoints. Stdin must be a
        // TTY for the interactive path; if it isn't and --yes wasn't
        // passed, bail with a hint rather than hang.
        if (! $skipConfirm) {
            if (! $this->console->stdinIsTty()) {
                $this->console->errln(
                    'Refusing to proceed: stdin is not a TTY and --yes was not given.'
                );
                $this->console->errln(
                    'Pass --yes explicitly when running non-interactively (CI / Docker).'
                );
                return self::EXIT_NEED_YES_FLAG;
            }
            $this->console->writeln('About to seed ' . $databasePath . '.');
            $confirmation = $this->console->prompt('Type INSTALL to proceed: ');
            if ($confirmation !== 'INSTALL') {
                $this->console->errln('Aborted (confirmation phrase did not match).');
                return self::EXIT_UNEXPECTED;
            }
        }

        // Boot the same iManager container the request lifecycle uses.
        // This also runs SchemaManager::migrate(), so the install command
        // is safe to invoke against an empty file: tables get created
        // before we touch any row.
        $container = $this->bootContainer($databasePath);

        /** @var CategoryRepository $categories */
        $categories = $container->get(CategoryRepository::class);
        /** @var FieldRepository $fields */
        $fields = $container->get(FieldRepository::class);
        /** @var ItemRepository $items */
        $items = $container->get(ItemRepository::class);

        // G2: real-state check. No lock-files; the DB itself is the
        // source of truth. Existing Pages category = already installed.
        if ($categories->findBySlug('pages') !== null) {
            $this->console->errln(
                'Scriptor is already installed (Pages category exists in '
                . $databasePath . ').'
            );
            $this->console->errln(
                'To start over, delete the database file explicitly and '
                . 'rerun this command.'
            );
            return self::EXIT_ALREADY;
        }

        // G3/G4: resolve and validate the password before any DB writes.
        // Failing here leaves the database untouched.
        $password = $this->resolvePassword($options);
        $validationError = $this->validatePassword($password);
        if ($validationError !== null) {
            $this->console->errln('Invalid admin password: ' . $validationError);
            return self::EXIT_BAD_PASSWORD;
        }

        $this->console->writeln('[1/4] Creating Pages category and 7 fields...');
        $pagesCategoryId = $this->ensurePagesCategory($categories, $fields);

        $this->console->writeln('[2/4] Creating Users category and 3 fields...');
        $usersCategoryId = $this->ensureUsersCategory($categories, $fields);

        $this->console->writeln('[3/4] Creating admin user...');
        $this->createAdminUser($items, $usersCategoryId, $username, $email, $password);

        $this->console->writeln('[4/4] Seeding Home page...');
        $this->createHomePage($items, $pagesCategoryId);

        $this->console->writeln('');
        $this->console->writeln('Scriptor is ready.');
        $this->console->writeln('  Frontend:    http://your-host/');
        $this->console->writeln('  Editor:      http://your-host/editor/');
        $this->console->writeln('  Admin user:  ' . $username);
        $this->console->writeln('  Database:    ' . $databasePath);

        return self::EXIT_OK;
    }

    private function bootContainer(string $databasePath): Container
    {
        // Mirror the request-time boot sequence so behaviour is identical.
        // ImanagerBootstrap::create() calls DefaultBootstrap::boot() which
        // applies any pending schema migrations to a fresh file.
        $container = ImanagerBootstrap::create(
            $this->scriptorRoot,
            ['databasePath' => $databasePath],
        );
        App::set($container);
        return $container;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function resolvePassword(array $options): string
    {
        if (isset($options['password']) && \is_string($options['password']) && $options['password'] !== '') {
            return $options['password'];
        }
        $envPassword = \getenv('SCRIPTOR_ADMIN_PASSWORD');
        if (\is_string($envPassword) && $envPassword !== '') {
            return $envPassword;
        }
        // TTY prompt path. Returns empty string when no TTY is available,
        // which validatePassword() will reject.
        return $this->passwordPrompt->readWithConfirmation(self::MIN_PASSWORD_LENGTH);
    }

    private function validatePassword(string $password): ?string
    {
        if ($password === '') {
            return 'no password supplied (use --password=..., $SCRIPTOR_ADMIN_PASSWORD, or run from a TTY)';
        }
        if (\strlen($password) < self::MIN_PASSWORD_LENGTH) {
            return 'too short (need ' . self::MIN_PASSWORD_LENGTH . '+ characters)';
        }
        if (\in_array(\strtolower($password), self::PASSWORD_BLACKLIST, true)) {
            return 'password is on the blocklist of obvious defaults';
        }
        return null;
    }

    private function ensurePagesCategory(
        CategoryRepository $categories,
        FieldRepository $fields,
    ): int {
        $now = \time();
        $category = $categories->ensure(new Category(
            id: null,
            name: 'Pages',
            slug: 'pages',
            position: 1,
            created: $now,
            updated: $now,
        ));

        $categoryId = $category->id ?? throw new \RuntimeException('Pages category save returned no id');

        // Field set matches docker/seed-demo.sql 1:1 so the editor's
        // existing Pages form keeps working without any further changes.
        $definitions = [
            Field::slug($categoryId, 'slug', 'Slug')->position(1),
            Field::text($categoryId, 'parent', 'Parent')->position(2),
            Field::text($categoryId, 'pagetype', 'Page Type')->position(3),
            Field::text($categoryId, 'menu_title', 'Enter menu title')->position(4),
            Field::longText($categoryId, 'content', 'Content')->position(5),
            Field::text($categoryId, 'template', 'Page template')->position(6),
            Field::file($categoryId, 'images', 'Images')->position(7),
        ];
        foreach ($definitions as $field) {
            $fields->ensure($field);
        }

        return $categoryId;
    }

    private function ensureUsersCategory(
        CategoryRepository $categories,
        FieldRepository $fields,
    ): int {
        $now = \time();
        $category = $categories->ensure(new Category(
            id: null,
            name: 'Users',
            slug: 'users',
            position: 2,
            created: $now,
            updated: $now,
        ));

        $categoryId = $category->id ?? throw new \RuntimeException('Users category save returned no id');

        $definitions = [
            Field::text($categoryId, 'role', 'Role')->position(1),
            Field::text($categoryId, 'email', 'Email')->position(2),
            Field::password($categoryId, 'password', 'Password')->position(3),
        ];
        foreach ($definitions as $field) {
            $fields->ensure($field);
        }

        return $categoryId;
    }

    private function createAdminUser(
        ItemRepository $items,
        int $usersCategoryId,
        string $username,
        string $email,
        string $password,
    ): void {
        $hash = \password_hash($password, \PASSWORD_BCRYPT);
        if ($hash === false) {
            throw new \RuntimeException('password_hash() returned false');
        }
        $now = \time();
        $items->save(new Item(
            id: null,
            categoryId: $usersCategoryId,
            name: $username,
            label: null,
            position: 1,
            active: true,
            data: [
                'role'     => 'siteadmin',
                'email'    => $email,
                'password' => $hash,
            ],
            created: $now,
            updated: $now,
        ));
    }

    private function createHomePage(ItemRepository $items, int $pagesCategoryId): void
    {
        $now = \time();
        $items->save(new Item(
            id: null,
            categoryId: $pagesCategoryId,
            name: 'Home',
            label: null,
            position: 1,
            active: true,
            data: [
                'slug'       => 'home',
                'parent'     => '0',
                'pagetype'   => '',
                'menu_title' => 'Home',
                'content'    => "# Welcome to Scriptor\n\nThis is your home page. Log in at `/editor/` to edit it or add more pages.",
                'template'   => 'home',
                'images'     => [],
            ],
            created: $now,
            updated: $now,
        ));
    }
}
