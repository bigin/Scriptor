<?php

declare(strict_types=1);

namespace Scriptor\Boot\Editor\Pages;

use Imanager\Domain\File;
use Imanager\Domain\Item;
use Imanager\Storage\FieldRepository;
use Imanager\Storage\FileRepository;
use Scriptor\Boot\Editor\Editor;
use Scriptor\Boot\Frontend\Page;
use Scriptor\Boot\Frontend\PageRepository;

/**
 * Pages module — list / edit / create / delete / renumber / Markdown
 * preview, all on the iManager 2.0 stack.
 *
 * The edit form's image section combines two sources: a FilePond
 * widget (14d-2) that uploads through `/editor/api/upload` and lists
 * the resulting FileRepository rows, plus a collapsible block with
 * the migrated 1.x `Item.data.images` entries rendered as inline
 * thumbnail previews. The frontend prefers the FileRepository upload
 * over the legacy entry once one exists (14d-3, BasicTheme::headlineImage).
 *
 * Side effects (DB writes, redirects, JSON responses) happen inside
 * the action handlers; render output lands on `$editor->pageContent`
 * exactly like the auth module.
 */
final class PagesModule
{
    /** @var list<int> */
    private array $reservedSlugs;

    public function __construct(
        private readonly Editor $editor,
        private readonly PageRepository $pages,
        private readonly FieldRepository $fields,
        private readonly FileRepository $files,
    ) {
        $reserved = (array) ($this->editor->config['reservedSlugs'] ?? []);
        /** @var list<int> $reserved */
        $this->reservedSlugs = $reserved;
    }

    public function execute(): void
    {
        $action = $this->editor->input->postString('action');
        if ($action !== '') {
            $this->dispatchAction($action);
        }

        $sub = $this->editor->urlSegments->get(1);
        if ($sub === 'delete') {
            $this->deleteAction();
            return;
        }

        if ($sub === 'edit') {
            $this->renderEdit();
            return;
        }

        $this->renderList();
    }

    /* ---------------------------------------------------------------- *
     * POST/GET action handlers
     * ---------------------------------------------------------------- */

    private function dispatchAction(string $action): void
    {
        match ($action) {
            'save-page'       => $this->saveAction(),
            'renumber-pages'  => $this->renumberAction(),
            'render-markdown' => $this->markdownPreviewAction(),
            default           => null,
        };
    }

