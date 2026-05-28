<?php

declare(strict_types=1);

namespace Scriptor\Boot\Editor\Pages;

use Imanager\Domain\File;
use Imanager\Domain\Item;
use Imanager\Storage\FieldRepository;
use Imanager\Storage\FileRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use Scriptor\Boot\Editor\Editor;
use Scriptor\Boot\Editor\Module;
use Scriptor\Boot\Events\Editor\PageFormRendering;
use Scriptor\Boot\Events\Editor\PageSaving;
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
final class PagesModule implements Module
{
    /** @var list<int> */
    private array $reservedSlugs;

    public function __construct(
        private readonly Editor $editor,
        private readonly PageRepository $pages,
        private readonly FieldRepository $fields,
        private readonly FileRepository $files,
        private readonly EventDispatcherInterface $dispatcher,
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
        // The new-page flow JS sets X-Requested-With when it has FilePond
        // files staged: it needs the new page id back as JSON so it can
        // upload each staged file against /editor/api/upload before
        // navigating to the redirect URL.
        $isXhr = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

        if (! $this->csrfPasses($this->editor->input->postString('tokenName'), $this->editor->input->postString('tokenValue'))) {
            $this->saveError($isXhr, $this->t('error_csrf_token_mismatch'));
            return;
        }

        $name = $this->editor->sanitizer->text(str_replace('"', '', $this->editor->input->postString('name')));
        if ($name === '') {
            $this->saveError($isXhr, $this->t('error_page_title') ?: 'A page name is required.');
            return;
        }

        $editingId = $this->editor->input->getInt('page', 0);
        $existing  = $editingId > 0 ? $this->pages->find($editingId) : null;

        $rawSlug = trim($this->editor->input->postString('slug'));
        if ($rawSlug === '') {
            // Empty slug = this page wants to own the site root URL.
            // Enforce uniqueness: at most one page may have the empty
            // slug, because the resolver maps `/` to whichever page
            // owns it — a second empty-slug page would be unreachable.
            $slug = '';
            $existingHome = $this->pages->findBySlug('');
            if ($existingHome !== null && $existingHome->id() !== $existing?->id()) {
                $this->saveError(
                    $isXhr,
                    $this->t('error_empty_slug_taken')
                        ?: 'Only one page may have an empty slug (the site root). Another page already owns it.',
                );
                return;
            }
        } else {
            $slug = preg_replace('/(-)\1+/', '$1', $this->editor->sanitizer->slug($rawSlug)) ?? '';
            if ($slug === '') {
                $this->saveError($isXhr, $this->t('error_page_name') ?: 'Page slug could not be derived.');
                return;
            }
            if (\in_array($slug, $this->reservedSlugs, true)) {
                $this->saveError($isXhr, $this->t('error_slug_reserved') ?: 'Slug is reserved.');
                return;
            }
        }

        $parentId = $this->editor->input->postInt('parent', 0);
        if ($existing !== null && $parentId === ($existing->id() ?? 0)) {
            $parentId = 0;
        } elseif ($parentId !== 0 && $this->pages->find($parentId) === null) {
            $parentId = 0;
        }

        // Cycle guard: reject the save if the proposed parent's chain
        // walks back through the page being edited (a → b → … → a). The
        // direct self-parent case above already collapses to root, so this
        // catches *indirect* cycles. Only relevant when editing an
        // existing page — a brand-new page has no dependants yet.
        if ($existing !== null
            && $parentId !== 0
            && $this->pages->wouldCreateCycle($existing->id() ?? 0, $parentId)
        ) {
            $this->saveError(
                $isXhr,
                $this->t('error_page_parent_cycle')
                    ?: 'Cannot set this parent — it would create a cycle in the page tree.',
            );
            return;
        }

        if ($this->pages->slugTaken($slug, $parentId, $existing?->id())) {
            $this->saveError($isXhr, $this->t('error_page_title_exists') ?: 'A page with that slug already exists under this parent.');
            return;
        }

        $menuTitleRaw = $this->editor->input->postString('menu_title');
        $menuTitle = $menuTitleRaw !== ''
            ? $this->editor->sanitizer->text(str_replace('"', '', $menuTitleRaw))
            : $name;

        $content = $this->editor->input->postString('content');
        if ($content === '') {
            $this->saveError($isXhr, $this->t('error_page_content') ?: 'Page content is required.');
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

        // Position: form may set an explicit value (lets editors mix
        // DB pages with plugin-contributed nav entries on one scale).
        // Empty / 0 falls back to keep-existing (edit) or next-free (new).
        $positionInput = $this->editor->input->postInt('position', 0);
        $position = $positionInput > 0
            ? $positionInput
            : ($existing?->item->position ?? $this->pages->nextPosition());

        $now = time();
        $item = new Item(
            id:         $existing?->id(),
            categoryId: $this->pages->categoryId,
            name:       $name,
            label:      $existing?->item->label,
            position:   $position,
            active:     $active,
            data:       $data,
            created:    $existing?->item->created ?? $now,
            updated:    $now,
        );

        // Plugin extension point: let listeners pull extra POST fields
        // into the item's data bag before persistence. Same plugin that
        // rendered fields via PageFormRendering picks them up here.
        $saving = new PageSaving($item, $this->editor->input);
        $this->dispatcher->dispatch($saving);
        if ($saving->extraData() !== []) {
            $item = new Item(
                id:         $item->id,
                categoryId: $item->categoryId,
                name:       $item->name,
                label:      $item->label,
                position:   $item->position,
                active:     $item->active,
                data:       [...$data, ...$saving->extraData()],
                created:    $item->created,
                updated:    $item->updated,
            );
        }

        try {
            $saved = $this->pages->save($item);
        } catch (\Throwable $e) {
            $this->saveError($isXhr, $this->t('error_saving_page') ?: 'Saving failed: ' . $e->getMessage());
            return;
        }

        // Image titles travel with the page form (one input per file row,
        // name="image_titles[<fileId>]"). Apply each title onto the
        // matching FileRepository row, scoped to this page's files so a
        // tampered POST can't relabel files of another item.
        $this->applyImageMetadata((int) $saved->id());

        $redirect = $this->editor->siteUrl . '/pages/edit/?page=' . $saved->id();

        if ($isXhr) {
            // Skip flash so the JSON consumer doesn't strand a leftover
            // success message on the next non-XHR navigation.
            $this->jsonResponse([
                'status'   => 'ok',
                'pageId'   => (int) $saved->id(),
                'redirect' => $redirect,
            ]);
        }

        $this->editor->flashMsg('success', $this->t('successful_saved_page') ?: 'Page saved.');
        $this->editor->redirect($redirect);
    }

    /**
     * Persist `image_titles[<fileId>]` and `image_positions[<fileId>]`
     * posted alongside the page form. Only files owned by `$pageId`
     * are eligible — defensive scoping against forged ids.
     */
    private function applyImageMetadata(int $pageId): void
    {
        if ($pageId < 1) {
            return;
        }
        $titles    = $this->editor->input->post('image_titles');
        $positions = $this->editor->input->post('image_positions');
        $hasTitles    = \is_array($titles) && $titles !== [];
        $hasPositions = \is_array($positions) && $positions !== [];
        if (! $hasTitles && ! $hasPositions) {
            return;
        }

        $field = $this->fields->findByName($this->pages->categoryId, 'images');
        if ($field === null || $field->id === null) {
            return;
        }

        $owned = [];
        foreach ($this->files->findByItemAndField($pageId, (int) $field->id) as $file) {
            if ($file->id !== null) {
                $owned[$file->id] = $file;
            }
        }

        if ($hasTitles) {
            foreach ($titles as $rawId => $rawTitle) {
                $fileId = (int) $rawId;
                $file   = $owned[$fileId] ?? null;
                if ($file === null) {
                    continue;
                }
                $title = (string) $rawTitle;
                if ($file->title === $title) {
                    continue;
                }
                $owned[$fileId] = $this->files->save($file->withTitle($title));
            }
        }

        if ($hasPositions) {
            foreach ($positions as $rawId => $rawPos) {
                $fileId = (int) $rawId;
                $file   = $owned[$fileId] ?? null;
                if ($file === null) {
                    continue;
                }
                $position = (int) $rawPos;
                if ($file->position === $position) {
                    continue;
                }
                $owned[$fileId] = $this->files->save($file->withPosition($position));
            }
        }
    }

    private function saveError(bool $isXhr, string $message): void
    {
        if ($isXhr) {
            $this->jsonResponse(['status' => 'error', 'error' => $message], 400);
        }
        $this->editor->addMsg('error', $message);
    }

    private function deleteAction(): void
    {
        $id = $this->editor->input->getInt('page', 0);
        if (! $this->csrfPasses($this->editor->input->getString('tokenName'), $this->editor->input->getString('tokenValue'))) {
            $this->editor->flashMsg('error', $this->t('error_csrf_token_mismatch'));
            $this->editor->redirect($this->editor->siteUrl . '/pages/');
        }
        if ($id <= 1) {
            $this->editor->flashMsg('error', $this->t('error_deleting_first_page') ?: 'The home page cannot be deleted.');
            $this->editor->redirect($this->editor->siteUrl . '/pages/');
        }
        $page = $this->pages->find($id);
        if ($page === null) {
            $this->editor->flashMsg('error', $this->t('error_deleting_page') ?: 'Page not found.');
            $this->editor->redirect($this->editor->siteUrl . '/pages/');
        }
        if ($this->pages->findByParent($id) !== []) {
            $this->editor->flashMsg('error', $this->t('error_remove_parent_page') ?: 'Cannot delete a page with child pages.');
            $this->editor->redirect($this->editor->siteUrl . '/pages/');
        }
        $this->pages->delete($id);
        $this->editor->flashMsg('success', $this->t('page_successful_removed') ?: 'Page deleted.');
        $this->editor->redirect($this->editor->siteUrl . '/pages/');
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
        // The JS drag-handler sends the moved id explicitly so the
        // server can reposition just that one row (midpoint between
        // its new neighbours) instead of renumbering everything
        // contiguously. Falling back to the old bulk-renumber path
        // when `moved` is absent keeps any stale page-loads working.
        $movedId = $this->editor->input->postInt('moved', 0);
        if ($movedId > 0) {
            $this->pages->reorderOne($movedId, $ids);
        } else {
            $this->pages->renumber($ids);
        }
        // Return the fresh id→position map so the client can update the
        // visible badges without a full page reload. Only the ids that
        // were submitted (i.e. currently rendered) are included.
        $positions = [];
        foreach ($ids as $id) {
            $page = $this->pages->find($id);
            if ($page !== null) {
                $positions[(string) $id] = $page->item->position;
            }
        }
        $this->jsonResponse(['status' => 1, 'positions' => $positions]);
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
        $byId = [];
        foreach ($pages as $p) {
            $byId[$p->id()] = $p;
        }
        $rows = '';
        foreach ($pages as $page) {
            $parentCell = '';
            if ($page->parent !== 0) {
                if (isset($byId[$page->parent])) {
                    $parent = $byId[$page->parent];
                    $parentCell = sprintf(
                        '%1$s (<a href="edit/?page=%2$d">%2$d</a>)',
                        htmlspecialchars($this->truncate($parent->name, 60), \ENT_QUOTES),
                        $page->parent,
                    );
                } else {
                    $parentCell = sprintf('(%d)', $page->parent);
                }
            }
            $rows .= sprintf(
                '<tr class="sortable">'
                . '<td><i class="gg-swap-vertical"></i> <span class="page-position">%2$d</span><input type="hidden" name="position[]" value="%1$d"></td>'
                . '<td>%1$d</td>'
                . '<td><a href="edit/?page=%1$d">%3$s</a></td>'
                . '<td>%4$s</td>'
                . '<td><a class="remove" rel="%5$s" href="delete/?page=%1$d&amp;tokenName=pages&amp;tokenValue=%6$s"><i class="gg-trash"></i></a></td>'
                . '</tr>',
                $page->id(),
                $page->item->position,
                htmlspecialchars($this->truncate($page->name, 80), \ENT_QUOTES),
                $parentCell,
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

        // Plugin extension point: dispatched once up-front so listeners
        // can fill named slots; each slot's buffer is then printed
        // verbatim right after its matching core field. Listeners own
        // their HTML escaping. Companion {@see PageSaving} event lets
        // the same plugin persist the posted values.
        $rendering = new PageFormRendering($page, $this->pages->categoryId);
        $this->dispatcher->dispatch($rendering);

        $html  = '<h1>' . htmlspecialchars($this->t($headerKey), \ENT_QUOTES) . '</h1>';
        $html .= '<form id="page-form" action="' . htmlspecialchars($action, \ENT_QUOTES) . '" method="post">';
        $html .= $this->fieldText('pagename', 'name', $this->t('title_label'), $page?->name ?? '', required: true);
        $html .= $rendering->htmlFor(PageFormRendering::SLOT_AFTER_NAME);
        $html .= $this->fieldText('menu-title', 'menu_title', $this->t('menu_title_label'), $page?->menu_title ?? '', infoText: $this->t('menu_title_field_infotext'));
        $html .= $rendering->htmlFor(PageFormRendering::SLOT_AFTER_MENU_TITLE);
        $html .= $this->fieldText('slug', 'slug', $this->t('name_label'), $page?->slug ?? '', infoText: $this->t('name_field_infotext'));
        $html .= $rendering->htmlFor(PageFormRendering::SLOT_AFTER_SLUG);
        $html .= $this->fieldTextarea('markdown', 'content', $this->t('content_label'), $page?->content ?? '', required: true);
        $html .= $rendering->htmlFor(PageFormRendering::SLOT_AFTER_CONTENT);
        $html .= $this->renderImagesSection($page);
        $html .= $rendering->htmlFor(PageFormRendering::SLOT_AFTER_IMAGES);
        $html .= $this->fieldSelect('parent', 'parent', $this->t('parent_label'), $parentOptions);
        $html .= $rendering->htmlFor(PageFormRendering::SLOT_AFTER_PARENT);
        $html .= $this->fieldText('template', 'template', $this->t('template_label'), $page?->template ?? '', infoText: $this->t('template_field_infotext'));
        $html .= $rendering->htmlFor(PageFormRendering::SLOT_AFTER_TEMPLATE);
        $html .= $this->fieldPosition($page);
        $html .= $rendering->htmlFor(PageFormRendering::SLOT_AFTER_POSITION);
        $html .= $this->fieldCheckbox('publish', 'published', $this->t('published_label'), $page?->active() ?? true);
        $html .= $rendering->htmlFor(PageFormRendering::SLOT_AFTER_PUBLISHED);
        $html .= $rendering->htmlFor(PageFormRendering::SLOT_END);

        $html .= '<input type="hidden" name="action" value="save-page">';
        $html .= sprintf('<input type="hidden" name="tokenName" value="%s">', htmlspecialchars('pages', \ENT_QUOTES));
        $html .= sprintf('<input type="hidden" name="tokenValue" value="%s">', htmlspecialchars($token, \ENT_QUOTES));
        $html .= '<button class="icons primary" type="submit" id="save" name="save" value="1"><i class="gg-drive"></i><span>&nbsp;' . htmlspecialchars($this->t('save_button'), \ENT_QUOTES) . '</span></button>';
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

        $field = $this->fields->findByName($this->pages->categoryId, 'images');
        if ($field === null || $field->id === null) {
            return '<div class="form-control"><label>' . $label . '</label>' . $infoBlock
                . '<p class="info-text"><em>The Pages category has no <code>images</code> field — nothing to render.</em></p></div>';
        }
        $fieldId = (int) $field->id;
        $token   = $this->editor->csrf->token('pages');

        // itemId 0 = new page; the JS picks deferred mode and uploads
        // each staged file only after the page-save XHR returns the
        // fresh page id.
        $itemId = $page?->id() ?? 0;
        $existingRows = '';
        if ($itemId > 0) {
            $rowIndex = 0;
            foreach ($this->files->findByItemAndField($itemId, $fieldId) as $file) {
                $existingRows .= $this->renderUploadedFileRow($file, $rowIndex);
                $rowIndex++;
            }
        }
        $existingBlock = $existingRows !== ''
            ? '<ul class="image-list image-list--uploaded">' . $existingRows . '</ul>'
            : '';

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

    private function renderUploadedFileRow(File $file, int $rowIndex): string
    {
        $i = static fn(string $s): string => htmlspecialchars($s, \ENT_QUOTES);
        $thumbName = \sprintf('300x300_%s', $file->name);
        // Public-URL convention from FileStorage::url() — the storage is
        // wired with /uploads as its public base in the bootstrap
        // (public/uploads/ on disk).
        $base = '/uploads';
        $assetUrl = $base . '/' . $file->path;
        $thumbUrl = $base . '/' . \dirname($file->path) . '/thumbnail/' . $thumbName;
        $token  = $this->editor->csrf->token('pages');
        $apiUrl = $this->editor->siteUrl . '/api/upload';
        $id     = (int) $file->id;
        $titleField    = 'image_titles[' . $id . ']';
        $positionField = 'image_positions[' . $id . ']';

        $altText = $file->title !== '' ? $file->title : '';
        $markdown = '![' . $altText . '](' . $assetUrl . ')';
        return '<li class="image-list__item" data-file-id="' . $id . '">'
            . '<a href="' . $i($assetUrl) . '" target="_blank">'
            . '<img src="' . $i($thumbUrl) . '" alt="' . $i($file->name) . '" loading="lazy" width="120" height="120">'
            . '</a>'
            . ' <div class="image-list__meta">'
                . '<code>' . $i($file->name) . '</code> '
                . '<button type="button" class="image-list__copy"'
                    . ' data-copy-md="' . $i($markdown) . '"'
                    . ' data-copy-path="' . $i($assetUrl) . '"'
                    . ' title="Copy Markdown — Shift+Click for path only">'
                    . '<i class="gg-copy"></i><span class="image-list__copy-label">copy</span>'
                . '</button> '
                . '<span class="muted">(' . $file->width . 'x' . $file->height . ', ' . $file->size . ' bytes)</span>'
                . '<div class="image-list__title-edit">'
                    . '<input type="text" class="image-list__title-input"'
                        . ' name="' . $i($titleField) . '"'
                        . ' placeholder="Caption / alt text"'
                        . ' value="' . $i($file->title) . '">'
                . '</div>'
            . '</div>'
            . ' <button type="button" class="image-list__remove"'
                . ' data-file-id="' . $id . '"'
                . ' data-csrf-name="pages"'
                . ' data-csrf-value="' . $i($token) . '"'
                . ' data-delete-url="' . $i($apiUrl) . '">'
                . '<i class="gg-trash"></i><span>&nbsp;remove</span>'
            . '</button>'
            // Order field — JS-sortable updates the value on drag-end so the
            // next page-save persists the new order onto the matching File rows.
            . '<input type="hidden" class="image-list__position" name="' . $i($positionField) . '" value="' . $rowIndex . '">'
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

    /**
     * Position number input. Empty value on the form leaves the page's
     * current position untouched. The top-level nav merges DB pages
     * with plugin-contributed entries by `position`, so editors with
     * mixed nav can set explicit values here without drag-reordering.
     */
    private function fieldPosition(?Page $page): string
    {
        $label = $this->t('position_label') ?: 'Position';
        $info  = $this->t('position_field_infotext')
            ?: 'Sort key for the top-level navigation. Lower numbers come first. Leave empty to keep the current value.';
        $value = $page !== null ? (string) $page->item->position : '';
        return sprintf(
            '<div class="form-control"><label for="position">%s</label>'
            . '<p class="info-text i-wrapp"><i class="gg-danger"></i>%s</p>'
            . '<input name="position" id="position" type="number" min="1" value="%s">'
            . '</div>',
            htmlspecialchars($label, \ENT_QUOTES),
            htmlspecialchars($info, \ENT_QUOTES),
            htmlspecialchars($value, \ENT_QUOTES),
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
            . '<th><b>' . $i($this->t('title_table_header')) . '</b></th>'
            . '<th><b>' . $i($this->t('parent_table_header')) . '</b></th>'
            . '<th><b>' . $i($this->t('delete_table_header')) . '</b></th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table>'
            . '<input type="hidden" name="action" value="renumber-pages">'
            . sprintf('<input type="hidden" name="tokenName" value="%s">', $i('pages'))
            . sprintf('<input type="hidden" name="tokenValue" value="%s">', $i($token))
            . '</form></div>'
            . '<a class="btn" href="./edit/"><button class="icons button primary" type="button"><i class="gg-math-plus"></i>&nbsp;'
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
        // Carry ALL existing keys forward, not just the six the
        // form knows about. Hidden core fields (via
        // PageFormRendering::hide) carry no POST value but should
        // keep their stored data; legacy `images` JSON from 1.x
        // imports lives here too; plugin-contributed keys whose
        // PageSaving listener didn't fire (listener deactivated,
        // event handler short-circuited on template) shouldn't get
        // nuked on every save. The five POST-driven core keys are
        // overwritten right after this call in saveAction(), so
        // preserving them here is harmless.
        return $page->item->data->toArray();
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
    private function jsonResponse(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        exit;
    }

}
