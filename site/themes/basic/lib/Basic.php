<?php

declare(strict_types=1);

namespace Themes\Basic;

use Imanager\Query\Pagination;
use Imanager\Templating\PaginationRenderer;
use Imanager\Templating\TemplateRenderer;
use Scriptor\Boot\Frontend\Page;
use Scriptor\Boot\Frontend\Site;
use Themes\Basic\Subscriber\MailChimp;

/**
 * The bundled Basic theme on iManager 2.0.
 *
 * Renders the public site (default page, blog list, single article, contact)
 * by extending {@see Site} and overriding the theme-specific render hooks.
 * All data access goes through the iManager 2.0 container — no calls into
 * the legacy `imanager()` function or `editor/core/*` classes survive.
 */
class BasicTheme extends Site
{
    public const VERSION = '2.0.0';

    /** @var array<string, string> */
    private array $tpls;

    /** @var array<string, string> */
    private array $paginationTpls;

    private ?Page $articles = null;
    private string $paginationMarkup = '';
    private ?string $forcedTemplate = null;

    /**
     * Loads theme-specific config + template fragments and resolves the
     * "articles container" page once. Called by {@see Site::__construct()}.
     */
    protected function init(): void
    {
        $themeConfigFile = $this->scriptorRoot . '/data/settings/basic-theme-config.php';
        $themeConfig = is_file($themeConfigFile) ? (require $themeConfigFile) : [];
        $themeConfig['site_name']         = $this->config['site_name']         ?? $themeConfig['site_name']         ?? 'Scriptor';
        $themeConfig['markup_cache_time'] = $this->config['markup_cache_time'] ?? $themeConfig['markup_cache_time'] ?? 0;
        $this->themeConfig = $themeConfig;

        $tpls = require dirname(__DIR__) . '/resources/_tpls.php';
        $this->paginationTpls = $tpls['pagination'] ?? [];
        unset($tpls['pagination']);
        /** @var array<string, string> $tpls */
        $this->tpls = $tpls;

        $articlesId = (int) ($this->themeConfig['articles_page_id'] ?? 0);
        if ($articlesId > 0) {
            $this->articles = $this->pages->find($articlesId);
        }
    }

    /**
     * Theme-specific render hooks. Anything not handled here delegates to
     * the parent Site so the standard cases (`content`, `navigation`,
     * `messages`) keep working.
     */
    public function render(string $element): ?string
    {
        return match ($element) {
            'archivesContent'  => $this->renderArchivesContent(),
            'archiveNav'       => $this->renderArchiveNav(),
            'pagination'       => $this->paginationMarkup,
            'hero'             => $this->renderHero(),
            'footerNav'        => $this->renderFooterNav(),
            'mainNavItems'     => $this->renderMainNavItems(),
            'socIcons'         => $this->renderSocIcons(),
            'articleDate'      => $this->renderArticleDate(),
            'emptyCsrfFields'  => $this->renderCsrfFields(false),
            default            => parent::render($element),
        };
    }

    /**
     * Output cache: persists rendered HTML for cacheable templates so the
     * next request can short-circuit in {@see hitCache()}. Always returns
     * the captured buffer so the template prints the page either way.
     */
    public function cache(): string
    {
        $output = parent::cache();
        if ($this->page === null) {
            return $output;
        }
        $cacheable = $this->themeConfig['cacheable_templates'] ?? [];
        $ttl = (int) ($this->themeConfig['markup_cache_time'] ?? 0);
        if ($ttl > 0 && \in_array($this->page->template, $cacheable, true)) {
            $this->cache->set($this->cacheKey(), $output, $ttl);
        }
        return $output;
    }

    /**
     * Returns the cached body for the current request, or null if the
     * cache is cold / disabled. Called by `_ext.php` before any rendering
     * happens so we can shortcut on a hit.
     */
    public function hitCache(): ?string
    {
        $ttl = (int) ($this->themeConfig['markup_cache_time'] ?? 0);
        if ($ttl <= 0) {
            return null;
        }
        $hit = $this->cache->get($this->cacheKey());
        return \is_string($hit) && $hit !== '' ? $hit : null;
    }

    /**
     * Routing override: when the URL targets the "articles container"
     * page (with optional `/pageN/` pagination tail) we render the blog
     * index, otherwise defer to the standard page resolver. Pages nested
     * under the articles container get the `blog-post` template forced
     * via {@see currentTemplate()} without mutating the page DTO.
     */
    public function routeArticles(): void
    {
        if ($this->articles !== null && $this->urlSegmentsTargetArticlesContainer()) {
            $this->page = $this->articles;
            return;
        }

        $this->execute();
        if (
            $this->articles !== null
            && $this->page !== null
            && $this->page->parent === $this->articles->id()
        ) {
            $this->forcedTemplate = 'blog-post';
        }
    }

