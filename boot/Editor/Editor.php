<?php

declare(strict_types=1);

namespace Scriptor\Boot\Editor;

use Imanager\Http\Csrf;
use Imanager\Http\Request;
use Imanager\Http\SessionStore;
use Imanager\Http\UrlSegments;
use Imanager\Templating\TemplateRenderer;
use Imanager\Validation\Sanitizer as ImanagerSanitizer;
use League\Container\Container;
use Psr\Log\LoggerInterface;
use Scriptor\Boot\Frontend\Sanitizer;

/**
 * Phase 14c-1 Editor surface — what the legacy editor theme files
 * (theme/template.php, theme/header.php, theme/summary.php, plus the
 * module render output) need from `$editor`:
 *
 *   - Properties:  config, siteUrl, themeUrl, version, csrf, msgs[],
 *                  pageTitle, pageContent, breadcrumbs, jsConfig,
 *                  i18n, input, sanitizer, urlSegments, session, logger
 *   - Helpers:     getProperty(), getResources(), addMsg(), renderMsgs(),
 *                  isLoggedIn(), currentUserId(), templateName()
 *
 * Modules (AuthModule today, more in 14c-2…14c-6) populate `pageTitle`
 * and `pageContent`; the editor template prints them straight through.
 */
class Editor
{
    /** Host URL, no path (e.g. `https://example.com`). */
    public string $baseUrl;
    /** Admin mount point (e.g. `https://example.com/editor`). */
    public string $siteUrl;
    /** Public URL of the editor's static-asset directory. */
    public string $themeUrl;
    public string $version = '2.0.0-dev';
    public string $pageTitle = 'Scriptor';
    public string $pageContent = '';
    public string $breadcrumbs = '';
    public string $jsConfig = '';

    /** @var list<array{type: string, value: string, header?: string}> */
    public array $msgs = [];

    public Csrf $csrf;
    public Request $input;
    public Sanitizer $sanitizer;
    public UrlSegments $urlSegments;
    public TemplateRenderer $templateParser;
    public SessionStore $session;
    public LoggerInterface $logger;

    /** @var array<string, mixed> */
    public array $config;

    /** @var array<string, string> */
    public array $i18n = [];

    /** @var array{link: list<string>, script: list<string>, script_body: list<string>} */
    private array $resources = [
        'link'        => [],
        'script'      => [],
        'script_body' => [],
    ];

    public function __construct(
        protected Container $container,
        array $config,
        protected string $scriptorRoot,
    ) {
        $this->config = $config;
        $this->csrf = $container->get(Csrf::class);
        $this->session = $container->get(SessionStore::class);
        $this->logger = $container->get(LoggerInterface::class);
        $this->input = Request::fromGlobals();
        $this->sanitizer = new Sanitizer($container->get(ImanagerSanitizer::class));
        $this->templateParser = new TemplateRenderer();

        $this->baseUrl  = self::detectSiteUrl();
        $this->siteUrl  = $this->baseUrl . '/' . trim((string) $config['admin_path'], '/');
        // Editor static assets live at /editor-assets/ in the webroot,
        // independent of the admin_path URL prefix.
        $this->themeUrl = $this->baseUrl . '/editor-assets';
        $this->urlSegments = self::editorSegments($config);

        $this->loadI18n();
    }

    public function isLoggedIn(): bool
    {
        return $this->session->get('loggedin') === true;
    }

    public function currentUserId(): ?int
    {
        $id = $this->session->get('userid');
        return \is_int($id) ? $id : null;
    }

    /**
     * Cookie-based session messages — set by modules during a redirect
     * cycle so the post-redirect render can show them. Drained by
     * `renderMsgs()`.
     */
    public function addMsg(string $type, string $text, string $header = ''): void
    {
        $msg = ['type' => $type, 'value' => $text];
        if ($header !== '') {
            $msg['header'] = $header;
        }
        $this->msgs[] = $msg;
    }

