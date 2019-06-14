<?php namespace Imanager;

class InputPassword implements InputInterface
{
	/**
	 * @var stdClass - The vield value object
	 */
	protected $value;

	/**
	 * @var Field object
	 */
	protected $field;

	/**
	 * @var int
	 */
	protected $maxLen = 255;

	/**
	 * @var int - default value, if it wasn't defined in field settings menu
	 */
	protected $minLen = 0;

	/**
	 * @var bool - default value if it wasn't defined in field settings menu
	 */
	protected $required = false;

	/**
	 * @var null
	 */
	public $confirm = null;

	/**
	 * @var null
	 */
	public $salt = null;

	/**
	 * @var null
	 */
	public $password = null;

	/**
	 * @var null int
	 */
	public $errorCode = null;

	/**
	 * InputPassword constructor.
	 *
	 * @param Field $field
	 */
	public function __construct(Field $field)
	{
		/**
		 * Set the field object
		 */
		$this->field = $field;

		$this->value = new PasswordFieldValue();

		/**
		 * Set local config values if these are set in the field settings (IM-Menu)
		 */
		if($this->field->required) {
			$this->required = true;
		}

		if($this->field->minimum) {
			$this->minLen = $this->field->minimum;
		}

		if($this->field->maximum) {
			$this->maxLen = $this->field->maximum;
		}
	}

	/**
	 * This method checks the field inputs and sets the field contents.
	 * If an error occurs, the method returns an error code.
	 *
	 * @param $value
	 * @param bool $sanitize
	 *
	 * @return int|stdClass
	 */
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
		if($password !== $confirm_password) {
			$this->errorCode = self::COMPARISON_FAILED;
			return false;
		}

		// Check min value length
		if($this->minLen > 0) {
			if(mb_strlen($password) < (int) $this->minLen) { return self::ERR_MIN_VALUE; }
		}

		// Check max value length
		if($this->maxLen > 0) {
			if(mb_strlen($password) > (int) $this->maxLen) { return self::ERR_MAX_VALUE; }
		}

		/**
		 * Uncomment it, if you want to increase the default sha1() hash, so you can
		 * switched to BCRYPT, which will always be 60 characters:
		 */
		$options = [
			'cost' => 11
		];
		$this->value->salt = '';
		$this->value->password = password_hash($password, PASSWORD_DEFAULT);

		// Build salt string
		// Note, since 2.4.4 salt is no longer used, but is still retained for compatibility reasons.
		//$this->values->salt = $this->randomString();
		// Create hashed pass
		//$this->values->value = sha1($value . $this->values->salt);
		//$this->values->value = password_hash($value, PASSWORD_DEFAULT);
		// Set confirmed flag
		//$this->field->setProtected('confirmed', true);

		return true;
	}

	/**
	 * The method that is called when initiating item content
	 * and is relevant for setting the field content.
	 * However, since we do not require any special formatting
	 * of the output, we can accept the value 1 to 1 here.
	 *
	 * @return stdClass
	 */
	public function prepareOutput(){ return $this->value; }

	/**
	 * Random string generator
	 *
	 * @param int $length
	 *
	 * @return string
	 */
	public function randomString($length = 10)
	{
		$characters = '0123456*789abcdefg$hijk#lmnopqrstuvwxyzABC+EFGHIJKLMNOPQRSTUVW@XYZ';
		$charactersLength = strlen($characters);
		$randomString = '';
		for($i = 0; $i < $length; $i++) {
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		}
		return $randomString;
	}
}
