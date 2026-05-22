<?php

declare(strict_types=1);

namespace Scriptor\Boot\Frontend;

use Imanager\Cache\FilesystemCache;
use Imanager\Files\FileStorage;
use Imanager\Files\ImageProcessor;
use Imanager\Http\Request;
use Imanager\Http\SessionStore;
use Imanager\Http\UrlSegments;
use Imanager\Storage\FileRepository;
use Imanager\Templating\TemplateRenderer;
use Imanager\Validation\Sanitizer as ImanagerSanitizer;
use League\Container\Container;
use Psr\EventDispatcher\EventDispatcherInterface;
use Scriptor\Boot\Events\Frontend\ContentRendering;
use Scriptor\Boot\Events\Frontend\PageResolved;
use Scriptor\Boot\Events\Frontend\PageResolving;
use Scriptor\Boot\Events\Frontend\RouteNotFound;

/**
 * Frontend renderer for the public Scriptor site, replacing the legacy
 * `Scriptor\Core\Site` for the duration of Phase 14b.
 *
 * Holds the `$site` surface that bundled themes consume:
 *   - properties: `siteUrl`, `themeUrl`, `version`, `config`, `page`,
 *     `urlSegments`, `input`, `sanitizer`, `pages`,
 *     `templateParser`, `cache`
 *   - rendering: `render($element)` with overridable hooks; theme
 *     subclasses override the `render*()` methods. The `messages`
 *     element renders the pending `$msgs[]` queue as HTML.
 *   - utilities: `getBasePath()`, `getPageUrl()`, `addMsg()`,
 *     `throw404()`, `getTCP()`, `templateName()`.
 *
 * Themes extend this class and override `render()`, individual
 * `render*()` methods, or `init()` to set theme-specific config.
 */
class Site
{
    public string $siteUrl;
    public string $themeUrl;
    public string $version = '2.0.0-dev';
    public ?Page $page = null;
    public UrlSegments $urlSegments;
    public Request $input;
    public Sanitizer $sanitizer;
    public SessionStore $session;
    public PageRepository $pages;
    public TemplateRenderer $templateParser;
    public FilesystemCache $cache;
    public ImageUrlBuilder $images;
    public FileRepository $files;
    public FileStorage $fileStorage;

    /** @var array<string, mixed> */
    public array $config;

    /**
     * Pending in-page messages set via {@see addMsg()}; consumed by
     * `renderMessages()` (or theme subclass).
     *
     * @var list<array{type: string, value: string, header?: string}>
     */
    public array $msgs = [];

    /** @var array<string, mixed> Theme-config payload exposed via getTCP(). */
    protected array $themeConfig = [];

    /**
     * Defaults for theme-config keys touched by the bundled "basic" theme's
     * default render path. Lets the page render end-to-end even before a
     * theme subclass attaches with real values.
     */
    private const TCP_DEFAULTS = [
        'site_name'      => 'Scriptor',
        'copyright_info' => '',
        'footer'         => [
            'sub_heading'           => '',
            'sub_paragraph'         => '',
            'submit_button_label'   => 'Subscribe',
            'middle_heading'        => '',
            'middle_paragraph'      => '',
        ],
    ];

    /**
     * Session cookie name. Matches `editor/index.php` so a logged-in
     * editor user shares one cookie across both surfaces; the bag
     * key below keeps frontend flash messages out of the editor's
     * own queue.
     */
    private const SESSION_NAME = 'IMSESSID';

    /**
     * SessionStore key the flash bag lives under. Distinct from the
     * editor's `msgs` key so a flash queued on the frontend is not
     * accidentally drained by the editor on the user's next admin
     * page hit (and vice versa).
     */
    private const FLASH_KEY = 'frontend_msgs';