    private function saveAction(): void
    {
        if (! $this->csrfPasses($this->editor->input->postString('tokenName'), $this->editor->input->postString('tokenValue'))) {
            $this->editor->addMsg('error', $this->t('error_csrf_token_mismatch'));
            return;
        }

        $name = $this->editor->sanitizer->text(str_replace('"', '', $this->editor->input->postString('name')));
        if ($name === '') {
            $this->editor->addMsg('error', $this->t('error_page_title') ?: 'A page name is required.');
            return;
        }

        $rawSlug = $this->editor->input->postString('slug');
        $slugSource = $rawSlug !== '' ? $rawSlug : $name;
        $slug = preg_replace('/(-)\1+/', '$1', $this->editor->sanitizer->slug($slugSource)) ?? '';
        if ($slug === '') {
            $this->editor->addMsg('error', $this->t('error_page_name') ?: 'Page slug could not be derived.');
            return;
        }
        if (\in_array($slug, $this->reservedSlugs, true)) {
            $this->editor->addMsg('error', $this->t('error_slug_reserved') ?: 'Slug is reserved.');
            return;
        }

        $editingId = $this->editor->input->getInt('page', 0);
        $existing  = $editingId > 0 ? $this->pages->find($editingId) : null;

        $parentId = $this->editor->input->postInt('parent', 0);
        if ($existing !== null && $parentId === ($existing->id() ?? 0)) {
            $parentId = 0;
        } elseif ($parentId !== 0 && $this->pages->find($parentId) === null) {
            $parentId = 0;
        }

        if ($this->pages->slugTaken($slug, $parentId, $existing?->id())) {
            $this->editor->addMsg('error', $this->t('error_page_title_exists') ?: 'A page with that slug already exists under this parent.');
            return;
        }

        $menuTitleRaw = $this->editor->input->postString('menu_title');
        $menuTitle = $menuTitleRaw !== ''
            ? $this->editor->sanitizer->text(str_replace('"', '', $menuTitleRaw))
            : $name;

        $content = $this->editor->input->postString('content');
        if ($content === '') {
            $this->editor->addMsg('error', $this->t('error_page_content') ?: 'Page content is required.');
            return;
        }

        $template = $this->editor->sanitizer->templateName($this->editor->input->postString('template'));
        $active   = $this->editor->input->postString('published') !== '';

        $data = $existing !== null ? $this->existingDataMap($existing) : [];
        $data['slug']       = $slug;
        $data['parent']     = $parentId;
        $data['menu_title'] = $menuTitle;
        $data['content']    = $content;
        $data['template']   = $template;
        $data['pagetype']   = $data['pagetype'] ?? '1';

        $now = time();
        $item = new Item(
            id:         $existing?->id(),
            categoryId: $this->pages->categoryId,
            name:       $name,
            label:      $existing?->item->label,
            position:   $existing?->item->position ?? $this->pages->nextPosition(),
            active:     $active,
            data:       $data,
            created:    $existing?->item->created ?? $now,
            updated:    $now,
        );

        try {
            $saved = $this->pages->save($item);
        } catch (\Throwable $e) {
            $this->editor->addMsg('error', $this->t('error_saving_page') ?: 'Saving failed: ' . $e->getMessage());
            return;
        }

        $this->editor->flashMsg('success', $this->t('successful_saved_page') ?: 'Page saved.');
        $this->redirect($this->editor->siteUrl . '/pages/edit/?page=' . $saved->id());
    }

    private function deleteAction(): void
    {
        $id = $this->editor->input->getInt('page', 0);
        if (! $this->csrfPasses($this->editor->input->getString('tokenName'), $this->editor->input->getString('tokenValue'))) {
            $this->editor->flashMsg('error', $this->t('error_csrf_token_mismatch'));
            $this->redirect($this->editor->siteUrl . '/pages/');
        }
        if ($id <= 1) {
            $this->editor->flashMsg('error', $this->t('error_deleting_first_page') ?: 'The home page cannot be deleted.');
            $this->redirect($this->editor->siteUrl . '/pages/');
        }
        $page = $this->pages->find($id);
        if ($page === null) {
            $this->editor->flashMsg('error', $this->t('error_deleting_page') ?: 'Page not found.');
            $this->redirect($this->editor->siteUrl . '/pages/');
        }
        if ($this->pages->findByParent($id) !== []) {
            $this->editor->flashMsg('error', $this->t('error_remove_parent_page') ?: 'Cannot delete a page with child pages.');
            $this->redirect($this->editor->siteUrl . '/pages/');
        }
        $this->pages->delete($id);
        $this->editor->flashMsg('success', $this->t('page_successful_removed') ?: 'Page deleted.');
        $this->redirect($this->editor->siteUrl . '/pages/');
    }

    private function renumberAction(): void
    {
        if (! $this->csrfPasses($this->editor->input->postString('tokenName'), $this->editor->input->postString('tokenValue'))) {
            $this->jsonResponse(['status' => 0, 'error' => 'csrf']);
        }
        $positions = $this->editor->input->post('position');
        if (! \is_array($positions)) {
            $this->jsonResponse(['status' => 0]);
        }
        $ids = [];
        foreach ($positions as $value) {
            $int = (int) $value;
            if ($int > 0) {
                $ids[] = $int;
            }
        }
        $this->pages->renumber($ids);
        $this->jsonResponse(['status' => 1]);
    }

