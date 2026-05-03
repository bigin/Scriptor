<?php

declare(strict_types=1);

namespace Scriptor\Boot\Frontend;

use Imanager\Cache\FilesystemCache;
use Imanager\Files\FileStorage;
use Imanager\Files\ImageProcessor;
use Imanager\Http\Request;
use Imanager\Http\UrlSegments;
use Imanager\Storage\FileRepository;
use Imanager\Templating\TemplateRenderer;
use Imanager\Validation\Sanitizer as ImanagerSanitizer;
use League\Container\Container;

/**
 * Frontend renderer for the public Scriptor site, replacing the legacy
 * `Scriptor\Core\Site` for the duration of Phase 14b.
 *
 * Holds the `$site` surface that bundled themes consume:
 *   - properties: `siteUrl`, `themeUrl`, `version`, `config`, `page`,
 *     `messages`, `urlSegments`, `input`, `sanitizer`, `pages`,
 *     `templateParser`, `cache`
 *   - rendering: `render($element)` with overridable hooks; theme
 *     subclasses override the `render*()` methods.
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
    public string $messages = '';
    public UrlSegments $urlSegments;
    public Request $input;
    public Sanitizer $sanitizer;
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
     * @param array<string, mixed> $config Scriptor config array
     */
    public function __construct(
        protected Container $container,
        array $config,
        protected string $scriptorRoot,
    ) {
        $this->config = $config;
        $this->sanitizer = new Sanitizer($container->get(ImanagerSanitizer::class));
        $this->pages = new PageRepository(
            $container->get(\Imanager\Storage\CategoryRepository::class),
            $container->get(\Imanager\Storage\ItemRepository::class),
        );
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
        $this->themeUrl = $this->siteUrl . '/site/themes/' . $this->config['theme_path'];
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
     * Resolve the requested page from the URL or fall back to home/404.
     *
     * The lookup honours nested page paths: when multiple pages share the
     * same slug the parent chain is verified by walking back through the
     * URL segments and rejecting mismatches as 404.
     */
    public function execute(): void
    {
        if ($this->urlSegments->isEmpty()) {
            $this->page = $this->pages->findHome();
            if ($this->page === null || ! $this->page->active()) {
                $this->throw404();
            }
            return;
        }

        $slug = $this->urlSegments->last();
        if ($slug === null) {
            $this->page = $this->pages->findHome();
            return;
        }

        $page = $this->pages->findBySlug($this->sanitizer->slug($slug));
        if ($page === null || ! $page->active()) {
            $this->throw404();
            return;
        }

        // Confirm the URL fully matches the page's parent chain so a
        // request like /wrong-parent/articles/ does not silently render
        // the matching child page. The home page (id=1) is reachable
        // both via `/` (already handled above) and via its own slug.
        if ($page->id() !== 1) {
            $expected = '/' . $this->getPageUrl($page);
            $actual   = '/' . $this->urlSegments->path(trailingSlash: true);
            if ($actual !== $expected) {
                $this->throw404();
                return;
            }
        }

        $this->page = $page;
    }

    public function render(string $element): ?string
    {
        return match ($element) {
            'content'    => $this->renderContent(),
            'navigation' => $this->renderNavigation(),
            'messages'   => $this->messages,
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
        $themeRoot = $this->scriptorRoot . '/site/themes/' . $this->config['theme_path'];
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
        $content = $this->page->content;
        $allowHtml = (bool) ($this->config['allowHtmlOutput'] ?? false);
        // Markdown rendering through iManager's Sanitizer (Parsedown +
        // optional HTMLPurifier). When allowHtmlOutput is true the input
        // may already contain raw HTML; either way Parsedown runs and
        // safe-mode strips dangerous tags on entry when html is disabled.
        $rendered = $this->sanitizer->markdown(
            $allowHtml ? $content : htmlspecialchars_decode($content),
        );
        return $rendered;
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
        $scheme = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host;
    }
}
