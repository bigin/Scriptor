<?php

class Profile extends Module
{
	/**
	 * $editor - Inherited from Module
	 */

	/*public function __construct($config) {
		parent::__construct($config);
		parent::init();
	}*/

	public function execute()
	{
		if($this->segments->get(0) == 'profile' && !$this->segments->get(1)) {
			\Imanager\Util::redirect('./edit/?profile='.(int)$_SESSION['userid']);
		}
		else if($this->segments->get(0) == 'profile' && $this->segments->get(1) == 'edit') {
			// Profile
			$this->pageTitle = 'Profile editor - Scriptor';
			$this->checkAction();
			$this->pageContent = $this->renderProfileEditor($_SESSION['userid']);
			$this->breadcrumbs = '<li><a href="../../">'.$this->i18n['dashboard_menu'].'</a></li><li><span>'.
				$this->i18n['profile_menu'].'</span></li>';
		}
	}

	/**
	 * Checks user actions
	 */
	protected function checkAction()
	{
		if($this->input->post->action == 'save-profile') {
			if($this->checkProfileData($_SESSION['userid'])) {
				if($this->user->save()) {
					$this->msgs[] = array(
						'type' => 'success',
						'value' => $this->i18n['profile_successful_saved']
					);
				}
			}
		}
	}

	protected function checkProfileData($userid)
	{
		$this->user = $this->users->getItem((int)$userid);

		if(!$this->input->post->username || !$this->input->post->email) {
			$this->msgs[] = array(
				'type' => 'error',
				'value' => $this->i18n['profile_incomplete']
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
				if(!$this->user->set('password', [
					'password' => $this->input->post->password,
					'confirm_password' => $this->input->post->password_confirm]
				)) {
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

	protected function renderProfileEditor($userid)
	{
		$user = $this->user;
		if(!$user) { $user = $this->users->getItem((int)$userid); }
		if(!$user) { return; }
		ob_start(); ?>
		<h1><?php echo $this->i18n['profile_editor_header']; ?></h1>
		<hr>
		<form id="profile-form" action="./" method="post">
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
			<input type="hidden" name="action" value="save-profile">
			<button class="icons" type="submit" id="save" name="save" value="1"><i class="fas fa-save"></i>
				<?php echo $this->i18n['save_button']; ?></button>
		</form>
		<?php return ob_get_clean();
	}
}