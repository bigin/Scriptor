<?php namespace Imanager;

class InputPassword implements InputInterface
{
	protected $value;

	protected $field;

	public $errorCode = null;

	public function __construct(Field $field)
	{
		$this->field = $field;
		$this->value = new PasswordFieldValue();
	}

	public function prepareInput($value, $sanitize = false)
	{
		if(!is_array($value)) {
			$this->errorCode = self::WRONG_VALUE_FORMAT;
			return false;
		}

		if(!isset($value['password']) || !isset($value['confirm_password'])) {
			$this->errorCode = self::EMPTY_REQUIRED;
			return false;
		}

		$password = trim($value['password']);
		$confirm_password = trim($value['confirm_password']);

		// Compare pass and confirmation pass
		if($password != $confirm_password) {
			$this->errorCode = self::COMPARISON_FAILED;
			return false;
		}

		// check min value
		if(!empty($this->field->minimum) && mb_strlen($password) < intval($this->field->minimum)) {
			$this->errorCode = self::ERR_MIN_LENGTH;
			return false;
		}
		// check input max value
		if(!empty($this->field->maximum) && mb_strlen($password) > intval($this->field->maximum)) {
			$this->errorCode = self::ERR_MAX_LENGTH;
			return false;
		}

		/**
		 * Uncomment it, if you want to increase the default sha1() hash, so you can
		 * switched to BCRYPT, which will always be 60 characters:
		 */
		/*$options = [
			'cost' => 11
		];
		$this->value->salt = '';
		$this->value->password = password_hash($password, PASSWORD_BCRYPT, $options);*/

		/**
		 * Comment out the next 2 lines if you want to use the secure BCRYPT
		 * password hashing method instead:
		 */
		$this->value->salt = $this->randomString();
		$this->value->password = sha1($password.$this->value->salt);

		return true;
	}

	public function prepareOutput(){ return $this->value; }

	public function randomString($length = 10)
	{
		$characters = '0123456*789abcdefg$hijk#lmnopqrstuvwxyzABC+EFGHIJKLMNOPQRSTUVW@XYZ';
		$charactersLength = strlen($characters);
		$randomString = '';
		for($i = 0; $i < $length; $i++)
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		return $randomString;
	}
}