    /**
     * @param array<string, mixed> $config Scriptor config array
     */
    public function __construct(
        protected Container $container,
        array $config,
        protected string $scriptorRoot,
    ) {
        $this->config = $config;
        $this->sanitizer = new Sanitizer($container->get(ImanagerSanitizer::class));
        $this->session = $container->get(SessionStore::class);
        // If a session cookie is already in the request, open the
        // session now so {@see renderMsgs()} can drain the flash bag
        // without trying to call session_start() mid-template (after
        // the theme's ob_start but before headers are guaranteed to
        // still be open). Anonymous visitors with no cookie skip the
        // session entirely; the public site stays stateless by
        // default. flashMsg() opens its own session when called.
        if (isset($_COOKIE[self::SESSION_NAME])) {
            self::ensureSessionStarted();
        }
        $this->pages = $container->get(PageRepository::class);
        $this->cache = $container->get(FilesystemCache::class);
        $this->files = $container->get(FileRepository::class);
        $this->fileStorage = $container->get(FileStorage::class);
        $this->images = new ImageUrlBuilder(
            $container->get(ImageProcessor::class),
            $scriptorRoot,
        );
        $this->templateParser = new TemplateRenderer();
        $this->input = Request::fromGlobals();
        $this->siteUrl  = self::detectSiteUrl();
        $this->themeUrl = $this->siteUrl . '/themes/' . $this->config['theme_path'];
        $this->urlSegments = UrlSegments::fromPath($_SERVER['REQUEST_URI'] ?? '/');
        $this->init();
    }

    /**
     * Theme-extension point — subclasses populate {@see $themeConfig} and
     * any other state once construction has wired the standard services.
     */
    protected function init(): void
    {
    }

    /**
     * Resolve the requested page from the URL or fall back to 404.
     *
     * The actual resolution lives in plugins now. Built-in DB-backed
     * slug resolution is shipped as `DbPagesResolverPlugin` (registered
     * as a core plugin in boot.php). This method only orchestrates the
     * event dispatch:
     *
     * 1. Dispatch {@see PageResolving}. Listeners can fill the
     *    `resolution` slot; first writer wins by convention.
     * 2. If something resolved, dispatch {@see PageResolved} for
     *    read-only side-effect listeners and return.
     * 3. Otherwise dispatch {@see RouteNotFound} as a last-chance
     *    resolver. If it still produces nothing, throw the 404.
     */
    public function execute(): void
    {
        $dispatcher = $this->container->get(EventDispatcherInterface::class);

        $resolving = new PageResolving($this->urlSegments);
        $dispatcher->dispatch($resolving);

        if ($resolving->resolution !== null) {
            $this->page = $resolving->resolution;
            $dispatcher->dispatch(new PageResolved($this->page));
            return;
        }

        $notFound = new RouteNotFound($this->urlSegments);
        $dispatcher->dispatch($notFound);

        if ($notFound->resolution !== null) {
            $this->page = $notFound->resolution;
            $dispatcher->dispatch(new PageResolved($this->page));
            return;
        }

        $this->throw404();
    }

    public function render(string $element): ?string
    {
        return match ($element) {
            'content'    => $this->renderContent(),
            'navigation' => $this->renderNavigation(),
            'messages'   => $this->renderMsgs(),
            // Theme-extension hooks. Theme subclasses override these by
            // returning their own markup before delegating to parent.
            'hero',
            'mainNavItems',
            'footerNav',
            'socIcons',
            'archivesContent',
            'archiveNav',
            'pagination',
            'articleDate',
            'emptyCsrfFields' => '',
            default => null,
        };
    }

    /**
     * Captures the output buffer started by `template.php` and returns it.
     * Subclasses (BasicTheme) layer caching on top by overriding.
     */
    public function cache(): string
    {
        $output = ob_get_clean();
        return $output === false ? '' : $output;
    }

    public function pages(): PageRepository
    {
        return $this->pages;
    }

    /**
     * Template name driving `template.php`. Defaults to `$page->template`;
     * theme subclasses (e.g. BasicTheme blog routing) override to inject
     * a different template without mutating the page DTO.
     */
    public function currentTemplate(): string
    {
        return $this->page?->template ?? '';
    }

    /**
     * Theme-config-property accessor — `$site->getTCP('site_name')`,
     * `$site->getTCP('footer')['sub_heading']`. Falls back to the bundled
     * defaults so the default render path keeps working when a theme has
     * not populated the relevant key.
     */
    public function getTCP(string $key): mixed
    {
        return $this->themeConfig[$key] ?? self::TCP_DEFAULTS[$key] ?? null;
    }

    /**
     * Append a status message to be rendered by the theme on the next
     * `$site->render('messages')` call. The same shape the legacy site
     * stored in `$_SESSION['msgs']`.
     */
    public function addMsg(string $type, string $text, string $header = ''): void
    {
        $msg = ['type' => $type, 'value' => $text];
        if ($header !== '') {
            $msg['header'] = $header;
        }
        $this->msgs[] = $msg;
    }

