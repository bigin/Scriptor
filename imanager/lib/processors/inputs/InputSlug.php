<?php namespace Imanager;

class InputSlug implements InputInterface
{
	public $value;

	protected $field;

	public $errorCode = null;

	public function __construct(Field $field)
	{
		$this->field = $field;
		$this->value = '';
	}

	public function prepareInput($value, $sanitize = false)
	{
		$this->value = ($sanitize) ? $this->sanitize($value) : $value;

		// check input required
		if($this->field->required && empty($this->value)) {
			$this->errorCode = self::EMPTY_REQUIRED;
			return false;
		}
		// check min value
		if(!empty($this->field->minimum) && mb_strlen($this->value, 'UTF-8') < (int) $this->field->minimum) {
			$this->errorCode = self::ERR_MIN_VALUE;
			return false;
		}
		// check input max value
		if(!empty($this->field->maximum) && mb_strlen($this->value, 'UTF-8') > (int) $this->field->maximum) {
			$this->errorCode = self::ERR_MAX_LENGTH;
			return false;
		}

		return true;
	}

	public function prepareOutput($sanitize = false){
		return ($sanitize) ? $this->sanitize($this->value) : $this->value;
	}

	protected function sanitize($value){return imanager('sanitizer')->pageName($value);}
}