    public function currentTemplate(): string
    {
        return $this->forcedTemplate ?? parent::currentTemplate();
    }

    private function urlSegmentsTargetArticlesContainer(): bool
    {
        if ($this->articles === null) {
            return false;
        }
        $segments = $this->urlSegments->segments;
        return $segments !== [] && end($segments) === $this->articles->slug;
    }

    /**
     * Dispatches user actions (contact, subscribe, loadToken) when posted.
     */
    public function actions(): void
    {
        $action = $this->input->postString('action');
        if ($action === '') {
            return;
        }
        $allowed = $this->themeConfig['allowed_actions'] ?? [];
        if (! \in_array($action, $allowed, true)) {
            return;
        }
        $method = $action . 'Action';
        if (method_exists($this, $method)) {
            $this->{$method}();
        }
    }

    /* --------------------------------------------------------------- *
     * Render helpers
     * --------------------------------------------------------------- */

    private function renderArchivesContent(): string
    {
        if ($this->articles === null) {
            return '';
        }
        $archive = $this->input->getString('archive');
        if ($archive !== '') {
            return $this->renderArchive($this->sanitizer->slug($archive));
        }
        return $this->renderArticles();
    }

    private function renderArchive(string $period): string
    {
        $parts = explode('-', $period, 2);
        if (\count($parts) === 2) {
            $year  = (int) $parts[0];
            $month = $parts[1];
        } else {
            $year  = (int) date('Y');
            $month = $parts[0];
        }
        $start = strtotime("$month $year");
        $end   = strtotime("$month $year +1 month");
        if ($start === false || $end === false || $this->articles === null) {
            $this->throw404();
        }
        \assert($this->articles !== null && $this->articles->id() !== null);
        $articles = $this->pages->findInTimeRange($start, $end, $this->articles->id());
        if ($articles === []) {
            $this->throw404();
        }
        return $this->renderArticleList($articles);
    }

    private function renderArticles(): string
    {
        if ($this->articles === null || $this->articles->id() === null) {
            return '';
        }
        $perPage = (int) ($this->themeConfig['articles_per_page'] ?? 10);
        $total   = $this->pages->countByParent($this->articles->id(), activeOnly: true);
        $page    = max(1, $this->urlSegments->pageNumber);

        $articles = $this->pages->findByParent(
            $this->articles->id(),
            orderBy: 'created',
            direction: \Imanager\Query\Direction::Desc,
            activeOnly: true,
            offset: ($page - 1) * $perPage,
            limit:  $perPage,
        );

        $this->paginationMarkup = '';
        if ($total > $perPage) {
            $renderer = new PaginationRenderer(new TemplateRenderer(), $this->paginationTpls);
            $this->paginationMarkup = $renderer->render(
                new Pagination($page, $perPage, $total),
                $this->getBasePath() . $this->getPageUrl($this->articles) . 'page%d/',
            );
        }

        return $this->renderArticleList($articles);
    }

    /**
     * @param list<Page> $articles
     */
    private function renderArticleList(array $articles): string
    {
        if ($articles === []) {
            return $this->templateParser->render($this->tpls['empty_article_row'], [
                'TEXT' => $this->themeConfig['msgs']['no_articles_found'] ?? 'No articles found',
            ]);
        }

        $output = '';
        $i = 0;
        foreach ($articles as $article) {
            $i++;
            $url     = $this->getBasePath() . $this->getPageUrl($article);
            $date    = $this->formatDate($article->created());
            $figure  = $this->renderArticleFigure($article, $url);
            $content = $this->renderArticleSummary($article);

            $output .= $this->templateParser->render($this->tpls['article_row'], [
                'HEADER_CLASS'      => $i === 1 ? ' class="uk-margin-top uk-padding-remove"' : '',
                'URL'               => $url,
                'HEADER_LINK_TITLE' => $article->name,
                'HEADER_TEXT'       => $article->name,
                'CREATED_DATE'      => $date,
                'FIGURE'            => $figure,
                'CONTENT'           => $content,
            ]);
        }
        return $output;
    }

