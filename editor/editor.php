<?php

class Editor
{
	/**
	 * @var object $imanager - Instance of IManager
	 */
	protected $imanager;

	/**
	 * @var array $config - Scriptor config
	 */
	public $config;

	/**
	 * @var sting $pageTitle - Meta page title
	 */
	public $pageTitle;

	/**
	 * @var string $pageUrl - Current page URL
	 */
	public $pageUrl;

	/**
	 * @var string $pageContent - Current page content
	 */
	public $pageContent;

	/**
	 * @var object $input - Input object instance
	 */
	protected $input;

	/**
	 * @var object $segments - Segments object instance
	 */
	protected $segments;

	/**
	 * @var array $pages - An array of Page objects
	 */
	protected $pages;

	/**
	 * @var array $users - An array of Users objects
	 */
	protected $users;

	/**
	 * @var array $msgs - An array of local error messages
	 */
	protected $msgs;

	/**
	 * @var string $messages - rendered messages (markup)
	 */
	public $messages;

	/**
	 * @var object $page - The current page object instance
	 */
	protected $page;

	/**
	 * @var object $user - The current user object instance
	 */
	protected $user;

	/**
	 * @var string $breadcrumbs - Breadcrumbs markup
	 */
	public $breadcrumbs;

	/**
	 * @var array $i18n - An array of language sets
	 */
	public $i18n;

	/**
	 * Editor constructor.
	 *
	 * @param $config
	 */
	public function __construct($config) {
		$this->config = $config;
		require("lang/{$this->config['editor_lang']}.php");
		$this->i18n = $i18n;
	}

