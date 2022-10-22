<?php

namespace Scriptor\Core\Modules;

use Imanager\Field;
use Imanager\FieldConfigs;
use Imanager\FieldFileupload;
use Imanager\Item;
use Imanager\TemplateParser;
use Imanager\Util;
use Scriptor\Core\Module;
use Scriptor\Core\Scriptor;

/**
 * Pages class
 *
 */
class Pages extends Module
{
	public $page;

	private $reservedSlugs;

	public $jsConfig;

	public function init()
	{
		parent::init();
		$this->csrf = Scriptor::getCSRF();
		$this->pages = $this->imanager->getCategory('name=Pages');
		$this->imanager->fieldMapper->init($this->pages->id, true);
		$this->reservedSlugs = $this->config['reservedSlugs'];
		if (Scriptor::execHook($this) && $this->event->replace) return;
	}

	public function execute()
	{
		$this->checkAction();
		
		if($this->segments->get(0) == 'pages' && !$this->segments->get(1)) {
			$this->pageTitle = 'Page list - Scriptor';
			$this->pageContent = $this->renderPageList();
			$this->breadcrumbs .= '<li><span>'.$this->i18n['pages_menu'].'</span></li>';
		}
		// Page editor
		elseif($this->segments->get(0) == 'pages' && $this->segments->get(1) == 'edit') {
			$this->pageTitle = 'Page editor - Scriptor';
			$this->pageContent = $this->renderEditorPage();
			$this->breadcrumbs .= '<li><a href="../">'.$this->i18n['pages_menu'].'</a><i class="gg-chevron-right"></i></li><li>'. 
				(($this->page) ? '<span>'.$this->i18n['pages_edit_menu'].'</span>' : '<span>'.$this->i18n['pages_create_menu']).
					'</span></li>';
		}
	}

	/**
	 * Checks user actions
	 */
	public function ___checkAction()
	{
		if($this->input->get->page) {
			$this->page = $this->pages->getItem((int)$this->input->get->page);
		}
		// Save page data
		if($this->input->post->action == 'save-page') {
			if($this->savePage()) {
				$this->redirect("./?page={$this->page->id}");
			}
		}
		// Renumber pages
		elseif($this->input->post->action == 'renumber-pages') {
			$status = $this->renumberPages();
			header('Content-type: application/json; charset=utf-8');
			echo json_encode(array('status' => $status));
			exit;
		}
		// Render page content view modal
		elseif($this->input->post->action == 'render-markdown') {
			$content = $this->renderContent();
			header('Content-type: application/json; charset=utf-8');
			echo json_encode(array('status' => 1, 'text' => $content));
			exit;
		}
		// Delete page
		elseif($this->segments->get(1) == 'delete' && $this->input->get->page) {
			$status = $this->removePage();
			$this->redirect('../');
			exit;
		}
	}

	protected function ___renderEditorPage()
	{
		$page = $this->page;
		//$this->anon->myFunction();
		ob_start(); ?>
		<!-- The Modal -->
		<div id="screen" class="modal">
			<!-- Modal content -->
			<div id="screen-content">
				<span class="close"><i class="gg-close"></i></span>
				<div id="page-text"></div>
			</div>
		</div>
		<h1><?php echo $this->i18n['page_edit_header']; ?></h1>
		<form id="page-form" action="./<?php echo isset($this->input->get->page) ? 
			'?page='.(int)$this->input->get->page : ''; ?>" method="post">
			<?php 
			echo $this->renderEditorTitleField($page);
			echo $this->renderEditorMenuTitleField($page);
			echo $this->renderEditorNameField($page);
			echo $this->renderEditorContentField($page);
			echo $this->renderEditorImageField($page);
			echo $this->renderEditorPageParentField($page);
			echo $this->renderEditorTemplateField($page);
			echo $this->renderEditorPublishField($page);
			echo $this->renderEditorActionFields($page);
			?>
		</form>
		<?php 
		$this->jsConfig = $this->buildJsConfig([
			'allowHtmlOutput' => $this->config['allowHtmlOutput']
		]);
		return ob_get_clean();
	}
	
