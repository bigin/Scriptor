<?php

/**
 * Class Editor
 */
class Editor extends Module
{
	protected function execute()
	{
		$this->checkAction();

		// Show login form (build in)
		if(!isset($_SESSION['loggedin']) || true != $_SESSION['loggedin']) {
			$this->pageTitle = 'Login - Scriptor';
			$this->pageContent = $this->renderLoginForm();
		}
		// Dashboard (build in)
		elseif(!$this->segments->get(0)) {
			$this->pageTitle = 'Dashboard - Scriptor';
			$this->pageContent = $this->renderDashboard();
			$this->breadcrumbs = '<li><span>'.$this->i18n['dashboard_menu'].'</span></li>';
		}
		// Settings section (build in)
		elseif($this->segments->get(0) == 'settings' && !$this->segments->get(1)) {
			$this->pageContent = $this->renderSettingsEditor();
			$this->breadcrumbs = '<li><a href="../">'.$this->i18n['dashboard_menu'].'</a></li><li><span>'.
				$this->i18n['settings_menu'].'</span></li>';
		}
		// Pages section (build in)
		elseif($this->segments->get(0) == 'pages') {
			$module = $this->config['modules'][$this->segments->get(0)];
			if(!$module['active']) { return; }
			include __DIR__ . '/modules/pages/pages.php';
			$module = new $module['class']($this->config);
			$module->map($this);
			$module->execute();
		}
		// Profile section (build in)
		elseif($this->segments->get(0) == 'profile') {
			$module = $this->config['modules'][$this->segments->get(0)];
			if(!$module['active']) { return; }
			include __DIR__ . '/modules/profile/profile.php';
			$module = new $module['class']($this->config);
			$module->map($this);
			$module->execute();
		}
		// Execute Module
		elseif($this->segments->get(0) && array_key_exists($this->segments->get(0), $this->config['modules'])) {
			$module = $this->config['modules'][$this->segments->get(0)];
			// Is module disabled?
			if(!$module['active']) { return; }
			// Module file exists?
			if(file_exists($module['path'] . '.php')) {
				// include module
				include $module['path'] . '.php';
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
		// Check log in data
		if(!isset($_SESSION['loggedin']) || true != $_SESSION['loggedin']) {
			$this->loginAction();
		}
		// Check log out data
		elseif($this->segments->get(0) == 'logout') {
			$this->logoutAction();
		}
	}

	/**
	 * Login process
	 * This method checks whether the user input is correct and
	 * performs the login process.
	 *
	 * @since 3.1.2 - CSRF check
	 *
	 * @return bool
	 */
	protected function loginAction()
	{
		if($this->input->post->action == 'login') {
			if($this->config['protectCSRF'] && !$this->csrf->isTokenValid(
					$this->input->post->tokenName,
					$this->input->post->tokenValue, true)) {
				$this->msgs[] = array(
					'type' => 'error',
					'value' => $this->i18n['error_csrf_token_mismatch']
				);
				return false;
			}
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

			$this->csrf->resetAll();

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
	 * Log out procedure
	 * This method checks whether the token is valid and
	 * performs the log out process.
	 */
	protected function logoutAction()
	{
		if($this->config['protectCSRF'] && !$this->csrf->isTokenValid(
			$this->input->get->tokenName,
			$this->input->get->tokenValue, true)) {
			$this->msgs[] = array(
				'type' => 'error',
				'value' => $this->i18n['error_csrf_token_mismatch']
			);
		} else {
			unset($_SESSION['loggedin']);
			unset($_SESSION['userid']);
			$this->msgs[] = array(
				'type' => 'success',
				'value' => $this->i18n['successful_logout']
			);
		}
		\Imanager\Util::redirect($this->pageUrl);
		exit;
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
			<?php echo $this->csrf->renderInputs(); ?>
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