	/**
	 * Init editor class
	 * Prepares some variables for local use and executes actions
	 *
	 */
	public function init()
	{
		$this->imanager = imanager();
		$this->pageUrl = $this->imanager->config->getUrl();
		$this->input = $this->imanager->input;
		$this->segments = $this->input->urlSegments;
		$this->pages = $this->imanager->getCategory('name=Pages');
		$this->users = $this->imanager->getCategory('name=Users');
		if(!isset($_SESSION['msgs'])) {
			$_SESSION['msgs'] = array();
		}
		$this->msgs = & $_SESSION['msgs'];

		$this->execute();
		$this->renderMessages();
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
			exit();
		}
		else if($this->segments->get(1) == 'delete' && $this->input->get->page) {
			$status = $this->removePage();
			\Imanager\Util::redirect('../');
			exit();
		}
		else if($this->segments->get(0) == 'logout') {
			unset($_SESSION['loggedin']);
			unset($_SESSION['userid']);
			\Imanager\Util::redirect($this->pageUrl);
			exit;
		}
		else if($this->input->post->action == 'save-profil') {
			if($this->checkProfilData($_SESSION['userid'])) {
				if($this->user->save()) {
					$this->msgs[] = array(
						'type' => 'success',
						'value' => $this->i18n['profil_successful_saved']
					);
				}
			}
		}
	}

	/**
	 * Login action only
	 * The method checks if the data were correct and then performs a login
	 *
	 * @return bool
	 */
	protected function loginAction()
	{
		if($this->input->post->action == 'login') {
			$name = $this->imanager->sanitizer->text($this->input->post->username);
			$user = $this->users->getItem("name=$name");
			if(!$user || !$user->password->compare($this->input->post->password)) {
				$this->msgs[] = array(
					'type' => 'error',
					'value' => $this->i18n['error_login']
				);
				return false;
			}

			$_SESSION['loggedin'] = true;
			$_SESSION['userid'] = $user->id;

			$this->msgs[] = array(
				'type' => 'success',
				'value' => $this->i18n['successful_login']
			);
			\Imanager\Util::redirect($this->pageUrl);
			exit;
		}
		return false;
	}


	protected function execute()
	{
		if(!isset($_SESSION['loggedin']) || true != $_SESSION['loggedin']) {
			$this->loginAction();
			$this->pageTitle = 'Login - Scriptor';
			$this->pageContent = $this->renderLoginForm();
			return;
		}
		if(!$this->segments->get(0)) {
			// Redirect to the pages
			$this->pageTitle = 'Dashboard - Scriptor';
			$this->pageContent = '';
			$this->breadcrumbs = '<li><a href="./">'.$this->i18n['dashboard_menu'].'</a></li>';
		} elseif($this->segments->get(0) == 'pages' && !$this->segments->get(1)) {
			// Pages list view
			$this->checkAction();
			$this->pageTitle = 'Page list - Scriptor';
			$this->pageContent = $this->renderPageList();
			$this->breadcrumbs = '<li><a href="../">'.$this->i18n['dashboard_menu'].'</a></li><li><a href="./">'.
				$this->i18n['pages_menu'].'</a></li>';
		} elseif($this->segments->get(0) == 'pages' && $this->segments->get(1) == 'edit') {
			// Page editor
			$this->pageTitle = 'Page editor - Scriptor';
			$this->checkAction();
			$this->pageContent = $this->renderPageEditor();
			$this->breadcrumbs = '<li><a href="../../">'.$this->i18n['dashboard_menu'].'</a></li><li><a href="../">'.
				$this->i18n['pages_menu'].'</a></li>';
		} elseif($this->segments->get(0) == 'pages' && $this->segments->get(1) == 'delete') {
			// Pages list remove page
			$this->checkAction();
		} elseif($this->segments->get(0) == 'settings' && !$this->segments->get(1)) {
			$this->checkAction();
			$this->pageContent = $this->renderSettingsEditor();
			$this->breadcrumbs = '<li><a href="../">'.$this->i18n['dashboard_menu'].'</a></li><li><a href="./">'.
				$this->i18n['settings_menu'].'</a></li>';
		} elseif($this->segments->get(0) == 'logout') {
			// Logout
			$this->checkAction();
		} elseif($this->segments->get(0) == 'profil' && $this->segments->get(1) == 'edit') {
			// Profil
			$this->pageTitle = 'Profil editor - Scriptor';
			$this->checkAction();
			$this->pageContent = $this->renderProfilEditor($_SESSION['userid']);
			$this->breadcrumbs = '<li><a href="../../">'.$this->i18n['dashboard_menu'].'</a></li><li><a href="./">'.
				$this->i18n['profil_menu'].'</a></li>';
		// It's a custom module?
		} elseif($this->segments->get(0) && array_key_exists($this->segments->get(0), $this->config['modules'])) {
			$module = $this->config['modules'][$this->segments->get(0)];
			// Module disabled
			if(!$module['active']) { return; }
			// Module file exists?
			if(!file_exists(dirname(__DIR__).'/modules/'.$module['class'].'/'.$module['class'].'.php')) {
				return;
			}
			// include module
			include dirname(__DIR__).'/modules/'.$module['class'].'/'.$module['class'].'.php';
			$module = new $module['class']();
			$module->execute();
		}
	}



	protected function renderPageEditor()
	{
		$page = $this->page;
		if(!$page && $this->input->get->page) { $page = $this->pages->getItem((int)$this->input->get->page); }

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


	protected function renderMessages()
	{
		if(!empty($this->msgs)) {
			$this->messages .= '<div class="message">';
			foreach($this->msgs as $msg) {
				if($msg['type'] == 'error') {
					$this->messages .= '<p class="error">'.$msg['value'].'</p>';
				} elseif($msg['type'] == 'success') {
					$this->messages .= '<p class="success">'.$msg['value'].'</p>';
				}
			}
			$this->messages .= '</div>';
			unset($_SESSION['msgs']);
			$_SESSION['msgs'] = null;
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
		$this->page->set('slug', $name);
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

	protected function renderSettingsEditor()
	{
		return '<p>'.$this->i18n['settings_page_text'].'</p>';
	}

	protected function renderProfilEditor($userid)
	{
		$user = $this->user;
		if(!$user) { $user = $this->users->getItem((int)$userid); }
		if(!$user) { return; }
		ob_start(); ?>
		<h1><?php echo $this->i18n['profil_editor_header']; ?></h1>
		<hr>
		<form id="profil-form" action="./" method="post">
			<div class="form-control">
				<label class="required" for="username"><?php echo $this->i18n['username_label']; ?></label>
				<input name="username" id="username" type="text" value="<?php echo $user->name; ?>">
			</div>
			<div class="form-control">
				<label class="required" for="email"><?php echo $this->i18n['email_label']; ?></label>
				<input name="email" id="email" type="email" value="<?php echo $user->email; ?>">
			</div>
			<div class="form-control">
				<label for="pass"><?php echo $this->i18n['new_password_label']; ?></label>
				<input name="password" id="pass" type="password">
			</div>
			<div class="form-control">
				<label for="pass_confirm"><?php echo $this->i18n['password_confirm_label']; ?></label>
				<input name="password_confirm" id="pass_confirm" type="password">
			</div>
			<input type="hidden" name="action" value="save-profil">
			<button class="icons" type="submit" id="save" name="save" value="1"><i class="fas fa-save"></i>
			<?php echo $this->i18n['save_button']; ?></button>
		</form>
		<?php return ob_get_clean();
	}

	protected function renderLoginForm()
	{
		ob_start(); ?>
		<h1><?php echo $this->i18n['login_header']; ?></h1>
		<hr>
		<form id="login-form" action="./" method="post">
			<div class="form-control">
				<label for="username"><?php echo $this->i18n['username_label']; ?></label>
				<input type="text" id="username" name="username">
			</div>
			<div class="form-control">
				<label for="pass"><?php echo $this->i18n['password_label']; ?></label>
				<input type="password" id="pass" name="password">
			</div>
			<input type="hidden" name="action" value="login">
			<button class="icons" type="submit" name="submit"><i class="fas fa-sign-in-alt"></i>
			<?php echo $this->i18n['login_button']; ?></button>
		</form>
		<?php return ob_get_clean();
	}

	protected function checkProfilData($userid)
	{
		$this->user = $this->users->getItem((int)$userid);

		if(!$this->input->post->username || !$this->input->post->email) {
			$this->msgs[] = array(
				'type' => 'error',
				'value' => $this->i18n['profil_incomplete']
			);
		}
		if($this->input->post->password) {
			if(mb_strlen($this->input->post->password) < 6) {
				$this->msgs[] = array(
					'type' => 'error',
					'value' => $this->i18n['short_password']
				);
			}
			if(!$this->msgs) {
				if(true !== $this->user->set('password', array(
					'password' => $this->input->post->password,
					'confirm_password' => $this->input->post->password_confirm)
					)
				) {
					$this->msgs[] = array(
						'type' => 'error',
						'value' => $this->i18n['error_password_comparison']
					);
				}
			}
		}
		$this->user->set('name', str_replace('"', '', $this->input->post->username));
		$this->user->set('email', $this->imanager->sanitizer->email($this->input->post->email));

		if($this->msgs) { return false; }

		return true;
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
}