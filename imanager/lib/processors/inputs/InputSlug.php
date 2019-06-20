<?php namespace Imanager;

class InputSlug extends InputText implements InputInterface
{
	/**
	 * @var int
	 */
	protected $maxLen = 255;

	/**
	 * InputSlug constructor.
	 *
	 * @param Field $field
	 */
	public function __construct(Field $field) {
		parent::__construct($field);
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
		// Set empty value, the input isn't required
		if(empty($value) && !$this->required) {
			$this->value = !isset($value) ? $this->default : $value;
			return true;
		}

		// Check input required
		if(($this->required) && empty($value)) {
			$this->errorCode = self::EMPTY_REQUIRED;
			return false;
		}

		// This field must always be sanitized
		$this->value = $this->sanitize($value);

		// Sanitizer has wiped the value?
		if(!$this->value) {
			$this->errorCode = self::WRONG_VALUE_FORMAT;
			return false;
		}

		// Check min value length
		if($this->minLen > 0 && mb_strlen($this->value, 'UTF-8') < (int) $this->minLen) {
			$this->errorCode = self::ERR_MIN_LENGTH;
			return false;
		}

		// Check max value length
		if($this->maxLen > 0 && mb_strlen($this->value, 'UTF-8') > (int) $this->maxLen) {
			$this->errorCode = self::ERR_MAX_LENGTH;
			return false;
		}

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
	public function prepareOutput() { return $this->value; }

	/**
	 * This is the method used for sanitizing.
	 * ItemManager' Sanitizer method "pageName" will be used for this.
	 *
	 * @param $value
	 *
	 * @return mixed
	 */
	protected function sanitize($value) { return imanager('sanitizer')->pageName($value); }
}