    private function markdownPreviewAction(): void
    {
        $rendered = $this->editor->sanitizer->markdown(
            $this->editor->input->postString('content'),
        );
        $this->jsonResponse(['status' => 1, 'text' => $rendered]);
    }

    /* ---------------------------------------------------------------- *
     * Render
     * ---------------------------------------------------------------- */

    private function renderList(): void
    {
        $pages = $this->pages->findAll();
        usort(
            $pages,
            static fn(Page $a, Page $b): int => $a->item->position <=> $b->item->position,
        );

        $token = $this->editor->csrf->token('pages');
        $rows = '';
        foreach ($pages as $page) {
            $rows .= sprintf(
                '<tr class="sortable">'
                . '<td><i class="gg-swap-vertical"></i><input type="hidden" name="position[]" value="%d"></td>'
                . '<td>%d</td><td>%s</td>'
                . '<td><a href="edit/?page=%1$d">%s</a></td>'
                . '<td><a class="remove" rel="%s" href="delete/?page=%1$d&amp;tokenName=pages&amp;tokenValue=%s"><i class="gg-trash"></i></a></td>'
                . '</tr>',
                $page->id(),
                $page->id(),
                $page->parent !== 0 ? (string) $page->parent : '',
                htmlspecialchars($this->truncate($page->name, 80), \ENT_QUOTES),
                htmlspecialchars($this->t('pre_delete_msg'), \ENT_QUOTES),
                rawurlencode($token),
            );
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="5">' . htmlspecialchars($this->t('no_page'), \ENT_QUOTES) . '</td></tr>';
        }

        $this->editor->pageTitle = 'Page list - Scriptor';
        $this->editor->breadcrumbs = sprintf('<li><span>%s</span></li>', htmlspecialchars($this->t('pages_menu'), \ENT_QUOTES));
        $this->editor->pageContent = $this->wrapList($rows, $token);
    }

    private function renderEdit(): void
    {
        $editingId = $this->editor->input->getInt('page', 0);
        $page = $editingId > 0 ? $this->pages->find($editingId) : null;

        $isEdit = $page !== null;
        $headerKey = $isEdit ? 'page_edit_header' : 'page_create_header';
        $crumbCurrent = $isEdit ? $this->t('pages_edit_menu') : $this->t('pages_create_menu');
        $this->editor->pageTitle = ($isEdit ? 'Page editor' : 'New page') . ' - Scriptor';
        $this->editor->breadcrumbs = sprintf(
            '<li><a href="../">%s</a><i class="gg-chevron-right"></i></li><li><span>%s</span></li>',
            htmlspecialchars($this->t('pages_menu'), \ENT_QUOTES),
            htmlspecialchars($crumbCurrent, \ENT_QUOTES),
        );

        $token = $this->editor->csrf->token('pages');
        $action = $isEdit ? './?page=' . (int) $page->id() : './';
        $parentOptions = $this->renderParentOptions($page);

        $html  = '<h1>' . htmlspecialchars($this->t($headerKey), \ENT_QUOTES) . '</h1>';
        $html .= '<form id="page-form" action="' . htmlspecialchars($action, \ENT_QUOTES) . '" method="post">';
        $html .= $this->fieldText('pagename', 'name', $this->t('title_label'), $page?->name ?? '', required: true);
        $html .= $this->fieldText('menu-title', 'menu_title', $this->t('menu_title_label'), $page?->menu_title ?? '', infoText: $this->t('menu_title_field_infotext'));
        $html .= $this->fieldText('slug', 'slug', $this->t('name_label'), $page?->slug ?? '', infoText: $this->t('name_field_infotext'));
        $html .= $this->fieldTextarea('markdown', 'content', $this->t('content_label'), $page?->content ?? '', required: true);
        $html .= $this->renderImagesSection($page);
        $html .= $this->fieldSelect('parent', 'parent', $this->t('parent_label'), $parentOptions);
        $html .= $this->fieldText('template', 'template', $this->t('template_label'), $page?->template ?? '', infoText: $this->t('template_field_infotext'));
        $html .= $this->fieldCheckbox('publish', 'published', $this->t('published_label'), $page?->active() ?? true);
        $html .= '<input type="hidden" name="action" value="save-page">';
        $html .= sprintf('<input type="hidden" name="tokenName" value="%s">', htmlspecialchars('pages', \ENT_QUOTES));
        $html .= sprintf('<input type="hidden" name="tokenValue" value="%s">', htmlspecialchars($token, \ENT_QUOTES));
        $html .= '<button class="icons" type="submit" id="save" name="save" value="1"><i class="gg-drive"></i><span>&nbsp;' . htmlspecialchars($this->t('save_button'), \ENT_QUOTES) . '</span></button>';
        $html .= '</form>';
        $this->editor->pageContent = $html;
    }