    /**
     * Push a flash message that survives a redirect via session
     * storage. Use for the POST-redirect-GET pattern: after a
     * successful action handler, queue the result with `flashMsg()`,
     * `header('Location: ...', true, 303)`, exit; the next GET drains
     * the bag through `renderMsgs()`. Opens the session lazily so a
     * theme that never calls `flashMsg()` keeps the public site
     * cookie-free.
     */
    public function flashMsg(string $type, string $text, string $header = ''): void
    {
        self::ensureSessionStarted();
        $bag = (array) $this->session->get(self::FLASH_KEY, []);
        $msg = ['type' => $type, 'value' => $text];
        if ($header !== '') {
            $msg['header'] = $header;
        }
        $bag[] = $msg;
        $this->session->set(self::FLASH_KEY, $bag);
    }

    /**
     * Render the pending message queue as a `<ul class="messages">`
     * list and drain it. Folds in any flash messages queued via
     * {@see flashMsg()} on a previous request before rendering, so
     * both in-request and post-redirect messages render in one pass.
     * Empty queue returns an empty string so a template can call
     * `<?= $site->render('messages') ?>` without conditionals.
     * Markup mirrors {@see Editor::renderMsgs()} so the frontend and
     * editor share the same CSS hooks. The `value` is emitted raw —
     * `addMsg()`/`flashMsg()` callers are trusted to either pass
     * static strings or escape themselves; the `type` and `header`
     * are HTML-escaped because they are intended to be plain text.
     */
    public function renderMsgs(): string
    {
        $flash = (array) $this->session->get(self::FLASH_KEY, []);
        if ($flash !== []) {
            foreach ($flash as $msg) {
                if (\is_array($msg) && isset($msg['type'], $msg['value'])) {
                    $this->msgs[] = [
                        'type'   => (string) $msg['type'],
                        'value'  => (string) $msg['value'],
                        'header' => isset($msg['header']) ? (string) $msg['header'] : '',
                    ];
                }
            }
            $this->session->remove(self::FLASH_KEY);
        }
        if ($this->msgs === []) {
            return '';
        }
        $html = '<ul class="messages">';
        foreach ($this->msgs as $msg) {
            $html .= sprintf(
                '<li class="msg msg-%s">%s%s</li>',
                htmlspecialchars((string) $msg['type'], \ENT_QUOTES),
                isset($msg['header']) && $msg['header'] !== ''
                    ? '<strong>' . htmlspecialchars((string) $msg['header'], \ENT_QUOTES) . '</strong> '
                    : '',
                (string) $msg['value'],
            );
        }
        $html .= '</ul>';
        $this->msgs = [];
        return $html;
    }

