<?php

class Editor extends Module
{
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
			$this->pageContent = $this->renderDashboard();
			$this->breadcrumbs = '<li><span>'.$this->i18n['dashboard_menu'].'</span></li>';
		}
		elseif($this->segments->get(0) == 'settings' && !$this->segments->get(1)) {
			$this->checkAction();
			$this->pageContent = $this->renderSettingsEditor();
			$this->breadcrumbs = '<li><a href="../">'.$this->i18n['dashboard_menu'].'</a></li><li><span>'.
				$this->i18n['settings_menu'].'</span></li>';
		}
		elseif($this->segments->get(0) == 'logout') {
			// Logout
			$this->checkAction();
		}
		elseif($this->segments->get(0) && array_key_exists($this->segments->get(0), $this->config['modules'])) {
			$module = $this->config['modules'][$this->segments->get(0)];
			// Is module disabled?
			if(!$module['active']) { return; }
			// Module file exists?
			if(file_exists(__DIR__ . '/modules/' . $module['path'] . '.php')) {
				// include module
				include __DIR__ . '/modules/' . $module['path'] . '.php';
				$module = new $module['class']($this->config);
				$module->map($this);
				$module->execute();
			}
		}
	}

	/**
	 * Checks user actions
	 */
	protected function checkAction()
	{
		if($this->segments->get(0) == 'logout') {
			unset($_SESSION['loggedin']);
			unset($_SESSION['userid']);
			\Imanager\Util::redirect($this->pageUrl);
			exit;
		}
	}

	/**
	 * Login action
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

	/**
	 * Renders the login form for the admin section
	 *
	 * @return false|string
	 */
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

	protected function renderSettingsEditor()
	{
		return '<p>'.$this->i18n['settings_page_text'].'</p>';
	}

	protected function renderDashboard()
	{
		return $this->i18n['dashboard_content'];
	}
}