    private function renderParentOptions(?Page $current): string
    {
        $opts = '<option value="0">' . htmlspecialchars($this->t('parent_select_option') ?: '— none —', \ENT_QUOTES) . '</option>';
        foreach ($this->pages->findAll() as $candidate) {
            if ($current !== null && $candidate->id() === $current->id()) {
                continue;
            }
            $selected = $current !== null && $candidate->id() === $current->parent ? ' selected' : '';
            $opts .= sprintf(
                '<option value="%d"%s>%s</option>',
                $candidate->id(),
                $selected,
                htmlspecialchars($this->truncate($candidate->name, 80), \ENT_QUOTES),
            );
        }
        return $opts;
    }

    private function renderImagesSection(?Page $page): string
    {
        $label = htmlspecialchars($this->t('header_image_label') ?: 'Images', \ENT_QUOTES);
        $info  = htmlspecialchars($this->t('header_image_infotext') ?: '', \ENT_QUOTES);
        $infoBlock = $info !== ''
            ? '<p class="info-text i-wrapp"><i class="gg-danger"></i>' . $info . '</p>'
            : '';

        // Edit existing page → wire FilePond against the upload endpoint.
        // New page → can't wire an upload (UploadHandler requires itemId>=1).
        if ($page === null || $page->id() === null) {
            return '<div class="form-control"><label>' . $label . '</label>' . $infoBlock
                . '<p class="info-text"><em>Save the page first to enable image upload.</em></p></div>';
        }

        $itemId  = (int) $page->id();
        $field   = $this->fields->findByName($this->pages->categoryId, 'images');
        if ($field === null || $field->id === null) {
            return '<div class="form-control"><label>' . $label . '</label>' . $infoBlock
                . '<p class="info-text"><em>The Pages category has no <code>images</code> field — nothing to render.</em></p></div>';
        }
        $fieldId = (int) $field->id;
        $token   = $this->editor->csrf->token('pages');

        $existingRows = '';
        foreach ($this->files->findByItemAndField($itemId, $fieldId) as $file) {
            $existingRows .= $this->renderUploadedFileRow($file);
        }
        $existingBlock = $existingRows !== ''
            ? '<ul class="image-list image-list--uploaded">' . $existingRows . '</ul>'
            : '';

        // FilePond container + the metadata bag the init script needs.
        // The script reads `data-*` attributes off this element, posts
        // multipart uploads to the API, and stuffs the new fileId into a
        // hidden input so the page form can render the up-to-date set
        // after a redirect.
        $pondId = 'filepond-images-' . $itemId;
        $widget = sprintf(
            '<input type="file" id="%s" class="filepond" data-itemid="%d" data-fieldid="%d" data-csrf-name="pages" data-csrf-value="%s" data-upload-url="%s" multiple>',
            htmlspecialchars($pondId, \ENT_QUOTES),
            $itemId,
            $fieldId,
            htmlspecialchars($token, \ENT_QUOTES),
            htmlspecialchars($this->editor->siteUrl . '/api/upload', \ENT_QUOTES),
        );

        return '<div class="form-control image-section">'
            . '<label>' . $label . '</label>'
            . $infoBlock
            . $existingBlock
            . $widget
            . '</div>';
    }