    /**
     * Reconstruct the URL prefix beneath which Scriptor is mounted —
     * everything left of the first slug segment, with no query string.
     * Useful when themes need to produce internal links without making
     * assumptions about the deployed path.
     */
    public function getBasePath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = explode('?', $uri, 2)[0];
        // Strip the segments path off the request URI in either form
        // (with or without trailing slash) so the base path always ends
        // with a single `/`.
        foreach ([
            $this->urlSegments->path(trailingSlash: true),
            $this->urlSegments->path(trailingSlash: false),
        ] as $segmentsPath) {
            if ($segmentsPath !== '' && str_ends_with($path, $segmentsPath)) {
                $path = substr($path, 0, -\strlen($segmentsPath));
                break;
            }
        }
        if ($path === '' || ! str_ends_with($path, '/')) {
            $path .= '/';
        }
        return $path === '' ? '/' : $path;
    }

    /**
     * Build the canonical URL path for a page by walking its parent
     * chain. Mirrors the legacy `Site::getPageUrl()` shape: slugs joined
     * with trailing slashes, root page (id=1) collapses to empty.
     *
     * Tolerates broken parent chains (self-references or cycles introduced
     * by old data) by tracking visited ids and bailing out on a repeat.
     */
    public function getPageUrl(Page $page): string
    {
        return $this->buildPageUrl($page, []);
    }

    /**
     * @param array<int, true> $visited
     */
    private function buildPageUrl(Page $page, array $visited): string
    {
        $id = $page->id() ?? 0;
        if (isset($visited[$id])) {
            // Cycle detected — collapse to the page's own slug to avoid recursion.
            return $page->slug !== '' ? $page->slug . '/' : '';
        }
        $visited[$id] = true;

        $url = '';
        if ($page->parent !== 0 && $page->parent !== $id) {
            $parent = $this->pages->find($page->parent);
            if ($parent !== null) {
                $url .= $this->buildPageUrl($parent, $visited);
            }
        }
        if ($id !== 1) {
            $url .= $page->slug . '/';
        }
        return $url;
    }

    public function throw404(): never
    {
        header('HTTP/1.0 404 Not Found');
        $themeRoot = $this->scriptorRoot . '/themes/' . $this->config['theme_path'];
        $notFound = $themeRoot . ($this->config['404page'] ?? '404') . '.php';
        if (is_file($notFound)) {
            $site = $this; // exposed to the included template
            include $notFound;
        } else {
            echo '<h1>404 — Not Found</h1>';
        }
        exit;
    }

    protected function renderContent(): string
    {
        if ($this->page === null) {
            return '';
        }

        // Give plugins a shot at substituting the rendered content.
        // The markdown-pages plugin, for instance, pre-computes
        // CommonMark output during PageResolving and returns it here
        // so its virtual pages don't get re-processed by Parsedown.
        $dispatcher = $this->container->get(EventDispatcherInterface::class);
        $event = new ContentRendering($this->page);
        $dispatcher->dispatch($event);

        if ($event->html !== null) {
            return $event->html;
        }

        // Default path: Parsedown in safe mode (escapes embedded HTML,
        // rejects non-whitelisted URL schemes).
        return $this->sanitizer->markdown($this->page->content);
    }

    /**
     * Public URL for a static asset of the active theme (lives under
     * public/themes/<theme>/). Pair with the PHP-source half at
     * <root>/themes/<theme>/.
     */
    public function themeAssetUrl(string $relative): string
    {
        return rtrim($this->themeUrl, '/') . '/' . ltrim($relative, '/');
    }

    /**
     * Public URL for an editor static asset (lives under
     * public/editor-assets/). Used by frontend templates that embed
     * admin-side resources — e.g. prism CSS shared between editor
     * and blog.
     */
    public function editorAssetUrl(string $relative): string
    {
        return rtrim($this->siteUrl, '/') . '/editor-assets/' . ltrim($relative, '/');
    }

    protected function renderNavigation(): string
    {
        $top = $this->pages->findActiveByParent(0);
        if ($top === []) {
            return '';
        }
        $items = '';
        foreach ($top as $entry) {
            $url = $this->siteUrl . '/'
                . ($entry->id() !== 1 ? $entry->slug . '/' : '');
            $title = $entry->menu_title !== '' ? $entry->menu_title : $entry->name;
            $items .= sprintf(
                '<li><a href="%s">%s</a></li>',
                htmlspecialchars($url, \ENT_QUOTES),
                htmlspecialchars($title, \ENT_QUOTES),
            );
        }
        return $items;
    }

    private static function detectSiteUrl(): string
    {
        // Prefer X-Forwarded-Proto when present — TLS-terminating reverse
        // proxies (nginx-proxy, traefik, …) reach PHP over plain HTTP
        // and signal the original scheme via this header. First value
        // wins for chained proxies ("https,http").
        $proto = $_SERVER['HTTP_X_FORWARDED_PROTO']
            ?? ((! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
        $scheme = strtolower(trim(explode(',', $proto)[0])) === 'https' ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host;
    }

    /**
     * Start the PHP session if it is not already active, with the
     * same cookie params {@see editor/index.php} uses so the editor
     * and frontend share one session. No-op on an already-active
     * session and safe to call multiple times.
     *
     * Cookie flags mirror the security baseline:
     *   - HttpOnly        — JS cannot read the session cookie
     *   - SameSite=Lax    — survives top-level same-site POST→redirect
     *                        but blocks third-party cross-site sends
     *   - Secure          — only on HTTPS; auto-detected from
     *                        X-Forwarded-Proto behind a TLS terminator
     */
    private static function ensureSessionStarted(): void
    {
        if (\session_status() === \PHP_SESSION_ACTIVE) {
            return;
        }
        \session_name(self::SESSION_NAME);
        $proto  = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null;
        $secure = $proto === 'https'
            || (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        \session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        \session_start();
    }
}