	protected function ___renderEditorTitleField($page)
	{
		ob_start(); ?>
		<div class="form-control">
			<label class="required" for="pagename"><?php echo $this->i18n['title_label']; ?></label>
			<input name="name" id="pagename" type="text" value="<?php echo isset($page->name) ? $page->name : ''; ?>">
		</div>
		<?php return ob_get_clean();
	}

	protected function ___renderEditorMenuTitleField($page)
	{
		ob_start(); ?>
		<div class="form-control">
			<label for="slug"><?php echo $this->i18n['menu_title_label']; ?></label>
			<p class="info-text i-wrapp"><i class="gg-danger"></i><?php echo $this->i18n['menu_title_field_infotext'] ?></p>
			<input name="menu_title" id="menu-title" type="text" value="<?php echo isset($page->menu_title) ? $page->menu_title : ''; ?>">
		</div>
		<?php return ob_get_clean();
	}

	protected function ___renderEditorNameField($page)
	{
		ob_start(); ?>
		<div class="form-control">
			<label for="slug"><?php echo $this->i18n['name_label']; ?></label>
			<p class="info-text i-wrapp"><i class="gg-danger"></i><?php echo $this->i18n['name_field_infotext'] ?></p>
			<input name="slug" id="slug" type="text" value="<?php echo isset($page->slug) ? $page->slug : ''; ?>">
		</div>
		<?php return ob_get_clean();
	}

	protected function ___renderEditorContentField($page)
	{
		ob_start(); ?>
		<div class="form-control">
			<label class="required" for="markdown"><?php echo $this->i18n['content_label']; ?></label>
			<textarea id="markdown" name="content"><?php echo isset($page->content) ? $page->content : ''; ?></textarea>
		</div>
		<?php return ob_get_clean();
	}

	protected function ___renderEditorImageField($page)
	{
		ob_start(); ?>
		<div class="form-control">
			<label><?php echo $this->i18n['header_image_label']; ?></label>
			<p class="info-text i-wrapp"><i class="gg-danger"></i><?php echo $this->i18n['header_image_infotext']; ?></p>
			<?php
			$labels = array(
				'add_files' => $this->i18n['upload_add_files'],
				'start' => $this->i18n['upload_start'],
				'cancel' => $this->i18n['upload_cancel'],
				'name_heading' => $this->i18n['upload_name_heading'],
				'delete' => $this->i18n['upload_delete'],
				'placeholder' => $this->i18n['upload_placeholder'],
			);
			// We'll use our own template, which is provided with special placeholders
			$tpl = $this->imanager->templateParser->render(
				file_get_contents(__DIR__.'/tpls/fileupload.tpl'), [
					'size_heading' => $this->i18n['upload_size_heading']
				]
			);
			$dirname = dirname($this->siteUrl);
			$timestamp_images = ($this->input->post->timestamp_images) ? 
					$this->input->post->timestamp_images : time();
			$fieldMarkup = new FieldFileupload();
			$field = $this->pages->getField('name=images');
			$field->configs->max_number_of_files = 100;
			$fieldMarkup->set('labels', $labels);
			$fieldMarkup->set('fileUploadTpl', $tpl, false);
			$fieldMarkup->set('url', "$dirname/");
			$fieldMarkup->set('action', "$dirname/imanager/upload/server/php/index.php");
			$fieldMarkup->set('id', $field->name);
			$fieldMarkup->set('categoryid', $field->categoryid);
			$fieldMarkup->set('itemid', isset($page->id) ? $page->id : null);
			$fieldMarkup->set('timestamp', $timestamp_images);
			$fieldMarkup->set('fieldid', $field->id);
			$fieldMarkup->set('configs', $field->configs, false);
			$fieldMarkup->set('name', $field->name);

			echo $fieldMarkup->render();
			echo $fieldMarkup->renderJsBlock();
			echo $fieldMarkup->renderJsLibs();
			?>
		</div>
		<?php return ob_get_clean();
	}