    public function renderMsgs(): string
    {
        $session = (array) $this->session->get('msgs', []);
        if ($session !== []) {
            foreach ($session as $msg) {
                if (\is_array($msg) && isset($msg['type'], $msg['value'])) {
                    $this->msgs[] = [
                        'type'   => (string) $msg['type'],
                        'value'  => (string) $msg['value'],
                        'header' => isset($msg['header']) ? (string) $msg['header'] : '',
                    ];
                }
            }
            $this->session->remove('msgs');
        }

        if ($this->msgs === []) {
            return '';
        }
        $html = '<ul class="messages">';
        foreach ($this->msgs as $msg) {
            $html .= \sprintf(
                '<li class="msg msg-%s">%s%s</li>',
                htmlspecialchars($msg['type'], \ENT_QUOTES),
                isset($msg['header']) && $msg['header'] !== ''
                    ? '<strong>' . htmlspecialchars($msg['header'], \ENT_QUOTES) . '</strong> '
                    : '',
                $msg['value'],
            );
        }
        $html .= '</ul>';
        $this->msgs = [];
        return $html;
    }

    /**
     * Push a flash message that survives a redirect via session storage.
     */
    public function flashMsg(string $type, string $text): void
    {
        $bag = (array) $this->session->get('msgs', []);
        $bag[] = ['type' => $type, 'value' => $text];
        $this->session->set('msgs', $bag);
    }

    public function getProperty(string $name): mixed
    {
        return match ($name) {
            'pageTitle'   => $this->pageTitle,
            'pageContent' => $this->pageContent,
            'breadcrumbs' => $this->breadcrumbs,
            'jsConfig'    => $this->jsConfig,
            'messages'    => $this->renderMsgs(),
            default       => null,
        };
    }

    /**
     * Module-supplied <link>/<script> tags. `position` decides whether
     * the tag goes into <head> or just before </body>.
     */
    public function addResource(string $kind, string $html, string $position = 'head'): void
    {
        $key = $kind === 'script' && $position === 'body' ? 'script_body' : $kind;
        if (! isset($this->resources[$key])) {
            return;
        }
        $this->resources[$key][] = $html;
    }

    public function getResources(string $kind, string $position = 'head'): string
    {
        $key = $kind === 'script' && $position === 'boddy' ? 'script_body' : $kind;
        $key = $kind === 'script' && $position === 'body'  ? 'script_body' : $key;
        return implode("\n", $this->resources[$key] ?? []);
    }

    public function templateName(string $value): string
    {
        return $this->sanitizer->templateName($value);
    }

    /**
     * `tokenName=…&tokenValue=…` ready for appending to a logout/GET URL.
     * Replaces 1.x `$csrf->renderUrl()` shape consumed by editor/theme/header.php.
     */
    public function csrfQueryString(string $name = 'logout_token'): string
    {
        $value = $this->csrf->token($name);
        return '?tokenName=' . rawurlencode($name) . '&tokenValue=' . rawurlencode($value);
    }

    /**
     * Public URL for an editor static asset (CSS, JS, image, font)
     * under public/editor-assets/. Used by editor templates and by any
     * frontend template that needs to embed admin-side resources
     * (e.g. the prism syntax-highlighter css from a blog post).
     */
    public function assetUrl(string $relative): string
    {
        return rtrim($this->themeUrl, '/') . '/' . ltrim($relative, '/');
    }

    private function loadI18n(): void
    {
        $lang = (string) ($this->config['lang'] ?? 'en_US');
        $file = $this->scriptorRoot . '/' . trim((string) $this->config['admin_path'], '/') . '/lang/' . $lang . '.php';
        if (! is_file($file)) {
            return;
        }
        $i18n = [];
        require $file;
        if (\is_array($i18n)) {
            /** @var array<string, string> $i18n */
            $this->i18n = $i18n;
        }
    }

    private static function editorSegments(array $config): UrlSegments
    {
        $admin = trim((string) $config['admin_path'], '/');
        $uri   = $_SERVER['REQUEST_URI'] ?? '/';
        // Strip the editor mount-point so `/editor/auth/logout/` parses
        // into segments [auth, logout].
        $path = parse_url($uri, \PHP_URL_PATH) ?? '/';
        if ($admin !== '' && str_starts_with($path, '/' . $admin)) {
            $path = substr($path, \strlen($admin) + 1);
        }
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }
        return UrlSegments::fromPath($path);
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
}