    private function renderArticleFigure(Page $article, string $articleUrl): string
    {
        $image = $this->headlineImage($article);
        if ($image === null) {
            return '';
        }
        $imageUrl = $this->getBasePath() . ltrim($this->images->url($image, width: 800, height: 350), '/');
        $info = '';
        if (! empty($image['title'])) {
            $info = $this->templateParser->render($this->tpls['art_list_image_caption'], [
                'TEXT' => $this->sanitizer->markdown((string) $image['title']),
            ]);
        }
        return $this->templateParser->render($this->tpls['art_list_figure'], [
            'URL'      => $articleUrl,
            'DATA_SRC' => $imageUrl,
            'ALT'      => '',
            'INFO_ROW' => $info,
        ]);
    }

    private function renderArticleSummary(Page $article): string
    {
        $limit = (int) ($this->themeConfig['summary_character_len'] ?? 400);
        $text  = htmlspecialchars_decode($article->content);
        if ($limit > 0 && mb_strlen($text) > $limit) {
            $text = mb_substr($text, 0, $limit) . ' …';
        }
        return $this->sanitizer->markdown($text);
    }

    private function renderArchiveNav(): string
    {
        if ($this->articles === null || $this->articles->id() === null) {
            return '';
        }
        $months = $this->collectArchiveMonths($this->articles->id());
        if ($months === []) {
            return '';
        }
        $rows    = '';
        $url     = $this->getBasePath() . $this->getPageUrl($this->articles);
        $curYear = (int) date('Y');
        $curMon  = date('F');
        foreach ($months as $year => $byMonth) {
            foreach ($byMonth as $month => $_count) {
                $isCurrent = ($year === $curYear) && ($month === $curMon);
                if ($year !== $curYear) {
                    $rows .= $this->templateParser->render($this->tpls['archive_nav_past_row'], [
                        'URL'   => $url . '?archive=' . $year . '-' . strtolower($month),
                        'MONTH' => $month,
                        'YEAR'  => (string) $year,
                    ]);
                } elseif (! $isCurrent) {
                    $rows .= $this->templateParser->render($this->tpls['archive_nav_current_row'], [
                        'URL'   => $url . '?archive=' . $year . '-' . strtolower($month),
                        'MONTH' => $month,
                    ]);
                }
            }
        }
        return $rows;
    }

    /**
     * @return array<int, array<string, int>>
     */
    private function collectArchiveMonths(int $articlesId): array
    {
        $months = [];
        foreach ($this->pages->findByParent($articlesId, activeOnly: true) as $article) {
            $date = $this->dateTime($article->created());
            $year  = (int) $date->format('Y');
            $month = $date->format('F');
            $months[$year][$month] = ($months[$year][$month] ?? 0) + 1;
        }
        return $months;
    }

    private function renderArticleDate(): string
    {
        if ($this->page === null) {
            return '';
        }
        $modified = '';
        if ($this->page->created() !== $this->page->updated()) {
            $modified = $this->templateParser->render($this->tpls['modified_date'], [
                'DATE' => $this->formatDate($this->page->updated()),
            ]);
        }
        $created = $this->templateParser->render($this->tpls['created_date'], [
            'DATE' => $this->formatDate($this->page->created()),
        ]);
        return $this->templateParser->render($this->tpls['article_date'], [
            'CREATED_DATE'  => $created,
            'MODIFIED_DATE' => $modified,
        ]);
    }

    private function renderHero(): string
    {
        if ($this->page === null) {
            return '';
        }
        $image = $this->headlineImage($this->page);
        if ($image === null) {
            return '';
        }
        $imageUrl = $this->getBasePath() . ltrim($this->images->url($image, width: 1200), '/');
        return $this->templateParser->render($this->tpls['hero'], [
            'SRC'  => $imageUrl,
            'INFO' => $this->sanitizer->markdown((string) ($image['title'] ?? '')),
        ]);
    }

    /**
     * Picks the first image for a page, preferring a 14d-1+ FileRepository
     * upload over the migrated 1.x `images[]` data array. The returned
     * shape is the 1.x-style `{name, path, title, position}` dict the
     * Frontend\ImageUrlBuilder consumes — `path` is normalised to point
     * at `data/uploads-2.0/...` so URL rewriting doesn't double-prefix.
     *
     * @return array{name: string, path: string, title: string, position: int}|null
     */
    private function headlineImage(Page $page): ?array
    {
        $itemId = $page->id();
        if ($itemId !== null) {
            $field = $this->resolveImagesField();
            if ($field !== null) {
                $files = $this->files->findByItemAndField($itemId, $field);
                if ($files !== []) {
                    $first = $files[0];
                    return [
                        'name'     => $first->name,
                        // FileStorage paths are <itemId>/<fieldId>/<file>; the
                        // ImageUrlBuilder rewrites `data/uploads/` legacy
                        // prefixes only, so prepend the 2.0 root explicitly.
                        'path'     => 'data/uploads-2.0/' . \dirname($first->path) . '/',
                        'title'    => $first->title,
                        'position' => $first->position,
                    ];
                }
            }
        }

        // Fallback: migrated 1.x image entries embedded in item.data.
        $legacy = $page->images[0] ?? null;
        if (! \is_array($legacy) || ! isset($legacy['name'], $legacy['path'])) {
            return null;
        }
        return [
            'name'     => (string) $legacy['name'],
            'path'     => (string) $legacy['path'],
            'title'    => (string) ($legacy['title'] ?? ''),
            'position' => (int) ($legacy['position'] ?? 0),
        ];
    }