	protected function ___renderEditorPageParentField($page)
	{
		// Parents
		$parent_options = '';
		if(count($this->pages->items)) {
			foreach($this->pages->items as $parent) {
				if($page && $parent->id == $page->id) { continue; }
				$parent_options .= '<option value="'.$parent->id.'"'.
					(($page && $parent->id == $page->parent) ? ' selected' : '').'>'.
					((mb_strlen($parent->name) > 80) ? mb_substr($parent->name, 0,80).'...' :
						$parent->name).'</option>';
			}
		}
		ob_start(); ?>
		<div class="form-control">
			<label for="parent"><?php echo $this->i18n['parent_label']; ?></label>
			<select name="parent" id="parent">
				<option><?php echo $this->i18n['parent_select_option']; ?></option>
				<?php echo $parent_options; ?>
			</select>
		</div>
		<?php return ob_get_clean();
	}

	protected function ___renderEditorTemplateField($page)
	{
		ob_start(); ?>
		<div class="form-control">
			<label for="template"><?php echo $this->i18n['template_label']; ?></label>
			<p class="info-text i-wrapp"><i class="gg-danger"></i><?php
				echo $this->i18n['template_field_infotext'] ?></p>
			<input name="template" id="template" type="text" value="<?php echo isset($page->template) ? $page->template : ''; ?>">
		</div>
		<?php return ob_get_clean();
	}

	protected function ___renderEditorPublishField($page)
	{
		ob_start(); ?>
		<div class="form-control">
			<label for="publish"><input name="published" id="publish" type="checkbox" value="1"<?php
				echo (isset($page->active) && !empty($page->active) ? ' checked' : '') ?>> <?php echo $this->i18n['published_label']; ?></label>
		</div>
		<?php return ob_get_clean();
	}

	protected function ___renderEditorActionFields($page)
	{
		ob_start()?>
		<input type="hidden" name="action" value="save-page">
		<button class="icons" type="submit" id="save" name="save" value="1"><i class="gg-drive"></i>
			<span>&nbsp;<?php echo $this->i18n['save_button']; ?></span></button>
		<button class="icons button" type="submit" id="render" name="render" value="1"><i 
			class="gg-screen"></i><span>&nbsp;<?php echo $this->i18n['view_button']; ?></span></button>
		<?php echo $this->csrf->renderInputs(); ?>
		<?php return ob_get_clean();
	}

	protected function ___renderPageList()
	{
		$rows = '<tr><td colspan="5">'.$this->i18n['no_page'].'</td></tr>';
		if($this->pages->items){
			$rows = $this->renderRows();
		}
		ob_start(); ?>
		<div id="page-list-wrapper">
			<h1><?php echo $this->i18n['pages_header'] ?></h1>
			<form id="page-list-form" action="./" method="post">
				<table id="page-list-table">
					<thead>
					<tr>
						<th><b><?php echo $this->i18n['position_table_header']; ?></b></th>
						<th><b><?php echo $this->i18n['id_table_header']; ?></b></th>
						<th><b><?php echo $this->i18n['parent_table_header']; ?></b></th>
						<th><b><?php echo $this->i18n['title_table_header']; ?></b></th>
						<th><b><?php echo $this->i18n['delete_table_header']; ?></b></th>
					</tr>
					</thead>
					<tbody>
					<?php echo $rows; ?>
					</tbody>
				</table>
				<input type="hidden" name="action" value="renumber-pages">
			</form>
		</div>
		<a class="btn" href="./edit/"><button class="icons button" name="button" value="
			<?php echo $this->i18n['create_button']; ?>" type="button">
			<i class="gg-math-plus"></i>&nbsp;<?php 
			echo $this->i18n['create_button']; ?></button></a>
		<?php
		return ob_get_clean();
	}