    private function renderUploadedFileRow(File $file): string
    {
        $i = static fn(string $s): string => htmlspecialchars($s, \ENT_QUOTES);
        $thumbName = \sprintf('300x300_%s', $file->name);
        // Public-URL convention from FileStorage::url() — the storage is
        // wired with /data/uploads-2.0 as its public base in the bootstrap.
        $base = '/data/uploads-2.0';
        $assetUrl = $base . '/' . $file->path;
        $thumbUrl = $base . '/' . \dirname($file->path) . '/thumbnail/' . $thumbName;
        $token  = $this->editor->csrf->token('pages');
        $apiUrl = $this->editor->siteUrl . '/api/upload';
        $id     = (int) $file->id;

        return '<li class="image-list__item" data-file-id="' . $id . '">'
            . '<a href="' . $i($assetUrl) . '" target="_blank">'
            . '<img src="' . $i($thumbUrl) . '" alt="' . $i($file->name) . '" loading="lazy" width="120" height="120">'
            . '</a>'
            . ' <div class="image-list__meta">'
                . '<code>' . $i($file->name) . '</code> '
                . '<span class="muted">(' . $file->width . 'x' . $file->height . ', ' . $file->size . ' bytes)</span>'
                . '<div class="image-list__title-edit">'
                    . '<input type="text" class="image-list__title-input"'
                        . ' placeholder="Caption / alt text"'
                        . ' value="' . $i($file->title) . '"'
                        . ' data-file-id="' . $id . '"'
                        . ' data-csrf-name="pages"'
                        . ' data-csrf-value="' . $i($token) . '"'
                        . ' data-patch-url="' . $i($apiUrl) . '">'
                    . '<button type="button" class="image-list__title-save" data-file-id="' . $id . '">save title</button>'
                    . '<span class="image-list__title-status muted" data-file-id="' . $id . '"></span>'
                . '</div>'
            . '</div>'
            . ' <button type="button" class="image-list__remove"'
                . ' data-file-id="' . $id . '"'
                . ' data-csrf-name="pages"'
                . ' data-csrf-value="' . $i($token) . '"'
                . ' data-delete-url="' . $i($apiUrl) . '">'
                . '<i class="gg-trash"></i> remove'
            . '</button>'
            . '</li>';
    }

    private function fieldText(string $id, string $name, string $label, string $value, bool $required = false, string $infoText = ''): string
    {
        $cls = $required ? ' class="required"' : '';
        $info = $infoText !== ''
            ? '<p class="info-text i-wrapp"><i class="gg-danger"></i>' . htmlspecialchars($infoText, \ENT_QUOTES) . '</p>'
            : '';
        return sprintf(
            '<div class="form-control"><label%s for="%s">%s</label>%s<input name="%s" id="%s" type="text" value="%s"></div>',
            $cls,
            htmlspecialchars($id, \ENT_QUOTES),
            htmlspecialchars($label, \ENT_QUOTES),
            $info,
            htmlspecialchars($name, \ENT_QUOTES),
            htmlspecialchars($id, \ENT_QUOTES),
            htmlspecialchars($value, \ENT_QUOTES),
        );
    }

    private function fieldTextarea(string $id, string $name, string $label, string $value, bool $required = false): string
    {
        $cls = $required ? ' class="required"' : '';
        return sprintf(
            '<div class="form-control"><label%s for="%s">%s</label><textarea id="%s" name="%s">%s</textarea></div>',
            $cls,
            htmlspecialchars($id, \ENT_QUOTES),
            htmlspecialchars($label, \ENT_QUOTES),
            htmlspecialchars($id, \ENT_QUOTES),
            htmlspecialchars($name, \ENT_QUOTES),
            htmlspecialchars($value, \ENT_QUOTES),
        );
    }

