<?php

namespace Scriptor;

class Profile extends Module
{
	private $user;

	 public function init()
	 {
		parent::init();
		$this->users = $this->imanager->getCategory('name=Users');
		$this->csrf = Scriptor::getCSRF();

		if(Scriptor::execHook($this) && $this->event->replace) return;
	 }

	/**
	 * Default execute module method
	 */
	public function execute()
	{
		$this->checkAction();

		// Profile editor section
		if($this->segments->get(0) == 'profile' && $this->segments->get(1) == 'edit') {
			$this->pageTitle = 'Profile editor - Scriptor';
			$this->pageContent = $this->renderProfileEditor($_SESSION['userid']);
			$this->breadcrumbs = '<li><a href="../../">'.$this->i18n['dashboard_menu'].'</a><i 
				class="gg-chevron-right"></i></li><li><span>'.$this->i18n['profile_menu'].'</span></li>';
		}
	}

	/**
	 * Checks user actions
	 */
	public function ___checkAction()
	{
		// Just redirect to profile view
		if($this->segments->get(0) == 'profile' && !$this->segments->get(1)) {
			\Imanager\Util::redirect('./edit/?profile='.(int)$_SESSION['userid']);
		}
		// Check and save user profile
		elseif($this->input->post->action == 'save-profile') {
			if($this->checkProfileData($_SESSION['userid'])) {
				$this->saveProfileData();
			}
		}
	}

	protected function ___checkProfileData($userid)
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

		if($this->config['protectCSRF'] && !$this->csrf->isTokenValid(
			$this->input->post->tokenName,
			$this->input->post->tokenValue, true)) {
			$this->msgs[] = array(
				'type' => 'error',
				'value' => $this->i18n['error_csrf_token_mismatch']
			);
		}

		if($this->msgs) { return false; }

		return true;
	}

	protected function ___saveProfileData()
	{
		if($this->user->save()) {
			$this->msgs[] = array(
				'type' => 'success',
				'value' => $this->i18n['profile_successful_saved']
			);
			\Imanager\Util::redirect('./');
		}

		return false;
	}

	protected function ___renderProfileEditor($userid)
	{
		$user = $this->user;
		if(!$user) { $user = $this->users->getItem((int)$userid); }
		if(!$user) { return; }
		ob_start(); ?>
		<h1><?php echo $this->i18n['profile_editor_header']; ?></h1>
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
			<button class="icons" type="submit" id="save" name="save" value="1"><i 
				class="gg-drive"></i>
				<span>&nbsp;<?php echo $this->i18n['save_button']; ?></span></button>
			<?php echo $this->csrf->renderInputs(); ?>
		</form>
		<?php return ob_get_clean();
	}
}