	protected function ___renderRows()
	{
		$rows = '';
		$sorted = $this->pages->sort('position', 'asc',  0, 0, $this->pages->items);
		$token = $this->csrf->renderUrl('&');
		//$sorted = $this->pages->sort('parent', 'asc',  0, 100, $sorted);
		foreach($sorted as $page) {
			$rows .= '
				<tr class="sortable">
					<td><i class="gg-swap-vertical"></i><input type="hidden" name="position[]" value="'.$page->id.'" ></td>
					<td>'.$page->id.'</td>
					<td>'.(($page->parent) ? $page->parent : '').'</td>
					<td><a href="edit/?page='.$page->id.'">'.
				((mb_strlen($page->name) > 80) ? mb_substr($page->name, 0,80).'...' : $page->name).
				'</td></a><td><a class="remove" rel="'.$this->i18n['pre_delete_msg'].
				'" href="delete/?page='.$page->id.$token.'"><i class="gg-trash"></i></a></td>
				</tr>
			';
		}
		return $rows;
	}

	/**
	 * Is called when the View button is clicked, e.g. 
	 * when rendering Markdown.
	 */
	protected function ___renderContent() 
	{
		$parsedown = $this->loadModule('parsedown');
		$templateParser = new TemplateParser();
		
		$imgPath = '';
		$field = $this->pages->getField('name=images');
		$pageId = isset($this->page->id) ? $this->page->id : null;
		if($pageId && $field && $this->pages) {
			$imgPath = $this->pages->id.".$pageId.$field->id/";
		} else {
			$imgPath = ".tmp_{$this->input->post->timestamp_images}_{$this->pages->id}.$field->id/";
		}
		
		$content = $templateParser->render($this->input->post->content, [
			'BASE_URL' => dirname($this->siteUrl).'/',
			'UPLOADS_URL' => dirname($this->siteUrl).'/data/uploads/',
			'IMAGES_URL' => dirname($this->siteUrl)."/data/uploads/$imgPath",
		]);

		return $parsedown->text($content);
	}

	protected function buildJsConfig(array $config) 
	{
		return '<script>const editConf = '.json_encode($config).'</script>';
	}

	protected function renumberPages()
	{
		if(!$this->input->post->position || !is_array($this->input->post->position)) {
			return false;
		}
		$pages = $this->pages->items;

		foreach($this->input->post->position as $pos => $pageid) {
			$page = $this->pages->getItem((int)$pageid, $pages);
			$page->position = ((int) $pos + 1);
		}
		$page->save();
		$this->imanager->sectionCache->expire();
		return true;
	}

	protected function ___removePage()
	{
		$page = $this->pages->getItem((int)$this->input->get->page);
		$child = ($page) ? $this->pages->getItem("parent=$page->id") : null;
		// Child pages are available, deletion is not possible
		if($child) {
			$this->msgs[] = array(
				'type' => 'error',
				'value' => $this->i18n['error_remove_parent_page']
			);
			return false;
		}
		elseif($page && $page->id == 1) {
			$this->msgs[] = array(
				'type' => 'error',
				'value' => $this->i18n['error_deleting_first_page']
			);
			return false;
		}
		elseif($this->config['protectCSRF'] && !$this->csrf->isTokenValid(
			$this->input->get->tokenName,
			$this->input->get->tokenValue, true)) {
				$this->msgs[] = array(
					'type' => 'error',
					'value' => $this->i18n['error_csrf_token_mismatch']
				);
			return false;
		}
		elseif($page && $page->id != 1 && $this->pages->remove($page)) {
			$this->msgs[] = array(
				'type' => 'success',
				'value' => $this->i18n['page_successful_removed']
			);
			$this->imanager->sectionCache->expire();
			return true;
		}

		$this->msgs[] = array(
			'type' => 'error',
			'value' => $this->i18n['error_deleting_page']
		);
		return false;
	}