    /**
     * Field id for the Pages.images upload field, or null when the
     * Pages category has no `images` field configured (in which case
     * we fall straight back to the legacy data array).
     */
    private function resolveImagesField(): ?int
    {
        static $cached;
        if ($cached !== null) {
            return $cached === 0 ? null : $cached;
        }
        $field = $this->container->get(\Imanager\Storage\FieldRepository::class)
            ->findByName($this->pages->categoryId, 'images');
        $cached = $field?->id ?? 0;
        return $cached === 0 ? null : $cached;
    }

    private function renderFooterNav(): string
    {
        $containerId = (int) ($this->themeConfig['footer_container_id'] ?? 0);
        if ($containerId === 0) {
            return '';
        }
        $container = $this->pages->find($containerId);
        if ($container === null) {
            return '';
        }
        return $this->templateParser->render($this->tpls['footer_nav'], [
            'MENU_TITLE' => $container->menu_title,
            'INFO'       => $container->content,
            'ITEM_ROWS'  => $this->renderNavItems([
                'parent' => $container->id() ?? 0,
                'icon'   => '&raquo; ',
            ]),
        ]);
    }

    private function renderMainNavItems(): string
    {
        return $this->renderNavItems([
            'exclude' => $this->themeConfig['main_nav_exclude_ids'] ?? [],
        ]);
    }

    /**
     * @param array{parent?: int, exclude?: list<int>, icon?: string} $options
     */
    private function renderNavItems(array $options = []): string
    {
        $parent  = (int) ($options['parent'] ?? 0);
        $exclude = $options['exclude'] ?? [];
        $icon    = $options['icon'] ?? '';

        $tree = $this->pages->levels(rootParent: $parent, excludeIds: $exclude);
        if (empty($tree[$parent])) {
            return '';
        }

        $navi = '';
        foreach ($tree[$parent] as $page) {
            $isActive = $this->page !== null
                && ($this->page->slug === $page->slug || $this->page->parent === $page->id());
            $navi .= $this->templateParser->render($this->tpls['nav_item'], [
                'CLASS' => $isActive ? 'uk-active' : '',
                'URL'   => $this->getBasePath() . $this->getPageUrl($page),
                'ICON'  => $icon,
                'TITLE' => $page->menu_title !== '' ? $page->menu_title : $page->name,
            ]);
        }
        return $navi;
    }

    private function renderSocIcons(): string
    {
        $icons = '';
        foreach ($this->themeConfig['social_media'] ?? [] as $name => $ref) {
            $icons .= $this->templateParser->render($this->tpls['icon_nav_row'], [
                'URL'       => (string) ($ref['href'] ?? '#'),
                'ICON_NAME' => (string) $name,
            ]);
        }
        return $icons;
    }

    private function renderCsrfFields(bool $populate = true): string
    {
        // CSRF infrastructure ist 14c-1 (auth module). For 14b-2 we render
        // empty placeholders so the form markup stays valid.
        unset($populate);
        return $this->templateParser->render($this->tpls['csrf_token_fields'], [
            'NAME'  => '',
            'VALUE' => '',
        ]);
    }

    /* --------------------------------------------------------------- *
     * Action handlers (legacy bridge — Subscribe/Contact/Mail).
     * Behaviour preserved verbatim; refined when Phase 14d wires real
     * upload+endpoint plumbing.
     * --------------------------------------------------------------- */

