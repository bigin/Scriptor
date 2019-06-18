<?php

/**
 * Class Pages
 *
 *
 * NOTE:
 * You can use $editor - Inherited from Module
 *
 */
class Pages extends Module
{
	public function execute()
	{
		if($this->input->get->page) {
			$this->page = $this->pages->getItem((int)$this->input->get->page);
		}
		if($this->segments->get(0) == 'pages' && !$this->segments->get(1)) {
			// Pages list view
			$this->checkAction();
			$this->pageTitle = 'Page list - Scriptor';
			$this->pageContent = $this->renderPageList();
			$this->breadcrumbs = '<li><a href="../">'.$this->i18n['dashboard_menu'].'</a></li><li><span>'.
				$this->i18n['pages_menu'].'</span></li>';
		}
		// Page editor
		elseif($this->segments->get(0) == 'pages' && $this->segments->get(1) == 'edit') {
			$this->pageTitle = 'Page editor - Scriptor';
			$this->checkAction();
			$this->pageContent = $this->renderPageEditor();
			$this->breadcrumbs = '<li><a href="../../">'.$this->i18n['dashboard_menu'].'</a></li><li><a href="../">'.
				$this->i18n['pages_menu'].'</a></li><li>'. (($this->page) ?
					'<span>'.$this->i18n['pages_edit_menu'].'</span>' : '<span>'.
					$this->i18n['pages_create_menu']).'</span></li>';
		}
		// Pages list remove page
		elseif($this->segments->get(0) == 'pages' && $this->segments->get(1) == 'delete') {
			$this->checkAction();
		}
		// Settings
		elseif($this->segments->get(0) == 'settings' && !$this->segments->get(1)) {
			$this->checkAction();
			$this->pageContent = $this->renderSettingsEditor();
			$this->breadcrumbs = '<li><a href="../">'.$this->i18n['dashboard_menu'].'</a></li><li><span>'.
				$this->i18n['settings_menu'].'</span></li>';
		}
	}

	/**
	 * Checks user actions
	 */
	protected function checkAction()
	{
		if($this->input->post->action == 'save-page') {
			if($this->savePage()) {
				\Imanager\Util::redirect("./?page={$this->page->id}");
				exit;
			}
		}
		elseif($this->input->post->action == 'renumber-pages') {
			$status = $this->renumberPages();
			header('Content-type: application/json; charset=utf-8');
			echo json_encode(array('status' => $status));
			exit;
		}
		else if($this->segments->get(1) == 'delete' && $this->input->get->page) {
			$status = $this->removePage();
			\Imanager\Util::redirect('../');
			exit;
		}
	}

	protected function renderPageEditor()
	{
		$page = $this->page;
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
		if(!$page) {
			ob_start(); ?>
			<!-- The Modal -->
			<div id="screen" class="modal">
				<!-- Modal content -->
				<div id="screen-content">
					<span class="close">&times;</span>
					<div id="page-text"></div>
				</div>
			</div>
			<h1><?php echo $this->i18n['page_create_header']; ?></h1>
			<hr>
			<form id="page-form" action="./" method="post">
				<div class="form-control">
					<label class="required" for="pagename"><?php echo $this->i18n['title_label']; ?></label>
					<input name="name" id="pagename" type="text" value="">
				</div>
				<div class="form-control">
					<label class="required" for="markdown"><?php echo $this->i18n['content_label']; ?></label>
					<textarea id="markdown" name="content" onkeyup="auto_grow(this)"></textarea>
				</div>
				<div class="form-control">
					<label for="parent"><?php echo $this->i18n['parent_label']; ?></label>
					<select name="parent" id="parent">
						<option><?php echo $this->i18n['parent_select_option']; ?></option>
						<?php echo $parent_options; ?>
					</select>
				</div>
				<div class="form-control">
					<label for="publish"><input name="published" id="publish" type="checkbox" value="1"<?php
						(($page && $page->active) ? ' checked' : '') ?>> <?php echo $this->i18n['published_label']; ?></label>
				</div>
				<input type="hidden" name="action" value="save-page">
				<button class="icons" type="submit" id="save" name="save" value="1"><i class="fas fa-save"></i>
					<?php echo $this->i18n['create_button']; ?></button>
				<button class="icons" type="submit" id="render" name="render" value="1"><i class="fas fa-eye"></i>
					<?php echo $this->i18n['view_button']; ?></button>
			</form>
			<?php
		} else {
			ob_start(); ?>
			<!-- The Modal -->
			<div id="screen" class="modal">
				<!-- Modal content -->
				<div id="screen-content">
					<span class="close">&times;</span>
					<div id="page-text"></div>
				</div>
			</div>
			<h1><?php echo $this->i18n['page_edit_header']; ?></h1>
			<hr>
			<form id="page-form" action="./?page=<?php echo (int)$this->input->get->page; ?>" method="post">
				<div class="form-control">
					<label class="required" for="pagename"><?php echo $this->i18n['title_label']; ?></label>
					<input name="name" id="pagename" type="text" value="<?php echo $page->name; ?>">
				</div>
				<div class="form-control">
					<label class="required" for="markdown"><?php echo $this->i18n['content_label']; ?></label>
					<textarea id="markdown" name="content" onkeyup="auto_grow(this)"><?php echo $page->content; ?></textarea>
				</div>
				<div class="form-control">
					<label for="parent"><?php echo $this->i18n['parent_label']; ?></label>
					<select name="parent" id="parent">
						<option><?php echo $this->i18n['parent_select_option']; ?></option>
						<?php echo $parent_options; ?>
					</select>
				</div>
				<div class="form-control">
					<label for="publish"><input name="published" id="publish" type="checkbox" value="1"<?php
						echo (($page->active) ? ' checked' : '') ?>> <?php echo $this->i18n['published_label']; ?></label>
				</div>
				<input type="hidden" name="action" value="save-page">
				<button class="icons" type="submit" id="save" name="save" value="1"><i class="fas fa-save"></i>
					<?php echo $this->i18n['save_button']; ?></button>
				<button class="icons" type="submit" id="render" name="render" value="1"><i class="fas fa-eye"></i>
					<?php echo $this->i18n['view_button']; ?></button>
			</form>
			<?php
		}
		return ob_get_clean();
	}


