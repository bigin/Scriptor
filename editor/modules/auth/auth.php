<?php

namespace Scriptor\Core\Modules;

use Imanager\TemplateParser;
use Imanager\Util;
use Scriptor\Core\Module;
use Scriptor\Core\Scriptor;

class Auth extends Module
{
	private $userIP;

	private $templateParser;

	private $users;

	public function init() {
		parent::init();
		$this->users = $this->imanager->getCategory('name=Users');
		$this->userIP = $this->getIP();
		$this->templateParser = new TemplateParser();
		$this->csrf = Scriptor::getCSRF();

		if(Scriptor::execHook($this) && $this->event->replace) return;
	}

	/**
	 * Default execute module method
	 */
	public function execute()
	{
		$this->checkAction();

		if(isset($_SESSION['loggedin']) && true === $_SESSION['loggedin']) {
			Util::redirect($this->siteUrl);
		}
		$this->pageTitle = 'Login - Scriptor';
		$this->pageContent = $this->renderLoginForm();
	}
	
	/**
	 * Checks user actions
	 */
	public function ___checkAction()
	{
		// Check log-in form user input
		if(!isset($_SESSION['loggedin']) || true !== $_SESSION['loggedin']) {
			$this->loginAction();
		} elseif($this->segments->get(1) == 'logout') { 
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

			if(true !== $this->checkAttempts()) {
				$this->msgs[] = array(
					'type' => 'error',
					'value' => $this->templateParser->render($this->i18n['error_max_login_attempts'], [
						'count' => $this->config['accessLockoutDuration']
					])
				);
				return false;
			}

			if(!isset($_SESSION['login_attempts'])) {
				$this->msgs[] = ['type' => 'error', 'value' => $this->i18n['error_cookie']];
				return false;
			}

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
				$this->increaseAttempts($this->userIP);
				$attempts = $this->config['maxFailedAccessAttempts'] - 
					(isset($_SESSION['login_attempts']['attempts']) ? (int)$_SESSION['login_attempts']['attempts'] : 1);
				if($attempts > 0) {
					$this->msgs[] = [
						'type' => 'error',
						'value' => $this->templateParser->render($this->i18n['error_login'], [
							'count' => $attempts
						])
					];	
				} else {
					$this->msgs[] = array(
						'type' => 'error',
						'value' => $this->templateParser->render($this->i18n['error_max_login_attempts'], [
							'count' => $this->config['accessLockoutDuration']
						])
					);	
				}
				return false;
			}

			$_SESSION['loggedin'] = true;
			$_SESSION['userid'] = $user->id;

			$this->csrf->resetAll();

			$this->resetAttempts();

			$this->msgs[] = array(
				'type' => 'success',
				'value' => $this->i18n['successful_login']
			);
			Util::redirect($this->siteUrl);
			exit;
		}
		return false;
	}

	/**
	 * Check the user login attempts.
	 * 
	 */
	private function checkAttempts()
	{
		if(empty($_SESSION['login_attempts']['ip']) || $_SESSION['login_attempts']['ip'] !== $this->userIP) {
			$this->createFirstAttempt($this->userIP);
		}
		if($_SESSION['login_attempts']['attempts'] >= $this->config['maxFailedAccessAttempts']) {
			$since_start = $_SESSION['login_attempts']['dt']->diff(new \DateTime());
			$minutes = $since_start->days * 24 * 60;
			$minutes += $since_start->h * 60;
			$minutes += $since_start->i;
			if($minutes >= $this->config['accessLockoutDuration']) {
				$this->resetAttempts();
				return true;
			}
			return false;
		}

		return true;
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
		Util::redirect($this->siteUrl);
		exit;
	}

	/**
	 * Renders the login form for the admin section
	 *
	 * @return string
	 */
	protected function renderLoginForm()
	{
		ob_start(); ?>
		<h1><?php echo $this->i18n['login_header']; ?></h1>
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
			<button class="icons button" type="submit" name="submit"><i class="gg-log-in"></i>
			<span><?php echo $this->i18n['login_button']; ?></span></button>
			<?php echo $this->csrf->renderInputs(); ?>
		</form>
		<?php return ob_get_clean();
	}

	/**
	 * Get the IP address of the current user (IPv4)
	 * 
	 * @return string - Returns string
	 */
	private function getIP()
	{
		if(!empty($_SERVER['HTTP_CLIENT_IP'])) $ip = $_SERVER['HTTP_CLIENT_IP']; 
		else if(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		else if(!empty($_SERVER['REMOTE_ADDR'])) $ip = $_SERVER['REMOTE_ADDR']; 
		else $ip = '0.0.0.0';
		// It's possible for X_FORWARDED_FOR to have more than one CSV separated IP address
		if(strpos($ip, ',') !== false) { list($ip) = explode(',', $ip); }
		// sanitize: if IP contains something other than digits, periods, commas, spaces, 
		// then don't use it and instead fallback to the REMOTE_ADDR. 
		$test = str_replace(['.', ',', ' '], '', $ip); 
		if(!ctype_digit("$test")) $ip = $_SERVER['REMOTE_ADDR'];

		if(strpos($ip, ',') !== false) {
			// return multiple IPs
			$ips = explode(',', $ip);
			foreach($ips as $key => $ip) {
				$ip = ip2long(trim($ip));
				$ip = long2ip($ip);
				$ips[$key] = $ip;
			}
			$ip = implode(',', $ips);
		} else {
			// sanitize by converting to and from integer
			$ip = ip2long(trim($ip));
			$ip = long2ip($ip);
		}
		return $ip;
	}

	/**
	 * Creates login attempt session.
	 */
	private function createFirstAttempt($userIP)
	{
		$_SESSION['login_attempts'] = [
			'ip' => $userIP,
			'attempts' => 0,
			'dt' => null
		];
	}

	/**
	 * Increases login attempt session 
	 */
	private function increaseAttempts()
	{
		if(isset($_SESSION['login_attempts']['attempts']) && 
		$_SESSION['login_attempts']['attempts'] < $this->config['maxFailedAccessAttempts']) {
			$_SESSION['login_attempts']['attempts'] += 1;
			if($_SESSION['login_attempts']['attempts'] >= $this->config['maxFailedAccessAttempts']) {
				$_SESSION['login_attempts']['dt'] = new \DateTime();
			}
		}
	}

	/**
	 * Resets login attempt session
	 */
	private function resetAttempts()
	{
		unset($_SESSION['login_attempts']);
	}
}