    private function fieldSelect(string $id, string $name, string $label, string $optionsHtml): string
    {
        return sprintf(
            '<div class="form-control"><label for="%s">%s</label><select name="%s" id="%s">%s</select></div>',
            htmlspecialchars($id, \ENT_QUOTES),
            htmlspecialchars($label, \ENT_QUOTES),
            htmlspecialchars($name, \ENT_QUOTES),
            htmlspecialchars($id, \ENT_QUOTES),
            $optionsHtml,
        );
    }

    private function fieldCheckbox(string $id, string $name, string $label, bool $checked): string
    {
        return sprintf(
            '<div class="form-control"><label for="%s"><input name="%s" id="%s" type="checkbox" value="1"%s> %s</label></div>',
            htmlspecialchars($id, \ENT_QUOTES),
            htmlspecialchars($name, \ENT_QUOTES),
            htmlspecialchars($id, \ENT_QUOTES),
            $checked ? ' checked' : '',
            htmlspecialchars($label, \ENT_QUOTES),
        );
    }

    private function wrapList(string $rows, string $token): string
    {
        $i = static fn(string $key): string => htmlspecialchars($key, \ENT_QUOTES);
        return '<div id="page-list-wrapper">'
            . '<h1>' . $i($this->t('pages_header')) . '</h1>'
            . '<form id="page-list-form" action="./" method="post">'
            . '<table id="page-list-table"><thead><tr>'
            . '<th><b>' . $i($this->t('position_table_header')) . '</b></th>'
            . '<th><b>' . $i($this->t('id_table_header')) . '</b></th>'
            . '<th><b>' . $i($this->t('parent_table_header')) . '</b></th>'
            . '<th><b>' . $i($this->t('title_table_header')) . '</b></th>'
            . '<th><b>' . $i($this->t('delete_table_header')) . '</b></th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table>'
            . '<input type="hidden" name="action" value="renumber-pages">'
            . sprintf('<input type="hidden" name="tokenName" value="%s">', $i('pages'))
            . sprintf('<input type="hidden" name="tokenValue" value="%s">', $i($token))
            . '</form></div>'
            . '<a class="btn" href="./edit/"><button class="icons button" type="button"><i class="gg-math-plus"></i>&nbsp;'
            . $i($this->t('create_button')) . '</button></a>';
    }

    /* ---------------------------------------------------------------- *
     * Helpers
     * ---------------------------------------------------------------- */

    /**
     * @return array<string, mixed>
     */
    private function existingDataMap(Page $page): array
    {
        $out = [];
        foreach (['slug', 'parent', 'pagetype', 'menu_title', 'content', 'template'] as $key) {
            if ($page->item->data->has($key)) {
                $out[$key] = $page->item->data->get($key);
            }
        }
        return $out;
    }

    private function csrfPasses(string $name, string $value): bool
    {
        if (! ($this->editor->config['protectCSRF'] ?? true)) {
            return true;
        }
        if ($name === '' || $value === '') {
            return false;
        }
        return $this->editor->csrf->validate($name, $value);
    }

    private function truncate(string $text, int $length): string
    {
        return mb_strlen($text) > $length ? mb_substr($text, 0, $length) . '…' : $text;
    }

    /**
     * @param array<string, string> $vars
     */
    private function t(string $key, array $vars = []): string
    {
        $template = $this->editor->i18n[$key] ?? '';
        if ($template === '' || $vars === []) {
            return $template;
        }
        foreach ($vars as $name => $value) {
            $template = str_replace('[[' . $name . ']]', $value, $template);
        }
        return $template;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonResponse(array $payload): never
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function redirect(string $url): never
    {
        header('Location: ' . $url, true, 302);
        exit;
    }
}
