<?php

namespace Scriptor;
/**
 * Class CSRF
 *
 * CSRF object, which provides an API for
 * cross site request forgery protection.
 */
class CSRF
{

	/**
	 * @var null - Token name
	 */
	private $name = null;

	/**
	 * @var array - Scriptor config
	 */
	private $config;

	/**
	 * CSRF constructor.
	 */
	public function __construct()
	{
		$this->config = Scriptor::getProperty('config');
	}

	/**
	 * Get a CSRF Token name, or create one if it doesn't yet exist
	 *
	 * @return string
	 *
	 */
	public function getTokenName()
	{
		$tokenName = null;
		if(isset($_SESSION['token'][$this->name])) {
			$tokenName = $this->name;
		}
		if(!$tokenName) {
			$this->name = 'TOKEN' . mt_rand() . "X" . time();
			// Allow last "maxNumTokens"
			$max = (!isset($this->config['maxNumTokens']) ||
				$this->config['maxNumTokens'] < 0 ||
				$this->config['maxNumTokens'] > 50) ? 1 : (int) $this->config['maxNumTokens'];
			if(isset($_SESSION['token']) && count($_SESSION['token']) >= $max) {
				$_SESSION['token'] = array_slice($_SESSION['token'], -($max - 1), $max);
			}
			$_SESSION['token'][$this->name] = '';
		}
		return $this->name;
	}

	/**
	 * Get a CSRF Token value as stored in the session, or create one if it doesn't yet exist
	 *
	 * @return string
	 *
	 */
	public function getTokenValue()
	{
		$tokenName = $this->getTokenName();
		$tokenValue = $_SESSION['token'][$tokenName];
		if(empty($tokenValue)) {
			$tokenValue = \Imanager\Util::randomToken(32);
			$_SESSION['token'][$tokenName] = $tokenValue;
		}
		return $tokenValue;
	}

	/**
	 * Get a CSRF Token timestamp
	 *
	 * @return string
	 *
	 */
	public function getTokenTime()
	{
		$name = $this->getTokenName();
		$time = (int) substr($name, strrpos($name, 'X')+1);
		return $time;
	}

	/**
	 * Get a CSRF Token name and value
	 *
	 * @return array ("name" => "token name", "value" => "token value", "time" => created timestamp)
	 *
	 */
	public function getToken()
	{
		return [
			'name' => $this->getTokenName(),
			'value' => $this->getTokenValue(),
			'time' => $this->getTokenTime()
		];
	}

	/**
	 * Returns true if the current POST request contains a valid CSRF token, false if not
	 *
	 * Example:
	 * if($csrf->isTokenValid($tokenName, $tokenValue)) {
	 *     // Token is valid
	 * } else {
	 *     // Invalid token
	 * }
	 *
	 * @param bool|null Reset after checking? Or omit (null) for auto.
	 * @return bool
	 *
	 */
	public function isTokenValid($name, $value, $reset = null)
	{
		$return = false;

		if(!isset($_SESSION['token'][$name])) return $return;

		$tokenValue = $_SESSION['token'][$name];

		if($value === $tokenValue) $return = true;

		if($reset) $this->resetToken($name);

		return $return;
	}

	/**
	 * Throws an exception if the token is invalid
	 *
	 * @param $name
	 * @param $value
	 * @throws ErrorExceptionion if token not valid
	 *
	 * @return bool returns true or throws exception
	 *
	 */
	public function validate($name, $value)
	{
		if(!$this->config['protectCSRF']) return true;
		if($this->isTokenValid($name, $value)) return true;
		$this->resetToken($name);
		\Imanager\Util::logException(new \ErrorException(
			'This request was aborted because it appears to be forged.'));
	}

	/**
	 * Clear out token value
	 *
	 */
	public function resetToken($name)
	{
		if(isset($_SESSION['token'][$name])) unset($_SESSION['token'][$name]);
	}

	/**
	 * Clear out all saved token values
	 *
	 */
	public function resetAll()
	{
		if(isset($_SESSION['token'])) unset($_SESSION['token']);
	}

	/**
	 * Render form input[hidden] containing the token name and value, as looked for by hasValidToken()
	 *
	 * ~~~~~
	 * <form method='post'>
	 *   <input type='submit'>
	 *   <?php echo $csrf->renderInputs(); ?>
	 * </form>
	 * ~~~~~
	 *
	 * @param int|string|null $id
	 * @return string
	 *
	 */
	public function renderInputs() {
		$tokenName = $this->getTokenName();
		$tokenValue = $this->getTokenValue();
		return "<input type='hidden' name='tokenName' value='$tokenName' class='_token'>\r\n".
			   "<input type='hidden' name='tokenValue' value='$tokenValue' class='_token'>";
	}

	/**
	 * Render https://your-website.com<?php echo $csrf->renderUrl(); ?>
	 * containing the token name and value, as looked for by hasValidToken().
	 *
	 * @param string $separator
	 *
	 * @return string
	 */
	public function renderUrl($separator = '?')
	{
		$tokenName = $this->getTokenName();
		$tokenValue = $this->getTokenValue();
		return "{$separator}tokenName=$tokenName&tokenValue=$tokenValue";
	}
}