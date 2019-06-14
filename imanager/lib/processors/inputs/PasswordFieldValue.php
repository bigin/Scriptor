<?php namespace Imanager;

class PasswordFieldValue
{
	/**
	 * @var Password value
	 */
	public $password;

	/**
	 * @var Salt value
	 */
	public $salt;

	/**
	 * This static method is called for complex field values
	 *
	 * @param $an_array
	 *
	 * @return PasswordFieldValue object
	 */
	public static function __set_state($an_array)
	{
		$_instance = new PasswordFieldValue();
		foreach($an_array as $key => $val) {
			if(is_array($val)) $_instance->{$key} = $val;
			else $_instance->{$key} = $val;
		}
		return $_instance;
	}

	/**
	 * Compares password with input $enteredPass
	 *
	 * @param $enteredPass
	 *
	 * @return bool
	 */
	public function compare($enteredPass)
	{
		if(!is_string($enteredPass)) {
			Util::logException(new \ErrorException('PasswordFieldValue::compare() expects parameter 1 to be string'));
		}
		/**
		 * If you use the secure BCRYPT password hashing method instead,
		 * please uncomment next line:
		 */
		return password_verify($enteredPass, $this->password);

		/**
		 * Comment out the next lines if you want to use the secure BCRYPT
		 * password hashing method instead.
		 */
		//$enterdHash = sha1( $enteredPass.$this->salt);
		//if($enterdHash === $this->password) return true;
		//return false;
	}
}