	protected function renderPageList()
	{
		$output = '';
		$rows = '<tr><td colspan="4">'.$this->i18n['no_page'].'</td></tr>';
		if($this->pages->items){
			$rows = $this->renderRows();
		}
		ob_start(); ?>
		<div id="page-list-wrapper">
			<h1><?php echo $this->i18n['pages_header'] ?></h1>
			<hr>
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
		<a class="btn" href="./edit/"><button class="icons" name="button" value="
			<?php echo $this->i18n['create_button']; ?>" type="button">
			<span class="ui-button-text"><i class="fa fa-plus-circle"></i> &nbsp;
				<?php echo $this->i18n['create_button']; ?></span></button></a>
		<?php
		$output = ob_get_clean();

		return $output;
	}


	protected function renderRows()
	{
		$rows = '';
		$sorted = $this->pages->sort('position', 'asc',  0, 100, $this->pages->items);
		//$sorted = $this->pages->sort('parent', 'asc',  0, 0, $sorted);
		foreach($sorted as $page) {
			$rows .= '
				<tr class="sortable">
					<td><i class="fas fa-sort"></i><input type="hidden" name="position[]" value="'.$page->id.'" ></td>
					<td>'.$page->id.'</td>
					<td>'.(($page->parent) ? $page->parent : '').'</td>
					<td><a href="edit/?page='.$page->id.'">'.
				((mb_strlen($page->name) > 80) ? mb_substr($page->name, 0,80).'...' : $page->name).
				'</td></a><td><a class="remove" rel="'.$this->i18n['pre_delete_msg'].
				'" href="delete/?page='.$page->id.'"><i class="far fa-trash-alt"></i></a></td>
				</tr>
			';
		}
		return $rows;
	}

	protected function renumberPages()
	{
		if(!$this->input->post->position || !is_array($this->input->post->position)) {
			return false;
		}
		foreach($this->input->post->position as $pos => $pageid) {
			$page = $this->pages->getItem((int)$pageid);
			$page->position = ((int) $pos + 1);
			$page->save();
		}
		$this->imanager->sectionCache->expire();
		return true;
	}

	protected function removePage()
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
		} else if($page && $page->id != 1 && $this->pages->remove($page)) {
			$this->msgs[] = array(
				'type' => 'success',
				'value' => $this->i18n['page_successful_removed']
			);
			$this->imanager->sectionCache->expire();
			return true;
		} else if($page && $page->id == 1) {
			$this->msgs[] = array(
				'type' => 'error',
				'value' => $this->i18n['error_deleting_first_page']
			);
			return false;
		} else {
			$this->msgs[] = array(
				'type' => 'error',
				'value' => $this->i18n['error_deleting_page']
			);
			return false;
		}
	}

	protected function savePage()
	{
		$this->page = null;

		if($this->input->get->page) { $this->page = $this->pages->getItem((int)$this->input->get->page); }
		if(!$this->page) { $this->page = new \Imanager\Item($this->pages->id); }

		$name = $this->imanager->sanitizer->text(str_replace('"', '', $this->input->post->name));
		if(!$name) {
			$this->msgs[] = array(
				'type' => 'error',
				'value' => $this->i18n['error_page_title']
			);
		}
		// Check if the name already exists
		$exists = $this->pages->getItem("name=$name");
		if($exists && $exists->id != $this->page->id) {
			$this->msgs[] = array(
				'type' => 'error',
				'value' => $this->i18n['error_page_title_exists']
			);
		}
		$this->page->set('name', $name);
		$content = htmlentities($this->input->post->content);
		if(!$content) {
			$this->msgs[] = array(
				'type' => 'error',
				'value' => $this->i18n['error_page_content']
			);
		}
		$this->page->set('content', $content);

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
		$slug = preg_replace("/(-)\\1+/", "$1", $this->imanager->sanitizer->pageName($name));
		$this->page->set('slug', $slug);
		if($this->page->save()) {
			$this->imanager->sectionCache->expire();
			$this->msgs[] = array(
				'type' => 'success',
				'value' => $this->i18n['successful_saved_page']
			);
			return true;
		}
		return false;
	}
}