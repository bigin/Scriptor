<?php

declare(strict_types=1);

namespace Scriptor\Boot\Frontend;

use Imanager\Http\UrlSegments;
use Imanager\Validation\Sanitizer as ImanagerSanitizer;
use League\Container\Container;

/**
 * Phase 14b-1 Frontend\Site — minimal renderer for the public Scriptor site.
 *
 * Replaces the legacy `Scriptor\Core\Site` for the duration of Phase 14b.
 * Holds the `$site` surface that the existing theme files (default.php,
 * template.php, _head.php, _header.php, _footer.php, …) consume:
 *
 *   - `siteUrl`, `themeUrl`, `version`, `config`, `messages`
 *   - `page` (Frontend\Page) once `execute()` resolved it
 *   - `render('content'|'navigation'|'messages'|hero/footerNav/…)` — the
 *     theme-overridable cases return empty strings until 14b-2 hooks
 *     `BasicTheme` back in.
 *   - `cache()` — captures the output buffer; persistence ist 14b-2.
 *
 * The class is intentionally NOT extending the legacy Module hierarchy.
 * Themes that need to extend it can override `render*()` methods directly.
 */
class Site
{
    public string $siteUrl;
    public string $themeUrl;
    public string $version = '2.0.0-dev';
    public ?Page $page = null;
    public string $messages = '';
    public UrlSegments $urlSegments;

    /** @var array<string, mixed> */
    public array $config;

    /** @var array<string, mixed> Theme-config payload exposed via getTCP(). */
    protected array $themeConfig = [];

    public Sanitizer $sanitizer;
    public PageRepository $pages;

    /**
     * Defaults for theme-config keys touched by the bundled "basic" theme's
     * default render path (header, footer, offcanvas). Lets the page render
     * end-to-end even before BasicTheme reattaches in 14b-2 with real values.
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
        $this->siteUrl  = self::detectSiteUrl();
        $this->themeUrl = $this->siteUrl . '/site/themes/' . $this->config['theme_path'];
        $this->urlSegments = UrlSegments::fromPath($_SERVER['REQUEST_URI'] ?? '/');
    }

    /**
     * Resolve the requested page from the URL or fall back to home/404.
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
        $this->page = $page;
    }

    public function render(string $element): ?string
    {
        return match ($element) {
            'content'    => $this->renderContent(),
            'navigation' => $this->renderNavigation(),
            'messages'   => $this->messages,
            // Theme-extension hooks. Themes (BasicTheme in 14b-2) override
            // these by subclassing Site and short-circuiting the parent call.
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
     * Captures the output buffer started by template.php and hands it back.
     * Filesystem caching is wired up by BasicTheme in 14b-2.
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
     * Theme-config-property accessor — `$site->getTCP('site_name')`,
     * `$site->getTCP('footer')['sub_heading']`. Returns the live theme-config
     * value when set (BasicTheme in 14b-2), otherwise a sane default so the
     * default render path doesn't crash on missing entries.
     */
    public function getTCP(string $key): mixed
    {
        return $this->themeConfig[$key] ?? self::TCP_DEFAULTS[$key] ?? null;
    }

    public function throw404(): void
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
        // Markdown rendering through iManager's Sanitizer (Parsedown + optional
        // HTMLPurifier). When allowHtmlOutput is true the input may already
        // contain raw HTML; the markdown call passes it through Parsedown
        // either way and lets safe-mode strip on entry.
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