    public function contactAction(): void
    {
        $email = $this->input->postString('replyto');
        $name  = $this->input->postString('name');
        $body  = $this->input->postString('text');
        if ($email === '' || $name === '' || $body === '') {
            $this->jsonResponse([
                'msgs' => $this->dumpMessages(['danger' => $this->themeConfig['msgs']['empty_mandatory_fields'] ?? 'Empty fields']),
            ]);
            return;
        }
        $cleanEmail = $this->sanitizer->email($email);
        if ($cleanEmail === null) {
            $this->jsonResponse([
                'msgs' => $this->dumpMessages(['danger' => $this->themeConfig['msgs']['empty_from_field'] ?? 'Invalid email']),
            ]);
            return;
        }

        $cfg = $this->themeConfig['email'] ?? [];
        $headers = "From: {$name} <{$cleanEmail}>\r\n"
            . "Reply-To: {$cleanEmail}\r\n"
            . 'X-Mailer: PHP/' . PHP_VERSION;
        $sent = @mail(
            (string) ($cfg['email_to'] ?? ''),
            (string) ($cfg['subject_contact'] ?? 'Contact'),
            $body,
            $headers,
        );
        if (! $sent) {
            $this->jsonResponse([
                'msgs' => $this->dumpMessages(['danger' => $this->themeConfig['msgs']['error_sending_email'] ?? 'Send failed']),
            ]);
            return;
        }
        $this->jsonResponse([
            'success' => true,
            'msgs'    => $this->dumpMessages(['success' => $this->themeConfig['msgs']['email_received'] ?? 'Sent']),
        ]);
    }

    public function loadTokenAction(): void
    {
        // CSRF arrives in 14c-1; until then return empty placeholders so
        // the JS subscribe/contact forms keep posting without errors.
        $this->jsonResponse([
            'success' => true,
            'csrf'    => ['tokenName' => '', 'tokenValue' => ''],
        ]);
    }

    public function subscribeAction(): void
    {
        $email = $this->sanitizer->email($this->input->postString('email'));
        if ($email === null || $email === '') {
            $this->jsonResponse([
                'success' => false,
                'msgs'    => $this->dumpMessages(['danger' => $this->themeConfig['msgs']['empty_email_field'] ?? 'Empty email']),
            ]);
            return;
        }
        $mc = new MailChimp($this->themeConfig['mail_chimp'] ?? []);
        $existing = $mc->get(mb_strtolower($email));
        if (
            \is_array($existing)
            && ($existing['email_address'] ?? null) === mb_strtolower($email)
            && ($existing['status'] ?? '') === 'subscribed'
        ) {
            $this->jsonResponse([
                'success' => true,
                'msgs'    => $this->dumpMessages(['success' => $this->themeConfig['msgs']['subsc_email_exists'] ?? 'Already subscribed']),
            ]);
            return;
        }
        $mc->add(['email_address' => mb_strtolower($email), 'status' => 'pending']);
        if ($mc->code === 200) {
            $confirmTpl = (string) ($this->themeConfig['msgs']['subsc_email_confirmation'] ?? '');
            $msg = $this->templateParser->render($confirmTpl, ['EMAIL' => $email]);
            $this->jsonResponse([
                'success' => true,
                'msgs'    => $this->dumpMessages(['success' => $msg]),
            ]);
            return;
        }
        $this->jsonResponse([
            'success' => false,
            'msgs'    => $this->dumpMessages(['danger' => $this->themeConfig['msgs']['subsc_faild'] ?? 'Failed']),
        ]);
    }

    /* --------------------------------------------------------------- *
     * Utilities
     * --------------------------------------------------------------- */

    private function cacheKey(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'cli';
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        return 'page_' . md5($host . $uri);
    }

    private function dateTime(int $timestamp): \DateTimeImmutable
    {
        $tz = (string) ($this->themeConfig['datetime_zone'] ?? '');
        $zone = $tz !== '' ? new \DateTimeZone($tz) : new \DateTimeZone(date_default_timezone_get());
        return (new \DateTimeImmutable('now', $zone))->setTimestamp($timestamp);
    }

    private function formatDate(int $timestamp): string
    {
        $format = (string) ($this->themeConfig['datetime_format'] ?? 'd F Y');
        return $this->dateTime($timestamp)->format($format);
    }

    /**
     * @param array<string, string> $extra
     * @return string Rendered messages markup.
     */
    private function dumpMessages(array $extra = []): string
    {
        foreach ($extra as $type => $text) {
            $this->addMsg((string) $type, $text);
        }
        $rendered = '';
        foreach ($this->msgs as $msg) {
            $rendered .= $this->templateParser->render($this->tpls['msg'], [
                'TYPE'   => $msg['type'],
                'HEADER' => $msg['header'] ?? '',
                'TEXT'   => $msg['value'],
            ]);
        }
        $this->msgs = [];
        return $rendered;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonResponse(array $payload): never
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

}