	protected function ___savePage()
	{
		$this->page = null;

		if ($this->input->get->page) { 
			$this->page = $this->pages->getItem((int)$this->input->get->page); 
		}
		if (!$this->page) { 
			$this->page = new Page($this->pages->id); 
		}

		$name = $this->imanager->sanitizer->text(str_replace('"', '', $this->input->post->name));
		if (!$name) {
			$this->msgs[] = array(
				'type' => 'error',
				'value' => $this->i18n['error_page_title']
			);
		}
		// Check if the name already exists
		$exists = $this->pages->getItem("name=$name");
		if ($exists && $exists->id != $this->page->id) {
			$this->msgs[] = array(
				'type' => 'error',
				'value' => $this->i18n['error_page_title_exists']
			);
		}
		$this->page->set('name', $name);

		if ($this->input->post->menu_title) {
			$menuTitle = $this->imanager->sanitizer->text(str_replace('"', '', $this->input->post->menu_title));
		} else {
			$menuTitle = $name;
		}
		$this->page->set('menu_title', $menuTitle);

		$url = trim($name, '-');
		if($this->input->post->slug) {
			$slug = preg_replace("/(-)\\1+/", "$1",
				$this->imanager->sanitizer->pageName($this->input->post->slug));
		} else {
			$slug = preg_replace("/(-)\\1+/", "$1",
				$this->imanager->sanitizer->pageName($url));
		}
		// Its one of the reserved names?
		if(in_array($slug, $this->reservedSlugs)) {
			$this->msgs[] = array(
				'type' => 'error',
				'value' => $this->i18n['error_slug_reserved']
			);
			return false;
		}
		// Invalid News name
		if($name && !$slug) {
			$this->msgs[] = array(
				'type' => 'error',
				'value' => $this->i18n['error_page_name']
			);
			return false;
		}

		$this->page->set('slug', $slug);

		$content = htmlentities($this->input->post->content);
		if(!$content) {
			$this->msgs[] = array(
				'type' => 'error',
				'value' => $this->i18n['error_page_content']
			);
		}
		$this->page->set('content', $content);

		$template = $this->imanager->sanitizer->templateName($this->input->post->template);
		$this->page->set('template', $template);

		$parentid = (int) $this->input->post->parent;
		if($this->page->id && $parentid == $this->page->id) {
			$parentid = null;
		} else if($parentid) {
			$parent = $this->pages->getItem($parentid);
			if(!$parent) {
				$parentid = null;
			}
		}
		$this->page->set('parent', $parentid, false);

		$this->page->active = false;
		if($this->input->post->published) {
			$this->page->active = true;
		}

		if(!empty($this->msgs)) { return false; }

		$this->page->set('pagetype', 1, false);

		if($this->config['protectCSRF'] && !$this->csrf->isTokenValid(
			$this->input->post->tokenName,
			$this->input->post->tokenValue, true)) {
			$this->msgs[] = array(
				'type' => 'error',
				'value' => $this->i18n['error_csrf_token_mismatch']
			);
			return false;
		}
		// Save page
		if($this->page->save()) {
			// Save images
			if($this->imagesSet()) {
				// Save page again
				$this->page->save();
				$this->imanager->sectionCache->expire();
				$this->msgs[] = array(
					'type' => 'success',
					'value' => $this->i18n['successful_saved_page']
				);
				return true;
			}
			$this->imanager->sectionCache->expire();
			
			$this->msgs[] = array(
				'type' => 'error',
				'value' => $this->i18n['error_page_images']
			);
			return false;
		}
		
		$this->msgs[] = array(
			'type' => 'error',
			'value' => $this->i18n['error_saving_page']
		);
		return false;
	}
	
	protected function imagesSet()
	{
		// Intercept timestamp in order to identify the temporary folder later    
		$timestamp_images = time();
		if($this->input->post->timestamp_images) {
			$timestamp_images = $this->sanitizer->date($this->input->post->timestamp_images);
		}
		/* $sanitize = ($this->config['allowHtmlImageTitle']) ? false : true;
		if($this->config['allowHtmlImageTitle'] && ! empty($this->input->post->title_images)) {
			foreach($this->input->post->title_images as $key => $title) {
				$this->input->post->title_images[$key] = trim(htmlentities(str_replace('"', '', $title)));
			}
		} */
		$dataSent = array(
			'file' => $this->input->post->position_images,
			'title' => $this->input->post->title_images,
			'timestamp' => $timestamp_images
		);
		if($this->page->set('images', $dataSent) === false) {
			$this->msgs[] = array(
				'type' => 'error',
				'value' => $this->i18n['error_page_images']
			);
			return false;
		}
		return true;
	}

	protected function ___redirect($url)
	{
		Util::redirect($url);
	}
}